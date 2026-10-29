{{-- Propuesta documental: lo que debe aportar el cliente y lo que genera el despacho --}}
<div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-bold text-gray-900 font-brand-serif">Propuesta documental</h3>
        @if($expediente->service)
            <a href="{{ route('panel.servicios.show', $expediente->service_id) }}" class="text-emerald-800 font-bold hover:underline">{{ $expediente->service->nombre }}</a>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('success') }}</div>
    @endif
    @include('panel.partials.errors')
    @if (session('pedir') !== null || session('faltan') !== null)
        @include('panel.partials.datos-documento', ['accion' => route('panel.expedientes.generar', $expediente->id), 'pedir' => (array) session('pedir'), 'faltan' => (array) session('faltan'), 'extras' => (array) session('extras'), 'ocultos' => ['document_template_id' => session('plantilla_id')]])
    @endif

    @if($propuesta['estado'] === 'sin_servicio')
        <p class="text-gray-500">Este expediente no tiene servicio asignado, así que no se puede deducir qué documentación debe aportar el cliente. Asigna un servicio al editarlo; mientras tanto puedes proponer un documento desde «Nueva propuesta».</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">El cliente debe aportar</h4>
                @forelse($propuesta['aportar'] as $req)
                    <div class="flex items-start gap-2 py-1.5 border-b border-gray-100 last:border-0">
                        <span class="mt-0.5 inline-block h-3.5 w-3.5 rounded border border-gray-300 shrink-0"></span>
                        <span class="text-gray-700">{{ $req->descripcion }}
                            @if($req->obligatorio) <span class="text-rose-700 font-bold uppercase text-[8px]">obligatorio</span>
                            @else <span class="text-gray-400 uppercase text-[8px]">opcional</span> @endif
                        </span>
                    </div>
                @empty
                    @if($propuesta['revisar'])
                        <p class="text-gray-700">{{ $propuesta['revisar'] }}</p>
                        <p class="text-amber-700 mt-2 text-[10px]">Lista sin desglosar todavía: revisar a mano.</p>
                    @else
                        <p class="text-gray-400">Este servicio no tiene requisitos definidos.</p>
                    @endif
                @endforelse
            </div>

            <div>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">Documentos que prepara el despacho</h4>
                @if($propuesta['estado'] === 'sin_documentos')
                    <p class="text-gray-400">Este servicio no genera documentos.</p>
                @else
                    @forelse($propuesta['generar'] as $plantilla)
                        <div class="py-1.5 border-b border-gray-100 last:border-0 text-gray-700 flex items-center justify-between gap-3">
                            <span>{{ $plantilla->nombre }} <span class="text-gray-400 uppercase text-[8px]">{{ $plantilla->tipo_documento }}</span></span>
                            @if ($plantilla->archivo)
                                <form method="POST" action="{{ route('panel.expedientes.generar', $expediente->id) }}">
                                    @csrf
                                    <input type="hidden" name="document_template_id" value="{{ $plantilla->id }}">
                                    <button type="submit" class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-3 py-1.5 rounded text-[10px] uppercase">Generar</button>
                                </form>
                            @else
                                <span class="text-amber-700 text-[9px] uppercase">sin archivo Word</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-amber-700">Todavía no hay una plantilla asignada a este servicio.</p>
                    @endforelse
                @endif
                @if($propuesta['accion'])
                    <p class="mt-4 text-gray-500"><span class="font-bold text-gray-700">Acción sugerida:</span> {{ $propuesta['accion'] }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
