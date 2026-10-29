<x-panel-layout>
    <!-- Encabezado -->
    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans font-brand-serif">Registro de Auditoría de Acciones</h1>
        <p class="text-sm text-gray-500">Historial completo de modificaciones, altas y bajas de información en la plataforma para cumplir con normativas de seguridad (RGPD).</p>
    </div>

    <!-- Filtros -->
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-semibold">
        <form action="{{ route('panel.auditoria.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2" for="modelo">Elemento modificado</label>
                <input type="text" id="modelo" name="modelo" value="{{ $buscarModelo }}" placeholder="Ej. Client, Expedient, Invoice" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
            </div>
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2" for="accion">Tipo de acción</label>
                <select id="accion" name="accion" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Cualquier acción</option>
                    @foreach (['creado' => 'Alta', 'modificado' => 'Modificación', 'eliminado' => 'Baja'] as $value => $text)
                        <option value="{{ $value }}" @selected($buscarAccion === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center space-x-3">
                <button type="submit" class="bg-[#2B2F33] hover:bg-[#4F555A] text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition">Filtrar</button>
                @if ($buscarModelo || $buscarAccion)
                    <a href="{{ route('panel.auditoria.index') }}" class="text-gray-500 hover:text-gray-700 underline">Limpiar</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tabla Logs -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs font-brand-sans">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="px-6 py-4">Operador</th>
                        <th class="px-6 py-4">Acción</th>
                        <th class="px-6 py-4">Elemento Modificado</th>
                        <th class="px-6 py-4">ID Elemento</th>
                        <th class="px-6 py-4">Dirección IP</th>
                        <th class="px-6 py-4 font-mono">Fecha y Hora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium font-mono">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50/50 transition">
                            
                            <!-- Operador -->
                            <td class="px-6 py-4 font-bold text-gray-900 font-brand-sans">
                                {{ $log->user->name ?? 'Usuario de la Web' }}
                                <span class="text-[10px] text-gray-400 block font-normal">{{ $log->user->email ?? 'Anon' }}</span>
                            </td>

                            <!-- Acción -->
                            <td class="px-6 py-4 font-brand-sans">
                                @php
                                    $colores = [
                                        'creado' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'modificado' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'eliminado' => 'bg-rose-50 text-rose-800 border-rose-200',
                                    ];
                                    $clase = $colores[$log->accion] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="border {{ $clase }} font-bold px-2 py-0.5 rounded text-[8px] uppercase tracking-wider">
                                    {{ $log->accion }}
                                </span>
                            </td>

                            <!-- Modelo -->
                            <td class="px-6 py-4 font-brand-sans">
                                {{ class_basename($log->modelo) }}
                            </td>

                            <!-- ID -->
                            <td class="px-6 py-4 text-gray-500">
                                #{{ $log->modelo_id }}
                            </td>

                            <!-- IP -->
                            <td class="px-6 py-4 font-mono text-[10px] text-gray-400">
                                {{ $log->ip ?? '—' }}
                            </td>

                            <!-- Fecha y Hora -->
                            <td class="px-6 py-4 font-mono text-[10px] text-gray-400">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>

                        </tr>
                    @empty
                        <tr class="font-brand-sans">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No se registran eventos de auditoría en la base de datos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="mt-6 text-xs font-semibold">
        {{ $logs->appends(request()->query())->links() }}
    </div>
</x-panel-layout>
