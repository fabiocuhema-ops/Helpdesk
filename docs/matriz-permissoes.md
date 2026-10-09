# Matriz de permissões

| Recurso/Ação | Administrador | Atendente | Gestor | Executor | Demandante |
| --- | --- | --- | --- | --- | --- |
| Entrar e sair com sessão segura | Sim | Sim | Sim | Sim | Sim |
| Registrar demanda recebida | Sim | Sim | Não | Não | Não |
| Consultar demandas | Todas | Setor autorizado | Setor autorizado | Atribuídas/equipe | Somente próprias |
| Atribuir executor/equipe | Sim | Não | Setor autorizado | Não | Não |
| Iniciar atendimento | Não operacional | Não | Não | Se atribuído | Não |
| Informar impedimento | Não operacional | Não | Não | Se atribuído | Não |
| Registrar providência/retomar | Sim | Não | Setor autorizado | Não | Não |
| Informar conclusão | Não operacional | Não | Não | Se atribuído | Não |
| Confirmar resolução | Não | Não | Não | Não | Somente própria |
| Informar pendência | Não | Não | Não | Não | Somente própria |
| Ver observações internas | Sim | Sim | Sim | Se atribuído | Não |
| Anexar evidência | Sim | Não | Sim | Se atribuído | Não |
| Baixar evidência privada | Sim | Setor autorizado | Setor autorizado | Se atribuído | Somente liberada |
| Integrações | Sim | Não | Não | Não | Não |
| IA de triagem | Sim | Sim | Sim | Não | Não |
| Reconhecimento | Sim | Consulta | Consulta | Próprio/geral demo | Não |

As permissões são aplicadas no servidor por usuário autenticado. O frontend apenas oculta ações indisponíveis, mas não é fonte de autorização.
