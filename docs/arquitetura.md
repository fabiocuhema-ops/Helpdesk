# Arquitetura - Missao Publica

## Componentes

- Laravel 13: API, autorizacao, transicoes, banco, worker e renderizacao inicial.
- React + TypeScript + Vite: experiencia web/mobile responsiva.
- SQLite local por padrao para demo imediata; PostgreSQL previsto no `docker-compose.yml`.
- Ollama local: IA configurada por `OLLAMA_URL` e `OLLAMA_MODEL`.
- Legado simulado: tabelas e API `/api/legacy/*`.
- Outbox: tabela `outbox_events`, gravada na mesma transacao da mudanca da demanda.
- Worker: `php artisan app:sync-mission-outbox`.
- PWA/Web Push: `public/sw.js`, endpoint `/api/push/subscribe`, variaveis VAPID.

## Estados

`registered -> assigned -> in_progress -> awaiting_validation -> resolved`

Estados alternativos: `blocked`, `pending_analysis`, `cancelled`.

## Permissoes

- Administrador: acesso demo amplo.
- Atendente: cria/importa demandas e consulta andamento.
- Gestor: atribui, desbloqueia, cancela e consulta indicadores.
- Executor: inicia, bloqueia, retoma apos apoio e conclui demandas atribuidas.
- Demandante: confirma ou reabre somente suas demandas.

## Indicadores

Os indicadores sao calculados a partir de `demands`: recebidas, em execucao, bloqueadas, aguardando validacao, resolvidas e media ate primeiro inicio. Ausencia de avaliacao nao vira nota zero.

## Gamificacao

A regra `demo-v1` concede pontos apenas na confirmacao do demandante:

- baixa: 10 pontos;
- media: 20 pontos;
- alta: 35 pontos;
- bonus: 5 pontos por estrela acima de 3.

O ledger guarda origem, regra, beneficiario, valor e justificativa. A chave unica evita duplicidade por retry.
