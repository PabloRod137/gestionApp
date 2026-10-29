<x-panel-layout>
    <!-- Bienvenida y Título -->
    <div class="mb-10 space-y-2">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">Mi jornada</h1>
        <p class="text-sm text-gray-500">Lo que espera una decisión, lo que hay que preparar y el estado de los datos del despacho.</p>
    </div>

    @include('panel.partials.ia-jornada')

    {{-- Circuito de trabajo --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10 text-xs">
        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-1">Esperan una decisión</h2>
            <p class="text-gray-400 mb-4">Propuestas y documentos pendientes de aprobar.</p>
            @forelse($decisiones as $p)
                <a href="{{ route('panel.propuestas.show', $p->id) }}" class="block border border-gray-100 rounded-xl p-3 mb-2 hover:bg-gray-50">
                    <span class="font-bold text-gray-900 block truncate">{{ $p->title }}</span>
                    <span class="text-gray-500 block truncate">{{ $p->expedient->client->razon_social }}</span>
                    <span class="mt-1 inline-block">@include('panel.propuestas.partials.estado', ['p' => $p])</span>
                </a>
            @empty
                <p class="text-gray-400">Nada pendiente de decidir.</p>
            @endforelse
        </div>
        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-1">Por preparar</h2>
            <p class="text-gray-400 mb-4">Documentos que hay que generar o a los que falta el PDF.</p>
            @forelse($porPreparar as $p)
                <a href="{{ route('panel.propuestas.show', $p->id) }}" class="block border border-gray-100 rounded-xl p-3 mb-2 hover:bg-gray-50">
                    <span class="font-bold text-gray-900 block truncate">{{ $p->title }}</span>
                    <span class="text-gray-500 block truncate">{{ $p->expedient->client->razon_social }}</span>
                    <span class="mt-1 inline-block">@include('panel.propuestas.partials.estado', ['p' => $p])</span>
                </a>
            @empty
                <p class="text-gray-400">Nada por preparar.</p>
            @endforelse
        </div>
        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-1">Calidad de los datos</h2>
            <p class="text-gray-400 mb-4">
                @if($importadoEn)
                    Datos de SharePoint actualizados {{ $importadoEn->diffForHumans() }}.
                    @if($importadoEn->lt(now()->subDay()))<span class="text-amber-700 font-bold">Hace más de 24 horas: conviene volver a importar.</span>@endif
                @else
                    Todavía no se ha importado la base del despacho.
                @endif
            </p>
            <ul class="space-y-1.5 text-gray-700">
                <li><span class="font-bold">{{ $calidad['expedientes_sin_servicio'] }}</span> expedientes activos sin servicio</li>
                <li><span class="font-bold">{{ $calidad['clientes_sin_nif'] }}</span> clientes sin NIF</li>
                <li><span class="font-bold">{{ $calidad['clientes_sin_contacto'] }}</span> clientes sin correo ni teléfono</li>
                <li><span class="font-bold">{{ $calidad['servicios_sin_plantilla'] }}</span> servicios que generan documento sin plantilla</li>
                <li><span class="font-bold">{{ $calidad['servicios_sin_desglosar'] }}</span> servicios con requisitos sin desglosar</li>
                <li><a href="{{ route('panel.sincronizacion.index') }}" class="hover:underline"><span class="font-bold">{{ $cambiosPendientesSharePoint }}</span> cambios pendientes de llegar a SharePoint</a></li>
            </ul>
            <p class="mt-4 text-gray-500"><a href="{{ route('panel.comunicaciones.index') }}" class="text-emerald-800 font-bold hover:underline">{{ $comunicacionesPreparadas }} comunicaciones preparadas</a></p>
        </div>
    </div>

    <!-- MÉTRIQUES CLAVE (GRID) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10 text-xs font-medium">
        
        <!-- Clientes Activos -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <div>
                <span class="text-gray-400 block">Fichas Clientes</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ $totalClientes }}</span>
            </div>
        </div>

        <!-- Expedientes en Trámite -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <span class="text-gray-400 block">Expedientes Activos</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ $expedientesActivos }}</span>
            </div>
        </div>

        <!-- Citas de hoy -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <span class="text-gray-400 block">Citas Agendadas</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ $citasPendientes }}</span>
            </div>
        </div>

        <!-- Facturación Mensual -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-rose-50 rounded-xl flex items-center justify-center text-rose-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M12 16c1.657 0 3-.895 3-2s-1.343-2-3-2-3-.895-3-2 1.343-2 3-2m0 8V7m0 9v1"/></svg>
            </div>
            <div>
                <span class="text-gray-400 block">Ingresos del Mes</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ number_format($facturacionMensual, 2, ',', '.') }}€</span>
            </div>
        </div>

    </div>

    <!-- SECCIÓN CONTENIDOS (GRID DOS COLUMNAS) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 text-xs font-medium">
        
        <!-- Col 1: Agenda del Día y Solicitudes Nuevas -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Agenda del día -->
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-base font-bold text-gray-900 font-brand-serif">Citas Agendadas para Hoy</h2>
                    <a href="{{ route('panel.citas.index') }}" class="text-xs font-bold color-gold-warm hover:underline">Ver agenda completa</a>
                </div>

                @forelse($citasHoy as $cita)
                    <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center mb-3">
                        <div class="space-y-1">
                            <span class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">
                                {{ $cita->service->nombre ?? 'Consulta General' }}
                            </span>
                            <h3 class="font-bold text-gray-900">Cliente: {{ $cita->client->razon_social }}</h3>
                            <p class="text-gray-500 font-mono">
                                Hora: {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hs | Asesor: {{ $cita->worker->name ?? 'Sin asignar' }}
                            </p>
                        </div>
                        <span class="bg-blue-100 text-blue-900 font-bold px-3 py-1 rounded-full uppercase tracking-wider text-[9px]">
                            {{ $cita->estado }}
                        </span>
                    </div>
                @empty
                    <p class="text-gray-400 py-4 font-normal">No hay reuniones agendadas para la jornada de hoy.</p>
                @endforelse
            </div>

            <!-- Leads Web Nuevos -->
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-base font-bold text-gray-900 font-brand-serif">Nuevas Consultas Web sin Asignar</h2>
                    <a href="{{ route('panel.solicitudes.index') }}" class="text-xs font-bold color-gold-warm hover:underline">Ver bandeja de entrada</a>
                </div>

                @forelse($solicitudesNuevas as $sol)
                    <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center mb-3">
                        <div class="space-y-1">
                            <h3 class="font-bold text-gray-900">{{ $sol->nombre }} <span class="text-gray-400 font-normal">({{ $sol->email }})</span></h3>
                            <p class="text-[10px] uppercase tracking-wider text-amber-500 font-bold">Interés: {{ $sol->servicio_interes }}</p>
                            <p class="text-gray-500 font-normal truncate max-w-lg">"{{ $sol->mensaje }}"</p>
                        </div>
                        <a href="{{ route('panel.solicitudes.show', $sol->id) }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-3 py-1.5 rounded-lg text-[10px] transition">
                            Asignar
                        </a>
                    </div>
                @empty
                    <p class="text-gray-400 py-4 font-normal text-emerald-800">¡Bandeja vacía! No hay nuevas solicitudes pendientes de asignación.</p>
                @endforelse
            </div>

        </div>

        <!-- Col 2: Actividad y mensajes -->
        <div class="lg:col-span-4 space-y-8">
            @include('panel.partials.mensajes-pendientes', ['pendientes' => $mensajesPendientes, 'totalPendientes' => $totalMensajesPendientes])
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Actividad reciente</h2>
                @forelse($actividadReciente as $a)
                    <a href="{{ route('panel.expedientes.show', $a->expedient_id) }}" class="block py-2 border-b border-gray-100 last:border-0 hover:bg-gray-50">
                        <span class="font-bold text-gray-800">{{ ucfirst(strtolower(str_replace('_', ' ', $a->action))) }}</span>
                        <span class="block text-gray-500">{{ $a->expedient->codigo }} · {{ $a->user?->name ?? 'sistema' }} · {{ $a->created_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="text-gray-400 font-normal">Todavía no hay actividad registrada.</p>
                @endforelse
            </div>
        </div>

    </div>
</x-panel-layout>
