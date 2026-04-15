<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'invoice_number',
        'cashier_id',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'idempotency_key',
        'notes'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'status' => OrderStatus::class,
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    // Relasi ke ProductReturn (jika ada)
    public function productReturns()
    {
        return $this->hasOne(ProductReturn::class, 'order_id');
    }

    // Helper method untuk cek apakah order ini memiliki return
    public function hasReturn(): bool
    {
        return $this->productReturns()->exists();
    }

}