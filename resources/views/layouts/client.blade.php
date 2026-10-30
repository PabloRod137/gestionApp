<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Portal Cliente | Nortia</title>


    <!-- Tailwind compilado con Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="font-brand-sans bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- BANNER DE IMPERSONACIÓN (Si un asesor está suplantando al cliente) -->
    @if(app('impersonate')->isImpersonating())
        <div class="bg-amber-600 text-white font-semibold text-center py-3.5 px-4 text-sm flex items-center justify-center space-x-4 border-b-2 border-amber-700 shadow-md">
            <svg aria-hidden="true" class="h-5 w-5 text-white animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Estás visualizando el portal como el cliente: <strong class="underline">{{ Auth::user()->name }}</strong>.</span>
            <a href="{{ route('impersonate.leave') }}" class="bg-white text-amber-950 font-bold px-4 py-1.5 rounded-lg text-xs hover:bg-amber-100 transition shadow">
                Volver al Panel Interno
            </a>
        </div>
    @endif

    <!-- CABECERA PARA MÓVILES (Navbar superior básica) -->
    <header class="bg-[#2B2F33] text-white md:hidden flex items-center justify-between h-16 px-4 shadow">
        <span class="flex items-center gap-3"><img src="/assets/logo-mark.png" alt="" class="h-10 w-10 object-contain"><span><span class="text-base font-extrabold tracking-wider block leading-tight whitespace-nowrap">NORTIA</span><span class="text-[9px] uppercase tracking-widest text-stone-400 font-semibold">Portal del cliente</span></span></span>
        <button id="mobile-sidebar-toggle" class="text-stone-300 hover:text-white focus:outline-none">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </header>

    <div class="flex-grow flex flex-col md:flex-row">
        
        <!-- SIDEBAR DE NAVEGACIÓN (Escritorio) -->
        <aside id="sidebar-menu" class="hidden md:flex flex-col w-full md:w-64 bg-[#2B2F33] text-white border-r border-white/10 flex-shrink-0 shadow-lg">
            
            <!-- Encabezado Sidebar -->
            <div class="h-20 flex items-center px-6 border-b border-white/10 bg-[#2B2F33]">
                <div>
                    <span class="flex items-center gap-3"><img src="/assets/logo-mark.png" alt="" class="h-10 w-10 object-contain"><span><span class="text-base font-extrabold tracking-wider block leading-tight whitespace-nowrap">NORTIA</span><span class="text-[9px] uppercase tracking-widest text-stone-400 font-semibold">Portal del cliente</span></span></span>
                </div>
            </div>

            <!-- Datos breves del perfil del cliente -->
            <div class="px-6 py-5 bg-white/5 border-b border-white/10">
                <p class="text-xs text-stone-400">Cliente conectado</p>
                <p class="text-sm font-semibold truncate mt-0.5" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</p>
                @if(Auth::user()->client)
                    <p class="text-[10px] text-stone-300 font-mono mt-1">{{ Auth::user()->client->nif }}</p>
                @endif
            </div>

            <!-- Enlaces de navegación -->
            <nav class="flex-grow px-4 py-6 space-y-2">
                <a href="{{ route('cliente.dashboard') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('cliente.dashboard') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Inicio / Dashboard</span>
                </a>
                
                <a href="{{ route('cliente.expedientes.index') }}" class="flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('cliente.expedientes*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <div class="flex items-center space-x-3">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Mis Expedientes</span>
                    </div>
                </a>

                <a href="{{ route('cliente.citas.index') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('cliente.citas*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Citas Previa</span>
                </a>

                <a href="{{ route('cliente.facturas.index') }}" class="flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('cliente.facturas*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <div class="flex items-center space-x-3">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Facturación / Recibos</span>
                    </div>
                </a>

                <a href="{{ route('cliente.datos.edit') }}" class="flex items-center space-x-3 px-4 py-3 rounded-lg text-sm font-medium transition {{ request()->routeIs('cliente.datos*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0M9 14h6"/></svg>
                    <span>Mis datos</span>
                </a>
            </nav>

            <!-- Boton de Salida / Desconexión -->
            <div class="p-4 border-t border-white/10">
                <a href="{{ route('profile.edit') }}" class="w-full flex items-center space-x-3 px-4 py-2.5 mb-1 rounded-lg text-sm font-medium transition {{ request()->routeIs('profile.*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Mi perfil</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm text-red-300 hover:bg-red-500/10 hover:text-red-200 transition font-medium">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- CONTENIDO DE LA PÁGINA -->
        <main class="flex-grow min-w-0 p-5 pb-24 sm:p-10 sm:pb-24 max-w-7xl mx-auto w-full">
            
            <!-- Mensajes flash del sistema -->
            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg text-emerald-800 text-sm flex items-start space-x-3 shadow-sm">
                    <svg class="h-5 w-5 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg text-red-800 text-sm flex items-start space-x-3 shadow-sm">
                    <svg class="h-5 w-5 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Aviso: faltan datos en su ficha -->
            @php $datosQueFaltan = Auth::user()->client ? \App\Support\DatosCliente::faltan(Auth::user()->client) : []; @endphp
            @if($datosQueFaltan && ! request()->routeIs('cliente.datos.*'))
                <div class="mb-6 p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg text-amber-900 text-sm flex flex-wrap items-center justify-between gap-3 shadow-sm" role="status">
                    <span>Para preparar tus documentos nos faltan algunos datos: <strong>{{ implode(', ', $datosQueFaltan) }}</strong>.</span>
                    <a href="{{ route('cliente.datos.edit') }}" class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded-lg text-xs whitespace-nowrap">Completar mis datos</a>
                </div>
            @endif

            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset

            {{ $slot }}
        </main>
    </div>

    <!-- Asistente privado del cliente (sin IA): solo trata los datos de quien ha iniciado sesión -->
    @if(Auth::user()?->client)
        <script type="module" src="/js/features/chat/client-bubble.js"></script>
    @endif

    <!-- Toggle del menú lateral en móviles -->
    <script>
        const menuBtn = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar-menu');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('flex');
            sidebar.classList.toggle('w-full');
            sidebar.classList.toggle('absolute');
            sidebar.classList.toggle('z-40');
            sidebar.classList.toggle('mt-16');
        });
    </script>
</body>
</html>
