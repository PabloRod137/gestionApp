<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->esBorrador() ? 'Borrador de factura' : 'Factura '.$invoice->numero }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.5;
        }
        .container {
            padding: 20px;
        }
        .header {
            width: 100%;
            margin-bottom: 30px;
        }
        .logo-section {
            width: 50%;
            vertical-align: top;
        }
        .meta-section {
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        .brand-name {
            font-size: 22px;
            font-weight: bold;
            color: #9c8d45; /* Brand Gold */
            margin: 0;
        }
        .brand-subtitle {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #787873; /* Contrast Gray */
            margin-top: 3px;
        }
        .doc-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            color: #111827;
        }
        .meta-details {
            font-size: 10px;
            color: #4b5563;
            margin-top: 5px;
        }
        .parties {
            width: 100%;
            margin-bottom: 30px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 15px 0;
        }
        .party-box {
            width: 50%;
            vertical-align: top;
        }
        .party-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .party-name {
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #1f2937;
        }
        .party-info {
            margin: 2px 0;
            color: #4b5563;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            padding: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            text-align: left;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        .items-table .text-right {
            text-align: right;
        }
        .totals-section {
            width: 100%;
        }
        .totals-space {
            width: 60%;
        }
        .totals-box {
            width: 40%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 10px;
            color: #4b5563;
        }
        .totals-table .total-row td {
            font-size: 14px;
            font-weight: bold;
            color: #1b190d;
            border-top: 2px solid #e5e7eb;
            padding-top: 10px;
        }
        .footer {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }
        .qr-block {
            width: 100%;
            text-align: center;
            margin: 0 0 18px 0;
        }
        .qr-block .qr-label { font-size: 10px; font-weight: bold; margin-bottom: 4px; }
        .qr-block img { width: 35mm; height: 35mm; padding: 4mm; background: #ffffff; }
        .qr-block .qr-legend { font-size: 10px; font-weight: bold; margin-top: 2px; }
        .aviso { margin: 0 0 14px 0; padding: 8px 10px; border: 1px solid #d1d5db; background: #f9fafb; color: #374151; font-size: 10px; }
        .aviso-borrador { border-color: #f59e0b; background: #fffbeb; color: #92400e; font-weight: bold; }
        .items-table td.nowrap { white-space: nowrap; }
        .nota-menor { font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>

@php
    $borrador = $invoice->esBorrador();
    $emisor = (! $borrador && $invoice->emisor_nif) ? ['nombre' => $invoice->emisor_nombre, 'nif' => $invoice->emisor_nif, 'direccion' => $invoice->emisor_direccion] + $despacho : $despacho;
    $receptor = (! $borrador && $invoice->receptor_nif) ? ['nombre' => $invoice->receptor_nombre, 'nif' => $invoice->receptor_nif, 'direccion' => $invoice->receptor_direccion, 'telefono' => $invoice->client->telefono] : ['nombre' => $invoice->client->razon_social, 'nif' => $invoice->client->nif, 'direccion' => $invoice->client->direccion, 'telefono' => $invoice->client->telefono];
    $eur = fn ($centimos) => number_format($centimos / 100, 2, ',', '.').' €';
    $pct = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    $titulo = $invoice->esRectificativa() ? 'Factura rectificativa' : 'Factura';
@endphp

<div class="container">
    @if ($borrador)
        <div class="aviso aviso-borrador">BORRADOR: sin número ni validez. La factura se numera y se registra al emitirla.</div>
    @endif
    @if (! $borrador && $invoice->emision !== 'historica' && config('verifactu.entorno') !== 'produccion')
        <div class="aviso aviso-borrador">FACTURA DE PRUEBA: generada en el entorno de pruebas de Veri*Factu, sin validez fiscal.</div>
    @endif
    @if ($invoice->emision === 'anulada')
        <div class="aviso">FACTURA ANULADA el {{ $invoice->anulada_at?->format('d/m/Y') }}. Motivo: {{ $invoice->anulada_motivo }}</div>
    @endif

    {{-- QR tributario (Veri*Factu): al principio de la factura, centrado --}}
    @if ($qr)
        <div class="qr-block">
            <div class="qr-label">QR tributario:</div>
            <img src="{{ $qr }}" alt="QR tributario">
            <div class="qr-legend">VERI*FACTU</div>
            <div class="nota-menor">Factura verificable en la sede electrónica de la AEAT</div>
        </div>
    @endif

    <!-- Cabecera -->
    <table class="header">
        <tr>
            <td class="logo-section">
                <div class="brand-name">{{ $emisor['nombre'] }}</div>
                <div class="brand-subtitle">Asesoría Jurídica y Económica</div>
            </td>
            <td class="meta-section">
                <div class="doc-title">{{ $titulo }}</div>
                <div class="meta-details">
                    <strong>Número:</strong> {{ $borrador ? '—' : $invoice->numero }}<br>
                    <strong>Fecha de expedición:</strong> {{ $borrador ? '—' : $invoice->fecha->format('d/m/Y') }}<br>
                    @if ($invoice->fecha_operacion)<strong>Fecha de la operación:</strong> {{ $invoice->fecha_operacion->format('d/m/Y') }}<br>@endif
                    @if ($invoice->esRectificativa())
                        <strong>Rectifica:</strong> {{ $invoice->rectificada?->numero }} ({{ $invoice->tipo_rectificativa === 'S' ? 'por sustitución' : 'por diferencias' }})<br>
                    @endif
                    @unless ($borrador)<strong>Cobro:</strong> {{ $invoice->estado === 'pagada' ? 'COBRADA' : 'PENDIENTE' }}@endunless
                </div>
            </td>
        </tr>
    </table>

    <!-- Emisor / Receptor -->
    <table class="parties">
        <tr>
            <td class="party-box">
                <div class="party-title">Emisor</div>
                <div class="party-name">{{ $emisor['nombre'] }}</div>
                @if ($emisor['nif'])<div class="party-info">NIF: {{ $emisor['nif'] }}</div>@endif
                @if ($emisor['direccion'])<div class="party-info">{{ $emisor['direccion'] }}</div>@endif
                @if ($despacho['telefono'])<div class="party-info">Tel: {{ $despacho['telefono'] }}</div>@endif
                @if ($despacho['email'])<div class="party-info">Email: {{ $despacho['email'] }}</div>@endif
            </td>
            <td class="party-box">
                <div class="party-title">Receptor / Cliente</div>
                <div class="party-name">{{ $receptor['nombre'] }}</div>
                <div class="party-info">NIF/CIF: {{ $receptor['nif'] }}</div>
                <div class="party-info">{{ $receptor['direccion'] ?: '—' }}</div>
                @if($receptor['telefono'])
                    <div class="party-info">Tel: {{ $receptor['telefono'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Conceptos -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="text-right" style="width: 8%">Cant.</th>
                <th class="text-right" style="width: 15%">Precio</th>
                <th class="text-right" style="width: 13%">IVA</th>
                <th class="text-right" style="width: 18%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
                <tr>
                    <td>{{ $line->concepto }}</td>
                    <td class="text-right">{{ $line->cantidad }}</td>
                    <td class="text-right">{{ number_format($line->precio_unitario, 2, ',', '.') }} €</td>
                    <td class="text-right nowrap">{{ ($line->calificacion ?: 'S1') === 'S1' ? $pct($line->tipo_iva).' %' : ($line->calificacion === 'N1' ? 'No sujeta' : 'Exenta') }}</td>
                    <td class="text-right">{{ number_format($line->subtotal, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totales -->
    <table class="totals-section">
        <tr>
            <td class="totals-space"></td>
            <td class="totals-box">
                <table class="totals-table">
                    @foreach ($totales['grupos'] as $g)
                        <tr>
                            <td>Base {{ $g['calificacion'] === 'S1' ? $pct($g['tipo']).' %' : ($g['calificacion'] === 'N1' ? 'no sujeta' : 'exenta') }}:</td>
                            <td class="text-right">{{ $eur($g['base']) }}</td>
                        </tr>
                        @if ($g['calificacion'] === 'S1')
                            <tr>
                                <td>I.V.A. {{ $pct($g['tipo']) }} %:</td>
                                <td class="text-right">{{ $eur($g['cuota']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    <tr class="total-row">
                        <td>Importe total:</td>
                        <td class="text-right">{{ $eur($totales['total']) }}</td>
                    </tr>
                    @if ($totales['retencion'])
                        <tr>
                            <td>Retención IRPF {{ $pct($invoice->retencion_irpf) }} %:</td>
                            <td class="text-right">-{{ $eur($totales['retencion']) }}</td>
                        </tr>
                        <tr>
                            <td><strong>A cobrar:</strong></td>
                            <td class="text-right"><strong>{{ $eur($totales['total'] - $totales['retencion']) }}</strong></td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Pie de página legal -->
    <div class="footer">
        {{ $emisor['nombre'] }}<br>
        En cumplimiento del RGPD, le informamos que sus datos están incorporados en ficheros responsabilidad del despacho.
    </div>
</div>

</body>
</html>
