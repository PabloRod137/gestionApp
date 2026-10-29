@php
    $day = \Carbon\Carbon::parse($appointment->fecha)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    $mode = $appointment->modalidad === 'presencial' ? 'Presencial en local' : 'Telemática por Teams';
@endphp
@if ($forOffice)
<p>Nueva solicitud de cita recibida desde la web.</p>
@else
<p>Hola {{ $appointment->guest_name }}, hemos recibido tu solicitud de cita. El despacho confirmará la disponibilidad y los siguientes pasos.</p>
@endif
<ul>
    <li><strong>Servicio:</strong> {{ $appointment->service->nombre }} ({{ $mode }})</li>
    <li><strong>Día y hora:</strong> {{ $day }}, {{ substr($appointment->hora, 0, 5) }}</li>
    @if ($forOffice)
        <li><strong>Nombre:</strong> {{ $appointment->guest_name }}</li>
        <li><strong>Correo:</strong> {{ $appointment->guest_email ?: '—' }}</li>
        <li><strong>Teléfono:</strong> {{ $appointment->guest_phone ?: '—' }}</li>
        <li><strong>Pagada:</strong> {{ $appointment->order_id ? 'Sí (pedido '.$appointment->order_id.')' : 'No' }}</li>
        @if ($appointment->notas)<li><strong>Notas:</strong> {{ $appointment->notas }}</li>@endif
    @endif
</ul>
