{{-- Preparar una comunicación sin documento. Queda como solicitud de BORRADOR; el programa nunca envía. --}}
@php
    $cliente = $expediente->client;
    $obligatorios = $expediente->service ? $expediente->service->requirements->where('normalizado', true)->where('obligatorio', true) : collect();
    $esDoc = ($comunicacion ?? null) === 'documentacion';
    $asuntoPorDefecto = ($esDoc ? 'Documentación pendiente' : 'Expediente').' · '.($expediente->codigo ?: $expediente->id);
    $cuerpoPorDefecto = $esDoc && $obligatorios->isNotEmpty()
        ? "Buenos días:\n\nPara continuar con el expediente {$expediente->codigo}, necesitamos la siguiente documentación:\n\n".$obligatorios->map(fn ($r) => '- '.$r->descripcion)->implode("\n")."\n\nQuedamos a la espera de su remisión.\n\nUn saludo,\n".\App\Support\Despacho::datos()['nombre']
        : '';
    $campoCom = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 bg-white';
@endphp
<div id="comunicacion" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
    <details @if(($comunicacion ?? null) || $errors->hasAny(['recipient', 'subject', 'body', 'tipo', 'confirmado'])) open @endif>
        <summary class="cursor-pointer text-base font-bold text-gray-900 font-brand-serif">Preparar comunicación</summary>
        <p class="text-gray-500 mt-2 mb-4">Queda como solicitud de borrador en el correo del despacho (Outlook). <strong>No se envía nada</strong>: la revisas y la envías tú.</p>
        @if($errors->hasAny(['recipient', 'subject', 'body', 'tipo', 'confirmado']))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
        @endif
        <form method="POST" action="{{ route('panel.comunicaciones.store', $expediente->id) }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="com_tipo">Tipo</label>
                <select id="com_tipo" name="tipo" class="{{ $campoCom }}">
                    @foreach(\App\Http\Controllers\Panel\ComunicacionController::TIPOS as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $esDoc ? 'SOLICITUD_DOCUMENTACION' : 'COMUNICACION_LIBRE') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="com_to">Destinatario (un único correo)</label>
                <input id="com_to" type="email" name="recipient" value="{{ old('recipient', $cliente->email) }}" required class="{{ $campoCom }}"></div>
            <div class="sm:col-span-2"><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="com_subject">Asunto</label>
                <input id="com_subject" name="subject" maxlength="250" value="{{ old('subject', $asuntoPorDefecto) }}" required class="{{ $campoCom }}"></div>
            <div class="sm:col-span-2"><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="com_body">Mensaje</label>
                <textarea id="com_body" name="body" rows="9" maxlength="20000" required class="{{ $campoCom }}" placeholder="Escribe el contenido que revisarás antes de enviar.">{{ old('body', $cuerpoPorDefecto) }}</textarea></div>
            <label class="sm:col-span-2 flex items-center gap-2 text-gray-700"><input type="checkbox" name="confirmado" value="1"> He revisado destinatario, asunto y mensaje.</label>
            <div class="sm:col-span-2"><button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Preparar para Outlook</button></div>
        </form>
    </details>
</div>
