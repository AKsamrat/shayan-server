<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
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
        $items = $this->order->items->map(function ($item) {
            return "• {$item->product->name} × {$item->quantity} — ৳" . number_format($item->total, 2);
        })->implode("\n");

        return (new MailMessage)
            ->subject("Order Confirmed — #{$this->order->order_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your order **#{$this->order->order_number}** has been placed successfully.")
            ->line("**Order Total:** ৳" . number_format($this->order->total, 2))
            ->line("**Payment Method:** " . ucfirst(str_replace('_', ' ', $this->order->payment_method)))
            ->line("**Items:**")
            ->line($items)
            ->action('View Order', url("/account/orders/{$this->order->id}"))
            ->line('Thank you for shopping with Shayan Mart!')
            ->line('If you have any questions, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total' => $this->order->total,
        ];
    }
}
