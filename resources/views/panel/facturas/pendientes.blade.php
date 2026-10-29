@php
    $colores = [
        'listo' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'borrador' => 'bg-amber-50 text-amber-800 border-amber-200',
        'a_presupuestar' => 'bg-blue-50 text-blue-800 border-blue-200',
        'falta_tarifa' => 'bg-rose-50 text-rose-800 border-rose-200',
        'falta_servicio' => 'bg-rose-50 text-rose-800 border-rose-200',
    ];
    $etiquetas = \App\Support\Cobros\PendienteDeFacturar::ETIQUETAS;
@endphp
<x-panel-layout>
    <div class="mb-6">
        <a href="{{ route('panel.facturas.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a facturas</span>
        </a>
    </div>

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 text-xs">
        <div class="space-y-1">
            <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Pendiente de facturar</h1>
            <p class="text-sm text-gray-500 font-brand-sans max-w-2xl">El programa mira los expedientes y su servicio con su tarifa, y detecta cuáles piden factura. Prepara borradores; tú los revisas y los emites. Nada se emite solo.</p>
        </div>
        @if ($listos)
            <form action="{{ route('panel.facturas.preparar-todos') }}" method="POST">
                @csrf
                <button type="submit" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow">Preparar los {{ $listos }} listos</button>
            </form>
        @endif
    </div>

    @if (session('success')) <div class="mb-4 p-3 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 text-xs rounded-r-lg">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ session('error') }}</div> @endif

    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                        <th class="px-3 py-4">Expediente</th>
                        <th class="px-3 py-4">Cliente</th>
                        <th class="px-3 py-4">Situación</th>
                        <th class="px-3 py-4">Qué pasa</th>
                        <th class="px-3 py-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-brand-sans">
                    @forelse ($filas as $f)
                        @php $e = $f['expediente']; @endphp
                        <tr class="hover:bg-gray-50/50 transition align-top">
                            <td class="px-3 py-4"><a href="{{ route('panel.expedientes.show', $e->id) }}" class="font-bold text-gray-900 hover:underline">{{ $e->codigo }}</a><div class="text-gray-500">{{ \Illuminate\Support\Str::limit($e->titulo, 60) }}</div></td>
                            <td class="px-3 py-4 font-semibold text-gray-800">{{ $e->client->razon_social ?? '—' }}</td>
                            <td class="px-3 py-4"><span class="border {{ $colores[$f['estado']] ?? 'bg-gray-100 text-gray-800 border-gray-200' }} font-bold px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider whitespace-nowrap">{{ $etiquetas[$f['estado']] ?? $f['estado'] }}</span></td>
                            <td class="px-3 py-4 max-w-sm">{{ $f['motivo'] }}</td>
                            <td class="px-3 py-4 text-right whitespace-nowrap">
                                @if ($f['estado'] === 'borrador')
                                    <a href="{{ route('panel.facturas.show', $f['factura']->id) }}" class="text-blue-700 hover:underline font-bold">Revisar borrador</a>
                                @else
                                    <form action="{{ route('panel.facturas.preparar', $e->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="font-bold {{ $f['estado'] === 'listo' ? 'text-emerald-700 hover:text-emerald-900' : 'text-gray-600 hover:text-gray-900' }}">{{ $f['estado'] === 'listo' ? 'Preparar borrador' : 'Preparar borrador (a completar)' }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">No hay nada pendiente de facturar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4 text-[11px] text-gray-400 font-brand-sans">No aparecen: lo ya facturado, los servicios incluidos en cuota o no facturables, y los expedientes cuyo servicio se factura al finalizar y aún están en curso.</p>
</x-panel-layout>
