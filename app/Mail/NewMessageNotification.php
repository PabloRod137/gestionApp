<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notificación por correo electrónico de nuevo mensaje.
 */
class NewMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Message $mensaje;
    public User $remitente;
    public string $nombreExpediente;

    /**
     * Crea una nueva instancia de la notificación.
     */
    public function __construct(Message $mensaje)
    {
        $this->mensaje = $mensaje;
        $this->remitente = $mensaje->sender;
        $this->nombreExpediente = $mensaje->expedient->titulo ?? 'Expediente sin título';
    }

    /**
     * Define el sobre del correo (asunto y remitente).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nortia: Nuevo mensaje en el expediente #' . $this->mensaje->expedient_id,
        );
    }

    /**
     * Define la vista de contenido del correo.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new_message',
        );
    }

    /**
     * Retorna los adjuntos del correo.
     */
    public function attachments(): array
    {
        return [];
    }
}
