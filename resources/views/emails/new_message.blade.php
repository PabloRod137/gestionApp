<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo mensaje recibido - Nortia</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 40px 0;
            color: #1f2937;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #9c8d45; /* Oro Corporativo */
            color: #1b190d; /* Negro Corporativo para contraste */
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #1b190d;
            font-weight: 600;
        }
        .body {
            padding: 40px;
        }
        .greeting {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .intro {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .message-box {
            background-color: #f9fafb;
            border-left: 4px solid #9c8d45;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 30px;
        }
        .message-box p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #374151;
            font-style: italic;
        }
        .action-button {
            display: block;
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            background-color: #787873;
            color: #ffffff !important;
            padding: 14px 28px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
        }
        .btn:hover {
            background-color: #9c8d45;
            color: #1b190d !important;
        }
        .footer {
            background-color: #787873;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
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
            <p>Notificaciones de Expediente</p>
        </div>

        <!-- Body -->
        <div class="body">
            <div class="greeting">Estimado usuario,</div>
            <div class="intro">
                Le informamos que ha recibido un nuevo mensaje de <strong>{{ $remitente->name }}</strong> relacionado con el expediente:
                <br>
                <strong style="color: #1b190d;">{{ $nombreExpediente }}</strong>.
            </div>

            <!-- Contenido del Mensaje -->
            <div class="message-box">
                <p>"{!! nl2br(e($mensaje->contenido)) !!}"</p>
            </div>

            <!-- Botón de acción -->
            <div class="action-button">
                <a href="{{ url('/dashboard') }}" class="btn">Acceder al Portal</a>
            </div>
            
            <div class="intro" style="font-size: 12px;">
                Si el botón no funciona, copie y pegue la siguiente dirección en su navegador web:
                <br>
                <a href="{{ url('/dashboard') }}" style="color: #9c8d45; font-weight: 600;">{{ url('/dashboard') }}</a>
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
