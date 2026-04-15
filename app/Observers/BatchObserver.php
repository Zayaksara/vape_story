<?php

namespace App\Observers;

use App\Models\Batch;
use App\Models\StockMutation;
use App\Enums\MutationType;
use App\Models\ReturnItem;
use App\Models\ProductReturn;



class BatchObserver
{
    public function updated(Batch $batch)
    {
        if ($batch->isDirty('stock_quantity')){
            $oldQuantity = $batch->getOriginal('stock_quantity');
            $newQuantity = $batch->stock_quantity;
            $difference = $newQuantity - $oldQuantity;

            $mutationType = $difference > 0 ? MutationType::RESTOCK : MutationType::SALE;

            StockMutation::create([
                'batch_id' => $batch->id,
                'mutation_type' => $mutationType,
                'quantity' => $difference,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Automatic stock mutation from batch update',
            ]);
        }
    }

    public function created(Batch $batch)
    {
        if ($batch->stock_quantity > 0) {
            StockMutation::create([
                'batch_id' => $batch->id,
                'mutation_type' => MutationType::RESTOCK,
                'quantity' => $batch->stock_quantity,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Initial stock mutation from batch creation',
            ]);
        }
    }
}