<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'lot_number',
        'expired_date',
        'stock_quantity',
        'cost_price'
    ];

    protected $casts = [
        'expired_date' => 'date',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    // relasi ke produk yang dimiliki batch inix
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // relasi untuk mencatat item pesanan yang menggunakan batch ini
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // relasi untuk mencatat mutasi stok (penambahan atau pengurangan)
    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }
    
    // scope untuk mengurutkan batch berdasarkan tanggal expired terdekat
    public function scopeOldestExpiry($query)
    {
        return $query->orderBy('expired_date', 'asc');
    }

    // scope untuk mencari batch yang masih tersedia (stock > 0)
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    // check apakah patch sudah expired
    public function isExpired()
    {
        return $this->expired_date->isPast();
    }

    // check apakah batch akan expired dalam 30 hari ke depan 
    public function isNearExpiry($days = 30)
    {
        return $this->expired_date->diffInDays(now()) <= $days;
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function scopeAvailableForProduct($query, $productId)
    {
        return $query->where('product_id', $productId)
                     ->where('stock_quantity', '>', 0)
                     ->where('expired_date', '>', now());
    }
}