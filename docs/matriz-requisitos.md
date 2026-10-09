# Matriz de requisitos obrigatorios

| Requisito | Implementacao | Teste/evidencia | Situacao |
| --- | --- | --- | --- |
| Gestao de tarefas municipais | Demandas, setores, locais, equipes, estados, dashboard e detalhe com linha do tempo | `php artisan test --filter=MissionPublicaFlowTest` | Funcional e verificado |
| Perfis e autorizacao | Papeis multiplos em `users.roles`, controle no servidor para transicoes e escopo basico por setor/propriedade | Teste bloqueia demandante atribuindo e valida lock | Funcional e verificado |
| Fluxo executor/demandante | Registrada, atribuida, em execucao, bloqueada, aguardando validacao, resolvida, pendencia | Teste percorre jornada principal | Funcional e verificado |
| Integracao bidirecional com legado | `legacy_tickets`, API legado, importacao, outbox persistente, worker, retry/backoff e falha temporaria | `php artisan app:sync-mission-outbox`; central de integracao | Funcional e verificado parcialmente |
| Push verdadeiro | Service worker, endpoint de inscricao e configuracao VAPID documentada | Registro SW pela interface; envio depende de chaves/dispositivo | Implementado, aguardando verificacao externa |
| Gamificacao auditavel | `score_ledgers`, regra demo-v1, pontuacao somente apos confirmacao, idempotencia por evento | Teste garante um ledger por resolucao | Funcional e verificado |
| IA local | Endpoint chama Ollama configuravel e registra sugestao; fallback demo identificado quando indisponivel | Testavel pela tela IA; depende de Ollama local para inferencia real | Implementado, aguardando verificacao externa |

## Itens simulados ou incompletos

- Envio Web Push assinado ainda nao dispara notificacao real pelo servidor.
- Upload binario de evidencias privadas esta modelado, mas a tela de upload final nao foi incluida.
- Separacao fisica do servico legado esta documentada para Compose/proximo passo; no prototipo roda no mesmo Laravel.
