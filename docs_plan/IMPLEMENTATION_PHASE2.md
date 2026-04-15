# IMPLEMENTATION CHECKLIST - PHASE 2: INVENTORY MANAGEMENT
**Status**: Siap dikerjakan
**Last Updated**: 2026-04-14
**Target**: Stok masuk (restock), manual adjustment, dan reporting stok
**Dependencies**: Phase 1 harus 100% selesai (semua model, observer, relationship sudah jalan normal)

---

## 🚀 CARA PAKAI
1. Kerjakan SATU item dalam waktu
2. Centang [x] setelah selesai + test
3. JANGAN LOMPAT step!
4. Setelah selesai, beri tahu saya untuk audit + lanjut Phase 3

---

## ⚠️ PENTING: URUTAN TIDAK BOLEH DIUBAH!

---

### **STEP 1: UPDATE BATCH OBSERVER (Created Event)**
**Waktu estimasi**: 10 menit  
**Dependencies**: Phase 1 Step 9

#### 1.1 Buka `app/Observers/BatchObserver.php`
Tambahkan method `created` di dalam class:

```php
public function created(Batch $batch)
{
    if ($batch->stock_quantity > 0) {
        StockMutation::create([
            'batch_id' => $batch->id,
            'mutation_type' => MutationType::IN,
            'quantity' => $batch->stock_quantity,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'Initial stock from batch creation / restock'
        ]);
    }
}
```
[ x ] Method created sudah ditambahkan

php artisan tinker
>>> $prod = App\Models\Product::first();
>>> $batch = $prod->batches()->create([
...     'lot_number' => 'LOT-TEST-001',
...     'expired_date' => now()->addMonths(12),
...     'stock_quantity' => 50,
...     'cost_price' => 80000
... ]);
>>> App\Models\StockMutation::where('batch_id', $batch->id)->latest()->first();  // harus muncul mutation IN

# STEP 2: ENHANCE PRODUCT & BATCH MODELS
Waktu estimasi: 20 menit

2.1 Update app/Models/Product.php

Tambahkan di dalam class (setelah method yang sudah ada):

```php
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
    return $this->batches()->sum(DB::raw('stock_quantity * cost_price'));
}

```
2.2 Update app/Models/Batch.php
Tambahkan di dalam class:

```php

public function isExpired()
{
    return $this->expired_date->isPast();
}

public function isNearExpiry($days = 30)
{
    return $this->expired_date->diffInDays(now()) <= $days;
}

public function scopeInStock($query)
{
    return $query->where('stock_quantity', '>', 0);
}
```

[x] Product model di-update
[x] Batch model di-update

test:
php artisan tinker
>>> App\Models\Product::lowStock()->get();
>>> App\Models\Product::nearExpiry()->get();
>>> $prod = App\Models\Product::first();
>>> $prod->totalStock();
>>> $prod->stockValue();

[x] jalan semua 

# STEP 3: CREATE INVENTORY SERVICE
## 3.1 Buat folder

```bash
mkdir -p app/Services
```

```php
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

    public function adjustStock(Batch $batch, int $quantity, string $notes = null)
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
```
[x] File InventoryService.php created
[x] Code sesuai template

php artisan tinker
>>> $service = new App\Services\InventoryService();
>>> $prod = App\Models\Product::first();
>>> $service->restock($prod, [
...     'lot_number' => 'RESTOCK-001',
...     'expired_date' => now()->addMonths(6),
...     'quantity' => 100,
...     'cost_price' => 90000
... ]);

hasil:
= App\Models\Batch {#8906
    product_id: "bcebb697-2022-420b-aec9-4759381f3e96",
    lot_number: "RESTOCK-001",
    expired_date: "2026-10-14 22:30:07",
    stock_quantity: 100,
    cost_price: 90000,
    updated_at: "2026-04-14 22:30:07",
    created_at: "2026-04-14 22:30:07",
  }

# STEP 4: CREATE FORM REQUESTS
## Waktu estimasi: 15 menit

```bash
php artisan make:request RestockRequest
php artisan make:request StockAdjustmentRequest
```

1. RestockRequest.php (Hanya Admin)
Buka file: app/Http/Requests/RestockRequest.php
Ganti seluruh isinya dengan kode berikut:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh restock
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'product_id'    => 'required|exists:products,id|uuid',
            'lot_number'    => 'required|string|max:50|unique:batches,lot_number',
            'expired_date'  => 'required|date|after:today',
            'quantity'      => 'required|integer|min:1',
            'cost_price'    => 'required|numeric|min:0',
            'notes'         => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'lot_number.unique'     => 'Nomor batch ini sudah pernah digunakan.',
            'expired_date.after'    => 'Tanggal kadaluarsa harus setelah hari ini.',
            'quantity.min'          => 'Jumlah restock minimal 1 unit.',
            'cost_price.min'        => 'Harga modal tidak boleh negatif.',
        ];
    }
```

2. StockAdjustmentRequest.php (hanya admin)

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh adjustment
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'batch_id'  => 'required|exists:batches,id|uuid',
            'quantity'  => 'required|integer|min:1',        // Positif saja di form
            'notes'     => 'required|string|max:255',       // Wajib alasan
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min'   => 'Jumlah adjustment minimal 1 unit.',
            'notes.required' => 'Alasan adjustment harus diisi.',
        ];
    }
}
```
# STEP 5: CREATE INVENTORY CONTROLLER + ROUTES
### Buat Controller

```bash
php artisan make:controller InventoryController
```

Tambahkan route di routes/web.php:
```php
use App\Http\Controllers\InventoryController;
use Inertia\Inertia;

    Route::middleware('auth')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
});
```

test:
```php

// Import dulu
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\RestockRequest;

// Data valid (harus lolos)
$dataValid = [
    'product_id'    => 'bcebb697-2022-420b-aec9-4759381f3e96', // ganti dengan UUID product kamu
    'lot_number'    => 'LOT-TEST-003',
    'expired_date'  => '2026-12-31',
    'quantity'      => 50,
    'cost_price'    => 85000,
    'notes'         => 'Restock dari supplier XYZ'
];

// Jalankan validasi
$validator = Validator::make($dataValid, (new RestockRequest)->rules());

if ($validator->fails()) {
    dd($validator->errors()->all());
} else {
    echo "✅ RestockRequest VALID\n";
}
```

```php

// Data valid
$dataAdjustValid = [
    'batch_id' => '5e68db0b-46f8-467b-8511-dba57016c414', // ganti dengan UUID batch kamu
    'quantity' => 30,
    'notes'    => 'Hasil stock opname - tambah 30 pcs'
];

$validator2 = Validator::make($dataAdjustValid, (new \App\Http\Requests\StockAdjustmentRequest)->rules());

if ($validator2->fails()) {
    dd($validator2->errors()->all());
} else {
    echo "✅ StockAdjustmentRequest VALID\n";
}
```

STEP 6: FINAL TESTING & REPORTING
Test semua method service + scopes + observer di Tinker.
Completion Checklist (centang semua):

 [x]BatchObserver sudah support created
 [x]Product & Batch model sudah punya scopes & helper
 [x]InventoryService sudah dibuat & tested
 [x]Restock & Adjustment berjalan dengan transaction
 [x]Reporting stok (totalStock, stockValue, low stock, near expiry) sudah bisa dipanggil

 ```bash
use App\Models\Product;
use App\Models\Batch;
use App\Services\InventoryService;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\StockAdjustmentRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

// ==============================================
// 1. CEK DATA & BUAT SAMPLE JIKA KOSONG
// ==============================================
echo "=== CEK DATA AWAL ===\n";

if (Product::count() === 0) {
    echo "⚠️  Tidak ada Product. Membuat sample...\n";
    $cat = \App\Models\Category::firstOrCreate(['name' => 'Liquid', 'slug' => 'liquid']);
    $prod = Product::create([
        'code' => 'TEST-LIQ-001',
        'name' => 'Mango Blast Liquid',
        'category_id' => $cat->id,
        'base_price' => 125000,
        'flavor' => 'Mango',
        'size_ml' => 30,
        'is_active' => true
    ]);
} else {
    $prod = Product::first();
}
echo "✅ Product dipakai: " . $prod->name . " (" . $prod->id . ")\n";

if (Batch::count() === 0) {
    echo "⚠️  Tidak ada Batch. Membuat sample...\n";
    $batch = $prod->batches()->create([
        'lot_number' => 'LOT-TEST-001',
        'expired_date' => now()->addMonths(8),
        'stock_quantity' => 120,
        'cost_price' => 85000
    ]);
} else {
    $batch = Batch::first();
}
echo "✅ Batch dipakai: " . $batch->lot_number . " (stok: " . $batch->stock_quantity . ")\n\n";

// ==============================================
// 2. TEST PRODUCT MODEL (Scopes + Helpers)
// ==============================================
echo "=== TEST PRODUCT MODEL ===\n";
echo "totalStock()          : " . $prod->totalStock() . "\n";
echo "stockValue()          : Rp " . number_format($prod->stockValue()) . "\n";
echo "lowStock(50)          : " . (Product::lowStock(50)->count() ? 'ADA' : 'TIDAK ADA') . "\n";
echo "nearExpiry(90 hari)   : " . (Product::nearExpiry(90)->count() ? 'ADA' : 'TIDAK ADA') . "\n\n";

// ==============================================
// 3. TEST BATCH MODEL
// ==============================================
echo "=== TEST BATCH MODEL ===\n";
echo "isExpired()           : " . ($batch->isExpired() ? 'YA' : 'TIDAK') . "\n";
echo "isNearExpiry(90)      : " . ($batch->isNearExpiry(90) ? 'YA' : 'TIDAK') . "\n";
echo "oldestExpiry count    : " . Batch::oldestExpiry()->count() . "\n";
echo "inStock count         : " . Batch::inStock()->count() . "\n\n";

// ==============================================
// 4. TEST INVENTORY SERVICE
// ==============================================
echo "=== TEST INVENTORY SERVICE ===\n";
$service = new InventoryService();

// Test Restock
try {
    $newBatch = $service->restock($prod, [
        'lot_number'    => 'LOT-RESTOCK-' . time(),
        'expired_date'  => now()->addMonths(12),
        'quantity'      => 50,
        'cost_price'    => 90000,
        'notes'         => 'Test restock dari Tinker'
    ]);
    echo "✅ Restock berhasil! Batch baru ID: " . $newBatch->id . " (stok +50)\n";
} catch (\Exception $e) {
    echo "❌ Restock gagal: " . $e->getMessage() . "\n";
}

// Test Adjustment (tambah stok)
try {
    $adjusted = $service->adjustStock($batch, 30, 'Test adjustment +30 dari Tinker');
    echo "✅ Adjustment (+30) berhasil! Stok sekarang: " . $batch->fresh()->stock_quantity . "\n";
} catch (\Exception $e) {
    echo "❌ Adjustment gagal: " . $e->getMessage() . "\n";
}

// Test Adjustment (kurangi stok)
try {
    $adjusted2 = $service->adjustStock($batch, -20, 'Test adjustment -20 dari Tinker');
    echo "✅ Adjustment (-20) berhasil! Stok sekarang: " . $batch->fresh()->stock_quantity . "\n";
} catch (\Exception $e) {
    echo "❌ Adjustment gagal: " . $e->getMessage() . "\n";
}
echo "\n";

// ==============================================
// 5. TEST FORM REQUEST VALIDATION
// ==============================================
echo "=== TEST FORM REQUESTS ===\n";

// Test RestockRequest
$restockData = [
    'product_id'    => $prod->id,
    'lot_number'    => 'LOT-VALID-' . time(),
    'expired_date'  => now()->addMonths(10)->format('Y-m-d'),
    'quantity'      => 75,
    'cost_price'    => 95000,
    'notes'         => 'Test valid restock'
];

$validatorRestock = Validator::make($restockData, (new RestockRequest)->rules());
echo "RestockRequest valid  : " . ($validatorRestock->fails() ? '❌' : '✅') . "\n";

if ($validatorRestock->fails()) {
    echo "   Error: " . implode(', ', $validatorRestock->errors()->all()) . "\n";
}

// Test StockAdjustmentRequest
$adjustData = [
    'batch_id' => $batch->id,
    'quantity' => 40,
    'notes'    => 'Test valid adjustment dari Tinker'
];

$validatorAdjust = Validator::make($adjustData, (new StockAdjustmentRequest)->rules());
echo "StockAdjustmentRequest valid : " . ($validatorAdjust->fails() ? '❌' : '✅') . "\n";

if ($validatorAdjust->fails()) {
    echo "   Error: " . implode(', ', $validatorAdjust->errors()->all()) . "\n";
}

echo "\n🎉 SEMUA TEST SELESAI!\n";
echo "Kalau semua ✅ berarti Phase 2 sampai Step 4 sudah aman.\n";
 ```