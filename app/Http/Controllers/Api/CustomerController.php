<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Notification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\RewardPoint;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    use ApiResponse;

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $recentOrders = Order::where('user_id', $user->id)
            ->with('items')
            ->latest()
            ->limit(5)
            ->get();

        $totalOrders = Order::where('user_id', $user->id)->count();
        $totalSpent = Order::where('user_id', $user->id)->where('payment_status', 'paid')->sum('total');
        $rewardPoints = RewardPoint::where('user_id', $user->id)->where('type', 'earned')->sum('points');
        $walletBalance = Wallet::where('user_id', $user->id)->first()?->balance ?? 0;

        return $this->success([
            'recent_orders' => $recentOrders,
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'reward_points' => $rewardPoints,
            'wallet_balance' => $walletBalance,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)->latest();
        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->where('id', $id)->update(['is_read' => true]);
        return $this->success(null, 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);
        return $this->success(null, 'All notifications marked as read');
    }

    public function wallet(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0, 'currency' => 'BDT']
        );

        return $this->success($wallet);
    }

    public function walletTransactions(Request $request): JsonResponse
    {
        $wallet = Wallet::where('user_id', $request->user()->id)->first();
        if (!$wallet) {
            return $this->success(['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0]);
        }

        $query = WalletTransaction::where('wallet_id', $wallet->id)->latest();
        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function rewardPoints(Request $request): JsonResponse
    {
        $points = RewardPoint::where('user_id', $request->user()->id)->latest()->get();
        return $this->success($points);
    }

    public function referrals(Request $request): JsonResponse
    {
        return $this->success([
            'link' => url('/register?ref=' . $request->user()->id),
            'total_referrals' => 0,
            'total_earned' => 0,
        ]);
    }

    public function redeemPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $service = app(RewardPointService::class);

        try {
            $rewardPoint = $service->redeemPoints(
                $user,
                $validated['points'],
                "Redeemed {$validated['points']} points for discount"
            );

            return $this->success([
                'reward_point' => $rewardPoint,
                'discount_amount' => $service->getDiscountAmount($validated['points']),
                'new_balance' => $service->getUserBalance($user),
            ], 'Points redeemed successfully', 201);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function checkRedemption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_amount' => 'required|numeric|min:0',
            'points' => 'required|integer|min:0',
        ]);

        $user = $request->user();
        $service = app(RewardPointService::class);

        $result = $service->canRedeem($user, $validated['cart_amount'], $validated['points']);

        return $this->success($result);
    }

    public function supportTickets(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['replies', 'replies.user'])
            ->withCount('replies')
            ->where('user_id', $request->user()->id)
            ->latest();
        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function createSupportTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'department' => 'required|string',
            'priority' => 'required|string|in:low,medium,high',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'department' => $validated['department'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        // Notify admins about the new ticket
        app(NotificationService::class)->supportTicketCreated($ticket->load('user'));

        return $this->success($ticket, 'Ticket created successfully', 201);
    }

    public function replyToTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin' => false,
        ]);

        // Notify admins about the customer's reply
        app(NotificationService::class)->supportTicketRepliedByCustomer($ticket->load('user'), $validated['message']);

        return $this->success($ticket->fresh(['replies', 'replies.user']), 'Reply sent');
    }
}
