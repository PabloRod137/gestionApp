@php
    $editando = (bool) $factura;
    $rectificativa = $factura?->esRectificativa();
    $calificaciones = \App\Models\InvoiceLine::CALIFICACIONES;

    // Filas de líneas: lo que el usuario escribió, las del borrador o la propuesta desde el expediente
    if (old('conceptos')) {
        $filas = collect(old('conceptos'))->map(fn ($c, $i) => [
            'concepto' => $c, 'cantidad' => old("cantidades.$i", 1), 'precio' => old("precios.$i"), 'tipo_iva' => old("tipos_iva.$i", 21), 'calificacion' => old("calificaciones.$i", 'S1'),
        ])->all();
    } elseif ($editando && $factura->lines->isNotEmpty()) {
        $filas = $factura->lines->map(fn ($l) => ['concepto' => $l->concepto, 'cantidad' => $l->cantidad, 'precio' => $l->precio_unitario, 'tipo_iva' => (float) $l->tipo_iva, 'calificacion' => $l->calificacion])->all();
    } else {
        $filas = [['concepto' => $pre['concepto'] ?? '', 'cantidad' => 1, 'precio' => $pre['precio'] ?? '', 'tipo_iva' => $pre['iva'] ?? 21, 'calificacion' => 'S1']];
    }
@endphp
<x-panel-layout>
    <div class="mb-6">
        <a href="{{ $editando ? route('panel.facturas.show', $factura->id) : route('panel.facturas.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>{{ $editando ? 'Volver al borrador' : 'Volver a facturas' }}</span>
        </a>
    </div>

    <div class="mb-8 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">
            {{ $rectificativa ? 'Factura rectificativa' : ($editando ? 'Editar borrador de factura' : 'Nueva factura') }}
        </h1>
        <p class="text-sm text-gray-500">
            @if ($rectificativa)
                Rectifica la factura {{ $factura->rectificada?->numero }}
                ({{ $factura->tipo_rectificativa === 'S' ? 'por sustitución: escribe cómo debe quedar la factura completa' : 'por diferencias: escribe solo lo que cambia, con importes negativos si resta' }}).
            @else
                Se guarda como borrador. El número, la fecha y el registro de facturación se asignan al emitirla, y desde entonces ya no se puede modificar.
            @endif
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-8 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-lg text-rose-800 text-xs shadow-sm">
            <h4 class="font-bold mb-2">Por favor, corrige los siguientes errores:</h4>
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $editando ? route('panel.facturas.update', $factura->id) : route('panel.facturas.store') }}" method="POST" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-6 max-w-5xl font-brand-sans">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="client_id">Cliente destinatario *</label>
                <select id="client_id" name="client_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="">Selecciona un cliente...</option>
                    @foreach($clientes as $cli)
                        <option value="{{ $cli->id }}" {{ old('client_id', $factura?->client_id ?? $pre['client_id'] ?? null) == $cli->id ? 'selected' : '' }}>{{ $cli->razon_social }} · {{ $cli->nif ?: 'sin NIF' }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[10px] text-gray-400">En producción el cliente debe tener un NIF válido (la AEAT rechaza los que no existen); en pruebas no hace falta.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="expedient_id">Expediente asociado</label>
                <select id="expedient_id" name="expedient_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                    <option value="">Sin expediente</option>
                    @foreach($expedientes as $exp)
                        <option value="{{ $exp->id }}" {{ old('expedient_id', $factura?->expedient_id ?? $pre['expedient_id'] ?? null) == $exp->id ? 'selected' : '' }}>{{ $exp->titulo }} ({{ $exp->client->razon_social }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="sm:col-span-3">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="descripcion">Descripción de la operación</label>
                <input id="descripcion" type="text" name="descripcion" maxlength="500" value="{{ old('descripcion', $factura?->descripcion) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Si se deja vacía se usa el primer concepto">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="fecha_operacion">Fecha de la operación</label>
                <input id="fecha_operacion" type="date" name="fecha_operacion" value="{{ old('fecha_operacion', $factura?->fecha_operacion?->format('Y-m-d')) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright font-mono">
                <p class="mt-1 text-[10px] text-gray-400">Solo si es distinta de la de emisión (que es la del día en que se emite).</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="retencion_irpf">Retención de IRPF (%)</label>
                <input id="retencion_irpf" type="number" step="0.01" min="0" max="100" name="retencion_irpf" value="{{ old('retencion_irpf', (float) ($factura?->retencion_irpf ?? 0)) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright font-mono">
                <p class="mt-1 text-[10px] text-gray-400">Se resta del importe a cobrar; no cambia el importe total de la factura. Confirmar el tipo con el gestor.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="estado">Cobro</label>
                <select id="estado" name="estado" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="pendiente" {{ old('estado', $factura?->estado ?? 'pendiente') === 'pendiente' ? 'selected' : '' }}>Pendiente de cobro</option>
                    <option value="pagada" {{ old('estado', $factura?->estado) === 'pagada' ? 'selected' : '' }}>Cobrada</option>
                </select>
            </div>
        </div>

        <div class="space-y-4 pt-4 border-t border-gray-150">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Conceptos</h3>
                <button type="button" id="btn-add-linea" class="bg-forest-medium hover:bg-forest-dark text-white font-bold px-3 py-1.5 rounded-lg text-[10px] uppercase tracking-wider transition">+ Añadir línea</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="tabla-lineas">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                            <th class="px-3 py-3 min-w-[16rem]">Concepto</th>
                            <th class="px-3 py-3 w-20">Cant.</th>
                            <th class="px-3 py-3 w-28 text-right">Precio (€)</th>
                            <th class="px-3 py-3 w-48">Tipo</th>
                            <th class="px-3 py-3 w-28">IVA</th>
                            <th class="px-3 py-3 w-20 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="contenedor-filas">
                        @foreach ($filas as $fila)
                            <tr class="fila-concepto hover:bg-gray-50/50">
                                <td class="px-3 py-2"><input type="text" name="conceptos[]" maxlength="255" class="w-full border border-gray-250 rounded-lg px-3 py-2 text-xs focus:ring-1 focus:ring-gold-bright" value="{{ $fila['concepto'] }}" placeholder="Ej. Redacción de contrato de arrendamiento" required></td>
                                <td class="px-3 py-2"><input type="number" min="1" step="1" name="cantidades[]" class="w-full border border-gray-250 rounded-lg px-3 py-2 text-xs text-right focus:ring-1 focus:ring-gold-bright" value="{{ $fila['cantidad'] }}" required></td>
                                <td class="px-3 py-2"><input type="number" step="0.01" name="precios[]" class="w-full border border-gray-250 rounded-lg px-3 py-2 text-xs text-right focus:ring-1 focus:ring-gold-bright" value="{{ $fila['precio'] }}" placeholder="0.00" required></td>
                                <td class="px-3 py-2">
                                    <select name="calificaciones[]" class="w-full border border-gray-250 rounded-lg pl-3 pr-8 py-2 text-xs focus:ring-1 focus:ring-gold-bright">
                                        @foreach ($calificaciones as $codigo => $texto)
                                            <option value="{{ $codigo }}" {{ $fila['calificacion'] === $codigo ? 'selected' : '' }}>{{ $codigo === 'S1' ? 'Sujeta (con IVA)' : $texto }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <select name="tipos_iva[]" class="w-full border border-gray-250 rounded-lg pl-3 pr-8 py-2 text-xs focus:ring-1 focus:ring-gold-bright">
                                        @foreach ([21, 10, 5, 4] as $tipo)
                                            <option value="{{ $tipo }}" {{ (float) $fila['tipo_iva'] === (float) $tipo ? 'selected' : '' }}>{{ $tipo }} %</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-center"><button type="button" class="text-rose-600 hover:text-rose-900 font-bold px-2 py-1 btn-del-linea">Quitar</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[10px] text-gray-400">Los suplidos y provisiones de fondos van como «No sujeta»; no llevan IVA. El IVA de una línea «Sujeta» es el elegido; en las demás no se aplica.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <button type="submit" name="accion" value="borrador" class="flex-1 border border-gray-300 hover:border-gray-500 text-gray-800 font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition">Guardar borrador</button>
            <button type="submit" name="accion" value="emitir" class="flex-1 bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow" onclick="return confirm('Al emitir la factura se le asigna su número y su registro de facturación, y ya no se podrá modificar ni borrar (solo anular o rectificar). ¿Emitirla ahora?')">Guardar y emitir</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const contenedor = document.getElementById('contenedor-filas');
            const plantilla = contenedor.querySelector('tr.fila-concepto').cloneNode(true);

            document.getElementById('btn-add-linea').addEventListener('click', () => {
                const fila = plantilla.cloneNode(true);
                fila.querySelectorAll('input').forEach(i => { i.value = i.name === 'cantidades[]' ? '1' : ''; });
                fila.querySelectorAll('select').forEach(s => { s.selectedIndex = 0; });
                contenedor.appendChild(fila);
                revisar();
            });
            contenedor.addEventListener('click', e => {
                if (e.target.classList.contains('btn-del-linea')) { e.target.closest('tr').remove(); revisar(); }
            });
            function revisar() {
                const filas = contenedor.querySelectorAll('tr.fila-concepto');
                contenedor.querySelectorAll('.btn-del-linea').forEach(b => b.disabled = filas.length <= 1);
            }
            revisar();
        });
    </script>
</x-panel-layout>
