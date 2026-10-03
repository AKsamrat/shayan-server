<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookPixelEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'event_data',
        'source_url',
        'user_id',
        'ip_address',
        'user_agent',
        'fbp',
        'fbc'
    ];

    protected $casts = [
        'event_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
