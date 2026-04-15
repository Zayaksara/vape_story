<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;  
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'category_id',
        'base_price',
        'nicotine_strength',

       
        'flavor',
        'size_ml',

        // Device-specific
        'battery_mah',
        'coil_type',
        'brand',
        'device_type',

        // Pod-specific
        'pod_type',
        'resistance_ohm',

        // General
        'description',
        'is_active',
        'expired_date',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'nicotine_strength' => 'decimal:2',
        'flavor' => 'string',
        'size_ml' => 'decimal:2',
        'battery_mah' => 'integer',
        'coil_type' => 'string',
        'brand' => 'string',
        'device_type' => 'string',
        'pod_type' => 'string',
        'resistance_ohm' => 'decimal:2',
        'is_active' => 'boolean',
        'expired_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // mencari produk dengan stok rendah (misalnya <= 20)
    public function scopeLowStock($query, $threshold = 20)
    {
        return $query->whereHas('batches', function ($q) use ($threshold) {
            $q->where('stock_quantity', '<=', $threshold);
        });
    }

    // mencari produk yang akan segera kedaluwarsa (misalnya dalam 30 hari)
    public function scopeNearExpiry($query, $days = 30)
    {
        return $query->whereHas('batches', function ($q) use ($days) {
            $q->where('expired_date', '<=', now()->addDays($days));
        });
    }


    public function totalStock()
    {
        return $this->batches()->sum('stock_quantity');
    }

    public function stockValue()
    {
        return $this->batches()->sum(DB::raw('stock_quantity * cost_price'));
    }
}
