<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\ShippingUpdateNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send an in-app notification (always stored in DB).
     */
    public function sendInApp(User $user, string $title, string $message, string $type = 'info', ?array $data = null): void
    {
        $user->notifications_model()->create([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Send email notification if user has email notifications enabled.
     */
    public function sendEmail(User $user, string $type, array $data): void
    {
        if (!$user->email_notifications) {
            Log::info("Email skipped for {$user->email} — email_notifications disabled");
            return;
        }

        // Check granular preference
        $granularKey = $this->getGranularEmailKey($type);
        if ($granularKey && !$user->{$granularKey}) {
            Log::info("Email skipped for {$user->email} — {$granularKey} disabled");
            return;
        }

        try {
            match ($type) {
                'order_placed' => $user->notify(new OrderPlacedNotification($data['order'])),
                'order_status_changed' => $user->notify(new OrderStatusChangedNotification($data['order'], $data['old_status'] ?? null)),
                'shipping_update' => $user->notify(new ShippingUpdateNotification($data['order'])),
                default => Log::warning("Unknown email notification type: {$type}"),
            };
        } catch (\Throwable $e) {
            Log::error("Failed to send email to {$user->email}: {$e->getMessage()}");
        }
    }

    /**
     * Send SMS notification if user has SMS notifications enabled.
     * Uses the configured SMS service (currently logs to file in development).
     */
    public function sendSms(User $user, string $type, array $data): void
    {
        if (!$user->sms_notifications) {
            Log::info("SMS skipped for {$user->phone} — sms_notifications disabled");
            return;
        }

        // Check granular preference
        $granularKey = $this->getGranularSmsKey($type);
        if ($granularKey && !$user->{$granularKey}) {
            Log::info("SMS skipped for {$user->phone} — {$granularKey} disabled");
            return;
        }

        if (!$user->phone) {
            Log::info("SMS skipped — no phone number for user {$user->id}");
            return;
        }

        $message = $this->buildSmsMessage($type, $data);

        // Send via configured SMS service
        $this->dispatchSms($user->phone, $message);
    }

    /**
     * Send all notification channels (in-app + email + SMS) for an event.
     */
    public function notify(User $user, string $type, string $title, string $message, array $data = []): void
    {
        // Always store in-app notification
        $this->sendInApp($user, $title, $message, $this->getInAppType($type), $data);

        // Send email if enabled
        $this->sendEmail($user, $type, $data);

        // Send SMS if enabled
        $this->sendSms($user, $type, $data);
    }

    // ==================== REWARD POINTS NOTIFICATIONS ====================

    /**
     * Notify user when they earn reward points.
     */
    public function pointsEarned(User $user, int $points, string $description): void
    {
        $this->notify(
            $user,
            'points_earned',
            'Points Earned!',
            "You earned {$points} reward points. {$description}",
            ['points' => $points, 'description' => $description]
        );
    }

    /**
     * Notify user when they redeem points.
     */
    public function pointsRedeemed(User $user, int $points, float $discount): void
    {
        $this->notify(
            $user,
            'points_redeemed',
            'Points Redeemed',
            "You redeemed {$points} points for a discount of  Brennan  {$discount}",
            ['points' => $points, 'discount' => $discount]
        );
    }

    // ==================== ORDER NOTIFICATIONS ====================

    /**
     * Notify customer when an order is placed.
     */
    public function orderPlaced(Order $order): void
    {
        $user = $order->user;
        if (!$user) return;

        $this->notify(
            $user,
            'order_placed',
            'Order Placed Successfully',
            "Your order #{$order->order_number} has been placed successfully. Total: " . number_format($order->total, 2),
            ['order' => $order]
        );
    }

    /**
     * Notify customer when order status changes.
     */
    public function orderStatusChanged(Order $order, ?string $oldStatus = null): void
    {
        $user = $order->user;
        if (!$user) return;

        $statusLabels = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            'refunded' => 'Refunded',
        ];

        $statusLabel = $statusLabels[$order->status] ?? ucfirst($order->status);

        $type = match ($order->status) {
            'shipped' => 'shipping_update',
            default => 'order_status_changed',
        };

        $this->notify(
            $user,
            $type,
            "Order {$statusLabel}",
            "Your order #{$order->order_number} has been {$statusLabel}.",
            ['order' => $order, 'old_status' => $oldStatus]
        );
    }

    // ==================== SUPPORT TICKET NOTIFICATIONS ====================

    /**
     * Notify admins when a new support ticket is created by a customer.
     */
    public function supportTicketCreated(SupportTicket $ticket): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        foreach ($admins as $admin) {
            $this->notify(
                $admin,
                'support_ticket_created',
                'New Support Ticket',
                "A new support ticket #{$ticket->ticket_number} has been created by {$ticket->user->name}. Subject: {$ticket->subject}",
                ['ticket' => $ticket]
            );
        }
    }

    /**
     * Notify admins when a customer replies to a support ticket.
     */
    public function supportTicketRepliedByCustomer(SupportTicket $ticket, string $replyMessage): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        foreach ($admins as $admin) {
            $this->notify(
                $admin,
                'support_ticket_replied',
                'New Reply to Support Ticket',
                "Customer {$ticket->user->name} replied to ticket #{$ticket->ticket_number}: {$replyMessage}",
                ['ticket' => $ticket, 'reply_message' => $replyMessage]
            );
        }
    }

    /**
     * Notify customer when an admin replies to their support ticket.
     */
    public function supportTicketRepliedByAdmin(SupportTicket $ticket, string $replyMessage): void
    {
        $customer = $ticket->user;
        if (!$customer) return;

        $this->notify(
            $customer,
            'support_ticket_replied',
            'Support Ticket Reply',
            "We have replied to your support ticket #{$ticket->ticket_number}. Subject: {$ticket->subject}",
            ['ticket' => $ticket, 'reply_message' => $replyMessage]
        );
    }

    /**
     * Notify customer when their support ticket status is updated.
     */
    public function supportTicketStatusUpdated(SupportTicket $ticket, string $oldStatus): void
    {
        $customer = $ticket->user;
        if (!$customer) return;

        $this->notify(
            $customer,
            'support_ticket_status_changed',
            'Support Ticket Status Updated',
            "Your support ticket #{$ticket->ticket_number} status has been updated from {$oldStatus} to {$ticket->status}",
            ['ticket' => $ticket, 'old_status' => $oldStatus]
        );
    }

    // ==================== HELPERS ====================

    private function getGranularEmailKey(string $type): ?string
    {
        return match ($type) {
            'order_placed', 'order_status_changed' => 'email_order_updates',
            'shipping_update' => 'email_shipping_updates',
            'promotion' => 'email_promotions',
            'support_ticket_created', 'support_ticket_replied', 'support_ticket_status_changed' => 'email_support_updates',
            default => null,
        };
    }

    private function getGranularSmsKey(string $type): ?string
    {
        return match ($type) {
            'order_placed', 'order_status_changed' => 'sms_order_updates',
            'shipping_update' => 'sms_shipping_updates',
            'promotion' => 'sms_promotions',
            'support_ticket_created', 'support_ticket_replied', 'support_ticket_status_changed' => 'sms_support_updates',
            default => null,
        };
    }

    private function getInAppType(string $type): string
    {
        return match ($type) {
            'order_placed', 'order_status_changed', 'shipping_update' => 'success',
            'promotion' => 'info',
            'support_ticket_created', 'support_ticket_replied', 'support_ticket_status_changed' => 'info',
            default => 'info',
        };
    }

    private function buildSmsMessage(string $type, array $data): string
    {
        $order = $data['order'] ?? null;

        return match ($type) {
            'order_placed' => "Shayan Mart: Your order #{$order->order_number} is confirmed. Total: " . number_format($order->total, 2),
            'order_status_changed' => "Shayan Mart: Your order #{$order->order_number} is now " . ucfirst($order->status) . ".",
            'shipping_update' => "Shayan Mart: Your order #{$order->order_number} has been shipped." . ($order->tracking_number ? " Tracking: {$order->tracking_number}" : ''),
            default => "Shayan Mart: You have a new notification.",
        };
    }

    /**
     * Dispatch SMS via the configured service.
     * In development, this logs the SMS. In production, integrate with
     * Twilio, Vonage, Banglalink, etc.
     */
    private function dispatchSms(string $phone, string $message): void
    {
        // Log SMS for development — replace with actual SMS provider in production
        Log::info("SMS sent to {$phone}: {$message}");

        // TODO: Integrate with SMS provider. Example:
        // \SMS::send($phone, $message);
        // or
        // Http::post('https://api.smsprovider.com/send', [
        //     'to' => $phone,
        //     'message' => $message,
        //     'api_key' => config('services.sms.api_key'),
        // ]);
    }
}
