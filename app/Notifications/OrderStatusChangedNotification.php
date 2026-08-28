<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $statusLabels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Being Processed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
        'refunded' => 'Refunded',
    ];

    private array $statusColors = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'error',
        'returned' => 'error',
        'refunded' => 'error',
    ];

    public function __construct(
        private Order $order,
        private ?string $oldStatus = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusLabel = $this->statusLabels[$this->order->status] ?? ucfirst($this->order->status);
        $color = $this->statusColors[$this->order->status] ?? 'info';

        $mail = (new MailMessage)
            ->subject("Order {$statusLabel} — #{$this->order->order_number}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your order **#{$this->order->order_number}** status has been updated.")
            ->line("**New Status:** {$statusLabel}")
            ->line("**Order Total:** ৳" . number_format($this->order->total, 2));

        if ($this->oldStatus) {
            $oldLabel = $this->statusLabels[$this->oldStatus] ?? ucfirst($this->oldStatus);
            $mail->line("**Previous Status:** {$oldLabel}");
        }

        // Add status-specific messages
        $mail->line(match ($this->order->status) {
            'confirmed' => 'Your order has been confirmed and will be processed soon.',
            'processing' => 'Your order is now being prepared for shipment.',
            'shipped' => 'Great news! Your order has been shipped.' . ($this->order->tracking_number ? " Tracking: **{$this->order->tracking_number}**" : ''),
            'delivered' => 'Your order has been delivered successfully. We hope you enjoy your purchase!',
            'cancelled' => 'Your order has been cancelled. If this was a mistake, please contact support.',
            default => '',
        });

        $mail->action('View Order', url("/account/orders/{$this->order->id}"))
            ->line('Thank you for shopping with Shayan Mart!');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->order->status,
        ];
    }
}
