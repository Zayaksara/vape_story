<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\StockMutation;
use App\Models\User;
use App\Enums\ReturnStatus;
use App\Enums\MutationType;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Kasir membuat return request (status: pending)
     */
    public function createReturn(Order $order, User $cashier, array $data): ProductReturn
    {
        return DB::transaction(function () use ($order, $cashier, $data) {

            // Pastikan order belum punya return
            if ($order->hasReturn()) {
                throw new \Exception('Order ini sudah memiliki return request.');
            }

            // Pastikan order berstatus completed
            if ($order->status !== \App\Enums\OrderStatus::COMPLETED) {
                throw new \Exception('Hanya order yang sudah completed yang bisa di-return.');
            }

            // Generate return number
            $returnNumber = $this->generateReturnNumber();

            $productReturn = ProductReturn::create([
                'return_number' => $returnNumber,
                'order_id'      => $order->id,
                'cashier_id'    => $cashier->id,
                'reason'        => $data['reason'],
                'status'        => ReturnStatus::PENDING,
                'notes'         => $data['notes'] ?? null,
            ]);

            // Simpan return items
            foreach ($data['items'] as $item) {
                ReturnItem::create([
                    'return_id'    => $productReturn->id,
                    'batch_id'     => $item['batch_id'],
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'subtotal'     => $item['quantity'] * $item['unit_price'],
                ]);
            }

            return $productReturn->load('returnItems');
        });
    }

    /**
     * Admin meng-approve return request
     */
    public function approveReturn(ProductReturn $productReturn, User $admin): ProductReturn
    {
        return DB::transaction(function () use ($productReturn, $admin) {

            if ($productReturn->status !== ReturnStatus::PENDING) {
                throw new \Exception('Hanya return dengan status pending yang bisa di-approve.');
            }

            // Update status return
            $productReturn->update([
                'status'      => ReturnStatus::APPROVED,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            // Restore stok setiap return item ke batch-nya
            foreach ($productReturn->returnItems as $item) {
                $batch = $item->batch;

                // Tambah stok kembali (gunakan increment agar observer tidak salah deteksi)
                $batch->increment('stock_quantity', $item->quantity);

                // Catat stock mutation dengan type RETURN (manual, tidak pakai observer)
                StockMutation::create([
                    'batch_id'        => $batch->id,
                    'mutation_type'   => MutationType::RETURN,
                    'quantity'        => $item->quantity,
                    'reference_type' => ProductReturn::class,
                    'reference_id'   => $productReturn->id,
                    'notes'           => 'Stok dikembalikan karena return #' . $productReturn->return_number,
                ]);
            }

            // Update status order menjadi cancelled (karena return sudah diproses)
            $productReturn->order->update([
                'status' => OrderStatus::CANCELLED,
            ]);

            $productReturn->update(['status' => ReturnStatus::PROCESSED]);

            return $productReturn->fresh();
        });
    }

    /**
     * Admin meng-reject return request
     */
    public function rejectReturn(ProductReturn $productReturn, User $admin, string $reason): ProductReturn
    {
        if ($productReturn->status !== ReturnStatus::PENDING) {
            throw new \Exception('Hanya return dengan status pending yang bisa di-reject.');
        }

        $productReturn->update([
            'status'      => ReturnStatus::REJECTED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'notes'       => $reason,
        ]);

        return $productReturn->fresh();
    }

    /**
     * Generate unique return number
     */
    private function generateReturnNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $count = ProductReturn::whereYear('created_at', $year)
                     ->whereMonth('created_at', $month)
                     ->count() + 1;

        return sprintf('RET-%s%s-%04d', $year, $month, $count);
    }
}