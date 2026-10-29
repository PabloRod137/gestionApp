<x-panel-layout>
    <div class="mb-6"><a href="{{ route('panel.servicios.index') }}" class="text-xs text-gray-500 hover:text-gray-900">← Volver al catálogo</a></div>

    @php
        $normalizados = $servicio->requirements->where('normalizado', true);
    @endphp

    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
        <span class="text-[10px] font-mono text-gray-400">{{ $servicio->codigo }}</span>
        <h1 class="text-xl font-bold text-gray-900 font-brand-serif">{{ $servicio->nombre }}</h1>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-gray-600">
            <div><dt class="text-gray-400 uppercase text-[9px]">Para</dt><dd>{{ $servicio->tipo_cliente ?: '—' }}</dd></div>
            <div><dt class="text-gray-400 uppercase text-[9px]">Cobro</dt><dd>{{ $servicio->tipo_cobro ?: '—' }} · {{ $servicio->periodicidad ?: '—' }}</dd></div>
            <div><dt class="text-gray-400 uppercase text-[9px]">Base imponible</dt><dd>{{ number_format($servicio->precio_base, 2, ',', '.') }} €{{ $servicio->iva !== null ? ' + '.(float) $servicio->iva.'% IVA' : '' }}</dd></div>
            <div><dt class="text-gray-400 uppercase text-[9px]">Genera documento</dt><dd>{{ $servicio->genera_documento ? 'Sí' : 'No' }}</dd></div>
        </dl>
        @if($servicio->accion_sugerida)<p class="mt-4 text-gray-500"><span class="font-bold text-gray-700">Acción sugerida:</span> {{ $servicio->accion_sugerida }}</p>@endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 text-xs">
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Documentación que se pide al cliente</h3>
            @forelse($normalizados as $r)
                <div class="py-1.5 border-b border-gray-100 last:border-0 text-gray-700">{{ $r->descripcion }}
                    @if($r->obligatorio)<span class="text-rose-700 font-bold uppercase text-[8px]">obligatorio</span>@else<span class="text-gray-400 uppercase text-[8px]">opcional</span>@endif
                </div>
            @empty
                <p class="text-gray-700">{{ $servicio->documentacion_requerida ?: 'Sin requisitos definidos.' }}</p>
                @if($servicio->documentacion_requerida)<p class="text-amber-700 mt-2 text-[10px]">Lista sin desglosar todavía.</p>@endif
            @endforelse
        </div>

        <div class="space-y-8">
            <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
                <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Modelos de documento</h3>
                @if (session('success'))
                    <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">{{ session('success') }}</div>
                @endif
                @forelse($servicio->templates as $t)
                    <div class="py-1.5 border-b border-gray-100 last:border-0 text-gray-700 flex items-center justify-between gap-3">
                        <span>{{ $t->nombre }} @unless($t->archivo)<span class="text-amber-700 text-[9px] uppercase">sin archivo Word</span>@endunless</span>
                        @role('admin')
                            <form method="POST" action="{{ route('panel.servicios.plantillas.quitar', [$servicio->id, $t->id]) }}">@csrf @method('DELETE')
                                <button class="text-rose-700 hover:underline text-[10px]">Quitar</button></form>
                        @endrole
                    </div>
                @empty
                    <p class="text-amber-700">Todavía no hay plantillas asignadas a este servicio.</p>
                @endforelse
                @role('admin')
                    @if ($disponibles->isNotEmpty())
                        <form method="POST" action="{{ route('panel.servicios.plantillas.asignar', $servicio->id) }}" class="mt-4 flex items-center gap-2">
                            @csrf
                            <label class="sr-only" for="document_template_id">Plantilla</label>
                            <select id="document_template_id" name="document_template_id" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-xs">
                                @foreach ($disponibles as $d)<option value="{{ $d->id }}">{{ $d->nombre }}</option>@endforeach
                            </select>
                            <button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-3 py-2 rounded text-[10px] uppercase">Asignar</button>
                        </form>
                    @endif
                @endrole
            </div>
            <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
                <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Últimos expedientes</h3>
                @forelse($expedientes as $e)
                    <a href="{{ route('panel.expedientes.show', $e->id) }}" class="block py-1.5 border-b border-gray-100 last:border-0 text-gray-700 hover:underline">{{ $e->codigo ? $e->codigo.' · ' : '' }}{{ $e->titulo }} <span class="text-gray-400">— {{ $e->client->razon_social }}</span></a>
                @empty
                    <p class="text-gray-400">Ningún expediente usa este servicio todavía.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-panel-layout>
