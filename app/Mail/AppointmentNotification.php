<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $type;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, string $type)
    {
        $this->appointment = $appointment;
        $this->type = $type;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'created' => 'Nueva solicitud de cita previa - Nortia',
            'confirmed' => 'Confirmación de tu cita previa - Nortia',
            'updated' => 'Modificación de tu cita previa - Nortia',
            'cancelled' => 'Cancelación de tu cita previa - Nortia',
            default => 'Notificación de cita previa - Nortia',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointments.notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
