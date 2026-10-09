# Roteiro de demonstracao de 5 minutos

1. Abrir `http://127.0.0.1:8000/login`.
2. Entrar como `atendente@missao.local` e criar chamado no legado simulado.
3. Clicar em `Importar legado` para receber a demanda na plataforma.
4. Sair e entrar como `gestor@missao.local` para atribuir a demanda ao tecnico.
5. Sair e entrar como `executor@missao.local`, iniciar, bloquear por falta de material e observar a linha do tempo.
6. Voltar como `gestor@missao.local`, desbloquear informando providencia.
7. Entrar como `executor@missao.local`, concluir com descricao da entrega e anexar evidencia privada.
8. Entrar como `demandante@missao.local`, confirmar com nota 5 ou informar pendencia.
9. Executar no terminal `php artisan app:sync-mission-outbox` e abrir a central de integracao.
10. Mostrar ranking, indicadores e IA local/fallback.

## Credenciais demo

Todos os usuarios demo usam senha `demo1234` em ambiente de demonstracao. Nao ha cadastro publico.

- `admin@missao.local`
- `atendente@missao.local`
- `gestor@missao.local`
- `executor@missao.local`
- `demandante@missao.local`
- `predial@missao.local`
