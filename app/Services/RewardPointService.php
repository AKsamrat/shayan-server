<?php

namespace App\Services;

use App\Models\User;
use App\Models\RewardPoint;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;

class RewardPointService
{
    // Default configuration values
    private const DEFAULT_POINTS_PER_DOLLAR = 1;
    private const DEFAULT_POINTS_EXPIRY_DAYS = 365; // 1 year
    private const DEFAULT_MIN_ORDER_FOR_POINTS = 1; // Minimum order amount to earn points
    private const DEFAULT_MAX_POINTS_PER_ORDER = 10000; // Maximum points per order
    private const DEFAULT_REDEMPTION_RATE = 100; // 100 points = $1 discount

    /**
     * Get points earning rate from settings.
     */
    public function getPointsRate(): int
    {
        return (int) Setting::getValue('reward_points_rate', self::DEFAULT_POINTS_PER_DOLLAR);
    }

    /**
     * Get points expiry days from settings.
     */
    public function getPointsExpiryDays(): int
    {
        return (int) Setting::getValue('reward_points_expiry_days', self::DEFAULT_POINTS_EXPIRY_DAYS);
    }

    /**
     * Get minimum order amount to earn points.
     */
    public function getMinOrderForPoints(): float
    {
        return (float) Setting::getValue('reward_points_min_order', self::DEFAULT_MIN_ORDER_FOR_POINTS);
    }

    /**
     * Get maximum points per order.
     */
    public function getMaxPointsPerOrder(): int
    {
        return (int) Setting::getValue('reward_points_max_per_order', self::DEFAULT_MAX_POINTS_PER_ORDER);
    }

    /**
     * Get redemption rate (how many points = $1).
     */
    public function getRedemptionRate(): int
    {
        return (int) Setting::getValue('reward_points_redemption_rate', self::DEFAULT_REDEMPTION_RATE);
    }

    /**
     * Get all reward points settings.
     */
    public function getSettings(): array
    {
        return [
            'points_rate' => $this->getPointsRate(),
            'expiry_days' => $this->getPointsExpiryDays(),
            'min_order' => $this->getMinOrderForPoints(),
            'max_per_order' => $this->getMaxPointsPerOrder(),
            'redemption_rate' => $this->getRedemptionRate(),
        ];
    }

    /**
     * Update reward points settings.
     */
    public function updateSettings(array $data): array
    {
        $validated = [
            'points_rate' => $data['points_rate'] ?? self::DEFAULT_POINTS_PER_DOLLAR,
            'expiry_days' => $data['expiry_days'] ?? self::DEFAULT_POINTS_EXPIRY_DAYS,
            'min_order' => $data['min_order'] ?? self::DEFAULT_MIN_ORDER_FOR_POINTS,
            'max_per_order' => $data['max_per_order'] ?? self::DEFAULT_MAX_POINTS_PER_ORDER,
            'redemption_rate' => $data['redemption_rate'] ?? self::DEFAULT_REDEMPTION_RATE,
        ];

        Setting::setValue('reward_points_rate', $validated['points_rate'], 'reward_points', 'integer');
        Setting::setValue('reward_points_expiry_days', $validated['expiry_days'], 'reward_points', 'integer');
        Setting::setValue('reward_points_min_order', $validated['min_order'], 'reward_points', 'integer');
        Setting::setValue('reward_points_max_per_order', $validated['max_per_order'], 'reward_points', 'integer');
        Setting::setValue('reward_points_redemption_rate', $validated['redemption_rate'], 'reward_points', 'integer');

        return $this->getSettings();
    }

    /**
     * Calculate points earned from an order.
     */
    public function calculateEarnedPoints(Order $order): int
    {
        $rate = $this->getPointsRate();
        $minOrder = $this->getMinOrderForPoints();
        $maxPerOrder = $this->getMaxPointsPerOrder();

        // Only calculate points for paid orders
        if ($order->payment_status !== 'paid') {
            return 0;
        }

        // Check if order meets minimum
        if ($order->total < $minOrder) {
            return 0;
        }

        // Calculate points based on order subtotal (excluding shipping, tax, etc.)
        // Using the base total before discounts
        $orderAmount = $order->subtotal ?? $order->total;
        $points = (int) floor($orderAmount * $rate);

        // Apply maximum cap
        return min($points, $maxPerOrder);
    }

    /**
     * Award points to a user for an order.
     */
    public function awardPoints(User $user, Order $order, string $description = null): RewardPoint
    {
        $points = $this->calculateEarnedPoints($order);

        if ($points <= 0) {
            throw new \RuntimeException('No points to award - order does not qualify');
        }

        $expiryDate = $this->getPointsExpiryDays() > 0 
            ? Carbon::now()->addDays($this->getPointsExpiryDays())
            : null;

        $desc = $description ?? "Earned {$points} points from order #{$order->order_number}";

        $rewardPoint = RewardPoint::create([
            'user_id' => $user->id,
            'points' => $points,
            'type' => 'earned',
            'description' => $desc,
            'expires_at' => $expiryDate,
        ]);

        // Update user's balance cache
        $this->updateUserBalance($user);

        // Send notification
        app(NotificationService::class)->pointsEarned($user, $points, $desc);

        return $rewardPoint;
    }

    /**
     * Redeem points for a discount.
     */
    public function redeemPoints(User $user, int $points, string $description = null): RewardPoint
    {
        $balance = $this->getUserBalance($user);

        if ($points <= 0) {
            throw new \RuntimeException('Redemption amount must be positive');
        }

        if ($points > $balance) {
            throw new \RuntimeException('Insufficient points balance');
        }

        $discount = $this->getDiscountAmount($points);
        $desc = $description ?? "Redeemed {$points} points for discount";

        $rewardPoint = RewardPoint::create([
            'user_id' => $user->id,
            'points' => $points,
            'type' => 'redeemed',
            'description' => $desc,
            'expires_at' => null,
        ]);

        // Add the discount amount to user's wallet
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => 'BDT']
        );
        
        $wallet->increment('balance', $discount);
        
        // Create wallet transaction record
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $discount,
            'description' => "Redeemed {$points} points - {$desc}"
        ]);

        // Update user's balance cache
        $this->updateUserBalance($user);

        // Send notification
        app(NotificationService::class)->pointsRedeemed($user, $points, $discount);

        return $rewardPoint;
    }

    /**
     * Get the discount amount for a given number of points.
     */
    public function getDiscountAmount(int $points): float
    {
        $rate = $this->getRedemptionRate();
        if ($rate <= 0) {
            return 0;
        }
        return (float) ($points / $rate);
    }

    /**
     * Get points required for a discount amount.
     */
    public function getPointsForDiscount(float $amount): int
    {
        $rate = $this->getRedemptionRate();
        if ($rate <= 0) {
            return 0;
        }
        return (int) ceil($amount * $rate);
    }

    /**
     * Get user's current points balance.
     */
    public function getUserBalance(User $user): int
    {
        $earned = RewardPoint::where('user_id', $user->id)
            ->where('type', 'earned')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->sum('points');

        $redeemed = RewardPoint::where('user_id', $user->id)
            ->where('type', 'redeemed')
            ->sum('points');

        return max(0, (int) ($earned - $redeemed));
    }

    /**
     * Update user's reward points balance cache.
     */
    public function updateUserBalance(User $user): void
    {
        $balance = $this->getUserBalance($user);
        $user->update(['reward_points_balance' => $balance]);
    }

    /**
     * Get user's reward points history.
     */
    public function getUserHistory(User $user, int $perPage = 15): \Illuminate\Contracts\Pagination\PaginationResult
    {
        $query = RewardPoint::where('user_id', $user->id)
            ->latest();

        return $query->paginate($perPage);
    }

    /**
     * Get all reward points with filters for admin.
     */
    public function getAllRewardPoints(array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = RewardPoint::query();

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['expired']) && $filters['expired'] === true) {
            $query->where('type', 'earned')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now());
        }

        if (isset($filters['expired']) && $filters['expired'] === false) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
        }

        if (isset($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->with('user')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Manually adjust a user's points balance.
     */
    public function adjustBalance(User $user, int $points, string $type, string $description): RewardPoint
    {
        if ($type !== 'earned' && $type !== 'redeemed') {
            throw new \InvalidArgumentException('Type must be either earned or redeemed');
        }

        $expiryDate = $type === 'earned' && $this->getPointsExpiryDays() > 0
            ? Carbon::now()->addDays($this->getPointsExpiryDays())
            : null;

        $rewardPoint = RewardPoint::create([
            'user_id' => $user->id,
            'points' => abs($points),
            'type' => $type,
            'description' => $description,
            'expires_at' => $expiryDate,
        ]);

        $this->updateUserBalance($user);

        return $rewardPoint;
    }

    /**
     * Expire old points for all users or a specific user.
     */
    public function expireOldPoints(?User $user = null): int
    {
        $query = RewardPoint::where('type', 'earned')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now());

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $count = $query->count();
        $query->update(['expires_at' => Carbon::now()]);

        // Update affected users' balances
        if ($user) {
            $this->updateUserBalance($user);
        } else {
            // Update all users who had expired points
            $userIds = RewardPoint::where('type', 'earned')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now())
                ->distinct('user_id')
                ->pluck('user_id');
            
            foreach (User::whereIn('id', $userIds)->get() as $u) {
                $this->updateUserBalance($u);
            }
        }

        return $count;
    }

    /**
     * Get reward points statistics for admin dashboard.
     */
    public function getStats(): array
    {
        $totalEarned = RewardPoint::where('type', 'earned')->sum('points');
        $totalRedeemed = RewardPoint::where('type', 'redeemed')->sum('points');
        $totalPending = $this->countActivePoints();
        $totalUsersWithPoints = RewardPoint::distinct('user_id')->count('user_id');

        return [
            'total_earned' => $totalEarned,
            'total_redeemed' => $totalRedeemed,
            'total_pending' => $totalPending,
            'total_users_with_points' => $totalUsersWithPoints,
            'active_points_value' => $this->getDiscountAmount($totalPending),
        ];
    }

    /**
     * Count all active (non-expired, non-redeemed) points.
     */
    public function countActivePoints(): int
    {
        $earned = RewardPoint::where('type', 'earned')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->sum('points');

        $redeemed = RewardPoint::where('type', 'redeemed')->sum('points');

        return max(0, (int) ($earned - $redeemed));
    }

    /**
     * Check if points can be redeemed for a given cart amount.
     */
    public function canRedeem(User $user, float $cartAmount, int $pointsToRedeem): array
    {
        $balance = $this->getUserBalance($user);
        $maxRedeemable = $this->getPointsForDiscount($cartAmount);
        $maxByBalance = min($balance, $maxRedeemable);

        $canRedeem = $pointsToRedeem > 0 && $pointsToRedeem <= $maxByBalance;

        return [
            'can_redeem' => $canRedeem,
            'balance' => $balance,
            'max_redeemable' => $maxRedeemable,
            'max_by_balance' => $maxByBalance,
            'discount_amount' => $canRedeem ? $this->getDiscountAmount($pointsToRedeem) : 0,
        ];
    }
}
