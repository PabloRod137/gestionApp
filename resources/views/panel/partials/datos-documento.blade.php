{{-- Datos que se completan en el momento para generar un documento (p. ej. con el cliente delante). Nunca es obligatorio: un hueco puede quedar en blanco.
     Variables: $accion (URL del POST), $pedir (marcas no guardadas), $faltan (datos guardados que faltan), $extras (lo ya escrito), $ocultos (campos hidden extra). --}}
@php
    $etiqueta = fn ($c) => \App\Support\GeneradorDocumentos::CAMPOS[$c] ?? ucfirst(str_replace('_', ' ', $c));
    $guardables = ['cliente_nif', 'cliente_email', 'cliente_telefono', 'cliente_direccion'];
    $hayGuardables = collect($faltan)->intersect($guardables)->isNotEmpty();
@endphp
<div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-4 text-sky-900">
    <p class="font-bold mb-1">Completa los datos que faltan para este documento</p>
    <p class="mb-3 text-sky-800">Puedes escribirlos ahora, por ejemplo con el cliente delante. Lo que dejes en blanco quedará en blanco en el documento; no hace falta tenerlo todo para generarlo.</p>
    <form method="POST" action="{{ $accion }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @csrf
        <input type="hidden" name="forzar" value="1">
        @foreach (($ocultos ?? []) as $nombre => $valor)<input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">@endforeach
        @foreach (array_merge($faltan, $pedir) as $c)
            <div>
                <label class="block text-[10px] uppercase tracking-wider text-sky-800 mb-1" for="extra_{{ $c }}">{{ $etiqueta($c) }}</label>
                <input id="extra_{{ $c }}" type="text" name="extra[{{ $c }}]" value="{{ old('extra.'.$c, $extras[$c] ?? '') }}" class="w-full border border-sky-200 rounded-lg px-3 py-2 text-xs bg-white text-gray-800">
            </div>
        @endforeach
        {{-- lo ya escrito para otros campos se conserva --}}
        @foreach ($extras as $c => $v)
            @if (! in_array($c, array_merge($faltan, $pedir), true))<input type="hidden" name="extra[{{ $c }}]" value="{{ $v }}">@endif
        @endforeach
        @if ($hayGuardables)
            <label class="sm:col-span-2 flex items-center gap-2 text-sky-900"><input type="checkbox" name="guardar_ficha" value="1" checked> Guardar en la ficha del cliente los datos que escriba (solo rellena huecos, no pisa nada).</label>
        @endif
        <div class="sm:col-span-2"><button type="submit" class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase">Generar documento</button></div>
    </form>
</div>
