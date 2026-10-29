<x-client-layout>
    <div class="max-w-md mx-auto text-center py-16 px-6 bg-white border border-gray-150 rounded-2xl shadow-sm mt-12 space-y-6">
        <!-- Icono -->
        <div class="h-16 w-16 bg-amber-50 rounded-full flex items-center justify-center text-amber-600 mx-auto border border-amber-200">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <div class="space-y-2">
            <h2 class="text-xl font-bold text-gray-900 font-brand-serif">Perfil de Cliente No Vinculado</h2>
            <p class="text-xs text-gray-500 leading-relaxed">
                Tu cuenta de usuario ({{ Auth::user()->email }}) está registrada en el sistema, pero no está vinculada a ninguna ficha de cliente en nuestra base de datos corporativa.
            </p>
        </div>

        <div class="border-t border-gray-100 pt-6 flex flex-col space-y-3">
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('worker'))
                <a href="{{ route('panel.dashboard') }}" class="bg-forest-dark hover:bg-gold-warm hover:text-[#1B1E21] text-[#1B1E21] font-bold py-3 rounded-lg text-xs uppercase tracking-wider transition shadow-sm">
                    Ir al Panel Administrativo
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 underline">
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</x-client-layout>
