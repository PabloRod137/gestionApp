<x-panel-layout>
    <!-- Encabezado y Acción -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10 text-xs">
        <div class="space-y-1">
            <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Facturación y Liquidaciones</h1>
            <p class="text-sm text-gray-500 font-brand-sans">Administra los honorarios, emitidos y cobros del despacho.</p>
        </div>
        <div class="flex items-center space-x-3">
            <!-- Exportar Excel -->
            <a href="{{ route('panel.facturas.export') }}" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold px-4 py-3 rounded-lg text-xs uppercase tracking-wider transition inline-flex items-center space-x-1.5 shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Exportar Excel</span>
            </a>
            <a href="{{ route('panel.facturas.pendientes') }}" class="border border-gray-300 hover:border-gray-500 text-gray-800 font-bold px-4 py-3 rounded-lg text-xs uppercase tracking-wider transition inline-flex items-center space-x-1.5">
                <span>Pendiente de facturar</span>
                @if (($pendientes ?? 0) > 0)<span class="bg-gold-warm text-forest-dark rounded-full px-2 py-0.5 text-[10px]">{{ $pendientes }}</span>@endif
            </a>
            <a href="{{ route('panel.facturas.create') }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow">
                Nueva Factura
            </a>
        </div>
    </div>

    @if (session('success')) <div class="mb-4 p-3 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 text-xs rounded-r-lg">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ session('error') }}</div> @endif

    <!-- Barra de Filtros -->
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-semibold">
        <form action="{{ route('panel.facturas.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            
            <!-- Buscador -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Número o Cliente</label>
                <input type="text" name="search" value="{{ $buscar }}" placeholder="Buscar por número o cliente..." class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
            </div>

            <!-- Filtro situación de la factura -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Situación</label>
                <select name="emision" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Todas</option>
                    <option value="borrador" {{ $emision === 'borrador' ? 'selected' : '' }}>Borradores</option>
                    <option value="emitida" {{ $emision === 'emitida' ? 'selected' : '' }}>Emitidas</option>
                    <option value="anulada" {{ $emision === 'anulada' ? 'selected' : '' }}>Anuladas</option>
                    <option value="historica" {{ $emision === 'historica' ? 'selected' : '' }}>Anteriores a Veri*Factu</option>
                </select>
            </div>

            <!-- Filtro Cobro -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Cobro</label>
                <select name="estado" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Cualquiera</option>
                    <option value="pendiente" {{ $estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="pagada" {{ $estado === 'pagada' ? 'selected' : '' }}>Cobrada</option>
                </select>
            </div>

            <!-- Botones -->
            <div class="flex items-center space-x-2">
                <button type="submit" class="w-full bg-forest-medium hover:bg-forest-dark text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition">
                    Filtrar Facturas
                </button>
                @if($buscar || $estado || $emision)
                    <a href="{{ route('panel.facturas.index') }}" class="text-gray-400 hover:text-gray-600 underline">Limpiar</a>
                @endif
            </div>

        </form>
    </div>

    <!-- Listado en Tabla -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                        <th class="px-3 py-4">Factura nº</th>
                        <th class="px-3 py-4">Cliente / Razón Social</th>
                        <th class="px-3 py-4">Fecha Emisión</th>
                        <th class="px-3 py-4 text-right">Base Imponible</th>
                        <th class="px-3 py-4 text-right">IVA</th>
                        <th class="px-3 py-4 text-right">Importe Total</th>
                        <th class="px-3 py-4 text-center">Situación</th>
                        <th class="px-3 py-4 text-center">Cobro</th>
                        <th class="px-3 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium font-mono">
                    @forelse($facturas as $fac)
                        <tr class="hover:bg-gray-50/50 transition">
                            
                            <!-- Número -->
                            <td class="px-3 py-4 font-bold text-gray-900 font-brand-sans">
                                {{ $fac->esBorrador() ? 'Borrador' : $fac->numero }}
                                @if ($fac->esRectificativa()) <span class="font-normal text-[9px] text-gray-400">(rectificativa)</span> @endif
                            </td>

                            <!-- Cliente -->
                            <td class="px-3 py-4 font-brand-sans font-semibold text-gray-800">
                                {{ $fac->client->razon_social }}
                            </td>

                            <!-- Fecha -->
                            <td class="px-3 py-4">
                                {{ \Carbon\Carbon::parse($fac->fecha)->format('d/m/Y') }}
                            </td>

                            <!-- Base Imponible -->
                            <td class="px-3 py-4 text-right">
                                {{ number_format($fac->base_imponible, 2, ',', '.') }}€
                            </td>

                            <!-- IVA -->
                            <td class="px-3 py-4 text-right">
                                {{ $fac->esBorrador() ? '—' : number_format($fac->cuota_iva ?: ($fac->total - $fac->base_imponible), 2, ',', '.').'€' }}
                            </td>

                            <!-- Total -->
                            <td class="px-3 py-4 text-right font-black text-emerald-950">
                                {{ number_format($fac->total, 2, ',', '.') }}€
                            </td>

                            <!-- Situación -->
                            <td class="px-3 py-4 text-center font-brand-sans">
                                @php
                                    $sit = ['borrador' => ['Borrador', 'bg-amber-50 text-amber-800 border-amber-200'], 'emitida' => ['Emitida', 'bg-emerald-50 text-emerald-800 border-emerald-200'], 'anulada' => ['Anulada', 'bg-gray-100 text-gray-700 border-gray-300'], 'historica' => ['Anterior', 'bg-gray-100 text-gray-700 border-gray-300']][$fac->emision] ?? ['—', 'bg-gray-100'];
                                    $ultimo = $fac->registros->last();
                                @endphp
                                <span class="border {{ $sit[1] }} font-bold px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">{{ $sit[0] }}</span>
                                @if ($ultimo)
                                    <div class="mt-1 text-[9px] {{ $ultimo->estado_envio === 'rechazado' ? 'text-rose-700 font-bold' : 'text-gray-400' }}">AEAT: {{ ['pendiente' => 'pendiente', 'aceptado' => 'aceptado', 'aceptado_errores' => 'con errores', 'rechazado' => 'rechazado'][$ultimo->estado_envio] ?? $ultimo->estado_envio }}</div>
                                @endif
                            </td>

                            <!-- Cobro -->
                            <td class="px-3 py-4 text-center font-brand-sans">
                                @if ($fac->esBorrador())
                                    <span class="text-gray-300">—</span>
                                @else
                                    <span class="border {{ $fac->estado === 'pagada' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' }} font-bold px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">{{ $fac->estado === 'pagada' ? 'Cobrada' : 'Pendiente' }}</span>
                                @endif
                            </td>

                            <!-- Acciones -->
                            <td class="px-3 py-4 text-right font-brand-sans whitespace-nowrap">
                                <a href="{{ route('panel.facturas.show', $fac->id) }}" class="text-gray-500 hover:text-gray-900 font-bold px-2 py-1">
                                    Ver Ficha
                                </a>
                                @if (! $fac->esBorrador() && $fac->emision !== 'anulada')
                                <form action="{{ route('panel.facturas.update-status', $fac->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    @if($fac->estado === 'pendiente')
                                        <input type="hidden" name="estado" value="pagada">
                                        <button type="submit" class="text-emerald-700 hover:text-emerald-900 font-bold px-2 py-1">
                                            Cobrar
                                        </button>
                                    @else
                                        <input type="hidden" name="estado" value="pendiente">
                                        <button type="submit" class="text-rose-600 hover:text-rose-900 font-bold px-2 py-1">
                                            Reabrir
                                        </button>
                                    @endif
                                </form>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr class="font-brand-sans">
                            <td colspan="9" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No se encontraron facturas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="mt-6 text-xs font-semibold">
        {{ $facturas->appends(request()->query())->links() }}
    </div>
</x-panel-layout>
