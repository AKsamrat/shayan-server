<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'role_id',
        'is_verified',
        'is_active',
        'two_factor_enabled',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'email_order_updates',
        'email_shipping_updates',
        'email_promotions',
        'email_newsletter',
        'sms_order_updates',
        'sms_shipping_updates',
        'sms_promotions',
        'reward_points_balance',
        'referrer_id',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referrer_id');
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'email_order_updates' => 'boolean',
            'email_shipping_updates' => 'boolean',
            'email_promotions' => 'boolean',
            'email_newsletter' => 'boolean',
            'sms_order_updates' => 'boolean',
            'sms_shipping_updates' => 'boolean',
            'sms_promotions' => 'boolean',
            'reward_points_balance' => 'integer',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function notifications_model()
    {
        return $this->hasMany(Notification::class);
    }
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function rewardPoints()
    {
        return $this->hasMany(RewardPoint::class);
    }

    public function vendorShop()
    {
        return $this->hasOne(VendorShop::class);
    }

    public function isVendor(): bool
    {
        return $this->role === 'vendor';
    }
}
