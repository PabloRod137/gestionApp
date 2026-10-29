<x-client-layout>
    <!-- Cabecera -->
    <div class="mb-10 space-y-2">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">Mis Expedientes</h1>
        <p class="text-sm text-gray-500">Listado histórico de trámites, expedientes y gestiones encomendadas al despacho.</p>
    </div>

    <!-- Lista de Expedientes -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="tabla-resp w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-bold uppercase tracking-wider">
                        <th class="px-4 py-4">Ref / Expediente</th>
                        <th class="px-4 py-4">Servicio Relacionado</th>
                        <th class="px-4 py-4">Asesor Asignado</th>
                        <th class="px-4 py-4">Apertura</th>
                        <th class="px-4 py-4 text-center">Estado</th>
                        <th class="px-4 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium">
                    @forelse($expedientes as $exp)
                        <tr class="hover:bg-gray-50/50 transition">
                            <!-- Ref / Título -->
                            <td class="px-4 py-4" data-l="Expediente">
                                <span class="font-bold text-gray-900 block max-w-xs" title="{{ $exp->titulo }}">
                                    {{ $exp->titulo }}
                                </span>
                                <span class="text-[10px] tabular-nums text-gray-400">#EXP-{{ str_pad($exp->id, 5, '0', STR_PAD_LEFT) }}</span>
                            </td>
                            
                            <!-- Servicio -->
                            <td class="px-4 py-4" data-l="Servicio">
                                <span class="text-amber-700 font-semibold text-xs">
                                    {{ $exp->service->nombre ?? 'Trámite General' }}
                                </span>
                            </td>

                            <!-- Asesor responsable -->
                            <td class="px-4 py-4" data-l="Asesor">
                                <div class="flex items-center space-x-2">
                                    <div class="h-6 w-6 rounded-full bg-forest-medium text-white flex items-center justify-center font-bold text-[10px]">
                                        {{ substr($exp->worker->name ?? 'A', 0, 1) }}
                                    </div>
                                    <span class="text-xs text-gray-800">{{ $exp->worker->name ?? 'Por asignar' }}</span>
                                </div>
                            </td>

                            <!-- Fecha Apertura -->
                            <td class="px-4 py-4 tabular-nums" data-l="Apertura">
                                {{ \Carbon\Carbon::parse($exp->fecha_apertura)->format('d/m/Y') }}
                            </td>

                            <!-- Estado -->
                            <td class="px-4 py-4 text-center" data-l="Estado">
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
                                <span class="border {{ $clase }} font-bold px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider whitespace-nowrap">
                                    {{ str_replace('_', ' ', $exp->estado) }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('cliente.expedientes.show', $exp->id) }}" class="inline-block bg-forest-dark hover:bg-gold-warm text-[#1B1E21] font-bold px-3.5 py-2 rounded-lg text-xs transition">
                                    Seguimiento
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No tienes expedientes de trabajo abiertos en este momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-client-layout>
