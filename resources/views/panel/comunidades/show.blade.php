<x-panel-layout>
    <div class="mb-6"><a href="{{ route('panel.comunidades.index') }}" class="text-xs text-gray-500 hover:text-gray-900">← Volver a comunidades</a></div>
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs">
        <h1 class="text-xl font-bold text-gray-900 font-brand-serif">{{ $comunidad->nombre }}</h1>
        <p class="text-gray-500 mt-1">CIF: {{ $comunidad->cif ?: '—' }}@if($comunidad->client) · <a class="text-emerald-800 font-bold hover:underline" href="{{ route('panel.clientes.show', $comunidad->client_id) }}">Ficha de cliente</a>@endif</p>
    </div>
    <p class="mb-4"><a href="{{ route('panel.comunidades.reporte', $comunidad->id) }}" class="inline-block bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-4 py-2 rounded-lg text-[10px] uppercase tracking-wider transition">Reporte mensual</a></p>
    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto text-xs">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider"><tr><th class="px-5 py-3">Unidad</th><th class="px-5 py-3">Propietario</th><th class="px-5 py-3">Teléfono</th><th class="px-5 py-3">Correo</th><th class="px-5 py-3 text-right">Coeficiente</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($comunidad->owners->sortBy('unidad', SORT_NATURAL) as $o)
                    <tr>
                        <td class="px-5 py-2.5 text-gray-600">{{ $o->unidad ?: '—' }}</td>
                        <td class="px-5 py-2.5 font-semibold text-gray-900">{{ trim($o->person->nombre.' '.$o->person->apellidos) }}</td>
                        <td class="px-5 py-2.5 text-gray-600">{{ $o->person->telefono ?: '—' }}</td>
                        <td class="px-5 py-2.5 text-gray-600">{{ $o->person->email ?: '—' }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-600">{{ $o->coeficiente !== null ? $o->coeficiente : 'sin dato' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Esta comunidad no tiene propietarios cargados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel-layout>
