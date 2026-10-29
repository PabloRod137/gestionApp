<x-client-layout>
    <!-- Bienvenida -->
    <div class="mb-10 space-y-2">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">¡Hola, {{ $client->razon_social }}!</h1>
        <p class="text-sm text-gray-500">Bienvenido a tu portal personal de Nortia. Aquí puedes realizar el seguimiento de tus trámites en tiempo real.</p>
    </div>

    <!-- Grid de Métricas Rápida -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10 text-xs">
        
        <!-- Tarjeta 1: Expedientes Activos -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">Expedientes Activos</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ $expedientesActivosCount }}</span>
            </div>
        </div>

        <!-- Tarjeta 2: Próximas Citas -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">Próximas Citas</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ $proximasCitas->count() }}</span>
            </div>
        </div>

        <!-- Tarjeta 3: Facturas Pendientes -->
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm flex items-center space-x-4">
            <div class="h-12 w-12 bg-rose-50 rounded-xl flex items-center justify-center text-rose-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <span class="text-gray-400 font-medium block">Facturas Pendientes</span>
                <span class="text-2xl font-bold text-gray-900 block mt-0.5">{{ $facturasPendientes->count() }}</span>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Col 1: Citas y Facturas -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Listado Próximas Citas -->
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-base font-bold text-gray-900 font-brand-serif">Agenda y Citas Previa</h3>
                    <a href="{{ route('cliente.citas.index') }}" class="text-xs font-bold color-gold-warm hover:underline">Gestionar citas</a>
                </div>

                @forelse($proximasCitas as $cita)
                    <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center text-xs mb-3">
                        <div class="space-y-1">
                            <span class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px]">
                                {{ $cita->service->nombre ?? 'Consulta General' }}
                            </span>
                            <h4 class="font-bold text-gray-900">
                                Reunión con Asesor: {{ $cita->worker->name ?? 'Por asignar' }}
                            </h4>
                            <p class="text-gray-500 tabular-nums">
                                {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} a las {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hs
                            </p>
                        </div>
                        <span class="bg-amber-100 text-amber-950 font-bold px-3 py-1 rounded-full uppercase tracking-wider text-[9px]">
                            {{ $cita->estado }}
                        </span>
                    </div>
                @empty
                    <p class="text-gray-400 text-xs py-2">No tienes citas programadas próximamente.</p>
                @endforelse
            </div>

            <!-- Recibos / Facturas Impagadas -->
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-base font-bold text-gray-900 font-brand-serif font-brand-sans">Facturas y Liquidaciones Pendientes</h3>
                    <a href="{{ route('cliente.facturas.index') }}" class="text-xs font-bold color-gold-warm hover:underline">Ver todas</a>
                </div>

                @forelse($facturasPendientes as $fac)
                    <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center text-xs mb-3">
                        <div class="space-y-1">
                            <h4 class="font-bold text-gray-900">Factura nº: {{ $fac->numero }}</h4>
                            <p class="text-gray-400 tabular-nums">Emitida: {{ \Carbon\Carbon::parse($fac->fecha)->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="font-black text-rose-700 text-sm">{{ number_format($fac->total, 2, ',', '.') }}€</span>
                            <a href="{{ route('cliente.facturas.pdf', $fac->id) }}" class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold px-3 py-1.5 rounded-lg text-[10px] transition border border-rose-200">
                                Descargar PDF
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 text-xs py-2 text-emerald-800 font-medium">¡Estás al día! No tienes facturas pendientes de pago.</p>
                @endforelse
            </div>

        </div>

        <!-- Col 2: Mensajes Recientes -->
        <div class="lg:col-span-4">
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm h-full flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-6">Últimos Mensajes del Asesor</h3>
                    
                    <div class="space-y-4">
                        @forelse($ultimosMensajes as $msg)
                            <div class="border-b border-gray-100 pb-3 last:border-0 text-xs space-y-1">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $msg->sender->name }}</span>
                                    <span class="text-[10px] text-gray-400 tabular-nums">{{ $msg->created_at->format('d/m H:i') }}</span>
                                </div>
                                <p class="text-[10px] uppercase font-bold color-gold-warm">Exp: {{ $msg->expedient->titulo }}</p>
                                <p class="text-gray-500 italic line-clamp-2">"{{ $msg->contenido }}"</p>
                            </div>
                        @empty
                            <p class="text-gray-400 text-xs py-2">No has recibido mensajes recientes.</p>
                        @endforelse
                    </div>
                </div>

                @if($ultimosMensajes->isNotEmpty())
                    <div class="pt-6 mt-6 border-t border-gray-100">
                        <a href="{{ route('cliente.expedientes.index') }}" class="w-full bg-forest-dark hover:bg-gold-warm hover:text-[#1B1E21] text-[#1B1E21] font-bold py-2.5 rounded-lg text-center block text-[10px] uppercase tracking-wider transition shadow-sm">
                            Abrir Chats de Expedientes
                        </a>
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-client-layout>
