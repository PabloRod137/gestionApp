<p>Nuevo mensaje recibido desde la web.</p>
<ul>
    <li><strong>Nombre:</strong> {{ $contact->nombre }}</li>
    <li><strong>Correo:</strong> {{ $contact->email }}</li>
    <li><strong>Teléfono:</strong> {{ $contact->telefono ?: '—' }}</li>
    <li><strong>Asunto:</strong> {{ $contact->servicio_interes }}</li>
</ul>
<p style="white-space: pre-line">{{ $contact->mensaje }}</p>
