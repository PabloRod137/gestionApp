{{-- Razonar con IA (modelo local). Propone; una persona confirma. Solo se muestra si está activada. --}}
@php
    $campoIa = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 bg-white';
    $ia = session('ia_resultado');
@endphp
<div id="razonar-ia" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-base font-bold text-gray-900 font-brand-serif">Razonar con IA</h3>
        <span class="bg-gray-100 text-gray-600 border border-gray-200 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">IA local</span>
    </div>

    @if(! config('ia.enabled'))
        <p class="text-gray-500">La IA local no está activada en este servidor. Cuando se active, aquí se podrá hacer una pregunta concreta sobre el expediente; el modelo local solo verá los datos de este cliente y nunca escribe ni envía nada.</p>
    @else
        <p class="text-gray-500 mb-4">Análisis bajo demanda limitado a este expediente y a su cliente. Primero se usa lo que ya sabe el programa; el modelo local solo interviene después. Su respuesta es una propuesta que tú confirmas.</p>

        @if ($errors->has('ia') || $errors->has('question'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">{{ $errors->first('ia') ?: $errors->first('question') }}</div>
        @endif

        <form method="POST" action="{{ route('panel.expedientes.razonar', $expediente->id) }}" class="space-y-3 mb-4">
            @csrf
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="ia_question">Duda concreta</label>
                <textarea id="ia_question" name="question" rows="3" maxlength="1200" required class="{{ $campoIa }}" placeholder="Ej. ¿Consta en la carpeta del cliente un documento con el DNI del representante?">{{ old('question') }}</textarea>
            </div>
            <button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Analizar ahora</button>
        </form>

        @if($ia)
            @include('panel.expedientes.partials.ia-resultado', ['ia' => $ia, 'expedienteId' => $expediente->id])
        @endif
    @endif
</div>
