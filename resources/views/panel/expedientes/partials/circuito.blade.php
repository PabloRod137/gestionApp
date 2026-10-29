{{-- Circuito de trabajo del expediente: propuestas, contexto del cliente y actividad --}}
@php($cliente = $expediente->client)
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8 text-xs font-brand-sans items-start">
    <div class="lg:col-span-7 bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
        <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Propuestas de actuación</h3>
        @forelse($propuestas as $pr)
            <a href="{{ route('panel.propuestas.show', $pr->id) }}" class="flex items-center justify-between gap-3 border border-gray-100 rounded-xl p-3 mb-2 hover:bg-gray-50">
                <span class="font-bold text-gray-900 truncate">{{ $pr->title }}</span>
                @include('panel.propuestas.partials.estado', ['p' => $pr])
            </a>
        @empty
            <p class="text-gray-400 mb-3">Todavía no hay propuestas para este expediente.</p>
        @endforelse

        <?php
            $delServicio = $expediente->service ? $expediente->service->templates : collect();
            $otrosModelos = \App\Models\DocumentTemplate::whereNotIn('id', $delServicio->pluck('id'))->orderBy('nombre')->get();
            $opcion = fn ($t) => '<option value="'.$t->id.'"'.(($tipoSugerido && $t->tipo_documento === $tipoSugerido) ? ' selected' : '').'>'.e($t->nombre.($t->archivo ? '' : ' (sin archivo Word)')).'</option>';
        ?>
        @if($delServicio->isNotEmpty() || $otrosModelos->isNotEmpty())
            <details id="nueva-propuesta" class="mt-4 border-t border-gray-100 pt-4" @if($errors->has('workflow') || $errors->has('title') || $tipoSugerido) open @endif>
                <summary class="cursor-pointer font-bold text-emerald-800">Nueva propuesta</summary>
                @if ($errors->has('workflow'))<p class="mt-2 text-rose-700">{{ $errors->first('workflow') }}</p>@endif
                @unless($expediente->service)<p class="mt-2 text-gray-500">Este expediente no tiene servicio asignado: puedes proponer igualmente cualquier modelo del despacho.</p>@endunless
                <form method="POST" action="{{ route('panel.propuestas.store', $expediente->id) }}" class="mt-3 space-y-3">
                    @csrf
                    <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="np_title">Título</label><input id="np_title" name="title" value="{{ old('title') }}" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs"></div>
                    <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="np_purpose">Finalidad</label><textarea id="np_purpose" name="purpose" rows="2" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs">{{ old('purpose') }}</textarea></div>
                    <div><label class="block text-[10px] uppercase tracking-wider text-gray-400 mb-1" for="np_tpl">Modelo de documento</label>
                        <select id="np_tpl" name="document_template_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs">
                            @if($delServicio->isNotEmpty())<optgroup label="Del servicio de este expediente">@foreach($delServicio as $t){!! $opcion($t) !!}@endforeach</optgroup>@endif
                            @if($otrosModelos->isNotEmpty())<optgroup label="Otros modelos del despacho">@foreach($otrosModelos as $t){!! $opcion($t) !!}@endforeach</optgroup>@endif
                        </select></div>
                    <button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Crear propuesta</button>
                </form>
            </details>
        @else
            <p class="mt-3 text-amber-700">Todavía no hay modelos de documento cargados.</p>
        @endif
    </div>

    <div class="lg:col-span-5 space-y-6">
        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Contexto del cliente</h3>
            @foreach([['NIF/CIF', $cliente->nif], ['Correo', $cliente->email], ['Teléfono', $cliente->telefono], ['Dirección', $cliente->direccion]] as [$etq, $valor])
                <div class="flex justify-between gap-4 py-1.5 border-b border-gray-100 last:border-0">
                    <span class="text-gray-500">{{ $etq }}</span>
                    @if($valor)<span class="text-gray-800 text-right">{{ $valor }}</span>@else<span class="text-amber-700 font-bold uppercase text-[9px]">falta</span>@endif
                </div>
            @endforeach
            @if($otrosExpedientes->isNotEmpty())
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-5 mb-2">Otros expedientes del cliente</h4>
                @foreach($otrosExpedientes as $o)
                    <a href="{{ route('panel.expedientes.show', $o->id) }}" class="block py-1 text-gray-700 hover:underline truncate">{{ $o->codigo }} · {{ $o->titulo }}</a>
                @endforeach
            @endif
        </div>

        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Actividad</h3>
            @forelse($actividad as $a)
                <div class="py-1.5 border-b border-gray-100 last:border-0 text-gray-700"><span class="font-bold">{{ ucfirst(strtolower(str_replace('_', ' ', $a->action))) }}</span> · {{ $a->user?->name ?? 'sistema' }}
                    <span class="block text-gray-400">{{ $a->created_at->format('d/m/Y H:i') }}</span></div>
            @empty
                <p class="text-gray-400">Sin actividad registrada.</p>
            @endforelse
        </div>
    </div>
</div>
