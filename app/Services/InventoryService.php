<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Enums\MutationType;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function restock(Product $product, array $data)
    {
        return DB::transaction(function () use ($product, $data) {
            $batch = Batch::create([
                'product_id'    => $product->id,
                'lot_number'    => $data['lot_number'],
                'expired_date'  => $data['expired_date'],
                'stock_quantity' => $data['quantity'],
                'cost_price'    => $data['cost_price'],
            ]);

            // Mutation IN sudah otomatis dari observer created
            return $batch;
        });
    }

    public function adjustStock(Batch $batch, int $quantity, ?string $notes = null)
    {
        return DB::transaction(function () use ($batch, $quantity, $notes) {
            $oldStock = $batch->stock_quantity;
            $newStock = $oldStock + $quantity; // quantity bisa negatif

            if ($newStock < 0) {
                throw new \Exception("Stok tidak boleh negatif");
            }

            $batch->update(['stock_quantity' => $newStock]);

            // Mutation sudah otomatis dari observer updated
            return $batch;
        });
    }

    public function getStockReport()
    {
        return Product::with(['batches' => fn($q) => $q->inStock()->oldestExpiry()])
            ->withSum('batches', 'stock_quantity')
            ->withSum('batches', 'cost_price')
            ->get();
    }
}