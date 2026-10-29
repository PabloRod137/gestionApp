<x-client-layout>
    <!-- Cabecera -->
    <div class="mb-10 space-y-2">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">Mis Facturas y Recibos</h1>
        <p class="text-sm text-gray-500">Historial de facturas emitidas por el despacho y estado de su cobro.</p>
    </div>

    <!-- Listado de Facturas -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="tabla-resp w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 font-bold uppercase tracking-wider">
                        <th class="px-4 py-4">Número Factura</th>
                        <th class="px-4 py-4">Asunto / Expediente</th>
                        <th class="px-4 py-4">Fecha de Emisión</th>
                        <th class="px-4 py-4 text-right">Importe Total</th>
                        <th class="px-4 py-4 text-center">Estado</th>
                        <th class="px-4 py-4 text-right">Documento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium">
                    @forelse($facturas as $fac)
                        <tr class="hover:bg-gray-50/50 transition">
                            <!-- Número -->
                            <td class="px-4 py-4 font-bold text-gray-900 font-brand-sans" data-l="Factura">
                                {{ $fac->numero }}
                            </td>

                            <!-- Expediente -->
                            <td class="px-4 py-4 font-brand-sans" data-l="Asunto">
                                {{ $fac->expedient->titulo ?? 'Gestión General / Varios' }}
                            </td>

                            <!-- Fecha -->
                            <td class="px-4 py-4 tabular-nums" data-l="Emisión">
                                {{ \Carbon\Carbon::parse($fac->fecha)->format('d/m/Y') }}
                            </td>

                            <!-- Total -->
                            <td class="px-4 py-4 text-right font-bold text-gray-900 font-brand-sans tabular-nums" data-l="Importe">
                                {{ number_format($fac->total, 2, ',', '.') }}€
                            </td>

                            <!-- Estado -->
                            <td class="px-4 py-4 text-center font-brand-sans" data-l="Estado">
                                @php
                                    $colores = [
                                        'pendiente' => 'bg-rose-50 text-rose-800 border-rose-200',
                                        'pagada' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        'cancelada' => 'bg-gray-50 text-gray-800 border-gray-200',
                                    ];
                                    $clase = $colores[$fac->estado] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="border {{ $clase }} font-bold px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider whitespace-nowrap">
                                    {{ $fac->estado }}
                                </span>
                            </td>

                            <!-- Descarga PDF -->
                            <td class="px-4 py-4 text-right font-brand-sans">
                                <a href="{{ route('cliente.facturas.pdf', $fac->id) }}" class="bg-forest-dark hover:bg-gold-warm hover:text-[#1B1E21] text-[#1B1E21] font-bold px-3 py-1.5 rounded-lg text-[10px] transition inline-flex items-center space-x-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    <span>Descargar PDF</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="font-brand-sans">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No tienes facturas registradas en tu historial.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-client-layout>
