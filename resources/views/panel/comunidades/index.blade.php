<x-panel-layout>
    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Comunidades de propietarios</h1>
        <p class="text-sm text-gray-500">Comunidades que administra el despacho y sus propietarios.</p>
    </div>
    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider"><tr><th class="px-5 py-3">Comunidad</th><th class="px-5 py-3">CIF</th><th class="px-5 py-3 text-right">Propietarios</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($comunidades as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3"><a href="{{ route('panel.comunidades.show', $c->id) }}" class="font-bold text-gray-900 hover:underline">{{ $c->nombre }}</a></td>
                        <td class="px-5 py-3 text-gray-600">{{ $c->cif ?: '—' }}</td>
                        <td class="px-5 py-3 text-right {{ $c->owners_count ? 'text-gray-600' : 'text-amber-700' }}">{{ $c->owners_count ?: 'sin cargar' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-gray-400">No hay comunidades.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel-layout>
