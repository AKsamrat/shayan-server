<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShippingUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your Order Has Been Shipped — #{$this->order->order_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Great news! Your order **#{$this->order->order_number}** has been shipped.");

        if ($this->order->tracking_number) {
            $mail->line("**Tracking Number:** {$this->order->tracking_number}");
        }

        if ($this->order->shipping_method) {
            $mail->line("**Shipping Method:** {$this->order->shipping_method}");
        }

        if ($this->order->shippingAddress) {
            $addr = $this->order->shippingAddress;
            $mail->line("**Shipping to:** {$addr->address_line_1}, {$addr->city}, {$addr->state} {$addr->postal_code}");
        }

        $mail->action('Track Order', url("/account/orders/{$this->order->id}"))
            ->line('Thank you for shopping with Shayan Mart!');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'tracking_number' => $this->order->tracking_number,
        ];
    }
}
