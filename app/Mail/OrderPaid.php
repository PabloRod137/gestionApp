<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Pedido pagado: al comprador con sus enlaces de reserva y al despacho con el resumen. */
class OrderPaid extends Mailable
{
    use SerializesModels;

    /** @param array<int, array{name: string, url: string}> $links */
    public function __construct(public Order $order, public array $links, public bool $forOffice)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->forOffice ? 'Nuevo pedido pagado '.$this->order->id : 'Gracias por tu compra - Nortia');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_paid');
    }
}
