# Roteiro de demonstracao de 5 minutos

1. Abrir `http://127.0.0.1:8000`.
2. Usar perfil `Ana Atendente` e criar chamado no legado simulado.
3. Clicar em `Importar legado` para receber a demanda na plataforma.
4. Trocar para `Bruno Gestor` e atribuir a demanda ao tecnico.
5. Trocar para `Carlos Tecnico`, iniciar, bloquear por falta de material e observar a linha do tempo.
6. Voltar para `Bruno Gestor`, desbloquear informando providencia.
7. Voltar para `Carlos Tecnico`, concluir com descricao da entrega.
8. Trocar para `Daniela Demandante`, confirmar com nota 5 ou informar pendencia.
9. Executar no terminal `php artisan app:sync-mission-outbox` e abrir a central de integracao.
10. Mostrar ranking, indicadores e IA local/fallback.

## Credenciais demo

Todos os usuarios demo usam senha `demo1234`, mas a tela usa troca de perfil para acelerar a apresentacao.

- `admin@missao.local`
- `atendente@missao.local`
- `gestor@missao.local`
- `executor@missao.local`
- `demandante@missao.local`
- `predial@missao.local`
