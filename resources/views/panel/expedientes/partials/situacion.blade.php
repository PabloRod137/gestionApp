{{-- Situación del expediente de un vistazo: Tengo / Falta / Puedo generar / Siguiente actuación + acciones --}}
<section aria-label="Situación operativa" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6 text-xs font-brand-sans">
    <article class="bg-white border border-gray-150 border-t-4 border-t-emerald-600 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3"><span class="font-black tracking-[0.1em] text-gray-800 uppercase">Tengo</span></div>
        <ul>
            @foreach($situacion['tengo'] as $t)
                <li class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 last:border-0"><span class="text-gray-700">{{ $t['texto'] }}</span><strong class="text-gray-900">{{ $t['valor'] }}</strong></li>
            @endforeach
        </ul>
    </article>

    <article id="falta" class="bg-white border border-gray-150 border-t-4 border-t-amber-500 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3"><span class="font-black tracking-[0.1em] text-gray-800 uppercase">Falta</span>
            <b class="h-6 min-w-6 px-2 rounded-full bg-amber-500 text-white inline-flex items-center justify-center">{{ count($situacion['falta']) }}</b></div>
        <ul>
            @forelse($situacion['falta'] as $f)
                <li class="py-2 border-b border-gray-100 last:border-0"><strong class="block text-gray-800">{{ $f['texto'] }}</strong><span class="text-gray-500">{{ $f['nota'] }}</span></li>
            @empty
                <li class="text-gray-400">No consta ningún bloqueo.</li>
            @endforelse
        </ul>
    </article>

    <article class="bg-white border border-gray-150 border-t-4 border-t-[#A28C3F] rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3"><span class="font-black tracking-[0.1em] text-gray-800 uppercase">Puedo generar</span>
            <b class="h-6 min-w-6 px-2 rounded-full bg-[#A28C3F] text-white inline-flex items-center justify-center">{{ count($situacion['generar']) }}</b></div>
        <ul>
            @forelse($situacion['generar'] as $g)
                <li class="py-2 border-b border-gray-100 last:border-0"><strong class="block text-gray-800">{{ $g['texto'] }}</strong><span class="text-gray-500">{{ $g['nota'] }}</span></li>
            @empty
                <li class="text-gray-400">No hay una generación disponible para este expediente.</li>
            @endforelse
        </ul>
    </article>

    <article class="bg-white border border-gray-150 border-t-4 border-t-[#2B2F33] rounded-2xl p-5 shadow-sm">
        <div class="mb-3"><span class="font-black tracking-[0.1em] text-gray-800 uppercase">Siguiente actuación</span></div>
        <h3 class="text-base font-bold text-gray-900 leading-snug mt-6">{{ $situacion['siguiente']['titulo'] }}</h3>
        <p class="text-gray-500 mt-2">{{ $situacion['siguiente']['detalle'] }}</p>
    </article>
</section>

<section aria-label="Acciones del expediente" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-8 text-xs font-brand-sans">
    @foreach($situacion['acciones'] as $a)
        <div class="bg-white border border-gray-150 rounded-2xl p-3 shadow-sm">
            @if($a['activa'])
                <a href="{{ $a['href'] }}" @if(!empty($a['externa'])) target="_blank" rel="noopener noreferrer" @endif
                   class="block w-full text-center font-extrabold uppercase tracking-wide text-[10px] px-2 py-3 rounded-lg {{ $a['clave'] === 'informe' ? 'bg-[#A28C3F] text-[#1B1E21]' : 'bg-[#2B2F33] text-white hover:bg-[#3a3f44]' }}">{{ $a['etiqueta'] }}</a>
            @else
                <span class="block w-full text-center font-extrabold uppercase tracking-wide text-[10px] px-2 py-3 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed" aria-disabled="true">{{ $a['etiqueta'] }}</span>
            @endif
            <small class="block mt-2 text-gray-500 leading-snug">{{ $a['nota'] }}</small>
        </div>
    @endforeach
</section>
