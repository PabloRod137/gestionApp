{{-- Mensajes de clientes: conversaciones cuyo último mensaje es del cliente (le debemos una respuesta). Variables: $pendientes (los que se enseñan), $totalPendientes. --}}
<div id="mensajes-clientes" class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-base font-bold text-gray-900 font-brand-serif">Mensajes de clientes</h2>
        @if($totalPendientes > 0)
            <span class="shrink-0 bg-amber-100 text-amber-800 font-bold px-2.5 py-0.5 rounded-full text-[10px] uppercase tracking-wider">{{ $totalPendientes }} por responder</span>
        @endif
    </div>

    @if($totalPendientes === 0)
        <p class="mt-2 text-gray-400 font-normal flex items-center gap-2"><span aria-hidden="true" class="text-emerald-600">✓</span> Todo respondido. Aquí aparecerán los clientes que escriban y esperen respuesta.</p>
    @else
        <p class="mt-1 mb-3 text-gray-400 font-normal">Clientes que han escrito y esperan respuesta, los que llevan más tiempo primero.</p>
        <ul class="max-h-96 overflow-y-auto -mx-2 divide-y divide-gray-100">
            @foreach($pendientes as $p)
                <li>
                    <a href="{{ route('panel.expedientes.show', $p['expediente']->id) }}#chat" data-urgencia="{{ $p['urgencia'] }}"
                       class="flex items-start gap-3 px-2 py-2.5 rounded-lg hover:bg-gray-50 group">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ ['alta' => 'bg-rose-500', 'media' => 'bg-amber-500', 'normal' => 'bg-emerald-500'][$p['urgencia']] }}" title="{{ ['alta' => 'Más de 2 días esperando', 'media' => 'Más de 1 día esperando', 'normal' => 'Reciente'][$p['urgencia']] }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-gray-900 truncate">{{ $p['expediente']->client->razon_social }}</span>
                            <span class="block text-gray-600 font-normal leading-snug" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $p['extracto'] }}</span>
                            <span class="block mt-0.5 text-[10px] text-gray-400"><span class="font-mono">{{ $p['cuando'] }}</span> · {{ $p['expediente']->codigo }}</span>
                        </span>
                        <span class="hidden xl:inline shrink-0 self-center text-[10px] font-bold uppercase tracking-wider text-emerald-800 opacity-0 group-hover:opacity-100">Responder →</span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if($totalPendientes > $pendientes->count())
            <p class="mt-3 text-gray-400 font-normal">y {{ $totalPendientes - $pendientes->count() }} más · <a href="{{ route('panel.expedientes.index') }}" class="text-emerald-800 font-bold hover:underline">ver expedientes</a></p>
        @endif
    @endif
</div>
