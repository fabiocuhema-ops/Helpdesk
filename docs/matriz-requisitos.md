# Matriz de requisitos obrigatorios

| Requisito | Implementacao | Teste/evidencia | Situacao |
| --- | --- | --- | --- |
| Gestao de tarefas municipais | Demandas, setores, locais, equipes, estados, dashboard e detalhe com linha do tempo | `php artisan test --filter=MissionPublicaFlowTest` | Funcional e verificado |
| Perfis e autorizacao | Login real Laravel, papeis multiplos em `users.roles`, rotas protegidas, escopo por setor/propriedade/equipe e acoes contextuais | Testes de login, logout, acesso indevido, executor nao atribuido e lock | Funcional e verificado |
| Fluxo executor/demandante | Registrada, atribuida, em execucao, bloqueada, aguardando validacao, resolvida, pendencia | Teste percorre jornada principal | Funcional e verificado |
| Integracao bidirecional com legado | `legacy_tickets`, API legado, importacao, outbox persistente, worker, retry/backoff e falha temporaria | `php artisan app:sync-mission-outbox`; central de integracao | Funcional e verificado parcialmente |
| Push verdadeiro | Service worker preservado, acionamento explicito pelo usuario e configuracao VAPID documentada | Registro SW pela interface; envio real depende de chaves/dispositivo | Implementado, aguardando verificacao externa |
| Gamificacao auditavel | `score_ledgers`, regra demo-v1, pontuacao somente apos confirmacao, idempotencia por evento | Teste garante um ledger por resolucao | Funcional e verificado |
| IA local | Endpoint chama Ollama configuravel e registra sugestao; fallback demo identificado quando indisponivel | Testavel pela tela IA; depende de Ollama local para inferencia real | Implementado, aguardando verificacao externa |

## Itens simulados ou incompletos

- Envio Web Push assinado ainda nao dispara notificacao real pelo servidor.
- Upload binario de evidencias privadas foi implementado com armazenamento local privado e autorizacao de download.
- Separacao fisica do servico legado esta documentada para Compose/proximo passo; no prototipo roda no mesmo Laravel.
