<x-panel-layout>
    <!-- Encabezado -->
    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans font-brand-serif">Solicitudes de Contacto y Leads</h1>
        <p class="text-sm text-gray-500">Administra las consultas y solicitudes de presupuesto recibidas a través de los formularios públicos de la web.</p>
    </div>

    <!-- Listado de Solicitudes -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs font-brand-sans">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="px-6 py-4">Nombre Solicitante</th>
                        <th class="px-6 py-4">Correo Electrónico</th>
                        <th class="px-6 py-4">Teléfono</th>
                        <th class="px-6 py-4">Servicio Interés</th>
                        <th class="px-6 py-4">Asesor Asignado</th>
                        <th class="px-6 py-4 text-center">Estado</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium font-mono">
                    @forelse($solicitudes as $sol)
                        <tr class="hover:bg-gray-50/50 transition">
                            
                            <!-- Nombre -->
                            <td class="px-6 py-4 font-bold text-gray-900 font-brand-sans">
                                {{ $sol->nombre }}
                            </td>

                            <!-- Email -->
                            <td class="px-6 py-4 font-mono text-gray-500">
                                {{ $sol->email }}
                            </td>

                            <!-- Telefono -->
                            <td class="px-6 py-4">
                                {{ $sol->telefono ?? '—' }}
                            </td>

                            <!-- Servicio -->
                            <td class="px-6 py-4 font-brand-sans">
                                <span class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px]">
                                    {{ $sol->servicio_interes ?? 'Contacto General' }}
                                </span>
                            </td>

                            <!-- Asesor Asignado -->
                            <td class="px-6 py-4 font-brand-sans text-gray-700">
                                {{ $sol->assignedWorker->name ?? 'Por asignar' }}
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4 text-center font-brand-sans">
                                @php
                                    $colores = [
                                        'nueva' => 'bg-blue-50 text-blue-800 border-blue-200',
                                        'en_contacto' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'convertida' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'descartada' => 'bg-gray-100 text-gray-800 border-gray-200',
                                    ];
                                    $clase = $colores[$sol->estado] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="border {{ $clase }} font-bold px-2.5 py-1 rounded-full text-[9px] uppercase tracking-wider">
                                    {{ str_replace('_', ' ', $sol->estado) }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4 text-right font-brand-sans flex items-center justify-end space-x-2">
                                <a href="{{ route('panel.solicitudes.show', $sol->id) }}" class="text-blue-600 hover:text-blue-900 font-bold px-2 py-1">
                                    Atender / Asignar
                                </a>
                                <form action="{{ route('panel.solicitudes.destroy', $sol->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas eliminar permanentemente este registro de lead?')">
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
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No se registran solicitudes de contacto recibidas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="mt-6 text-xs font-semibold">
        {{ $solicitudes->links() }}
    </div>
</x-panel-layout>
