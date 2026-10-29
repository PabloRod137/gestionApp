{{-- "Preguntar a la IA" en Mi jornada. Se elige el expediente (la IA solo ve a su cliente) y se hace una pregunta concreta. --}}
@php
    $campoIa = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 bg-white';
    $ia = session('ia_resultado');
    $activo = session('expediente_activo') ? \App\Models\Expedient::find(session('expediente_activo')) : null;
    $sugeridos = \App\Models\Expedient::with('client')->whereIn('estado', ['abierto', 'en_proceso', 'pendiente_cliente'])->latest('id')->take(300)->get();
@endphp
<div id="preguntar-ia" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-10 text-xs font-brand-sans">
    <div class="flex items-center justify-between mb-2">
        <h2 class="text-base font-bold text-gray-900 font-brand-serif">Preguntar a la IA</h2>
        <span class="bg-gray-100 text-gray-600 border border-gray-200 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">IA local</span>
    </div>

    @if(! config('ia.enabled'))
        <p class="text-gray-500">La IA local no está activada en este servidor. Cuando se active, aquí se podrá elegir un expediente y hacer una pregunta concreta; el modelo local solo verá los datos del cliente de ese expediente y nunca escribe ni envía nada.</p>
    @else
        <p class="text-gray-500 mb-4">Elige el expediente y haz una pregunta concreta. Primero se usa lo que ya sabe el programa; el modelo local solo interviene después y solo ve a ese cliente. Su respuesta es una propuesta que tú confirmas. No busca entre todos los clientes.</p>

        @if ($errors->has('ia') || $errors->has('question') || $errors->has('expediente'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">{{ $errors->first('ia') ?: ($errors->first('expediente') ?: $errors->first('question')) }}</div>
        @endif

        <form method="POST" action="{{ route('panel.ia.preguntar') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            @csrf
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="ia_expediente">Expediente (código o cliente)</label>
                <input id="ia_expediente" name="expediente" list="ia_expedientes" required autocomplete="off" value="{{ old('expediente', $ia['codigo'] ?? $activo?->codigo) }}" class="{{ $campoIa }}" placeholder="Ej. 2026-077">
                <datalist id="ia_expedientes">
                    @foreach($sugeridos as $e)<option value="{{ $e->codigo }}">{{ $e->client->razon_social }}</option>@endforeach
                </datalist>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="ia_question">Duda concreta</label>
                <textarea id="ia_question" name="question" rows="2" maxlength="1200" required class="{{ $campoIa }}" placeholder="Ej. ¿Consta en la carpeta del cliente un documento con el DNI del representante?">{{ old('question') }}</textarea>
            </div>
            <div class="md:col-span-3"><button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Analizar ahora</button></div>
        </form>

        @if($ia)
            <p class="mb-2 text-gray-600">Respuesta sobre <a href="{{ route('panel.expedientes.show', $ia['expediente_id']) }}" class="font-bold text-emerald-800 hover:underline">{{ $ia['codigo'] }} · {{ $ia['cliente'] }}</a></p>
            @include('panel.expedientes.partials.ia-resultado', ['ia' => $ia, 'expedienteId' => $ia['expediente_id']])
        @endif
    @endif
</div>
