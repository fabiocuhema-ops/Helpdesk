import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

type User = { id: number; name: string; email: string; roles: string[]; sector_id: number | null };
type Demand = {
    id: number;
    title: string;
    description: string;
    status: string;
    status_label: string;
    priority: string;
    complexity: string;
    lock_version: number;
    requester?: string;
    executor?: string;
    team?: string;
    sector?: string;
    origin?: string;
    events: Array<{ type: string; description: string; occurred_at: string; visible_to_requester: boolean }>;
};
type Bootstrap = {
    user: User;
    users: User[];
    sectors: Array<{ id: number; name: string }>;
    locations: Array<{ id: number; name: string; sector_id: number }>;
    teams: Array<{ id: number; name: string; sector_id: number }>;
    taskTypes: Array<{ id: number; name: string; category: string }>;
    statuses: Record<string, string>;
    metrics: Record<string, number>;
    ranking: Array<{ name: string; points: number }>;
    integration: Record<string, number>;
    push: { publicKey: string | null; note: string };
};

const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf };

async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, { headers: jsonHeaders, credentials: 'same-origin', ...options });
    if (!response.ok) {
        throw new Error(await response.text());
    }
    return response.json() as Promise<T>;
}

function App() {
    const [boot, setBoot] = useState<Bootstrap | null>(null);
    const [demands, setDemands] = useState<Demand[]>([]);
    const [selected, setSelected] = useState<Demand | null>(null);
    const [message, setMessage] = useState('Carregando ambiente...');
    const [form, setForm] = useState({ title: 'Ponto de rede sem funcionamento', description: 'Sala de vacina sem conexao de rede no computador principal.', priority: 'alta', complexity: 'media' });
    const [legacy, setLegacy] = useState({ title: 'Ponto de rede sem funcionamento', description: 'Servidora ligou informando ponto de rede sem funcionamento.', requester_name: 'Daniela Demandante', requester_email: 'demandante@missao.local', sector_hint: 'TI' });
    const [aiText, setAiText] = useState('Ponto de rede da sala de vacina nao conecta e atendimento esta parado.');
    const [aiResult, setAiResult] = useState<Record<string, unknown> | null>(null);

    const refresh = async () => {
        const [bootstrap, list] = await Promise.all([api<Bootstrap>('/api/bootstrap'), api<{ data: Demand[] }>('/api/demands')]);
        setBoot(bootstrap);
        setDemands(list.data);
        setSelected((current) => list.data.find((item) => item.id === current?.id) ?? list.data[0] ?? null);
        setMessage('Pronto para demonstracao.');
    };

    useEffect(() => {
        refresh().catch((error) => setMessage(`Erro ao carregar: ${error.message}`));
    }, []);

    const roleLabel = useMemo(() => boot?.user.roles.join(', ') ?? '', [boot]);

    const login = async (userId: number) => {
        await api('/api/login-demo', { method: 'POST', body: JSON.stringify({ user_id: userId }) });
        await refresh();
    };

    const transition = async (action: string, extra: Record<string, unknown> = {}) => {
        if (!selected) return;
        const body = { action, lock_version: selected.lock_version, ...extra };
        const updated = await api<Demand>(`/api/demands/${selected.id}/transition`, { method: 'POST', body: JSON.stringify(body) });
        setSelected(updated);
        await refresh();
    };

    const createDemand = async () => {
        if (!boot) return;
        await api('/api/demands', {
            method: 'POST',
            body: JSON.stringify({
                ...form,
                sector_id: boot.sectors[0]?.id,
                location_id: boot.locations[0]?.id,
                task_type_id: boot.taskTypes[0]?.id,
                requester_id: boot.users.find((user) => user.roles.includes('demandante'))?.id,
            }),
        });
        await refresh();
    };

    const createLegacy = async () => {
        await api('/api/legacy/tickets', { method: 'POST', body: JSON.stringify(legacy) });
        setMessage('Chamado criado no legado simulado. Clique em Importar legado.');
        await refresh();
    };

    const importLegacy = async () => {
        await api('/api/legacy/import', { method: 'POST' });
        await refresh();
    };

    const sync = async () => {
        setMessage('Execute no terminal: php artisan app:sync-mission-outbox. A central sera atualizada no proximo refresh.');
    };

    const askAi = async () => {
        const result = await api<{ suggestion: Record<string, unknown>; status: string; error?: string }>('/api/ai/suggest', { method: 'POST', body: JSON.stringify({ description: aiText, demand_id: selected?.id }) });
        setAiResult({ status: result.status, ...result.suggestion, erro: result.error });
    };

    const enablePush = async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setMessage('Push indisponivel neste navegador.');
            return;
        }
        const registration = await navigator.serviceWorker.register('/sw.js');
        setMessage(`Service worker registrado. Para push real, configure VAPID e chame subscribe(): ${registration.scope}`);
    };

    if (!boot) {
        return <main className="loading">{message}</main>;
    }

    return (
        <main className="mission-app">
            <header className="topbar">
                <div>
                    <p className="eyebrow">Hackathon SJP</p>
                    <h1>Missao Publica</h1>
                    <p>Gestao web, execucao mobile, integracao, gamificacao e IA local para tarefas municipais.</p>
                </div>
                <label>
                    Perfil demo
                    <select value={boot.user.id} onChange={(event) => login(Number(event.target.value))}>
                        {boot.users.map((user) => <option key={user.id} value={user.id}>{user.name} - {user.roles.join('/')}</option>)}
                    </select>
                </label>
            </header>

            <section className="notice">{message} Usuario: <strong>{boot.user.name}</strong> ({roleLabel})</section>

            <section className="metrics">
                {Object.entries(boot.metrics).map(([key, value]) => <article key={key}><span>{key}</span><strong>{value}</strong></article>)}
                {Object.entries(boot.integration).map(([key, value]) => <article key={key}><span>integracao {key}</span><strong>{value}</strong></article>)}
            </section>

            <section className="workspace">
                <aside className="panel">
                    <div className="panel-title">
                        <h2>Demandas</h2>
                        <button onClick={refresh}>Atualizar</button>
                    </div>
                    <div className="demand-list">
                        {demands.length === 0 && <p className="empty">Nenhuma demanda encontrada.</p>}
                        {demands.map((demand) => (
                            <button className={selected?.id === demand.id ? 'demand active' : 'demand'} key={demand.id} onClick={() => setSelected(demand)}>
                                <strong>{demand.title}</strong>
                                <span>{demand.status_label} · {demand.priority} · {demand.sector}</span>
                            </button>
                        ))}
                    </div>
                </aside>

                <section className="panel detail">
                    {selected ? (
                        <>
                            <div className="panel-title">
                                <div>
                                    <h2>{selected.title}</h2>
                                    <p>{selected.status_label} · {selected.requester} · {selected.executor ?? 'sem executor'}</p>
                                </div>
                                <span className={`status ${selected.status}`}>{selected.status_label}</span>
                            </div>
                            <p>{selected.description}</p>
                            <div className="actions">
                                <button onClick={() => transition('assign', { executor_id: boot.users.find((user) => user.roles.includes('executor'))?.id, team_id: boot.teams[0]?.id })}>Atribuir</button>
                                <button onClick={() => transition('start')}>Iniciar</button>
                                <button onClick={() => transition('block', { description: 'Falta de material registrada; aguardando apoio do gestor.' })}>Bloquear</button>
                                <button onClick={() => transition('unblock', { description: 'Material entregue ao executor.' })}>Retomar</button>
                                <button onClick={() => transition('complete', { description: 'Ponto refeito, testado e liberado ao setor.' })}>Concluir</button>
                                <button onClick={() => transition('confirm', { description: 'Demandante confirmou funcionamento.', rating: 5 })}>Confirmar</button>
                                <button onClick={() => transition('reopen', { description: 'Problema persistiu apos teste do demandante.' })}>Informar pendencia</button>
                            </div>
                            <h3>Linha do tempo</h3>
                            <ol className="timeline">
                                {selected.events.map((event, index) => <li key={`${event.type}-${index}`}><strong>{event.type}</strong><span>{event.occurred_at}</span><p>{event.description}</p></li>)}
                            </ol>
                        </>
                    ) : <p className="empty">Selecione uma demanda.</p>}
                </section>
            </section>

            <section className="grid">
                <section className="panel">
                    <h2>Cadastro pelo atendente</h2>
                    <input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} aria-label="Titulo" />
                    <textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} aria-label="Descricao" />
                    <button onClick={createDemand}>Registrar demanda</button>
                </section>
                <section className="panel">
                    <h2>Legado simulado</h2>
                    <input value={legacy.title} onChange={(event) => setLegacy({ ...legacy, title: event.target.value })} aria-label="Titulo legado" />
                    <textarea value={legacy.description} onChange={(event) => setLegacy({ ...legacy, description: event.target.value })} aria-label="Descricao legado" />
                    <button onClick={createLegacy}>Criar no legado</button>
                    <button onClick={importLegacy}>Importar legado</button>
                    <button onClick={sync}>Sincronizar outbox</button>
                </section>
                <section className="panel">
                    <h2>IA local por Ollama</h2>
                    <textarea value={aiText} onChange={(event) => setAiText(event.target.value)} aria-label="Texto para IA" />
                    <button onClick={askAi}>Sugerir triagem</button>
                    {aiResult && <pre>{JSON.stringify(aiResult, null, 2)}</pre>}
                </section>
                <section className="panel">
                    <h2>Ranking e push</h2>
                    {boot.ranking.map((row) => <p key={row.name}><strong>{row.name}</strong>: {row.points} pontos</p>)}
                    <button onClick={enablePush}>Preparar push neste navegador</button>
                    <p className="hint">{boot.push.note}</p>
                </section>
            </section>
        </main>
    );
}

createRoot(document.getElementById('app')!).render(<App />);
