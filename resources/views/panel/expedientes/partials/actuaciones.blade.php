{{-- Actuaciones automáticas: servicio sugerido por el asunto y pasos propuestos (presupuesto, documento, factura). Nada se genera ni se envía. --}}
@php
    $svc = $expediente->service;
    $pasos = app(\App\Support\PlanActuaciones::class)->pasos($expediente);
    $sug = session('servicio_sugerido');
    $sug = ($sug && ($sug['expediente_id'] ?? null) === $expediente->id) ? $sug : null;
    $hayPendientes = collect($pasos)->contains('estado', 'pendiente');
    $etiquetas = ['pendiente' => ['Por proponer', 'bg-amber-100 text-amber-800'], 'propuesta' => ['Ya propuesta', 'bg-emerald-100 text-emerald-800'], 'sin_modelo' => ['Falta el modelo Word', 'bg-rose-100 text-rose-800'], 'manual' => ['Se hace en Facturación', 'bg-sky-100 text-sky-800']];
    $boton = 'bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider';
    $botonSec = 'bg-white border border-gray-300 text-gray-700 font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider';
@endphp
<div id="actuaciones" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-base font-bold text-gray-900 font-brand-serif">Actuaciones propuestas</h3>
        @if($svc)<span class="text-gray-500">Servicio: <a href="{{ route('panel.servicios.show', $svc->id) }}" class="text-emerald-800 font-bold hover:underline">{{ $svc->nombre }}</a></span>@endif
    </div>

    @if ($errors->has('service_id') || $errors->has('workflow'))
        <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">{{ $errors->first('service_id') ?: $errors->first('workflow') }}</div>
    @endif

    @if($svc)
        <p class="text-gray-500 mb-3">Según el servicio del expediente, estos son los pasos habituales. Al proponerlas quedan <strong>pendientes de aprobar</strong>: no se genera ni se envía nada.</p>
        @forelse($pasos as $p)
            @php [$txt, $cls] = $etiquetas[$p['estado']]; @endphp
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 last:border-0 py-2">
                <div>
                    <span class="font-bold text-gray-800">{{ $p['titulo'] }}</span>
                    @if($p['finalidad'])<span class="block text-gray-500">{{ $p['finalidad'] }}</span>@endif
                    @if($p['nota'])<span class="block text-amber-700">{{ $p['nota'] }}</span>@endif
                    @if($p['tipo'] === 'factura')<a href="{{ route('panel.facturas.create', ['expediente' => $expediente->id]) }}" class="text-emerald-800 font-bold hover:underline">Preparar la factura</a>@endif
                </div>
                <span class="shrink-0 px-2 py-0.5 rounded-full font-bold text-[9px] uppercase tracking-wider {{ $cls }}">{{ $txt }}</span>
            </div>
        @empty
            <p class="text-gray-400">Este servicio no genera presupuesto, documento ni factura aparte.</p>
        @endforelse
        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if($hayPendientes)
                <form method="POST" action="{{ route('panel.expedientes.proponer-actuaciones', $expediente->id) }}">@csrf<button class="{{ $boton }}">Proponer actuaciones</button></form>
            @endif
            <form method="POST" action="{{ route('panel.expedientes.sugerir-servicio', $expediente->id) }}">@csrf<button class="{{ $botonSec }}">¿No es este servicio? Sugerir otro</button></form>
        </div>
    @else
        <p class="text-gray-500 mb-3">Este expediente no tiene servicio. El programa puede sugerirlo a partir del asunto («{{ $expediente->titulo }}»); tú lo confirmas.</p>
        <form method="POST" action="{{ route('panel.expedientes.sugerir-servicio', $expediente->id) }}" class="flex flex-wrap items-center gap-4">
            @csrf
            <button class="{{ $boton }}">Sugerir servicio</button>
            @if(config('ia.enabled'))<label class="flex items-center gap-2 text-gray-600"><input type="checkbox" name="usar_ia" value="1"> Probar también con la IA local (experimental: tarda unos 30 s y acierta poco; revisa siempre su sugerencia)</label>@endif
        </form>
    @endif

    @if($sug)
        <div class="mt-4 border border-amber-200 bg-amber-50/50 rounded-xl p-4">
            <span class="bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">Sugerencia: pendiente de que la confirmes</span>
            @if($sug['aviso_ia'])<p class="mt-2 text-amber-800">{{ $sug['aviso_ia'] }}</p>@endif
            @if($sug['ia'])
                <div class="mt-3 flex items-center justify-between gap-3"><span><strong>IA local:</strong> {{ $sug['ia']['nombre'] }}<span class="block text-gray-500">{{ $sug['ia']['motivo'] }}</span></span>
                    @include('panel.expedientes.partials.asignar-servicio', ['serviceId' => $sug['ia']['id'], 'origen' => 'ia', 'svc' => $svc])</div>
            @endif
            @forelse($sug['candidatos'] as $c)
                <div class="mt-3 flex items-center justify-between gap-3"><span><strong>{{ $c['nombre'] }}</strong>@if($c['claro']) <span class="text-emerald-800">· coincidencia clara</span>@endif<span class="block text-gray-500">Por: {{ $c['motivo'] }}</span></span>
                    @include('panel.expedientes.partials.asignar-servicio', ['serviceId' => $c['id'], 'origen' => 'palabras_clave', 'svc' => $svc])</div>
            @empty
                <p class="mt-3 text-gray-600">Ningún servicio encaja con ese asunto. Asígnalo a mano al editar el expediente.</p>
            @endforelse
        </div>
    @endif
</div>
