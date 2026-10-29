@php
    $emitida = ! $factura->esBorrador();
    $despacho = \App\Support\Despacho::datos();
    $emisor = $emitida && $factura->emisor_nif ? ['nombre' => $factura->emisor_nombre, 'nif' => $factura->emisor_nif, 'direccion' => $factura->emisor_direccion] : $despacho;
    $receptor = $emitida && $factura->receptor_nif ? ['nombre' => $factura->receptor_nombre, 'nif' => $factura->receptor_nif, 'direccion' => $factura->receptor_direccion] : ['nombre' => $factura->client->razon_social, 'nif' => $factura->client->nif, 'direccion' => $factura->client->direccion];
    $etiquetaEmision = ['borrador' => ['Borrador', 'bg-amber-50 text-amber-800 border-amber-200'], 'emitida' => ['Emitida', 'bg-emerald-50 text-emerald-800 border-emerald-200'], 'anulada' => ['Anulada', 'bg-gray-100 text-gray-700 border-gray-300'], 'historica' => ['Anterior a Veri*Factu', 'bg-gray-100 text-gray-700 border-gray-300']][$factura->emision] ?? ['—', 'bg-gray-100'];
    $iva = fn ($c) => number_format($c / 100, 2, ',', '.').' €';
    $estadosEnvio = ['pendiente' => 'Pendiente de envío', 'aceptado' => 'Aceptado por la AEAT', 'aceptado_errores' => 'Aceptado con errores', 'rechazado' => 'Rechazado por la AEAT'];
@endphp
<x-panel-layout>
    <div class="mb-6 flex flex-wrap justify-between items-center gap-3 text-xs">
        <a href="{{ route('panel.facturas.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition font-brand-sans">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a facturas</span>
        </a>
        <div class="flex flex-wrap items-center gap-2 font-brand-sans">
            @if ($factura->esBorrador())
                <a href="{{ route('panel.facturas.edit', $factura->id) }}" class="border border-gray-300 hover:border-gray-500 text-gray-800 font-bold px-4 py-2 rounded-lg transition">Editar borrador</a>
            @endif
            <a href="{{ route('panel.facturas.pdf', $factura->id) }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-4 py-2 rounded-lg transition inline-flex items-center space-x-1 shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>{{ $factura->esBorrador() ? 'Vista previa PDF' : 'Descargar PDF' }}</span>
            </a>
        </div>
    </div>

    @if (session('success')) <div class="mb-4 p-3 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 text-xs rounded-r-lg">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ $errors->first() }}</div> @endif

    {{-- Qué falta para poder emitir --}}
    @if ($factura->esBorrador() && count($problemas))
        <div class="max-w-3xl mx-auto mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 font-brand-sans">
            <p class="font-bold mb-1">Antes de emitirla hay que resolver:</p>
            <ul class="list-disc pl-5 space-y-0.5">@foreach ($problemas as $p)<li>{{ $p }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs font-mono max-w-3xl mx-auto space-y-8">
        <div class="flex justify-between items-start border-b border-gray-100 pb-6 gap-4 font-brand-sans">
            <div class="space-y-1">
                <span class="text-emerald-900 font-bold uppercase tracking-widest text-[9px]">{{ $emisor['nombre'] ?: 'Nortia' }}</span>
                <h1 class="text-xl font-bold text-gray-950 font-brand-serif leading-tight">
                    {{ $factura->esRectificativa() ? 'Factura rectificativa' : 'Factura' }}
                    {{ $factura->esBorrador() ? '(borrador, sin número)' : 'nº '.$factura->numero }}
                </h1>
                <p class="text-gray-400 font-mono text-[10px]">
                    @if ($factura->esBorrador()) La fecha y el número se asignan al emitirla.
                    @else Emisión: {{ $factura->fecha->format('d/m/Y') }} @endif
                </p>
                @if ($factura->esRectificativa())
                    <p class="text-gray-500 text-[10px]">Rectifica la factura {{ $factura->rectificada?->numero ?? '—' }} ({{ $factura->tipo_rectificativa === 'S' ? 'por sustitución' : 'por diferencias' }}, {{ $factura->tipo_factura }}){{ $factura->motivo_rectificacion ? ': '.$factura->motivo_rectificacion : '' }}</p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-1.5">
                <span class="border {{ $etiquetaEmision[1] }} font-bold px-3 py-1 rounded-full uppercase tracking-wider text-[9px]">{{ $etiquetaEmision[0] }}</span>
                @if ($emitida)
                    <span class="border {{ $factura->estado === 'pagada' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200' }} font-bold px-3 py-1 rounded-full uppercase tracking-wider text-[9px]">{{ $factura->estado === 'pagada' ? 'Cobrada' : 'Pendiente de cobro' }}</span>
                @endif
            </div>
        </div>

        @if ($factura->emision === 'anulada')
            <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 font-brand-sans text-[11px]">
                Anulada el {{ $factura->anulada_at?->format('d/m/Y H:i') }}. Motivo: {{ $factura->anulada_motivo }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 text-[11px] font-brand-sans text-gray-600">
            <div class="space-y-2">
                <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[10px]">Emisor</h4>
                <p class="font-bold text-gray-800">{{ $emisor['nombre'] }}</p>
                <p>NIF: {{ $emisor['nif'] ?: '—' }}</p>
                <p>Dirección: {{ $emisor['direccion'] ?: '—' }}</p>
            </div>
            <div class="space-y-2">
                <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[10px]">Receptor / cliente</h4>
                <p class="font-bold text-gray-800">{{ $receptor['nombre'] }}</p>
                <p class="uppercase">NIF/CIF: {{ $receptor['nif'] ?: '—' }}</p>
                <p>Dirección: {{ $receptor['direccion'] ?: '—' }}</p>
            </div>
        </div>

        <div class="border border-gray-100 rounded-xl overflow-hidden font-brand-sans">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="px-4 py-3">Concepto</th>
                        <th class="px-4 py-3 text-right">Cant.</th>
                        <th class="px-4 py-3 text-right">Precio</th>
                        <th class="px-4 py-3 text-right">IVA</th>
                        <th class="px-4 py-3 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($factura->lines as $item)
                        <tr>
                            <td class="px-4 py-3 text-xs">{{ $item->concepto }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs">{{ $item->cantidad }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs">{{ number_format($item->precio_unitario, 2, ',', '.') }} €</td>
                            <td class="px-4 py-3 text-right font-mono text-xs">{{ ($item->calificacion ?: 'S1') === 'S1' ? rtrim(rtrim(number_format($item->tipo_iva, 2, ',', ''), '0'), ',').' %' : $item->calificacion }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs">{{ number_format($item->subtotal, 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-400">Sin líneas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-end font-brand-sans">
            <div class="w-80 space-y-2 text-right border-t border-gray-100 pt-4">
                @foreach ($totales['grupos'] as $g)
                    <div class="flex justify-between text-gray-500">
                        <span>Base {{ $g['calificacion'] === 'S1' ? rtrim(rtrim(number_format($g['tipo'], 2, ',', ''), '0'), ',').' %' : $g['calificacion'] }}:</span>
                        <span class="font-mono text-gray-900">{{ $iva($g['base']) }}</span>
                    </div>
                    @if ($g['calificacion'] === 'S1')
                        <div class="flex justify-between text-gray-500"><span>IVA {{ rtrim(rtrim(number_format($g['tipo'], 2, ',', ''), '0'), ',') }} %:</span><span class="font-mono text-gray-900">{{ $iva($g['cuota']) }}</span></div>
                    @endif
                @endforeach
                <div class="flex justify-between gap-4 text-base font-extrabold border-t border-gray-200 pt-2 text-emerald-950"><span>Importe total:</span><span class="font-mono">{{ $iva($totales['total']) }}</span></div>
                @if ($totales['retencion'])
                    <div class="flex justify-between text-gray-500"><span>Retención IRPF {{ rtrim(rtrim(number_format((float) $factura->retencion_irpf, 2, ',', ''), '0'), ',') }} %:</span><span class="font-mono text-gray-900">−{{ $iva($totales['retencion']) }}</span></div>
                    <div class="flex justify-between font-bold text-gray-900"><span>A cobrar:</span><span class="font-mono">{{ $iva($totales['total'] - $totales['retencion']) }}</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Acciones según el estado --}}
    <div class="max-w-3xl mx-auto mt-6 space-y-4 font-brand-sans text-xs">
        @if ($factura->esBorrador())
            <div class="flex flex-wrap gap-3">
                <form action="{{ route('panel.facturas.emitir', $factura->id) }}" method="POST" onsubmit="return confirm('Al emitir la factura se le asigna su número y su registro de facturación, y ya no se podrá modificar ni borrar (solo anular o rectificar). ¿Emitirla ahora?')">
                    @csrf
                    <button type="submit" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-2.5 rounded-lg transition shadow-sm">Emitir factura</button>
                </form>
                <form action="{{ route('panel.facturas.destroy', $factura->id) }}" method="POST" onsubmit="return confirm('¿Borrar este borrador?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="border border-rose-300 text-rose-700 hover:bg-rose-50 font-bold px-5 py-2.5 rounded-lg transition">Borrar borrador</button>
                </form>
            </div>
        @endif

        @if (in_array($factura->emision, ['emitida', 'historica']))
            <div class="grid sm:grid-cols-2 gap-4">
                <details class="bg-white border border-gray-150 rounded-xl p-4">
                    <summary class="cursor-pointer font-bold text-gray-800">Rectificar esta factura</summary>
                    <form action="{{ route('panel.facturas.rectificar', $factura->id) }}" method="POST" class="mt-3 space-y-3">
                        @csrf
                        <label class="block">Tipo
                            <select name="tipo" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2">
                                <option value="S">Por sustitución (la nueva factura la reemplaza entera)</option>
                                <option value="I">Por diferencias (solo lo que cambia)</option>
                            </select>
                        </label>
                        <label class="block">Clave
                            <select name="clave" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2">
                                @foreach (\App\Support\Verifactu\Facturacion::TIPOS_RECTIFICATIVA as $k => $t) <option value="{{ $k }}">{{ $t }}</option> @endforeach
                            </select>
                        </label>
                        <label class="block">Motivo <input type="text" name="motivo" maxlength="255" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2"></label>
                        <button type="submit" class="border border-gray-300 hover:border-gray-500 font-bold px-4 py-2 rounded-lg">Crear borrador de rectificativa</button>
                    </form>
                </details>
                @if ($factura->emision === 'emitida')
                    <details class="bg-white border border-gray-150 rounded-xl p-4">
                        <summary class="cursor-pointer font-bold text-gray-800">Anular esta factura</summary>
                        <form action="{{ route('panel.facturas.anular', $factura->id) }}" method="POST" class="mt-3 space-y-3" onsubmit="return confirm('Se enviará un registro de anulación a la AEAT y la factura quedará anulada. ¿Continuar?')">
                            @csrf
                            <label class="block">Motivo de la anulación * <input type="text" name="motivo" required maxlength="255" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2"></label>
                            <button type="submit" class="border border-rose-300 text-rose-700 hover:bg-rose-50 font-bold px-4 py-2 rounded-lg">Anular factura</button>
                        </form>
                    </details>
                @endif
            </div>
        @endif

        {{-- Registros de facturación (Veri*Factu) --}}
        @if ($factura->registros->isNotEmpty())
            <div class="bg-white border border-gray-150 rounded-xl p-4">
                <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[10px] mb-3">Registros de facturación (Veri*Factu)</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[11px]">
                        <thead><tr class="text-gray-400 uppercase tracking-wider text-[9px]"><th class="py-1 pr-3">Registro</th><th class="py-1 pr-3">Generado</th><th class="py-1 pr-3">Huella</th><th class="py-1 pr-3">Envío a la AEAT</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            @foreach ($factura->registros as $r)
                                <tr>
                                    <td class="py-2 pr-3 font-bold">{{ $r->tipo === 'alta' ? 'Alta' : 'Anulación' }}</td>
                                    <td class="py-2 pr-3 font-mono">{{ \Illuminate\Support\Carbon::parse($r->fecha_hora_huso)->format('d/m/Y H:i:s') }}</td>
                                    <td class="py-2 pr-3 font-mono" title="{{ $r->huella }}">{{ substr($r->huella, 0, 16) }}…</td>
                                    <td class="py-2 pr-3">
                                        <span class="font-bold {{ $r->estado_envio === 'rechazado' ? 'text-rose-700' : ($r->estado_envio === 'pendiente' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $estadosEnvio[$r->estado_envio] ?? $r->estado_envio }}</span>
                                        @if ($r->csv) <span class="font-mono text-gray-500">· CSV {{ $r->csv }}</span> @endif
                                        @if ($r->descripcion_error) <div class="text-rose-700">{{ $r->codigo_error }} {{ $r->descripcion_error }}</div> @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (! config('verifactu.activo'))
                    <p class="mt-3 text-[10px] text-gray-400">El envío a la AEAT está apagado en este equipo: los registros se generan y encadenan, y se enviarán al activarlo.</p>
                @endif
            </div>
        @endif
    </div>
</x-panel-layout>
