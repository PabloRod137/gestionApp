<x-panel-layout>
    <!-- Encabezado y Acción -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10">
        <div class="space-y-1">
            <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Agenda y Citas del Despacho</h1>
            <p class="text-sm text-gray-500">Supervisa las reuniones programadas con los clientes del despacho.</p>
        </div>
        <a href="{{ route('panel.citas.create') }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Agendar Nueva Cita
        </a>
    </div>

    <!-- Barra de Filtros -->
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-semibold">
        <form action="{{ route('panel.citas.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            
            <!-- Fecha -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Fecha</label>
                <input type="date" name="fecha" value="{{ $fechaFiltro }}" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
            </div>

            <!-- Asesor -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Asesor asignado</label>
                <select name="worker_id" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Cualquier asesor</option>
                    @foreach($asesores as $ase)
                        <option value="{{ $ase->id }}" {{ $workerFiltro == $ase->id ? 'selected' : '' }}>{{ $ase->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Estado -->
            <div>
                <label class="block text-gray-400 uppercase text-[9px] mb-2">Estado de la Cita</label>
                <select name="estado" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Cualquier estado</option>
                    <option value="pendiente" {{ $estadoFiltro === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="confirmada" {{ $estadoFiltro === 'confirmada' ? 'selected' : '' }}>Confirmada</option>
                    <option value="realizada" {{ $estadoFiltro === 'realizada' ? 'selected' : '' }}>Realizada</option>
                    <option value="cancelada" {{ $estadoFiltro === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>

            <!-- Botones -->
            <div class="flex items-center space-x-2">
                <button type="submit" class="w-full bg-forest-medium hover:bg-forest-dark text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition">
                    Filtrar Agenda
                </button>
                @if($fechaFiltro || $workerFiltro || $estadoFiltro)
                    <a href="{{ route('panel.citas.index') }}" class="text-gray-400 hover:text-gray-600 underline">Limpiar</a>
                @endif
            </div>

        </form>
    </div>

    <!-- Listado de Citas en Tabla -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                        <th class="px-6 py-4">Cliente</th>
                        <th class="px-6 py-4">Servicio de Interés</th>
                        <th class="px-6 py-4">Asesor Responsable</th>
                        <th class="px-6 py-4">Fecha y Hora</th>
                        <th class="px-6 py-4 text-center">Estado</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium font-mono">
                    @forelse($citas as $cita)
                        <tr class="hover:bg-gray-50/50 transition">
                            
                             <!-- Cliente / Invitado -->
                             <td class="px-6 py-4 font-brand-sans">
                                 @if($cita->client)
                                     <div class="font-bold text-gray-900">
                                         {{ $cita->client->razon_social }}
                                     </div>
                                     <div class="text-[10px] text-gray-400 font-normal font-sans">
                                         Cliente Registrado
                                     </div>
                                 @else
                                     <div class="font-bold text-stone-800">
                                         {{ $cita->guest_name }} <span class="bg-amber-150 text-amber-800 text-[9px] font-bold px-1.5 py-0.5 rounded ml-1">Invitado</span>
                                     </div>
                                     <div class="text-[10px] text-gray-400 font-normal font-sans">
                                         {{ $cita->guest_email }} | {{ $cita->guest_phone }}
                                     </div>
                                 @endif
                             </td>

                            <!-- Servicio -->
                            <td class="px-6 py-4 font-brand-sans">
                                <span class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px]">
                                    {{ $cita->service->nombre ?? 'Consulta General' }}
                                </span>
                            </td>

                            <!-- Asesor -->
                            <td class="px-6 py-4 font-brand-sans text-gray-700">
                                {{ $cita->worker->name ?? 'Sin asignar' }}
                            </td>

                            <!-- Horario -->
                            <td class="px-6 py-4">
                                {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hs
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4 text-center font-brand-sans">
                                @php
                                    $colores = [
                                        'pendiente' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'confirmada' => 'bg-blue-50 text-blue-800 border-blue-200',
                                        'realizada' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'cancelada' => 'bg-rose-50 text-rose-800 border-rose-200',
                                    ];
                                    $clase = $colores[$cita->estado] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="border {{ $clase }} font-bold px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">
                                    {{ $cita->estado }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4 text-right font-brand-sans flex items-center justify-end space-x-2">
                                @if($cita->estado === 'pendiente')
                                    <form action="{{ route('panel.citas.confirmar', $cita->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1 rounded text-[10px] uppercase transition" title="Confirmar Cita">
                                            Confirmar
                                        </button>
                                    </form>
                                    <form action="{{ route('panel.citas.cancelar', $cita->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas cancelar esta cita?')">
                                        @csrf
                                        <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold px-2.5 py-1 rounded text-[10px] uppercase transition" title="Cancelar Cita">
                                            Cancelar
                                        </button>
                                    </form>
                                @elseif($cita->estado === 'confirmada')
                                    <form action="{{ route('panel.citas.cancelar', $cita->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas cancelar esta cita?')">
                                        @csrf
                                        <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold px-2.5 py-1 rounded text-[10px] uppercase transition" title="Cancelar Cita">
                                            Cancelar
                                        </button>
                                    </form>
                                @elseif($cita->estado === 'cancelada')
                                    <form action="{{ route('panel.citas.confirmar', $cita->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1 rounded text-[10px] uppercase transition" title="Confirmar Cita">
                                            Confirmar
                                        </button>
                                    </form>
                                @endif
                                
                                <a href="{{ route('panel.citas.edit', $cita->id) }}" class="text-blue-600 hover:text-blue-900 font-bold px-2 py-1">
                                    Modificar / Estado
                                </a>
                                <form action="{{ route('panel.citas.destroy', $cita->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas eliminar esta cita de la agenda?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-900 font-bold px-2 py-1">
                                        Eliminar
                                    </button>
                                </form>
                            </td>

                        </tr>
                    @empty
                        <tr class="font-brand-sans">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No se encontraron citas agendadas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-panel-layout>
