<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'brand_id',           // ← BARU
        'base_price',
        'nicotine_strength',
        'flavor',
        'size_ml',
        'battery_mah',
        'coil_type',
        'pod_type',
        'resistance_ohm',
        'description',
        'image',              // ← BARU
        'is_active',
    ];

    protected $casts = [
        'base_price'        => 'decimal:2',
        'nicotine_strength' => 'decimal:2',
        'size_ml'           => 'decimal:2',
        'resistance_ohm'    => 'decimal:2',
        'is_active'         => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()                    // ← BARU
    {
        return $this->belongsTo(Brand::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    // ==================== SCOPES & HELPERS ====================
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query, $threshold = 20)
    {
        return $query->whereHas('batches', function ($q) use ($threshold) {
            $q->where('stock_quantity', '<=', $threshold);
        });
    }

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
        return $this->batches()->sum(\DB::raw('stock_quantity * cost_price'));
    }
}