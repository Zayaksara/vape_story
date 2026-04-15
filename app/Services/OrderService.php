<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\DB;
use App\Models\StockMutation;
use App\Enums\MutationType;

class OrderService
{
    /**
     * Buat transaksi penjualan baru.
     *
     * $data = [
     *   'payment_method' => 'cash',
     *   'paid_amount'    => 200000,
     *   'discount_amount'=> 0,        // optional, discount level order
     *   'notes'          => '...',    // optional
     *   'idempotency_key'=> 'uuid',   // optional, dari client
     *   'items' => [
     *     ['product_id' => 'uuid', 'quantity' => 2, 'unit_price' => 75000],
     *     ...
     *   ]
     * ]
     */
    public function createOrder(User $cashier, array $data): Order
    {
        return DB::transaction(function () use ($cashier, $data) {

            // Cek idempotency key — cegah double posting
            if (!empty($data['idempotency_key'])) {
                $existing = Order::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing; // Return order yang sama, tidak buat baru
                }
            }

            // Hitung total dari items
            $orderItems   = [];
            $totalAmount  = 0;

            foreach ($data['items'] as $item) {
                // FIFO: ambil batch yang paling dekat expiry-nya
                $batch = Batch::availableForProduct($item['product_id'])
                    ->lockForUpdate() // Cegah race condition
                    ->first();

                if (!$batch) {
                    throw new \Exception(
                        "Stok habis untuk produk: {$item['product_id']}"
                    );
                }

                if ($batch->stock_quantity < $item['quantity']) {
                    throw new \Exception(
                        "Stok tidak cukup. Tersedia: {$batch->stock_quantity}, diminta: {$item['quantity']}"
                    );
                }

                $unitPrice      = $item['unit_price'];
                $discountItem   = $item['discount_amount'] ?? 0;
                $subtotal       = ($unitPrice - $discountItem) * $item['quantity'];
                $totalAmount   += $subtotal;

                $orderItems[] = [
                    'batch'     => $batch,
                    'data'      => [
                        'batch_id'        => $batch->id,
                        'product_name'    => $batch->product->name, // SNAPSHOT
                        'quantity'        => $item['quantity'],
                        'unit_price'      => $unitPrice,
                        'discount_amount' => $discountItem,
                        'subtotal'        => $subtotal,
                    ],
                ];
            }

            $discountOrder  = $data['discount_amount'] ?? 0;
            $taxAmount      = $data['tax_amount'] ?? 0;
            $grandTotal     = $totalAmount - $discountOrder + $taxAmount;
            $paidAmount     = $data['paid_amount'];
            $changeAmount   = $paidAmount - $grandTotal;

            if ($changeAmount < 0) {
                throw new \Exception(
                    "Pembayaran kurang. Total: {$grandTotal}, dibayar: {$paidAmount}"
                );
            }

            // Buat order header
            $order = Order::create([
                'invoice_number'  => $this->generateInvoiceNumber(),
                'cashier_id'      => $cashier->id,
                'total_amount'    => $grandTotal,
                'discount_amount' => $discountOrder,
                'tax_amount'      => $taxAmount,
                'paid_amount'     => $paidAmount,
                'change_amount'   => $changeAmount,
                'payment_method'  => $data['payment_method'],
                'status'          => OrderStatus::COMPLETED,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            // Simpan order items & potong stok
            foreach ($orderItems as $orderItem) {
                $order->orderItems()->create($orderItem['data']);

                // Potong stok batch (Observer otomatis catat StockMutation OUT)
                $orderItem['batch']->decrement('stock_quantity', $orderItem['data']['quantity']);

                StockMutation::where('batch_id', $orderItem['batch']->id)
                    ->where('mutation_type', 'OUT')
                    ->whereNull('related_id')
                    ->update([
                        'mutation_id' => MutationType::SALE,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'notes' => "Penjualan - Invoice" . $order->invoice_number,
                    ]);
            }

            return $order->load('orderItems');
        });
    }

    /**
     * Kasir atau admin cancel order (hanya jika status pending/completed).
     * Stok dikembalikan otomatis.
     */
    public function cancelOrder(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor) {

            if (!$order->isCancellable()) {
                throw new \Exception(
                    "Order dengan status '{$order->status->value}' tidak bisa dibatalkan."
                );
            }

            // Kembalikan stok ke masing-masing batch
            foreach ($order->orderItems as $item) {
                $item->batch->increment('stock_quantity', $item->quantity);
                // Observer otomatis catat StockMutation IN (restore)
                StockMutation::where('batch_id', $item->batch_id)
                    ->where('mutation_type', 'IN')
                    ->whereNull('related_id')
                    ?->update([
                        'mutation_id' => MutationType::RETURN,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'notes' => "Pembatalan order - Invoice" . $order->invoice_number,
                    ]);

            }

            $order->update(['status' => OrderStatus::CANCELLED]);

            return $order->fresh();
        });
    }

    /**
     * Generate invoice number: INV-YYYYMM-XXXX
     */
    private function generateInvoiceNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $count = Order::whereYear('created_at', $year)
                      ->whereMonth('created_at', $month)
                      ->count() + 1;

        return sprintf('INV-%s%s-%04d', $year, $month, $count);
    }
}

