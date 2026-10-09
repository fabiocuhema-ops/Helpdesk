<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Helpdesk') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="app-shell">
            <section class="hero">
                <p class="eyebrow">Laravel pronto</p>
                <h1>Helpdesk</h1>
                <p class="lead">
                    Ambiente preparado com PHP, Laravel, HTML, CSS, JavaScript, Vite e SQLite para iniciar o desenvolvimento.
                </p>
                <div class="actions">
                    <a href="https://laravel.com/docs" target="_blank" rel="noreferrer">Documentacao Laravel</a>
                    <span id="status">Servidor local ativo</span>
                </div>
            </section>

            <section class="stack" aria-label="Tecnologias configuradas">
                <article>
                    <strong>PHP</strong>
                    <span>Backend Laravel configurado</span>
                </article>
                <article>
                    <strong>JavaScript</strong>
                    <span>Vite pronto para desenvolvimento</span>
                </article>
                <article>
                    <strong>HTML e CSS</strong>
                    <span>Blade e assets organizados</span>
                </article>
            </section>
        </main>
    </body>
</html>
