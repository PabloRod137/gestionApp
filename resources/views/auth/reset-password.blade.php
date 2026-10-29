<x-guest-layout title="Nueva contraseña" badge="Recuperar acceso" lead="Elige una contraseña nueva para tu cuenta.">
    <span class="badge">Recuperar acceso</span>
    <h2>Elige una contraseña nueva</h2>
    <p class="client-auth-intro">Usa una contraseña que no hayas utilizado en otros sitios.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="username" required autofocus>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Contraseña nueva</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label class="form-label" for="password_confirmation">Repite la contraseña</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            @error('password_confirmation')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn--primary client-auth-submit" type="submit">Guardar la contraseña</button>
    </form>
</x-guest-layout>
