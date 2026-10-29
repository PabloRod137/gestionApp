<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Notificación de Cita - Nortia</title>
    <style>
        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f6f6f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #9c8d45; /* Oro Corporativo */
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid #787873; /* Gris de Contraste */
        }
        .header h1 {
            color: #1b190d; /* Negro Corporativo para contraste */
            font-family: 'Playfair Display', Georgia, serif;
            margin: 0;
            font-size: 24px;
            letter-spacing: 1px;
        }
        .header h1 span {
            color: #1b190d;
        }
        .content {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.6;
        }
        .greeting {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #1b190d;
        }
        .status-box {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: bold;
            text-align: center;
        }
        .status-created {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        .status-confirmed {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .status-updated {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .status-cancelled {
            background-color: #fdf2f2;
            color: #dc2626;
            border: 1px solid #fde2e2;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .details-table td.label {
            font-weight: bold;
            color: #666666;
            width: 30%;
        }
        .details-table td.value {
            color: #1b190d;
        }
        .footer {
            background-color: #787873; /* Gris de Contraste */
            padding: 30px;
            text-align: center;
            font-size: 12px;
            color: #ffffff;
            border-top: 1px solid #9c8d45; /* Oro Corporativo */
        }
        .button {
            display: inline-block;
            background-color: #787873; /* Gris de Contraste */
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #9c8d45;
            color: #1b190d !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lex<span>Guardian</span></h1>
        </div>
        <div class="content">
            <div class="greeting">
                Hola, {{ $appointment->client ? ($appointment->client->user->name ?? $appointment->client->razon_social) : $appointment->guest_name }}.
            </div>
            
            @if($type === 'created')
                <p>Hemos recibido tu solicitud de cita previa correctamente. En estos momentos está pendiente de asignación y confirmación por parte de nuestro equipo de gestores y asesores.</p>
                <div class="status-box status-created">
                    Estado: PENDIENTE DE CONFIRMACIÓN
                </div>
            @elseif($type === 'confirmed')
                <p>¡Buenas noticias! Tu cita previa ha sido confirmada por nuestro equipo. A continuación encontrarás todos los detalles de la misma:</p>
                <div class="status-box status-confirmed">
                    Estado: CITA CONFIRMADA
                </div>
            @elseif($type === 'updated')
                <p>Te informamos de que se han realizado modificaciones en los detalles de tu cita previa. Por favor, revisa la nueva información programada:</p>
                <div class="status-box status-updated">
                    Estado: CITA MODIFICADA
                </div>
            @elseif($type === 'cancelled')
                <p>Te informamos de que tu cita previa ha sido cancelada. Si se trata de un error o deseas programar una nueva cita, puedes volver a solicitarla desde nuestra web.</p>
                <div class="status-box status-cancelled">
                    Estado: CITA CANCELADA
                </div>
            @endif

            <table class="details-table">
                <tr>
                    <td class="label">Servicio:</td>
                    <td class="value">{{ $appointment->service->nombre ?? 'Asesoría General' }}</td>
                </tr>
                <tr>
                    <td class="label">Fecha:</td>
                    <td class="value">{{ \Carbon\Carbon::parse($appointment->fecha)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Hora:</td>
                    <td class="value">{{ \Carbon\Carbon::parse($appointment->hora)->format('H:i') }} hs</td>
                </tr>
                @if($appointment->worker)
                    <tr>
                        <td class="label">Gestor/Asesor Asignado:</td>
                        <td class="value">{{ $appointment->worker->name }}</td>
                    </tr>
                @endif
                @if($appointment->notas)
                    <tr>
                        <td class="label">Notas / Consulta:</td>
                        <td class="value">{{ $appointment->notas }}</td>
                    </tr>
                @endif
            </table>

            @if($type !== 'cancelled' && $appointment->client)
                <div style="text-align: center;">
                    <a href="{{ route('login') }}" class="button">Acceder a Mi Portal</a>
                </div>
            @endif
        </div>
        <div class="footer">
            <p><strong>{{ \App\Support\Despacho::datos()['nombre'] }}</strong></p>
            <p>{{ \App\Support\Despacho::lineaDeContacto() }}</p>
            <p style="font-size: 10px; margin-top: 20px; color: #e5e5e0;">Este es un correo automático. Por favor, no respondas directamente a este mensaje.</p>
        </div>
    </div>
</body>
</html>
