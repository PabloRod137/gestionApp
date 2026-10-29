<x-panel-layout>
    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Comunicaciones</h1>
        <p class="text-sm text-gray-500">Solicitudes de borrador con el PDF aprobado. <strong>El programa no envía correos</strong>: crea el borrador en el correo del despacho (Outlook) y una persona lo revisa y lo envía desde allí.</p>
    </div>

    @if (session('success'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    @php
        $estados = ['pendiente_adaptador' => ['Pendiente de crear el borrador', 'text-amber-700'], 'procesando' => ['Creando…', 'text-sky-700'], 'borrador_creado' => ['Borrador creado y verificado', 'text-emerald-800'], 'error' => ['Error', 'text-rose-700']];
    @endphp
    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider"><tr><th class="px-5 py-3">Asunto</th><th class="px-5 py-3">Destinatario</th><th class="px-5 py-3">Expediente</th><th class="px-5 py-3">Preparada por</th><th class="px-5 py-3">Estado</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($solicitudes as $s)
                    @php [$texto, $color] = $estados[$s->status] ?? [$s->status, 'text-gray-600']; @endphp
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-5 py-3"><a href="{{ $s->proposal_id ? route('panel.propuestas.show', $s->proposal_id) : route('panel.expedientes.show', $s->expedient_id) }}" class="font-bold text-gray-900 hover:underline">{{ $s->subject }}</a></td>
                        <td class="px-5 py-3 text-gray-600">{{ $s->recipient }}</td>
                        <td class="px-5 py-3 text-gray-600">@php($ex = $s->expedient ?? $s->proposal->expedient){{ $ex->codigo }} · {{ $ex->client->razon_social }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $s->requester?->name ?? '—' }} · {{ $s->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3">
                            <span class="font-bold {{ $color }}">{{ $texto }}</span>
                            @if($s->remote_web_link)<a href="{{ $s->remote_web_link }}" target="_blank" rel="noopener noreferrer" class="block text-emerald-800 hover:underline">Abrir borrador en Outlook</a>@endif
                            @if($s->status === 'error')<span class="block text-rose-700 mt-1">{{ $s->last_error }}</span>@endif
                            @if(in_array($s->status, ['pendiente_adaptador', 'error']))
                                <form method="POST" action="{{ route('panel.comunicaciones.crear', $s->id) }}" class="mt-2">@csrf
                                    <button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-3 py-1.5 rounded text-[10px] uppercase tracking-wider">{{ $s->status === 'error' ? 'Reintentar' : 'Crear borrador ahora' }}</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Todavía no hay comunicaciones preparadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $solicitudes->links() }}</div>
</x-panel-layout>
