<?php

namespace App\Models;

use App\Enums\MutationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'batch_id',
        'mutation_type',
        'quantity',
        'reference_type',
        'reference_id',
        'notes'
    ];

    protected $casts = [
        'mutation_type' => MutationType::class,
        'quantity' => 'integer',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function scopeOut($query)
    {
        return $query->where('mutation_type', MutationType::OUT);
    }

    public function scopeIn($query)
    {
        return $query->where('mutation_type', MutationType::IN);
    }
}
