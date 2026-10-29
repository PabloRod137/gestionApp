<x-panel-layout>
    @php
        $pasos = ['proposed' => 'Propuesta', 'approved' => 'Aprobada', 'generated' => 'Documento', 'document_approved' => 'Revisado', 'communication_requested' => 'Comunicación'];
        $orden = array_keys($pasos);
        $actual = array_search($p->status, $orden);
        $boton = 'bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider';
        $campo = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-800 bg-white';
    @endphp

    <div class="mb-6"><a href="{{ route('panel.expedientes.show', $p->expedient_id) }}" class="text-xs text-gray-500 hover:text-gray-900">← Volver al expediente {{ $p->expedient->codigo }}</a></div>

    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-6 text-xs">
        <div class="flex items-start justify-between gap-4">
            <div>
                <span class="text-[10px] font-mono text-gray-400">Propuesta #{{ $p->id }} · versión {{ $p->version }}</span>
                <h1 class="text-xl font-bold text-gray-900 font-brand-serif">{{ $p->title }}</h1>
                <p class="text-gray-500 mt-1">{{ $p->expedient->client->razon_social }} · {{ $p->expedient->service?->nombre }} · modelo: {{ $p->template?->nombre ?? '—' }}</p>
            </div>
            @include('panel.propuestas.partials.estado', ['p' => $p])
        </div>
        <p class="mt-4 text-gray-700"><span class="font-bold">Finalidad:</span> {{ $p->purpose }}</p>
        @if($p->status === 'rejected')<p class="mt-2 text-rose-700"><span class="font-bold">Motivo del rechazo:</span> {{ $p->rejection_reason }}</p>@endif
    </div>

    @if (session('success'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    {{-- Pasos --}}
    @if($p->status !== 'rejected')
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-6">
        <ol class="flex items-center justify-between text-[10px] uppercase tracking-wider font-bold">
            @foreach($pasos as $clave => $texto)
                @php $i = array_search($clave, $orden); @endphp
                <li class="flex flex-col items-center gap-1.5 flex-1">
                    <span class="h-7 w-7 rounded-full flex items-center justify-center text-xs {{ $i <= $actual ? 'bg-emerald-600 text-white' : 'bg-white border-2 border-gray-300 text-gray-400' }}">{{ $i + 1 }}</span>
                    <span class="{{ $i <= $actual ? 'text-gray-800' : 'text-gray-400' }}">{{ $texto }}</span>
                </li>
            @endforeach
        </ol>
    </div>
    @endif

    {{-- Acción del paso actual --}}
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-6 text-xs">
        @if($p->status === 'proposed')
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-3">1. Revisar y aprobar la propuesta</h2>
            <form method="POST" action="{{ route('panel.propuestas.update', $p->id) }}" class="space-y-3 mb-4">
                @csrf @method('PATCH')
                <input type="hidden" name="version" value="{{ $p->version }}">
                <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="title">Título</label><input id="title" name="title" value="{{ old('title', $p->title) }}" class="{{ $campo }}" required></div>
                <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="purpose">Finalidad</label><textarea id="purpose" name="purpose" rows="3" class="{{ $campo }}" required>{{ old('purpose', $p->purpose) }}</textarea></div>
                <button class="bg-white border border-gray-300 text-gray-700 font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Guardar corrección</button>
            </form>
            <form method="POST" action="{{ route('panel.propuestas.aprobar', $p->id) }}">@csrf<input type="hidden" name="version" value="{{ $p->version }}"><button class="{{ $boton }}">Aprobar propuesta</button></form>

        @elseif($p->status === 'approved')
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-3">2. Generar el documento</h2>
            @if (session('pedir') !== null || session('faltan') !== null)
                @include('panel.partials.datos-documento', ['accion' => route('panel.propuestas.generar', $p->id), 'pedir' => (array) session('pedir'), 'faltan' => (array) session('faltan'), 'extras' => (array) session('extras'), 'ocultos' => ['version' => $p->version]])
            @else
                <form method="POST" action="{{ route('panel.propuestas.generar', $p->id) }}">@csrf <input type="hidden" name="version" value="{{ $p->version }}"><button class="{{ $boton }}">Generar documento Word</button></form>
            @endif

        @elseif($p->status === 'generated')
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-3">3. Revisar el documento</h2>
            @if($p->pdf_document_id)
                <p class="text-gray-600 mb-3">Se han creado el Word y su PDF. Revísalos: la aprobación se hace sobre las huellas exactas de los dos archivos. Si corriges el PDF, puedes sustituirlo abajo.</p>
            @else
                <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">No se pudo crear el PDF automáticamente. Descarga el Word, expórtalo a PDF y súbelo aquí para continuar.</p>
            @endif
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <a href="{{ route('panel.documentos.download', $p->generated_document_id) }}" class="bg-white border border-gray-300 text-gray-700 font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Descargar Word</a>
                @if($p->pdf_document_id)<a href="{{ route('panel.documentos.download', $p->pdf_document_id) }}" class="bg-white border border-gray-300 text-gray-700 font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Descargar PDF</a>@endif
            </div>
            <form method="POST" action="{{ route('panel.propuestas.pdf', $p->id) }}" enctype="multipart/form-data" class="flex items-center gap-3 mb-5">
                @csrf <input type="hidden" name="version" value="{{ $p->version }}">
                <label class="sr-only" for="pdf">PDF revisado</label><input id="pdf" type="file" name="pdf" accept="application/pdf" required class="text-[11px]">
                <button class="{{ $boton }}">{{ $p->pdf_document_id ? 'Sustituir por un PDF corregido' : 'Adjuntar PDF' }}</button>
            </form>
            @if($p->pdf_document_id)
                <form method="POST" action="{{ route('panel.propuestas.revisar', $p->id) }}" class="space-y-3">
                    @csrf <input type="hidden" name="version" value="{{ $p->version }}">
                    <label class="flex items-center gap-2 text-gray-700"><input type="checkbox" name="confirmado" value="1"> He revisado el documento Word y su PDF exactos.</label>
                    <button class="{{ $boton }}">Aprobar documento</button>
                </form>
            @endif

        @elseif($p->status === 'document_approved')
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-3">4. Preparar la comunicación</h2>
            @if(auth()->check())
                <p class="text-gray-600 mb-3">Se prepara la solicitud de borrador con el PDF aprobado. <strong>No se envía nada.</strong> El Word nunca se adjunta al cliente.</p>
                <form method="POST" action="{{ route('panel.propuestas.comunicacion', $p->id) }}" class="space-y-3">
                    @csrf <input type="hidden" name="version" value="{{ $p->version }}">
                    <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="recipient">Destinatario (un único correo)</label><input id="recipient" type="email" name="recipient" value="{{ old('recipient', $p->expedient->client->email) }}" class="{{ $campo }}" required></div>
                    <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="subject">Asunto</label><input id="subject" name="subject" value="{{ old('subject', $p->title.' · Expediente '.$p->expedient->codigo) }}" class="{{ $campo }}" required></div>
                    <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="body">Mensaje</label><textarea id="body" name="body" rows="5" class="{{ $campo }}" required>{{ old('body', "Estimado/a cliente:\n\nLe adjuntamos el documento relativo a su expediente.\n\nUn saludo,\n".\App\Support\Despacho::datos()['nombre']) }}</textarea></div>
                    <label class="flex items-center gap-2 text-gray-700"><input type="checkbox" name="confirmado" value="1"> He revisado destinatario, asunto, mensaje y adjunto.</label>
                    <button class="{{ $boton }}">Preparar comunicación</button>
                </form>
            @endif

        @elseif($p->status === 'communication_requested')
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-3">Comunicación preparada</h2>
            <dl class="space-y-1 text-gray-700">
                <div><dt class="inline font-bold">Para:</dt> <dd class="inline">{{ $p->communicationDraft->recipient }}</dd></div>
                <div><dt class="inline font-bold">Asunto:</dt> <dd class="inline">{{ $p->communicationDraft->subject }}</dd></div>
                <div><dt class="inline font-bold">Adjunto:</dt> <dd class="inline">{{ $p->pdfDocument?->nombre }}</dd></div>
                <div><dt class="inline font-bold">Estado:</dt> <dd class="inline">pendiente de crear el borrador · <strong>no se ha enviado nada</strong></dd></div>
            </dl>
        @else
            <p class="text-gray-500">Esta propuesta ya no admite más pasos.</p>
        @endif

        @if(in_array($p->status, ['proposed', 'approved', 'generated', 'document_approved']))
            <details class="mt-6 border-t border-gray-100 pt-4">
                <summary class="cursor-pointer text-rose-700 font-bold">Rechazar propuesta</summary>
                <form method="POST" action="{{ route('panel.propuestas.rechazar', $p->id) }}" class="mt-3 flex items-center gap-3">
                    @csrf <input type="hidden" name="version" value="{{ $p->version }}">
                    <label class="sr-only" for="reason">Motivo</label><input id="reason" name="reason" placeholder="Motivo del rechazo" class="{{ $campo }}" required>
                    <button class="bg-rose-700 text-white font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Rechazar</button>
                </form>
            </details>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-3">Revisiones</h3>
            @foreach($p->revisions->reverse() as $r)
                <div class="py-1.5 border-b border-gray-100 last:border-0 text-gray-700">Versión {{ $r->version }} · {{ $r->data['title'] ?? '' }} <span class="text-gray-400">— {{ \App\Models\Proposal::ESTADOS[$r->data['status'] ?? ''] ?? '' }}</span></div>
            @endforeach
        </div>
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-3">Actividad</h3>
            @foreach($p->activities as $a)
                <div class="py-1.5 border-b border-gray-100 last:border-0 text-gray-700"><span class="font-bold">{{ str_replace('_', ' ', ucfirst(strtolower($a->action))) }}</span> · {{ $a->user?->name ?? 'sistema' }} <span class="text-gray-400">· {{ $a->created_at->format('d/m/Y H:i') }}</span>@if($a->detail)<span class="block text-gray-500">{{ $a->detail }}</span>@endif</div>
            @endforeach
        </div>
    </div>
</x-panel-layout>
