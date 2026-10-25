<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $note = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your order '.$this->order->order_number.' is '.strtolower($this->order->status_label));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-status');
    }
}
