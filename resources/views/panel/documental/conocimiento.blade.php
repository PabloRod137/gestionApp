@php $estados = ['analizado' => 'Analizados', 'pendiente' => 'En cola', 'procesando' => 'Procesando', 'omitido' => 'Omitidos', 'error' => 'Con error', 'desaparecido' => 'Desaparecidos']; @endphp
<x-panel-layout>
    <div class="mb-6"><a href="{{ route('panel.clientes.show', $cliente->id) }}" class="text-xs text-gray-500 hover:text-gray-900">← Volver a la ficha de {{ $cliente->razon_social }}</a></div>
    <div class="mb-6 space-y-1">
        <h1 class="text-2xl font-extrabold text-gray-900 font-brand-serif">Conocimiento de {{ $cliente->razon_social }}</h1>
        <p class="text-sm text-gray-500 max-w-3xl">Relaciona su ficha, sus expedientes, lo que exige cada servicio, los modelos disponibles y los documentos leídos en este equipo. Las coincidencias son pistas por nombre y texto del documento: confírmalas tú.</p>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 text-xs font-brand-sans mb-6">
        <div class="bg-white border border-gray-150 rounded-2xl p-5 shadow-sm">
            <h2 class="font-bold text-gray-900 uppercase tracking-wider text-[10px] mb-3">Ficha</h2>
            <dl class="space-y-1.5">@foreach ($mapa['ficha'] as $f)<div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $f['etiqueta'] }}</dt><dd class="font-mono {{ $f['valor'] ? 'text-gray-900' : 'text-rose-600' }}">{{ $f['valor'] ?: 'falta' }}</dd></div>@endforeach</dl>
            <p class="mt-3 text-[10px] text-gray-500">Propuestas pendientes: <a class="font-bold text-emerald-800 hover:underline" href="{{ route('panel.documental.index') }}">{{ $mapa['hallazgos']['pendientes'] }}</a> · resueltas: {{ $mapa['hallazgos']['resueltos'] }}</p>
        </div>
        <div class="bg-white border border-gray-150 rounded-2xl p-5 shadow-sm lg:col-span-2">
            <h2 class="font-bold text-gray-900 uppercase tracking-wider text-[10px] mb-3">Documentos leídos ({{ $mapa['documentos']['total'] }})</h2>
            <p class="mb-2 text-gray-600">@forelse ($mapa['documentos']['por_estado'] as $e => $n){{ $estados[$e] ?? $e }}: <strong>{{ $n }}</strong>@if (! $loop->last) · @endif @empty Todavía no hay documentos inventariados. @endforelse</p>
            <ul class="divide-y divide-gray-100 max-h-48 overflow-y-auto">
                @foreach ($mapa['documentos']['recientes'] as $d)
                    <li class="py-1 flex justify-between gap-3"><span class="truncate">{{ $d->nombre }}</span><span class="text-gray-400 whitespace-nowrap">{{ $estados[$d->estado] ?? $d->estado }}{{ $d->metodo ? ' · '.$d->metodo : '' }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

    @forelse ($mapa['expedientes'] as $x)
        <div class="bg-white border border-gray-150 rounded-2xl p-5 shadow-sm mb-3 text-xs font-brand-sans">
            <div class="flex flex-wrap justify-between gap-2">
                <a href="{{ route('panel.expedientes.show', $x['expediente']->id) }}" class="font-bold text-gray-900 hover:underline">{{ $x['expediente']->codigo }} · {{ \Illuminate\Support\Str::limit($x['expediente']->titulo, 70) }}</a>
                <span class="text-gray-500">{{ $x['servicio']?->nombre ?? 'Sin servicio asignado' }}</span>
            </div>
            @if ($x['requisitos'])
                <ul class="mt-3 space-y-1">
                    @foreach ($x['requisitos'] as $r)
                        <li class="flex justify-between gap-3"><span>{{ $r['texto'] }}</span>
                            <span class="{{ $r['posibles'] ? 'text-emerald-700' : 'text-gray-400' }} text-right">{{ $r['posibles'] ? 'Posible: '.implode(', ', $r['posibles']) : 'sin documento localizado' }}</span></li>
                    @endforeach
                </ul>
            @endif
            @if ($x['plantillas'])<p class="mt-2 text-[10px] text-gray-500">Se puede generar: {{ implode(' · ', $x['plantillas']) }}</p>@endif
        </div>
    @empty
        <p class="text-xs text-gray-400">Este cliente no tiene expedientes.</p>
    @endforelse
</x-panel-layout>
