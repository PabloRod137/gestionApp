<x-panel-layout>
    <!-- Botón Volver -->
    <div class="mb-6">
        <a href="{{ route('panel.clientes.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver al listado</span>
        </a>
    </div>

    <!-- Título y Datos Rápidos -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs">
        <div class="space-y-1">
            <span class="bg-forest-medium text-white px-2 py-0.5 rounded text-[9px] uppercase font-bold tracking-wider">{{ $cliente->tipo }}</span>
            <h1 class="text-xl font-bold text-gray-900 font-brand-serif leading-tight mt-1">{{ $cliente->razon_social }}</h1>
            <p class="text-gray-400 font-mono">NIF/CIF: <strong class="text-gray-800 uppercase">{{ $cliente->nif }}</strong></p>
        </div>
        <div class="flex items-center space-x-3">
            @if($cliente->user_id)
                <form method="POST" action="{{ route('panel.clientes.impersonate', $cliente->id) }}" class="inline">@csrf<button type="submit" class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold px-4 py-2 rounded-lg text-xs transition">
                    Suplantar Sesión
                </button></form>
            @endif
            <a href="{{ route('panel.clientes.edit', $cliente->id) }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-4 py-2 rounded-lg text-xs transition">
                Editar Datos
            </a>
        </div>
    </div>

    <!-- Pestañas e Historiales -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 text-xs">
        
        <!-- Ficha de Datos Personales / Observaciones -->
        <div class="lg:col-span-4 space-y-6">
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 pb-3">Información Filiación</h3>
                
                <div class="space-y-2">
                    <span class="text-gray-400 block">Teléfono contacto</span>
                    <span class="text-gray-800 font-bold font-mono">{{ $cliente->telefono ?? '—' }}</span>
                </div>
                <div class="space-y-2">
                    <span class="text-gray-400 block">Dirección fiscal/postal</span>
                    <span class="text-gray-800 font-bold">{{ $cliente->direccion ?? '—' }}</span>
                </div>
                <div class="space-y-2">
                    <span class="text-gray-400 block">Observaciones internas / Historial</span>
                    <p class="text-gray-500 italic whitespace-pre-line">{{ $cliente->notas ?? 'Sin observaciones anotadas.' }}</p>
                </div>
            </div>
        </div>

        <!-- Historiales de Expedientes, Facturas y Citas -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Expedientes Asociados -->
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4 border-b border-gray-100 pb-3">Expedientes Asociados</h3>
                
                <div class="space-y-3">
                    @forelse($cliente->expedients as $exp)
                        <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center">
                            <div class="space-y-1">
                                <span class="font-bold text-gray-900">{{ $exp->titulo }}</span>
                                <p class="text-[10px] text-gray-400">Servicio: {{ $exp->service->nombre ?? 'Varios' }} | Apertura: {{ \Carbon\Carbon::parse($exp->fecha_apertura)->format('d/m/Y') }}</p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="bg-gray-100 text-gray-800 font-bold px-2 py-0.5 rounded text-[9px] uppercase">
                                    {{ $exp->estado }}
                                </span>
                                <a href="{{ route('panel.expedientes.show', $exp->id) }}" class="text-blue-600 hover:underline font-bold">Ver ficha</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400 py-2">Este cliente no tiene expedientes registrados.</p>
                    @endforelse
                </div>
            </div>

            <p><a href="{{ route('panel.documental.conocimiento', $cliente->id) }}" class="inline-block border border-gray-300 hover:border-gray-500 text-gray-800 font-bold px-4 py-2 rounded-lg text-xs">Conocimiento documental del cliente</a></p>

            <!-- Facturas Emitidas -->
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4 border-b border-gray-100 pb-3">Historial de Facturación</h3>
                
                <div class="space-y-3">
                    @forelse($cliente->invoices as $fac)
                        <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center font-mono">
                            <div class="space-y-1 font-brand-sans">
                                <span class="font-bold text-gray-900">{{ $fac->esBorrador() ? "Borrador" : $fac->numero }}</span>
                                <p class="text-[10px] text-gray-400">Fecha: {{ \Carbon\Carbon::parse($fac->fecha)->format('d/m/Y') }}</p>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="font-black text-gray-900">{{ number_format($fac->total, 2, ',', '.') }}€</span>
                                <span class="bg-gray-100 text-gray-800 font-bold px-2 py-0.5 rounded text-[9px] font-brand-sans uppercase">
                                    {{ $fac->estado }}
                                </span>
                                <a href="{{ route('panel.facturas.show', $fac->id) }}" class="text-blue-600 hover:underline font-bold font-brand-sans">Detalle</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400 py-2">No se han emitido facturas para este cliente.</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>
</x-panel-layout>
