<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopUp extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_CHALLENGE = 'challenge';

    protected $fillable = [
        'user_id',
        'order_id',
        'amount',
        'payment_method',
        'payment_type',
        'status',
        'snap_token',
        'redirect_url',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'status_text',
        'formatted_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case self::STATUS_SUCCESS: return 'Success';
            case self::STATUS_FAILED: return 'Failed';
            case self::STATUS_EXPIRED: return 'Expired';
            case self::STATUS_CANCELLED: return 'Cancelled';
            case self::STATUS_CHALLENGE: return 'Challenge';
            default: return 'Pending';
        }
    }

    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function isSuccessful()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }
}