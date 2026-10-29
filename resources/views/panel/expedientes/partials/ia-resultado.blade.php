{{-- Resultado de "Razonar con IA": siempre una propuesta pendiente de validación. Variables: $ia (resultado en sesión), $expedienteId. --}}
@php
    $campoIa = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 bg-white';
    $r = $ia['resultado'];
    $hayDato = $r['dato_propuesta'] !== '' && $r['dato_propuesta'] !== 'NO LOCALIZADO';
@endphp
<div class="border border-amber-200 bg-amber-50/50 rounded-xl p-4">
    <span class="bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider">Pendiente de validación</span>
    <dl class="grid grid-cols-[130px_1fr] gap-x-4 gap-y-1.5 mt-3 text-gray-700">
        <dt class="text-gray-500">Dato / propuesta</dt><dd class="font-semibold">{{ $r['dato_propuesta'] ?: '—' }}</dd>
        <dt class="text-gray-500">Fuente</dt><dd>{{ $r['fuente'] ?: '—' }}</dd>
        <dt class="text-gray-500">Documento</dt><dd>{{ $r['documento'] ?: '—' }}</dd>
        <dt class="text-gray-500">Expediente de origen</dt><dd>{{ $r['expediente_origen'] ?: '—' }}</dd>
        <dt class="text-gray-500">Justificación</dt><dd>{{ $r['justificacion'] ?: '—' }}</dd>
    </dl>
    <p class="mt-3 text-gray-400">Ruta: {{ $ia['ruta'] }} · modelo: {{ $ia['modelo'] }} · {{ $ia['archivos_considerados'] }} archivo(s) del mismo cliente considerados. No se ha buscado en otros clientes.</p>

    @if($hayDato)
        <form method="POST" action="{{ route('panel.expedientes.incorporar', $expedienteId) }}" class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 border-t border-amber-200 pt-4">
            @csrf
            <input type="hidden" name="fuente" value="{{ $r['fuente'] ?: $r['documento'] }}">
            <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="ia_campo">Completar en la ficha</label>
                <select id="ia_campo" name="campo" class="{{ $campoIa }}"><option value="nif">NIF / CIF</option><option value="email">Correo</option><option value="telefono">Teléfono</option><option value="direccion">Dirección</option></select></div>
            <div class="sm:col-span-2"><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="ia_valor">Valor (revísalo y corrígelo)</label>
                <input id="ia_valor" name="valor" value="{{ old('valor', $r['dato_propuesta']) }}" class="{{ $campoIa }}" required></div>
            @if ($errors->has('valor') || $errors->has('confirmado') || $errors->has('campo'))
                <p class="sm:col-span-3 text-rose-700">{{ $errors->first('valor') ?: ($errors->first('confirmado') ?: $errors->first('campo')) }}</p>
            @endif
            <label class="sm:col-span-3 flex items-center gap-2 text-gray-700"><input type="checkbox" name="confirmado" value="1"> He comprobado la fuente del dato.</label>
            <label class="sm:col-span-3 flex items-center gap-2 text-gray-500"><input type="checkbox" name="reemplazar" value="1"> Reemplazar el dato actual si ya existe.</label>
            <div class="sm:col-span-3"><button class="bg-white border border-gray-300 text-gray-700 font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Incorporar a la ficha</button></div>
        </form>
    @endif
</div>
