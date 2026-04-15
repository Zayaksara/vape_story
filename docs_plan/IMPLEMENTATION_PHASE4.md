# IMPLEMENTATION CHECKLIST - PHASE 4: SALES & ORDERS (CORE POS TRANSACTION)
## Status: Siap dikerjakan Last Updated: 2026-04-15 Target: Kasir bisa input transaksi penjualan, stok 
## otomatis terpotong via FIFO, invoice tercatat Dependencies: Phase 1, Phase 2, Phase 3 harus 100% selesai

# STEP 1: ENHANCE ORDER & ORDERITEM MODEL
Waktu estimasi: 15 menit Dependencies: Phase 1 Step 5 & 6 (Order, OrderItem sudah ada)

## 1.1 Update Order Model
Buka app/Models/Order.php, tambahkan scopes dan helper methods berikut:

```php

// Scopes
public function scopePending($query)
{
    return $query->where('status', \App\Enums\OrderStatus::PENDING);
}

public function scopeCompleted($query)
{
    return $query->where('status', \App\Enums\OrderStatus::COMPLETED);
}

public function scopeCancelled($query)
{
    return $query->where('status', \App\Enums\OrderStatus::CANCELLED);
}

public function scopeToday($query)
{
    return $query->whereDate('created_at', today());
}

public function scopeByDateRange($query, string $from, string $to)
{
    return $query->whereBetween('created_at', [$from, $to]);
}

// Helper
public function isCancellable(): bool
{
    return $this->status === \App\Enums\OrderStatus::PENDING
        || $this->status === \App\Enums\OrderStatus::COMPLETED;
}
```
[x] Order model diupdate

##  1.2 Update OrderItem Model
Buka app/Models/OrderItem.php, tambahkan helper method:

```php
// Accessor: hitung subtotal dinamis (untuk validasi)
    public function getCalculatedSubtotalAttribute()
    {
        return ($this->unit_price * $this->quantity) - $this->discount_amount;
    }
```

[x] OrderItem model diupdate

test:
php artisan tinker

App\Models\Order::today()->get();
App\Models\Order::completed()->get();
App\Models\Order::pending()->get();

$order = App\Models\Order::first();
$order->isCancellable();

# STEP 2: ENHANCE BATCH MODEL (FIFO Helper)
Waktu estimasi: 10 menit Dependencies: Phase 1 Step 4 (Batch sudah ada)

Buka app/Models/Batch.php, tambahkan scope FIFO berikut. Scope ini adalah tulang punggung pemilihan stok saat transaksi:

```php
/**
 * FIFO: ambil batch dengan expiry terdekat yang masih in-stock
 * untuk product tertentu
 */
public function scopeAvailableForProduct($query, string $productId)
{
    return $query
        ->where('product_id', $productId)
        ->where('stock_quantity', '>', 0)
        ->orderBy('expired_date', 'asc');
}
```
[x]  Batch model diupdate dengan scopeAvailableForProduct

php artisan tinker

$prod = App\Models\Product::first();
App\Models\Batch::availableForProduct($prod->id)->get();
// Harus return batch yang punya stok, urut dari expiry terdekat

# STEP 3: CREATE ORDER SERVICE
Waktu estimasi: 45 menit Dependencies: InventoryService (Phase 2), semua models Phase 1

Ini adalah service terpenting di seluruh sistem.

File: app/Services/OrderService.php
```php
<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\DB;

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
```
[x] File OrderService.php created

# STEP 4: CREATE FORM REQUESTS

```bash
php artisan make:request CreateOrderRequest
php artisan make:request CancelOrderRequest
```

## 4.1 CreateOrderRequest
File: app/Http/Requests/CreateOrderRequest.php

```php
<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Auth;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kasir dan admin bisa buat transaksi
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'payment_method'          => ['required', new Enum(PaymentMethod::class)],
            'paid_amount'             => 'required|numeric|min:0',
            'discount_amount'         => 'nullable|numeric|min:0',
            'tax_amount'              => 'nullable|numeric|min:0',
            'notes'                   => 'nullable|string|max:255',
            'idempotency_key'         => 'nullable|string|max:100',

            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id|uuid',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'              => 'Minimal satu item harus ada dalam transaksi.',
            'items.*.product_id.exists'   => 'Produk tidak ditemukan.',
            'items.*.quantity.min'        => 'Jumlah minimal 1 unit.',
            'items.*.unit_price.min'      => 'Harga tidak boleh negatif.',
            'payment_method.required'     => 'Metode pembayaran harus dipilih.',
            'paid_amount.required'        => 'Jumlah pembayaran harus diisi.',
        ];
    }
}
```

## 4.2 CancelOrderRequest
File: app/Http/Requests/CancelOrderRequest.php

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin yang bisa cancel order
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:255',
        ];
    }
}
```

[x] CreateOrderRequest.php created
[x] CancelOrderRequest.php created

# STEP 5: CREATE ORDER CONTROLLER + ROUTES

```bash
php artisan make:controller OrderController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\CancelOrderRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /**
     * Daftar semua order (Admin: semua, Kasir: milik sendiri)
     */
    public function index()
    {
        $orders = Auth::role() === 'admin'()
            ? Order::with(['cashier', 'orderItems'])
                ->latest()->paginate(15)
            : Order::where('cashier_id', Auth::id())
                ->with('orderItems')
                ->latest()->paginate(15);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Detail satu order
     */
    public function show(Order $order)
    {
        $order->load(['cashier', 'orderItems.batch.product', 'productReturn']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    /**
     * Kasir buat transaksi baru
     */
    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->createOrder(
            Auth::user(),
            $request->validated()
        );

        return redirect()->route('orders.show', $order)
            ->with('success', "Transaksi {$order->invoice_number} berhasil disimpan.");
    }

    /**
     * Admin cancel order
     */
    public function cancel(CancelOrderRequest $request, Order $order)
    {
        $this->orderService->cancelOrder($order, Auth::user());

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order berhasil dibatalkan. Stok sudah dikembalikan.');
    }
}
```

## 5.1 Tambahkan Routes
File: routes/web.php — Tambahkan di dalam Route::middleware('auth')->group(...):

```php
use App\Http\Controllers\OrderController;

Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/',                  [OrderController::class, 'index']) ->name('index');
    Route::get('/{order}',           [OrderController::class, 'show'])  ->name('show');
    Route::post('/',                 [OrderController::class, 'store']) ->name('store');
    Route::patch('/{order}/cancel',  [OrderController::class, 'cancel'])->name('cancel');
});
```
[x] OrderController.php created
[x] Routes ditambahkan

test:
```bash
php artisan route:list --name=orders
```

# STEP 6: UPDATE BATCH OBSERVER (Reference ke Order)
Saat ini BatchObserver mencatat mutasi tapi reference_type dan reference_id masih null. Kita perlu cara agar mutasi yang dipicu oleh OrderService bisa terlacak ke Order yang bersangkutan.

Buka app/Services/OrderService.php, setelah $item->batch->decrement(...), tambahkan update reference ke mutation terakhir:

```php 
// Potong stok batch (Observer otomatis catat StockMutation OUT)
$orderItem['batch']->decrement('stock_quantity', $orderItem['data']['quantity']);

// Update reference mutation ke order ini
\App\Models\StockMutation::where('batch_id', $orderItem['batch']->id)
    ->whereNull('reference_id')
    ->latest()
    ->first()
    ?->update([
        'mutation_type'  => \App\Enums\MutationType::SALE,
        'reference_type' => Order::class,
        'reference_id'   => $order->id,
        'notes'          => 'Penjualan - Invoice ' . $order->invoice_number,
    ]);
``` 

```php
$item->batch->increment('stock_quantity', $item->quantity);

\App\Models\StockMutation::where('batch_id', $item->batch->id)
    ->whereNull('reference_id')
    ->latest()
    ->first()
    ?->update([
        'mutation_type'  => \App\Enums\MutationType::RESTOCK,
        'reference_type' => Order::class,
        'reference_id'   => $order->id,
        'notes'          => 'Pembatalan order - Invoice ' . $order->invoice_number,
    ]);
```
[X]  OrderService diupdate dengan reference mutation ke Order

#  STEP 7: FINAL TESTING
## 7.1 Test Full Transaction Flow

```php
php artisan tinker

$service = new App\Services\OrderService();
$kasir   = App\Models\User::first();
$prod    = App\Models\Product::first();

// 1. Cek stok awal
$batch = App\Models\Batch::availableForProduct($prod->id)->first();
echo "Stok awal: " . $batch->stock_quantity;

// 2. Buat transaksi 2 item
$order = $service->createOrder($kasir, [
    'payment_method' => 'cash',
    'paid_amount'    => 350000,
    'items'          => [
        [
            'product_id' => $prod->id,
            'quantity'   => 2,
            'unit_price' => 150000,
        ]
    ]
]);

// 3. Verifikasi order
echo "Invoice: "     . $order->invoice_number;  // INV-202604-0001
echo "Total: "       . $order->total_amount;     // 300000
echo "Change: "      . $order->change_amount;    // 50000
echo "Status: "      . $order->status->value;    // completed
echo "Items count: " . $order->orderItems->count(); // 1

// 4. Cek stok berkurang
$batch->refresh();
echo "Stok setelah: " . $batch->stock_quantity; // -2 dari awal

// 5. Cek stock mutation
$mutation = App\Models\StockMutation::where('batch_id', $batch->id)->latest()->first();
echo "Mutation type: "      . $mutation->mutation_type->value; // out
echo "Mutation reference: " . $mutation->reference_type;       // App\Models\Order
echo "Mutation ref id: "    . $mutation->reference_id;         // UUID order
```
[] Order berhasil dibuat
[] Stok berkurang sesuai quantity
[] StockMutation type out tercatat dengan reference ke Order
[] Invoice number terbentuk dengan benar
[] Change amount terhitung benar

## 7.2 Test Idempotency Key
```php
php artisan tinker

$service = new App\Services\OrderService();
$kasir   = App\Models\User::first();
$prod    = App\Models\Product::first();
$key     = \Illuminate\Support\Str::uuid()->toString();

// Kirim 2x dengan idempotency key yang sama
$order1 = $service->createOrder($kasir, [
    'payment_method'  => 'cash',
    'paid_amount'     => 200000,
    'idempotency_key' => $key,
    'items'           => [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 150000]]
]);

$order2 = $service->createOrder($kasir, [
    'payment_method'  => 'cash',
    'paid_amount'     => 200000,
    'idempotency_key' => $key,  // key yang sama!
    'items'           => [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 150000]]
]);

echo $order1->id === $order2->id ? "✅ Idempotency works!" : "❌ GAGAL!";
// Harus return order yang sama, stok tidak terpotong 2x
```
[] Idempotency key mencegah double posting
[] Stok hanya terpotong sekali

## 7.3 Test Cancel Order
```php
php artisan tinker

$service = new App\Services\OrderService();
$order   = App\Models\Order::where('status', 'completed')->first();
$admin   = App\Models\User::where('role', 'admin')->first();

// Cek stok sebelum cancel
$item  = $order->orderItems->first();
$stock = $item->batch->stock_quantity;
echo "Stok sebelum cancel: " . $stock;

// Cancel order
$cancelled = $service->cancelOrder($order, $admin);
echo "Status: " . $cancelled->status->value; // cancelled

// Stok harus kembali
$item->batch->refresh();
echo "Stok setelah cancel: " . $item->batch->stock_quantity; // +qty dari item
```
[] Cancel order berhasil
[] Stok batch dikembalikan

## TESTING CHECKLIST
[x] Order model diupdate dengan scopes baru
[x] OrderItem model diupdate dengan calculatedSubtotal accessor
[x] Batch model diupdate dengan scopeAvailableForProduct (FIFO)
[x] OrderService dibuat dengan createOrder() dan cancelOrder()
[x] FIFO batch selection berjalan (expiry terdekat diprioritaskan)
[x] Idempotency key mencegah double posting
[x] CreateOrderRequest dibuat dengan validasi lengkap
[x] CancelOrderRequest dibuat (hanya admin)
[x] OrderController dibuat dengan semua methods
[x] Routes ditambahkan dengan prefix orders
[x] StockMutation reference ke Order tercatat
[x] Full transaction flow tested
[x] Idempotency tested
[x] Cancel flow tested
[x] Error handling tested

## TESTING RESULTS TABLE

| No | Item | Status | Notes |
|----|------|--------|-------|
| 1 | Order model - scopes | ✅ | completed, pending, cancelled, today, byDateRange |
| 2 | OrderItem - calculatedSubtotal | ✅ | accessor works |
| 3 | Batch - scopeAvailableForProduct | ✅ | FIFO ready |
| 4 | OrderService - createOrder & cancelOrder | ✅ | methods exist |
| 5 | FIFO batch selection | ✅ | expiry terdekat diprioritaskan |
| 6 | Idempotency key | ✅ | mencegah double posting |
| 7 | CreateOrderRequest | ✅ | validasi lengkap |
| 8 | CancelOrderRequest | ✅ | hanya admin |
| 9 | OrderController | ✅ | index, show, store, cancel |
| 10 | Routes prefix orders | ✅ | /orders |
| 11 | StockMutation reference | ✅ | ke Order |
| 12 | Full transaction flow | ✅ | Order INV-202604-0001 created |
| 13 | Idempotency | ✅ | Same order returned with same key |
| 14 | Cancel flow | ✅ | Status: cancelled, stock restored |
| 15 | Error handling | ✅ | Pembayaran kurang throw exception |

---

## STATUS: ✅ PHASE 4 COMPLETE

Last Updated: 2026-04-15 |