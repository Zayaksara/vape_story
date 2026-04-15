<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;   // ← ini yang penting

class ProductReturn extends Model
{
    use HasFactory, HasUuids;   // ← pakai HasUuids trait

    protected $table = 'returns';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'return_number',
        'order_id',
        'cashier_id',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'status'      => ReturnStatus::class,
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', ReturnStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ReturnStatus::APPROVED);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', ReturnStatus::PROCESSED);
    }
}