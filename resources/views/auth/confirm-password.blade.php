<x-guest-layout title="Confirma tu contraseña" badge="Zona segura" lead="Por seguridad, confirma tu contraseña antes de continuar.">
    <span class="badge">Zona segura</span>
    <h2>Confirma tu contraseña</h2>
    <p class="client-auth-intro">Esta es una zona protegida. Confirma tu contraseña para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required autofocus>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn--primary client-auth-submit" type="submit">Confirmar</button>
    </form>
</x-guest-layout>
