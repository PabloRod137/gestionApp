<x-panel-layout>
    <!-- Encabezado y Acción -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10">
        <div class="space-y-1">
            <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Seguimiento de Expedientes</h1>
            <p class="text-sm text-gray-500">Supervisa las gestiones y estados de los expedientes activos del despacho.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <!-- Importar Excel -->
            <form action="{{ route('panel.expedientes.import') }}" method="POST" enctype="multipart/form-data" class="inline-block">
                @csrf
                <input type="file" name="archivo_excel" class="hidden" id="file-import-expedientes" onchange="this.form.submit()">
                <button type="button" onclick="document.getElementById('file-import-expedientes').click()" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-255 font-bold px-4 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow-sm inline-flex items-center space-x-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Importar Excel</span>
                </button>
            </form>

            <!-- Exportar Excel -->
            <a href="{{ route('panel.expedientes.export') }}" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold px-4 py-3 rounded-lg text-xs uppercase tracking-wider transition inline-flex items-center space-x-1.5 shadow-sm font-brand-sans">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Exportar Excel</span>
            </a>

            <!-- Nuevo Expediente -->
            <a href="{{ route('panel.expedientes.create') }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow">
                Nuevo Expediente
            </a>
        </div>
    </div>

    <!-- Barra de Filtros y Búsquedas Avanzadas -->
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-semibold">
        <form action="{{ route('panel.expedientes.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            
            <!-- Buscador Título -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Buscar</label>
                <input type="text" name="search" value="{{ $buscar }}" placeholder="Título, nº de expediente o cliente..." class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
            </div>

            <!-- Filtro Estado -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Estado</label>
                <select name="estado" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Cualquier estado</option>
                    <option value="abierto" {{ $estado === 'abierto' ? 'selected' : '' }}>Abierto</option>
                    <option value="en_proceso" {{ $estado === 'en_proceso' ? 'selected' : '' }}>En Proceso</option>
                    <option value="pendiente_cliente" {{ $estado === 'pendiente_cliente' ? 'selected' : '' }}>Pendiente Cliente</option>
                    <option value="finalizado" {{ $estado === 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                    <option value="cancelado" {{ $estado === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>

            <!-- Filtro Cliente -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Cliente</label>
                <select name="client_id" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Cualquier cliente</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ $clienteId == $c->id ? 'selected' : '' }}>{{ $c->razon_social }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Botones -->
            <div class="flex items-center space-x-2">
                <button type="submit" class="w-full bg-forest-medium hover:bg-forest-dark text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition">
                    Filtrar
                </button>
                @if($buscar || $estado || $clienteId || $asesorId)
                    <a href="{{ route('panel.expedientes.index') }}" class="text-gray-400 hover:text-gray-600 underline text-xs">Limpiar</a>
                @endif
            </div>

        </form>
    </div>

    <!-- Tabla de Expedientes -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                        <th class="px-6 py-4">Ref / Asunto</th>
                        <th class="px-6 py-4">Cliente</th>
                        <th class="px-6 py-4">Asesor Asignado</th>
                        <th class="px-6 py-4">Apertura</th>
                        <th class="px-6 py-4 text-center">Estado</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium font-mono">
                    @forelse($expedientes as $exp)
                        <tr class="hover:bg-gray-50/50 transition">
                            <!-- Ref / Título -->
                            <td class="px-6 py-4">
                                <a href="{{ route('panel.expedientes.show', $exp->id) }}" class="font-bold text-gray-900 block truncate max-w-xs hover:underline font-brand-sans" title="{{ $exp->titulo }}">
                                    {{ $exp->titulo }}
                                </a>
                                <span class="text-[10px] text-gray-400 block">{{ $exp->codigo ? 'Nº '.$exp->codigo : '#EXP-'.str_pad($exp->id, 5, '0', STR_PAD_LEFT) }}</span>
                            </td>

                            <!-- Cliente -->
                            <td class="px-6 py-4 font-brand-sans text-gray-800 font-semibold">
                                {{ $exp->client->razon_social }}
                            </td>

                            <!-- Asesor responsable -->
                            <td class="px-6 py-4 font-brand-sans text-gray-700">
                                {{ $exp->worker->name ?? 'Sin asignar' }}
                            </td>

                            <!-- Fecha Apertura -->
                            <td class="px-6 py-4">
                                {{ \Carbon\Carbon::parse($exp->fecha_apertura)->format('d/m/Y') }}
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4 text-center font-brand-sans">
                                @php
                                    $colores = [
                                        'abierto' => 'bg-blue-50 text-blue-800 border-blue-200',
                                        'en_proceso' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'pendiente_cliente' => 'bg-purple-50 text-purple-800 border-purple-200',
                                        'finalizado' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'cancelado' => 'bg-rose-50 text-rose-800 border-rose-200',
                                    ];
                                    $clase = $colores[$exp->estado] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="border {{ $clase }} font-bold px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">
                                    {{ str_replace('_', ' ', $exp->estado) }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4 text-right font-brand-sans flex items-center justify-end space-x-2">
                                <a href="{{ route('panel.expedientes.show', $exp->id) }}" class="text-gray-500 hover:text-gray-900 font-bold px-2 py-1">
                                    Ficha
                                </a>
                                <a href="{{ route('panel.expedientes.edit', $exp->id) }}" class="text-blue-600 hover:text-blue-900 font-bold px-2 py-1">
                                    Editar
                                </a>
                                <form action="{{ route('panel.expedientes.destroy', $exp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas archivar/eliminar este expediente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-900 font-bold px-2 py-1">
                                        Archivar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr class="font-brand-sans">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No se encontraron expedientes con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="mt-6 text-xs font-semibold">
        {{ $expedientes->appends(request()->query())->links() }}
    </div>
</x-panel-layout>
