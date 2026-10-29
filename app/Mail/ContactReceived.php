<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al despacho de un mensaje enviado desde el formulario de contacto. */
class ContactReceived extends Mailable
{
    use SerializesModels;

    public function __construct(public ContactRequest $contact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuevo mensaje de contacto - Nortia', replyTo: [$this->contact->email]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact_received');
    }
}
