<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Solicitud de cita recibida desde la web: un aviso para el despacho y otro, más breve, para quien la pide. */
class ReservationReceived extends Mailable
{
    use SerializesModels;

    public function __construct(public Appointment $appointment, public bool $forOffice)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->forOffice ? 'Nueva solicitud de cita - Nortia' : 'Hemos recibido tu solicitud de cita - Nortia');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservation_received');
    }
}
