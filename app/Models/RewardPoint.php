<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardPoint extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'points', 'type', 'description', 'expires_at'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
