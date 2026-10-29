<x-panel-layout>
    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">Sincronización con SharePoint</h1>
        <p class="text-sm text-gray-500">Cada avance que haces en el panel (propuesta aprobada, documento generado, cambio de estado…) se prepara para que Power Automate lo refleje en SharePoint. <strong>El programa no escribe en SharePoint</strong>: deja el cambio, Power Automate lo aplica y SharePoint lo confirma en la siguiente lectura.</p>
    </div>

    @if (session('success'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 text-xs">
        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm"><span class="text-gray-400 block">Pendientes de entregar</span><span class="text-2xl font-bold text-gray-900">{{ $pendientes }}</span></div>
        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm"><span class="text-gray-400 block">Entregados, esperando confirmación</span><span class="text-2xl font-bold text-gray-900">{{ $entregados }}</span></div>
        <div class="bg-white border {{ $sinConfirmar ? 'border-amber-300' : 'border-gray-150' }} rounded-2xl p-6 shadow-sm"><span class="text-gray-400 block">Más de {{ $horas }} h sin confirmar</span><span class="text-2xl font-bold {{ $sinConfirmar ? 'text-amber-700' : 'text-gray-900' }}">{{ $sinConfirmar }}</span>
            @if($sinConfirmar)<p class="mt-1 text-amber-800">{{ $sinConfirmar }} sin confirmar: comprueba que el flujo de Power Automate se está ejecutando.</p>@endif</div>
    </div>

    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm mb-8 text-xs flex flex-wrap items-center justify-between gap-4">
        <p class="text-gray-600 max-w-2xl">Carpeta de salida para Power Automate: <code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $carpeta }}</code>. Cada entrega son dos archivos (JSON y CSV) con las columnas de la lista «Registro_Actividad_Expediente».</p>
        <form method="POST" action="{{ route('panel.sincronizacion.exportar') }}">@csrf<button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-4 py-2 rounded text-[10px] uppercase tracking-wider">Exportar ahora</button></form>
    </div>

    <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-400 uppercase text-[9px] tracking-wider"><tr><th class="px-5 py-3">Evento</th><th class="px-5 py-3">Expediente</th><th class="px-5 py-3">Actividad</th><th class="px-5 py-3">Estado en SharePoint</th><th class="px-5 py-3">Situación</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @php $badge = ['pendiente' => ['Pendiente de entregar', 'bg-amber-100 text-amber-800'], 'exportado' => ['Entregado', 'bg-sky-100 text-sky-800'], 'confirmado' => ['Confirmado', 'bg-emerald-100 text-emerald-800']]; @endphp
                @forelse($eventos as $e)
                    @php [$txt, $cls] = $badge[$e->estado]; $tarde = $e->estado === 'exportado' && $e->exportado_en && $e->exportado_en->lte(now()->subHours($horas)); @endphp
                    <tr class="align-top hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-gray-600">{{ $e->evento_id }}<span class="block text-gray-400">{{ $e->ocurrido_en->format('d/m/Y H:i') }}</span></td>
                        <td class="px-5 py-3">@if($e->esDeCliente())<a href="{{ route('panel.clientes.show', $e->client_id) }}" class="font-bold text-gray-900 hover:underline">Ficha de cliente</a><span class="block text-gray-500">{{ $e->client->razon_social }}</span>@else<a href="{{ route('panel.expedientes.show', $e->expedient_id) }}" class="font-bold text-gray-900 hover:underline">{{ $e->expedient->codigo }}</a><span class="block text-gray-500">{{ $e->expedient->client->razon_social }}</span>@endif</td>
                        <td class="px-5 py-3 text-gray-700">{{ $e->actividad }}@if($e->esDeCliente())<span class="block text-gray-500">{{ $e->descripcion }}</span>@endif<span class="block text-gray-400">{{ $e->actor ?? 'sistema' }}</span></td>
                        <td class="px-5 py-3 text-gray-700">{{ $e->estado_anterior ? $e->estado_anterior.' → ' : '' }}{{ $e->estado_nuevo ?? '—' }}</td>
                        <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full font-bold text-[9px] uppercase tracking-wider {{ $cls }}">{{ $txt }}</span>@if($tarde)<span class="block mt-1 text-amber-700 font-bold">sin confirmar</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Todavía no hay cambios para sincronizar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-panel-layout>
