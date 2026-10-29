<x-panel-layout>
    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Propuestas de actuación</h1>
        <p class="text-sm text-gray-500">Cada propuesta recorre el mismo circuito: aprobación, documento, revisión y comunicación preparada. Nada se envía desde aquí.</p>
    </div>

    <form method="GET" action="{{ route('panel.propuestas.index') }}" class="mb-6 flex items-center gap-3 text-xs">
        <label class="sr-only" for="estado">Estado</label>
        <select id="estado" name="estado" class="border border-gray-200 rounded-lg px-4 py-2 text-xs" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach($estados as $clave => $texto)<option value="{{ $clave }}" @selected($estado === $clave)>{{ $texto }}</option>@endforeach
        </select>
    </form>

    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider"><tr><th class="px-5 py-3">Propuesta</th><th class="px-5 py-3">Expediente</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Preparada por</th><th class="px-5 py-3">Última actualización</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($propuestas as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3"><a href="{{ route('panel.propuestas.show', $p->id) }}" class="font-bold text-gray-900 hover:underline">{{ $p->title }}</a></td>
                        <td class="px-5 py-3 text-gray-600">{{ $p->expedient->codigo ?: '#'.$p->expedient_id }} · {{ $p->expedient->client->razon_social }}</td>
                        <td class="px-5 py-3">@include('panel.propuestas.partials.estado', ['p' => $p])</td>
                        <td class="px-5 py-3 text-gray-600">{{ $p->creator?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $p->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No hay propuestas. Se crean desde la ficha de un expediente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $propuestas->links() }}</div>
</x-panel-layout>
