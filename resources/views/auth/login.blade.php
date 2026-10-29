<x-guest-layout title="Iniciar sesión" lead="Consulta tus expedientes, documentos, citas y facturas con una sesión individual y protegida.">
    <span class="badge">Acceso seguro</span>
    <h2>Iniciar sesión</h2>
    <p class="client-auth-intro">Usa el correo con el que te diste de alta.</p>

    @if (session('status'))
        <div class="form-status" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-check form-group">
            <input id="remember" name="remember" type="checkbox">
            <label for="remember">Mantener la sesión iniciada en este equipo</label>
        </div>
        <button class="btn btn--primary client-auth-submit" type="submit">Entrar</button>
        <div class="client-auth-links">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">He olvidado mi contraseña</a>
            @endif
        </div>
    </form>
</x-guest-layout>
