import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

type User = { id: number; name: string; email: string; roles: string[]; sector_id: number | null };
type Evidence = { id: number; name: string; mime_type: string; size: number; visible_to_requester: boolean; uploaded_at: string; download_url: string };
type DemandEvent = { type: string; label: string; description: string; occurred_at: string; visible_to_requester: boolean };
type Demand = {
    id: number;
    uuid: string;
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
    actions: string[];
    evidences: Evidence[];
    events: DemandEvent[];
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
    navigation: Array<{ href: string; label: string }>;
    push: { publicKey: string | null; note: string };
};

const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
const metricLabels: Record<string, string> = {
    received: 'Recebidas',
    in_progress: 'Em execução',
    blocked: 'Bloqueadas',
    awaiting_validation: 'Aguardando validação',
    resolved: 'Resolvidas',
    avg_first_start_hours: 'Média até início',
};
const roleNames: Record<string, string> = {
    admin: 'Administrador',
    atendente: 'Atendente',
    gestor: 'Gestor',
    executor: 'Executor',
    demandante: 'Demandante',
};

async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, { headers: jsonHeaders, credentials: 'same-origin', ...options });
    if (response.status === 401) {
        window.location.href = '/login';
        throw new Error('Sessão expirada.');
    }
    if (!response.ok) {
        throw new Error(await response.text());
    }
    return response.json() as Promise<T>;
}

async function upload<T>(url: string, body: FormData): Promise<T> {
    const response = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin' });
    if (!response.ok) {
        throw new Error(await response.text());
    }
    return response.json() as Promise<T>;
}

function hasRole(user: User, role: string): boolean {
    return user.roles.includes(role);
}

function App() {
    const [boot, setBoot] = useState<Bootstrap | null>(null);
    const [demands, setDemands] = useState<Demand[]>([]);
    const [selected, setSelected] = useState<Demand | null>(null);
    const [message, setMessage] = useState('Carregando...');
    const [query, setQuery] = useState('');
    const [form, setForm] = useState({ title: 'Ponto de rede sem funcionamento', description: 'Sala de vacina sem conexão de rede no computador principal.', priority: 'alta', complexity: 'media' });
    const [legacy, setLegacy] = useState({ title: 'Ponto de rede sem funcionamento', description: 'Servidora ligou informando ponto de rede sem funcionamento.', requester_name: 'Daniela Demandante', requester_email: 'demandante@missao.local', sector_hint: 'TI' });
    const [aiText, setAiText] = useState('Ponto de rede da sala de vacina não conecta e atendimento está parado.');
    const [aiResult, setAiResult] = useState<Record<string, unknown> | null>(null);
    const path = window.location.pathname;

    const refresh = async () => {
        const [bootstrap, list] = await Promise.all([api<Bootstrap>('/api/bootstrap'), api<{ data: Demand[] }>('/api/demands')]);
        setBoot(bootstrap);
        setDemands(list.data);
        const pathMatch = path.match(/\/demandas\/(\d+)/);
        const initial = pathMatch ? list.data.find((item) => item.id === Number(pathMatch[1])) : undefined;
        setSelected((current) => initial ?? list.data.find((item) => item.id === current?.id) ?? list.data[0] ?? null);
        setMessage('Dados atualizados.');
    };

    useEffect(() => {
        refresh().catch((error) => setMessage(`Erro: ${error.message}`));
    }, []);

    const filtered = useMemo(() => {
        const text = query.toLowerCase();
        return demands.filter((demand) => [demand.uuid, demand.title, demand.requester, demand.sector, demand.status_label].join(' ').toLowerCase().includes(text));
    }, [demands, query]);

    const transition = async (action: string, extra: Record<string, unknown> = {}) => {
        if (!selected) return;
        const updated = await api<Demand>(`/api/demands/${selected.id}/transition`, {
            method: 'POST',
            body: JSON.stringify({ action, lock_version: selected.lock_version, ...extra }),
        });
        setSelected(updated);
        setMessage('Ação registrada com sucesso.');
        await refresh();
    };

    const createDemand = async (event: FormEvent) => {
        event.preventDefault();
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
        setMessage('Demanda registrada.');
        await refresh();
    };

    const createLegacy = async () => {
        await api('/api/legacy/tickets', { method: 'POST', body: JSON.stringify(legacy) });
        setMessage('Chamado criado no legado simulado.');
        await refresh();
    };

    const importLegacy = async () => {
        await api('/api/legacy/import', { method: 'POST' });
        setMessage('Chamado importado do legado.');
        await refresh();
    };

    const askAi = async () => {
        const result = await api<{ suggestion: Record<string, unknown>; status: string; error?: string }>('/api/ai/suggest', { method: 'POST', body: JSON.stringify({ description: aiText, demand_id: selected?.id }) });
        setAiResult({ situação: result.status, ...result.suggestion, erro: result.error });
    };

    const uploadEvidence = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selected) return;
        const data = new FormData(event.currentTarget);
        await upload(`/api/demands/${selected.id}/evidences`, data);
        setMessage('Evidência enviada e armazenada de forma privada.');
        await refresh();
    };

    const enablePush = async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setMessage('Push indisponível neste navegador.');
            return;
        }
        const registration = await navigator.serviceWorker.register('/sw.js');
        setMessage(`Service worker registrado. Permissão de push deve ser concedida pelo usuário. Escopo: ${registration.scope}`);
    };

    if (!boot) {
        return <main className="loading">{message}</main>;
    }

    const pageTitle = boot.navigation.find((item) => item.href === path)?.label ?? 'Visão geral';
    const roleLabel = boot.user.roles.map((role) => roleNames[role] ?? role).join(', ');

    return (
        <div className="app-layout">
            <aside className="sidebar">
                <a className="brand" href="/visao-geral"><span>MP</span><strong>Missão Pública</strong></a>
                <nav>
                    {boot.navigation.map((item) => <a className={path === item.href ? 'active' : ''} href={item.href} key={item.href}>{item.label}</a>)}
                </nav>
            </aside>

            <div className="main-shell">
                <header className="app-header">
                    <div>
                        <p className="eyebrow">Hackathon SJP</p>
                        <h1>{pageTitle}</h1>
                    </div>
                    <div className="user-menu">
                        <span className="notification">Notificações</span>
                        <div><strong>{boot.user.name}</strong><small>{roleLabel}</small></div>
                        <form method="POST" action="/logout">
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="ghost">Sair</button>
                        </form>
                    </div>
                </header>

                <section className="notice">{message}</section>

                {renderPage(path, { boot, demands: filtered, selected, setSelected, query, setQuery, form, setForm, legacy, setLegacy, aiText, setAiText, aiResult, createDemand, createLegacy, importLegacy, transition, askAi, uploadEvidence, enablePush, refresh })}
            </div>
        </div>
    );
}

type PageProps = {
    boot: Bootstrap;
    demands: Demand[];
    selected: Demand | null;
    setSelected: (demand: Demand) => void;
    query: string;
    setQuery: (value: string) => void;
    form: { title: string; description: string; priority: string; complexity: string };
    setForm: (value: { title: string; description: string; priority: string; complexity: string }) => void;
    legacy: { title: string; description: string; requester_name: string; requester_email: string; sector_hint: string };
    setLegacy: (value: { title: string; description: string; requester_name: string; requester_email: string; sector_hint: string }) => void;
    aiText: string;
    setAiText: (value: string) => void;
    aiResult: Record<string, unknown> | null;
    createDemand: (event: FormEvent) => Promise<void>;
    createLegacy: () => Promise<void>;
    importLegacy: () => Promise<void>;
    transition: (action: string, extra?: Record<string, unknown>) => Promise<void>;
    askAi: () => Promise<void>;
    uploadEvidence: (event: FormEvent<HTMLFormElement>) => Promise<void>;
    enablePush: () => Promise<void>;
    refresh: () => Promise<void>;
};

function renderPage(path: string, props: PageProps) {
    if (path === '/demandas/nova') return <RegisterPage {...props} />;
    if (path === '/minhas-missoes') return <DemandListPage {...props} title="Minhas missões" mode="missions" />;
    if (path === '/minhas-solicitacoes') return <DemandListPage {...props} title="Minhas solicitações" mode="requests" />;
    if (path.startsWith('/demandas/') && props.selected) return <DemandDetail {...props} />;
    if (path === '/demandas') return <DemandListPage {...props} title="Demandas" mode="all" />;
    if (path === '/integracoes') return <IntegrationPage {...props} />;
    if (path === '/reconhecimento') return <RecognitionPage {...props} />;
    if (path === '/indicadores') return <Dashboard {...props} admin />;
    if (path === '/equipes' || path === '/administracao') return <AdminPage {...props} />;
    return <Dashboard {...props} />;
}

function Dashboard({ boot, demands, selected, transition, enablePush, admin = false }: PageProps & { admin?: boolean }) {
    const blocked = demands.filter((demand) => demand.status === 'blocked');
    const awaiting = demands.filter((demand) => demand.status === 'awaiting_validation');

    return (
        <>
            <section className="metrics">
                {Object.entries(boot.metrics).filter(([key]) => admin || key !== 'avg_first_start_hours' || !hasRole(boot.user, 'demandante')).map(([key, value]) => (
                    <article key={key}><span>{metricLabels[key] ?? key}</span><strong>{value}</strong></article>
                ))}
            </section>
            <section className="dashboard-grid">
                <Panel title={hasRole(boot.user, 'executor') ? 'Minhas missões prioritárias' : hasRole(boot.user, 'demandante') ? 'Minhas solicitações' : 'Demandas recentes'}>
                    <DemandCards demands={demands.slice(0, 6)} />
                </Panel>
                <Panel title="Atenção necessária">
                    {blocked.length === 0 && awaiting.length === 0 ? <p className="empty">Nenhuma pendência crítica no momento.</p> : <DemandCards demands={[...blocked, ...awaiting].slice(0, 5)} />}
                </Panel>
                {selected && <Panel title="Ação rápida"><ContextActions boot={boot} demand={selected} transition={transition} enablePush={enablePush} /></Panel>}
            </section>
        </>
    );
}

function DemandListPage(props: PageProps & { title: string; mode: string }) {
    return (
        <section className="workspace">
            <Panel title={props.title}>
                <input value={props.query} onChange={(event) => props.setQuery(event.target.value)} placeholder="Pesquisar por protocolo, demandante, local ou estado" />
                <div className="table-list">
                    {props.demands.length === 0 && <p className="empty">Nenhuma demanda encontrada.</p>}
                    {props.demands.map((demand) => (
                        <a href={`/demandas/${demand.id}`} className="table-row" key={demand.id}>
                            <strong>{demand.title}</strong>
                            <span>{demand.requester ?? 'Sem demandante'}</span>
                            <Badge status={demand.status} label={demand.status_label} />
                            <span>{demand.priority}</span>
                        </a>
                    ))}
                </div>
            </Panel>
        </section>
    );
}

function DemandDetail(props: PageProps) {
    const demand = props.selected;
    if (!demand) return null;

    return (
        <section className="detail-layout">
            <Panel title={demand.title}>
                <div className="detail-heading">
                    <div>
                        <p className="muted">Protocolo {demand.uuid}</p>
                        <Badge status={demand.status} label={demand.status_label} />
                    </div>
                    <strong>Prioridade {demand.priority}</strong>
                </div>
                <p>{demand.description}</p>
                <dl className="info-grid">
                    <div><dt>Demandante</dt><dd>{demand.requester}</dd></div>
                    <div><dt>Setor</dt><dd>{demand.sector}</dd></div>
                    <div><dt>Executor</dt><dd>{demand.executor ?? 'Não atribuído'}</dd></div>
                    <div><dt>Equipe</dt><dd>{demand.team ?? 'Não atribuída'}</dd></div>
                </dl>
                <ContextActions boot={props.boot} demand={demand} transition={props.transition} enablePush={props.enablePush} />
            </Panel>

            <Panel title="Evidências privadas">
                {demand.actions.includes('evidence') && (
                    <form className="upload-form" onSubmit={props.uploadEvidence}>
                        <input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf,.txt" required />
                        <label className="checkbox-line"><input type="checkbox" name="visible_to_requester" value="1" /> Liberar ao demandante</label>
                        <button type="submit">Enviar evidência</button>
                    </form>
                )}
                {demand.evidences.length === 0 ? <p className="empty">Nenhuma evidência liberada para este perfil.</p> : demand.evidences.map((evidence) => (
                    <a className="evidence-link" href={evidence.download_url} key={evidence.id}>{evidence.name}<span>{evidence.uploaded_at}</span></a>
                ))}
            </Panel>

            <Panel title="Linha do tempo">
                <ol className="timeline">
                    {demand.events.map((event, index) => <li key={`${event.type}-${index}`}><strong>{event.label}</strong><span>{event.occurred_at}</span><p>{event.description}</p></li>)}
                </ol>
            </Panel>

            {(hasRole(props.boot.user, 'admin') || hasRole(props.boot.user, 'atendente') || hasRole(props.boot.user, 'gestor')) && <AiPanel {...props} />}
        </section>
    );
}

function RegisterPage(props: PageProps) {
    return (
        <section className="grid two">
            <Panel title="Registrar demanda">
                <form className="form-stack" onSubmit={props.createDemand}>
                    <label>Título<input value={props.form.title} onChange={(event) => props.setForm({ ...props.form, title: event.target.value })} required /></label>
                    <label>Descrição<textarea value={props.form.description} onChange={(event) => props.setForm({ ...props.form, description: event.target.value })} required /></label>
                    <label>Prioridade<select value={props.form.priority} onChange={(event) => props.setForm({ ...props.form, priority: event.target.value })}><option value="baixa">Baixa</option><option value="media">Média</option><option value="alta">Alta</option><option value="critica">Crítica</option></select></label>
                    <button type="submit">Registrar demanda</button>
                </form>
            </Panel>
            <AiPanel {...props} />
        </section>
    );
}

function IntegrationPage(props: PageProps) {
    return (
        <section className="grid two">
            <Panel title="Legado simulado">
                <input value={props.legacy.title} onChange={(event) => props.setLegacy({ ...props.legacy, title: event.target.value })} />
                <textarea value={props.legacy.description} onChange={(event) => props.setLegacy({ ...props.legacy, description: event.target.value })} />
                <button onClick={props.createLegacy}>Criar chamado no legado</button>
                <button onClick={props.importLegacy}>Importar para plataforma</button>
                <p className="hint">Sincronização de saída: execute `php artisan app:sync-mission-outbox`.</p>
            </Panel>
            <Panel title="Saúde da integração">
                <p>Pendentes: <strong>{props.boot.integration.pending}</strong></p>
                <p>Enviados: <strong>{props.boot.integration.sent}</strong></p>
                <p>Com falha: <strong>{props.boot.integration.failed}</strong></p>
            </Panel>
        </section>
    );
}

function RecognitionPage({ boot }: PageProps) {
    return <Panel title="Reconhecimento">{boot.ranking.length === 0 ? <p className="empty">Nenhuma pontuação confirmada ainda.</p> : boot.ranking.map((row) => <p key={row.name}><strong>{row.name}</strong>: {row.points} pontos</p>)}</Panel>;
}

function AdminPage({ boot }: PageProps) {
    return (
        <section className="grid two">
            <Panel title="Usuários e papéis">{boot.users.map((user) => <p key={user.id}><strong>{user.name}</strong><br /><span>{user.roles.map((role) => roleNames[role] ?? role).join(', ')}</span></p>)}</Panel>
            <Panel title="Setores e equipes">{boot.sectors.map((sector) => <p key={sector.id}><strong>{sector.name}</strong></p>)}{boot.teams.map((team) => <p key={`t-${team.id}`}>{team.name}</p>)}</Panel>
        </section>
    );
}

function AiPanel({ aiText, setAiText, aiResult, askAi }: PageProps) {
    return (
        <Panel title="Assistente de triagem">
            <textarea value={aiText} onChange={(event) => setAiText(event.target.value)} />
            <button onClick={askAi}>Sugerir categoria e encaminhamento</button>
            {aiResult && <pre>{JSON.stringify(aiResult, null, 2)}</pre>}
        </Panel>
    );
}

function ContextActions({ boot, demand, transition, enablePush }: { boot: Bootstrap; demand: Demand; transition: PageProps['transition']; enablePush: PageProps['enablePush'] }) {
    const ask = (label: string, fallback: string) => window.prompt(label, fallback) ?? '';
    const executor = boot.users.find((user) => user.roles.includes('executor'));
    const team = boot.teams[0];
    return (
        <div className="actions">
            {demand.actions.includes('assign') && <button onClick={() => transition('assign', { executor_id: executor?.id, team_id: team?.id })}>Atribuir executor</button>}
            {demand.actions.includes('start') && <button onClick={() => transition('start')}>Iniciar atendimento</button>}
            {demand.actions.includes('block') && <button onClick={() => transition('block', { description: ask('Motivo e descrição do impedimento', 'Falta de material para concluir o serviço.') })}>Informar impedimento</button>}
            {demand.actions.includes('unblock') && <button onClick={() => transition('unblock', { description: ask('Providência adotada', 'Material entregue ao executor.') })}>Registrar providência</button>}
            {demand.actions.includes('complete') && <button onClick={() => transition('complete', { description: ask('Descrição da entrega', 'Serviço executado e testado no local.') })}>Informar conclusão</button>}
            {demand.actions.includes('confirm') && <button onClick={() => transition('confirm', { description: 'Demandante confirmou a resolução.', rating: 5 })}>Confirmar resolução</button>}
            {demand.actions.includes('reopen') && <button onClick={() => transition('reopen', { description: ask('Justificativa da pendência', 'O problema persistiu após a entrega.') })}>Informar pendência</button>}
            <button className="ghost" onClick={enablePush}>Ativar notificações</button>
        </div>
    );
}

function DemandCards({ demands }: { demands: Demand[] }) {
    if (demands.length === 0) return <p className="empty">Nada para exibir.</p>;
    return <div className="card-list">{demands.map((demand) => <a href={`/demandas/${demand.id}`} className="mission-card" key={demand.id}><strong>{demand.title}</strong><span>{demand.requester ?? demand.sector}</span><Badge status={demand.status} label={demand.status_label} /></a>)}</div>;
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
    return <section className="panel"><div className="panel-title"><h2>{title}</h2></div>{children}</section>;
}

function Badge({ status, label }: { status: string; label: string }) {
    return <span className={`status ${status}`}>● {label}</span>;
}

createRoot(document.getElementById('app')!).render(<App />);
