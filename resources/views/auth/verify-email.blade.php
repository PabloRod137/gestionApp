<x-guest-layout title="Confirma tu correo" badge="Un último paso" lead="Confirma tu correo electrónico para activar tu cuenta.">
    <span class="badge">Un último paso</span>
    <h2>Confirma tu correo</h2>
    <p class="client-auth-intro">Te hemos enviado un enlace de confirmación. Pulsa en él para activar tu cuenta. Si no lo encuentras, revisa la carpeta de spam o pide otro.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="form-status" role="status">Te hemos enviado un nuevo enlace de confirmación al correo con el que te registraste.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="btn btn--primary client-auth-submit" type="submit">Reenviar el correo de confirmación</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="client-auth-links">
        @csrf
        <button type="submit" class="client-link-button">Cerrar sesión</button>
    </form>
</x-guest-layout>
