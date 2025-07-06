<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyRequest extends Model
{
    use HasFactory;

    protected $table = 'emergency_requests';

    protected $fillable = [
        'user_id',
        'service_id',
        'service_name',
        'description',
        'amount',
        'status',
        'request_date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_date' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emergencyService()
    {
        return $this->belongsTo(Emergency::class, 'service_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'emergency_request_id');
    }
}