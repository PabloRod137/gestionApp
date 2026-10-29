<x-panel-layout>
    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Catálogo de servicios</h1>
        <p class="text-sm text-gray-500">Servicios del despacho con su tarifa, la documentación que se pide al cliente y la acción sugerida.</p>
    </div>

    <form method="GET" action="{{ route('panel.servicios.index') }}" class="bg-white border border-gray-150 p-5 rounded-2xl shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end text-xs">
        <div class="sm:col-span-2">
            <label class="block text-gray-400 uppercase text-[9px] font-semibold mb-2" for="search">Buscar</label>
            <input id="search" type="text" name="search" value="{{ $buscar }}" placeholder="Nombre o código del servicio..." class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs">
        </div>
        <div>
            <label class="block text-gray-400 uppercase text-[9px] font-semibold mb-2" for="tipo_cliente">Tipo de cliente</label>
            <select id="tipo_cliente" name="tipo_cliente" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($tipos as $t)<option value="{{ $t }}" @selected($tipo === $t)>{{ $t }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider">
                <tr><th class="px-5 py-3">Servicio</th><th class="px-5 py-3">Para</th><th class="px-5 py-3">Cobro</th><th class="px-5 py-3 text-right">Base</th><th class="px-5 py-3 text-right">Requisitos</th><th class="px-5 py-3 text-right">Expedientes</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($servicios as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('panel.servicios.show', $s->id) }}" class="font-bold text-gray-900 hover:underline">{{ $s->nombre }}</a>
                            <span class="block text-[10px] font-mono text-gray-400">{{ $s->codigo }}@unless($s->activo) · inactivo @endunless</span>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $s->tipo_cliente ?: '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $s->tipo_cobro ?: '—' }}{{ $s->periodicidad && $s->periodicidad !== 'Único' ? ' · '.$s->periodicidad : '' }}</td>
                        <td class="px-5 py-3 text-right text-gray-800">{{ number_format($s->precio_base, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-right text-gray-600">{{ $s->requirements_count }}</td>
                        <td class="px-5 py-3 text-right text-gray-600">{{ $s->expedients_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No hay servicios que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $servicios->links() }}</div>
</x-panel-layout>
