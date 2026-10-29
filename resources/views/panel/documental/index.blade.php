@php
    $pestanas = ['pendientes' => 'Propuestas', 'contradicciones' => 'Contradicciones', 'resueltos' => 'Resueltos'];
    $campos = \App\Models\Hallazgo::CAMPOS;
@endphp
<x-panel-layout>
    <div class="mb-6 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Bandeja documental</h1>
        <p class="text-sm text-gray-500 font-brand-sans max-w-3xl">El programa lee en este equipo los documentos de las carpetas de cada cliente y propone datos para su ficha (NIF, correo, teléfono). Nada se guarda hasta que una persona lo acepta; cada decisión queda en el historial. Ningún documento sale de este equipo.</p>
    </div>

    @if (session('success')) <div class="mb-4 p-3 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 text-xs rounded-r-lg">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ session('error') }}</div> @endif
    @if (! $raiz)
        <div class="mb-4 p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-900 text-xs rounded-r-lg">No hay todavía una carpeta raíz de documentos configurada (<code>DOCUMENTAL_RAIZ</code>). Cuando se conecte la carpeta de los clientes, aquí aparecerá lo que el programa encuentre.</div>
    @elseif (! $activo)
        <div class="mb-4 p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-900 text-xs rounded-r-lg">El inventario automático está apagado (<code>DOCUMENTAL_ACTIVO=false</code>). Puedes revisar las carpetas a mano con el botón.</div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6 text-xs font-brand-sans">
        @foreach (['inventariados' => 'Inventariados', 'analizados' => 'Analizados', 'omitidos' => 'Omitidos', 'con_error' => 'Con error', 'por_procesar' => 'En cola', 'pendientes' => 'Propuestas', 'contradicciones' => 'Contradicciones'] as $k => $etiqueta)
            <div class="bg-white border border-gray-150 rounded-xl p-3"><div class="text-[9px] uppercase tracking-wider text-gray-400 font-bold">{{ $etiqueta }}</div><div class="text-lg font-extrabold text-gray-900">{{ number_format($resumen[$k], 0, ',', '.') }}</div></div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 font-brand-sans text-xs">
        <div class="flex gap-2">
            @foreach ($pestanas as $clave => $titulo)
                <a href="{{ route('panel.documental.index', ['vista' => $clave]) }}" class="px-4 py-2 rounded-lg font-bold border {{ $vista === $clave ? 'bg-forest-dark text-white border-transparent' : 'border-gray-300 text-gray-700 hover:border-gray-500' }}">{{ $titulo }}</a>
            @endforeach
        </div>
        @if ($raiz)
            <form action="{{ route('panel.documental.revisar') }}" method="POST">@csrf
                <button type="submit" class="border border-gray-300 hover:border-gray-500 text-gray-800 font-bold px-4 py-2 rounded-lg">Revisar las carpetas ahora</button>
            </form>
        @endif
    </div>

    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse font-brand-sans">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                        <th class="px-3 py-3">Cliente</th><th class="px-3 py-3">Dato</th><th class="px-3 py-3">En el documento</th><th class="px-3 py-3">En la ficha</th><th class="px-3 py-3">Origen</th><th class="px-3 py-3 text-right">Decisión</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 align-top">
                    @forelse ($hallazgos as $h)
                        <tr>
                            <td class="px-3 py-3"><a href="{{ route('panel.documental.conocimiento', $h->client_id) }}" class="font-bold text-gray-900 hover:underline">{{ $h->client->razon_social }}</a></td>
                            <td class="px-3 py-3">{{ $campos[$h->campo] ?? $h->campo }}</td>
                            <td class="px-3 py-3 font-mono font-bold">{{ $h->valor }}
                                <div class="font-sans font-normal text-[10px] {{ $h->confianza >= 60 ? 'text-emerald-700' : 'text-amber-700' }}">confianza {{ $h->confianza }} %{{ $h->posible_tercero ? ' · posible dato de un tercero' : '' }}</div>
                            </td>
                            <td class="px-3 py-3 font-mono">{{ $h->valor_actual ?: '—' }}</td>
                            <td class="px-3 py-3 max-w-xs">{{ $h->documento->nombre }}<div class="text-[10px] text-gray-400">{{ $h->pagina ? 'p. '.$h->pagina.' · ' : '' }}{{ $h->metodo }} · {{ substr((string) $h->sha256, 0, 10) }}</div>
                                @if ($h->fragmento)<div class="text-[10px] text-gray-500 italic break-words">«…{{ $h->fragmento }}…»</div>@endif
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                @if ($h->estado === 'pendiente')
                                    <form action="{{ route('panel.documental.decidir', $h->id) }}" method="POST" class="inline">@csrf
                                        <button name="accion" value="{{ $h->tipo === 'propuesta' ? 'aceptar' : 'corregir' }}" class="font-bold text-emerald-700 hover:text-emerald-900 px-2">{{ $h->tipo === 'propuesta' ? 'Aceptar' : 'Corregir ficha' }}</button>
                                        <button name="accion" value="rechazar" class="font-bold text-rose-600 hover:text-rose-900 px-2">Rechazar</button>
                                    </form>
                                @else
                                    <div class="font-bold {{ $h->estado === 'rechazado' ? 'text-rose-700' : 'text-emerald-700' }}">{{ ucfirst($h->estado) }}</div>
                                    <div class="text-[10px] text-gray-400">{{ $h->decididoPor?->name }} · {{ $h->decidido_at?->format('d/m/Y H:i') }}</div>
                                    @if ($h->estado === 'rechazado')
                                        <form action="{{ route('panel.documental.decidir', $h->id) }}" method="POST">@csrf<button name="accion" value="reabrir" class="text-[10px] text-gray-500 hover:underline">Reabrir</button></form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">No hay nada en esta lista.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 text-xs">{{ $hallazgos->links() }}</div>
</x-panel-layout>
