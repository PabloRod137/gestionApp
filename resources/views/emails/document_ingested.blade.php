<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo documento recibido - Nortia</title>
    <style>
        body {
            font-family: 'Outfit', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f7f6f0;
            margin: 0;
            padding: 40px 0;
            color: #1b190d;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2dfd2;
        }
        .header {
            background-color: #9c8d45; /* Oro Corporativo */
            color: #1b190d; /* Negro Corporativo para contraste */
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid #787873; /* Gris de Contraste */
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #1b190d;
            font-weight: 600;
        }
        .body {
            padding: 40px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1b190d;
        }
        .intro {
            font-size: 14px;
            color: #4a4a40;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .details-box {
            background-color: #fcfbf7;
            border-left: 4px solid #9c8d45;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
            border-top: 1px solid #f0edf0;
            border-right: 1px solid #f0edf0;
            border-bottom: 1px solid #f0edf0;
        }
        .details-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .details-list li {
            font-size: 14px;
            line-height: 1.8;
            color: #4a4a40;
        }
        .details-list li strong {
            color: #1b190d;
        }
        .action-button {
            display: block;
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            background-color: #787873;
            color: #ffffff !important;
            padding: 12px 28px;
            text-decoration: none;
            font-weight: 600;
            border-radius: 6px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn:hover {
            background-color: #9c8d45;
            color: #1b190d !important;
        }
        .footer {
            background-color: #787873;
            padding: 25px 40px;
            text-align: center;
            font-size: 11px;
            color: #ffffff;
            border-top: 1px solid #9c8d45;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Nortia</h1>
            <p>Ingesta Automática de Documentación</p>
        </div>

        <!-- Body -->
        <div class="body">
            <div class="greeting">Hola,</div>
            <div class="intro">
                Te informamos que se ha recibido y archivado un nuevo documento de forma **100% automatizada** en el sistema:
            </div>

            <!-- Detalles del Documento -->
            <div class="details-box">
                <ul class="details-list">
                    <li><strong>Cliente:</strong> {{ $clientName }}</li>
                    <li><strong>Archivo:</strong> {{ $document->nombre }}</li>
                    <li><strong>Vía de Entrada:</strong> {{ $channelName }}</li>
                    <li><strong>Expediente Asignado:</strong> {{ $document->expedient->titulo ?? 'General (Sin asignar a expediente específico)' }}</li>
                    <li><strong>Fecha de Ingesta:</strong> {{ $document->created_at->format('d/m/Y H:i:s') }}</li>
                </ul>
            </div>

            <!-- Botón de acción -->
            <div class="action-button">
                <a href="{{ url('/panel/dashboard') }}" class="btn">Ver en el Panel de Trabajo</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            {{ \App\Support\Despacho::datos()['nombre'] }}<br>
            {{ \App\Support\Despacho::lineaDeContacto() }}
        </div>
    </div>
</body>
</html>
