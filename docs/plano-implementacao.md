# Plano de implementacao - Missao Publica

## Etapas executadas

1. Inspecao do projeto Laravel em `/home/antonio/Projetos/Helpdesk`, Git em `.git-local`, PHP 8.4, Composer, Node e npm.
2. Instalacao do Laravel Boost conforme `AGENTS.md`.
3. Inclusao de React + TypeScript + Vite no frontend.
4. Criacao do modelo de dominio: setores, locais, equipes, tipos, demandas, eventos append-only, evidencias, legado simulado, outbox, ledger de pontuacao, IA e push.
5. Criacao da API para login demo, demandas, transicoes, legado simulado, IA local por Ollama e inscricao push.
6. Criacao da interface responsiva em portugues para dashboard, lista, detalhe, linha do tempo, cadastro, integracao, IA, ranking e push.
7. Criacao de seed demo idempotente com perfis, setores, equipes e jornada inicial.
8. Criacao de worker de sincronizacao `php artisan app:sync-mission-outbox`.
9. Inclusao de testes de transicao, autorizacao, concorrencia, outbox e pontuacao sem duplicidade.

## Decisoes

- A demo local continua usando SQLite para rodar imediatamente no ambiente atual. O `docker-compose.yml` fornece PostgreSQL para execucao municipal/local completa.
- Web Push esta preparado com service worker, endpoint de inscricao e variaveis VAPID. Envio real depende das chaves e do navegador/dispositivo.
- IA tenta Ollama real via `OLLAMA_URL`; se indisponivel, registra retorno `demo_fallback` explicitamente identificado.
- Evidencias tem tabela privada e metadados, mas upload binario completo ficou como proximo passo por limite de tempo.

## Proximas etapas recomendadas

- Conectar Laravel ao PostgreSQL do Compose e executar a suite completa nesse banco.
- Implementar envio Web Push assinado com biblioteca WebPush PHP.
- Completar upload/download autorizado de evidencias privadas.
- Separar o legado simulado em processo/container proprio se a banca exigir isolamento fisico.
- Ampliar testes E2E com navegador real em desktop e celular.
