# Missao Publica - Hackathon SJP

Prototipo funcional no projeto Helpdesk para acompanhamento e execucao de tarefas municipais. A aplicacao demonstra gestao web, experiencia mobile/PWA, legado simulado, outbox com retry, gamificacao auditavel e assistente de triagem com Ollama local.

## Stack

- PHP 8.4 e Laravel 13
- React 19 + TypeScript + Vite
- SQLite para demo imediata
- PostgreSQL previsto em `docker-compose.yml`
- Ollama local configuravel por ambiente
- Service worker para PWA/Web Push

## Inicio rapido

```bash
composer install
npm install
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Abra:

```text
http://127.0.0.1:8000/login
```

## Usuarios demo

Todos usam senha `demo1234` somente no ambiente de demonstracao. Nao ha cadastro publico e o seletor de perfil foi removido.

- `admin@missao.local`
- `atendente@missao.local`
- `gestor@missao.local`
- `executor@missao.local`
- `demandante@missao.local`
- `predial@missao.local`

## Comandos uteis

```bash
php artisan app:sync-mission-outbox
php artisan test --filter=MissionPublicaFlowTest
npm run typecheck
npm run build
vendor/bin/pint --dirty --format agent
```

## Paginas principais

- `/login`
- `/visao-geral`
- `/demandas`
- `/demandas/nova`
- `/minhas-missoes`
- `/minhas-solicitacoes`
- `/indicadores`
- `/reconhecimento`
- `/integracoes`
- `/administracao`

## Docker Compose

O arquivo `docker-compose.yml` inclui PostgreSQL e Ollama para uma execucao municipal/local sem SaaS obrigatorio. Antes de usar em ambiente real, ajuste segredos, volumes, HTTPS e extensoes PHP da imagem.

```bash
docker compose up
```

## IA local

Variaveis:

```dotenv
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.1
```

Se o Ollama ou modelo nao estiverem disponiveis, a aplicacao grava sugestao `demo_fallback`, claramente identificada como demonstrativa.

## Web Push e HTTPS

Variaveis:

```dotenv
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:demo@missao-publica.local
```

O prototipo registra service worker e inscricao. Para teste fisico em celular, use HTTPS com certificado confiavel no dispositivo, por exemplo com mkcert na rede local. Web Push normalmente depende da infraestrutura de entrega do fornecedor do navegador; envie somente identificador opaco e aviso generico.

## Documentacao

- `docs/plano-implementacao.md`
- `docs/matriz-permissoes.md`
- `docs/matriz-requisitos.md`
- `docs/arquitetura.md`
- `docs/roteiro-demo.md`

## Licenca e titularidade

Nao foi definida titularidade ou licenca do codigo novo. Os direitos devem ser formalizados no instrumento do desafio.
