<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Panel de Trabajo | Nortia</title>


    <!-- Tailwind compilado con Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Estilos de marca Nortia -->
    <style>
        .font-brand-sans { font-family: 'Outfit', sans-serif; }
        .color-forest-dark { color: #786c35; }
        .bg-[#2B2F33] { background-color: #9c8d45; }
        .bg-forest-medium { background-color: #787873; }
        .color-gold-bright { color: #786c35; }
        .bg-gold-bright { background-color: #9c8d45; }
    </style>
</head>
<body class="font-brand-sans bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- CABECERA PARA MÓVILES -->
    <header class="bg-[#2B2F33] text-white md:hidden flex items-center justify-between h-16 px-4 shadow">
        <span class="flex items-center gap-3"><img src="/assets/logo-mark.png" alt="" class="h-10 w-10 object-contain"><span><span class="text-base font-extrabold tracking-wider block leading-tight whitespace-nowrap">NORTIA</span><span class="text-[9px] uppercase tracking-widest text-stone-400 font-semibold">Panel de gestión</span></span></span>
        <button id="mobile-sidebar-toggle" class="text-stone-300 hover:text-white focus:outline-none">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </header>

    <div class="flex-grow flex flex-col md:flex-row">
        
        <!-- SIDEBAR DE NAVEGACIÓN (Escritorio y Móvil) -->
        <aside id="sidebar-menu" class="hidden md:flex flex-col w-full md:w-64 bg-[#2B2F33] text-white border-r border-white/10 flex-shrink-0 shadow-lg">
            
            <!-- Encabezado Sidebar -->
            <div class="h-20 flex items-center px-6 border-b border-white/10 bg-[#2B2F33]">
                <div>
                    <span class="flex items-center gap-3"><img src="/assets/logo-mark.png" alt="" class="h-10 w-10 object-contain"><span><span class="text-base font-extrabold tracking-wider block leading-tight whitespace-nowrap">NORTIA</span><span class="text-[9px] uppercase tracking-widest text-stone-400 font-semibold">Panel de gestión</span></span></span>
                </div>
            </div>

            <!-- Datos breves del usuario conectado -->
            <div class="px-6 py-5 bg-white/5 border-b border-white/10">
                <p class="text-xs text-stone-400">Usuario conectado</p>
                <p class="text-sm font-semibold truncate mt-0.5" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</p>
                <div class="mt-1 flex items-center space-x-1.5">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 block"></span>
                    <span class="text-[9px] uppercase tracking-widest text-stone-300 font-bold">
                        {{ Auth::user()->hasRole('admin') ? 'Administrador' : 'Asesor / Personal' }}
                    </span>
                </div>
            </div>

            <!-- Enlaces de navegación -->
            <nav class="flex-grow px-4 py-6 space-y-1.5 overflow-y-auto">
                
                <a href="{{ route('panel.dashboard') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.dashboard') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Mi jornada</span>
                </a>
                
                <div class="pt-4 pb-1">
                    <span class="text-[10px] uppercase tracking-widest text-stone-300 font-bold px-4">Despacho</span>
                </div>

                <a href="{{ route('panel.clientes.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.clientes*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Clientes</span>
                </a>

                <a href="{{ route('panel.expedientes.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.expedientes*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Expedientes</span>
                </a>

                <a href="{{ route('panel.propuestas.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.propuestas*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <span>Propuestas</span>
                </a>

                <a href="{{ route('panel.comunicaciones.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.comunicaciones*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>Comunicaciones</span>
                </a>

                <a href="{{ route('panel.documental.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.documental*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v13H7a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
                    <span>Bandeja documental</span>
                </a>

                <a href="{{ route('panel.sincronizacion.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.sincronizacion*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M5.6 15A8 8 0 0018.8 17M18.4 9A8 8 0 005.2 7"/></svg>
                    <span>Sincronización</span>
                </a>

                <a href="{{ route('panel.servicios.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.servicios*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span>Servicios</span>
                </a>

                <a href="{{ route('panel.comunidades.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.comunidades*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Comunidades</span>
                </a>

                <a href="{{ route('panel.plantillas.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.plantillas*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 4h8m-8 4h5M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                    <span>Plantillas</span>
                </a>

                <a href="{{ route('panel.citas.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.citas*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Agenda de Citas</span>
                </a>

                <a href="{{ route('panel.facturas.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.facturas*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Facturación</span>
                </a>

                <div class="pt-4 pb-1">
                    <span class="text-[10px] uppercase tracking-widest text-stone-300 font-bold px-4">Contenidos y Leads</span>
                </div>

                <a href="{{ route('panel.blog.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.blog*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5m15 6l-7.5 7.5-7.5-7.5"/></svg>
                    <span>Blog Actualidad</span>
                </a>

                <a href="{{ route('panel.solicitudes.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.solicitudes*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                    <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 00-2-2H6a2 2 0 00-2 2z"/></svg>
                    <span>Leads y Solicitudes</span>
                </a>

                <!-- Sección exclusiva para administradores -->
                @role('admin')
                    <div class="pt-4 pb-1">
                        <span class="text-[10px] uppercase tracking-widest text-stone-300 font-bold px-4">Administración</span>
                    </div>

                    <a href="{{ route('panel.configuracion.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.configuracion*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Configuración Global</span>
                    </a>

                    <a href="{{ route('panel.auditoria.index') }}" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('panel.auditoria*') ? 'bg-[#A28C3F] text-[#1B1E21] font-bold shadow' : 'text-stone-300 hover:bg-white/10 hover:text-white' }}">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Logs de Auditoría</span>
                    </a>
                @endrole
            </nav>

            <!-- Cerrar Sesión del Asesor -->
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

        <!-- CONTENIDO PRINCIPAL -->
        <main class="flex-grow p-6 sm:p-10 w-full overflow-x-hidden">
            {{-- Expediente activo: el que se ha abierto por última vez en esta sesión --}}
            @php($activo = session('expediente_activo') ? \App\Models\Expedient::with('client')->find(session('expediente_activo')) : null)
            @if($activo)
                <div class="mb-6 -mt-2 flex items-center justify-between gap-4 rounded-xl bg-[#2B2F33] text-white px-5 py-3 text-xs shadow">
                    <p><span class="block text-[9px] font-bold uppercase tracking-[0.14em] text-[#D5C27A]">Expediente activo</span>
                        <strong class="text-sm">{{ $activo->codigo ?: '#'.$activo->id }} · {{ $activo->client->razon_social }}</strong>
                        <span class="text-stone-300 ml-2">{{ $activo->titulo }}</span></p>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('panel.expedientes.show', $activo->id) }}" class="border border-white/30 hover:bg-white/10 rounded-lg px-3 py-1.5 font-bold">Ver expediente</a>
                        <form action="{{ route('panel.expediente-activo.limpiar') }}" method="POST">@csrf
                            <button type="submit" class="border border-white/30 hover:bg-white/10 rounded-lg px-3 py-1.5 font-bold" title="Deja de recordar este expediente">Limpiar</button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Alertas flash de éxito o error -->
            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg text-emerald-800 text-sm flex items-start space-x-3 shadow">
                    <svg class="h-5 w-5 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg text-red-800 text-sm flex items-start space-x-3 shadow">
                    <svg class="h-5 w-5 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset

            {{ $slot }}
        </main>
    </div>

    <!-- Toggle sidebar menú en móviles -->
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
