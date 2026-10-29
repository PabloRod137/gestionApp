<x-panel-layout>
    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Modelos de documento</h1>
        <p class="text-sm text-gray-500">Plantillas Word del despacho. El programa rellena en ellas los datos del cliente y del expediente.</p>
    </div>

    @include('panel.partials.errors')
    @if (session('success'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>
    @endif

    @php $esAdmin = auth()->user()->hasRole('admin'); @endphp

    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto mb-8">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider"><tr><th class="px-5 py-3">Modelo</th><th class="px-5 py-3">Tipo</th><th class="px-5 py-3">Archivo Word y campos</th><th class="px-5 py-3 text-right">Servicios</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($plantillas as $p)
                    <tr class="align-top">
                        <td class="px-5 py-3 font-bold text-gray-900">{{ $p->nombre }}@unless($p->activo)<span class="text-gray-400 font-normal"> · inactivo</span>@endunless
                            <span class="block font-mono text-[10px] font-normal text-gray-400">{{ $p->codigo }}</span></td>
                        <td class="px-5 py-3 text-gray-600">{{ $p->tipo_documento ?: '—' }}</td>
                        <td class="px-5 py-3">
                            @if (array_key_exists($p->id, $campos))
                                @if ($campos[$p->id] === null)
                                    <p class="text-rose-700">No se puede leer el archivo. Sube otro.</p>
                                @else
                                    <p class="text-emerald-800 font-bold mb-1">Archivo cargado</p>
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        @forelse($campos[$p->id] as $campo)
                                            <span class="font-mono text-[10px] rounded px-1.5 py-0.5 border {{ isset($conocidos[$campo]) ? 'bg-gray-50 border-gray-200 text-gray-700' : 'bg-sky-50 border-sky-200 text-sky-800' }}" title="{{ $conocidos[$campo] ?? 'Dato que se pide al generar el documento' }}">{{ $campo }}</span>
                                        @empty
                                            <span class="text-gray-400">Sin campos: se copiará tal cual.</span>
                                        @endforelse
                                    </div>
                                    @if (collect($campos[$p->id])->contains(fn ($c) => ! isset($conocidos[$c])))
                                        <p class="text-sky-800 text-[10px]">Los campos en azul no están guardados: el programa los pedirá al generar el documento.</p>
                                    @endif
                                @endif
                            @else
                                <p class="text-amber-700 mb-1">Sin archivo Word</p>
                            @endif
                            @if ($esAdmin)
                                <form method="POST" action="{{ route('panel.plantillas.archivo', $p->id) }}" enctype="multipart/form-data" class="flex items-center gap-2 mt-1">
                                    @csrf
                                    <input type="file" name="archivo" accept=".docx" required class="text-[10px] max-w-[200px]">
                                    <button type="submit" class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-3 py-1.5 rounded text-[10px] uppercase">{{ isset($campos[$p->id]) ? 'Reemplazar' : 'Subir' }}</button>
                                </form>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right {{ $p->services_count ? 'text-gray-600' : 'text-amber-700' }}">{{ $p->services_count ?: 'sin asignar' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">No hay modelos cargados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm p-6 text-xs">
        <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-1">Campos que se pueden usar en un Word</h3>
        <p class="text-gray-500 mb-4">Escríbelos entre llaves, con el símbolo del dólar delante: por ejemplo <span class="font-mono">${cliente_nombre}</span>.</p>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1.5">
            @foreach ($conocidos as $campo => $descripcion)
                <div class="flex justify-between gap-4 border-b border-gray-100 py-1"><dt class="font-mono text-[11px] text-gray-800">${{ '{' }}{{ $campo }}{{ '}' }}</dt><dd class="text-gray-500 text-right">{{ $descripcion }}</dd></div>
            @endforeach
        </dl>
    </div>
</x-panel-layout>
