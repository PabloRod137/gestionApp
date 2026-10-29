<x-guest-layout title="Recuperar contraseña" badge="Recuperar acceso" lead="Te enviaremos un enlace para elegir una contraseña nueva.">
    <span class="badge">Recuperar acceso</span>
    <h2>He olvidado mi contraseña</h2>
    <p class="client-auth-intro">Indica el correo de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.</p>

    @if (session('status'))
        <div class="form-status" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn--primary client-auth-submit" type="submit">Enviar el enlace</button>
        <div class="client-auth-links">
            <a href="{{ route('login') }}">Volver a iniciar sesión</a>
        </div>
    </form>
</x-guest-layout>
