<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#14213d">
        <title>Entrar - Missão Pública</title>
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <main class="login-page">
            <section class="login-brand">
                <p class="eyebrow">Hackathon SJP</p>
                <h1>Missão Pública</h1>
                <p>Gestão de demandas e execução de serviços municipais</p>
            </section>

            <form class="login-card" method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <div>
                    <h2>Acessar plataforma</h2>
                    <p>Use uma conta autorizada pelo administrador municipal.</p>
                </div>

                @if ($errors->any())
                    <div class="form-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <label>
                    E-mail
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </label>

                <label>
                    Senha
                    <span class="password-field">
                        <input id="password" type="password" name="password" autocomplete="current-password" required>
                        <button type="button" onclick="const input=document.getElementById('password'); input.type = input.type === 'password' ? 'text' : 'password'; this.textContent = input.type === 'password' ? 'Mostrar' : 'Ocultar';">Mostrar</button>
                    </span>
                </label>

                <label class="checkbox-line">
                    <input type="checkbox" name="remember" value="1">
                    Manter sessão neste dispositivo
                </label>

                <button type="submit">Entrar</button>
                <p class="hint">Sem acesso ou esqueceu a senha? Contate o administrador. Recuperação por e-mail depende de configuração oficial.</p>
            </form>
        </main>
    </body>
</html>
