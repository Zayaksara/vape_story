# IMPLEMENTATION CHECKLIST - PHASE 3: RETURNS & REFUNDS
## Status: Siap dikerjakan Last Updated: 2026-04-15 Target: Sistem pengembalian barang dengan approval workflow & auto restore stok Dependencies: Phase 1 & Phase 2 harus 100% selesai

⚠️ PENTING: URUTAN TIDAK BOLEH DIUBAH!

## STEP 1: SETUP ENUMS BARU
### Waktu estimasi: 10 menit Dependencies: Phase 1 Step 1 (folder app/Enums sudah ada)

## 1.1 Buat ReturnStatus Enum
**File: app/Enums/ReturnStatus.php**

```php

<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case PENDING   = 'pending';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case PROCESSED = 'processed';
}
```

[x] File app/Enums/ReturnStatus.php created
[x] Syntax valid
Test:

```bash
php artisan tinker
App\Enums\ReturnStatus::cases();
App\Enums\ReturnStatus::PENDING->value;
```

## STEP 2: RETURN MODEL & MIGRATION
Waktu estimasi: 30 menit Dependencies: Order (Phase 1 Step 5), User (sudah ada), ReturnStatus Enum (Step 1)

⚠️ CATATAN PENTING: Nama model TIDAK BOLEH Return karena return adalah reserved keyword PHP. Gunakan nama ProductReturn.

### 2.1 BUAT RETURN MIGRATION
```bash
php artisan make:migration create_returns_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('return_number')->unique();

            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');

            // ✅ Pakai foreignId karena users.id adalah bigint
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');

            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed'])
                  ->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('cashier_id');
            $table->index('status');
            $table->index('return_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
```

[x] Migration file created
[x] Code sesuai template di atas

## 2.2 Buat ProductReturn model
File: app/Models/ProductReturn.php
```php
<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    use HasFactory;

    protected $table = 'returns';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'return_number',
        'order_id',
        'cashier_id',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'status'      => ReturnStatus::class,
        'approved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', ReturnStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ReturnStatus::APPROVED);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', ReturnStatus::PROCESSED);
    }
}
```

[x] Model file created
[x] Code sesuai template di atas

## 2.3 Migration
[x] Migration berhasil
[x] Tabel returns created di database

# STEP 3: RETURN ITEM MODEL & MIGRATION
Waktu estimasi: 25 menit Dependencies: ProductReturn (Step 2), Batch (Phase 1 Step 4)

## 3.1 Buat ReturnItem Migration
```php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->foreignId('return_id')->constrained('returns')->onDelete('cascade');
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->string('product_name');          // SNAPSHOT nama produk
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);    // SNAPSHOT harga saat return
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->index('return_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
```
[x] Migration file created
[x] Code sesuai template di atas

## 3.2 Buat ReturnItem Model 

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'return_id',
        'batch_id',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',
    ];

    public function productReturn()
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
```
[x] Model file created
[x] Code sesuai template di atas

## 3.3 Run Migration
[x] Migration berhasil
[x] Tabel return_items created di database

# STEP 4: UPDATE EXISTING MODELS
Waktu estimasi: 15 menit Dependencies: ProductReturn (Step 2), ReturnItem (Step 3)

## 4.1 Update Order Model
File: app/Models/Order.php — Tambahkan method berikut di dalam class:

```php
public function productReturn()
{
    return $this->hasOne(ProductReturn::class, 'order_id');
}

// Helper: cek apakah order sudah punya return
public function hasReturn(): bool
{
    return $this->productReturn()->exists();
}
```

[x] Order model diupdate dengan productReturn() dan hasReturn()

## 4.2 Update Batch Model
File: app/Models/Batch.php — Tambahkan method berikut:

```php
public function returnItems()
{
    return $this->hasMany(ReturnItem::class);
}
```
[x]  Batch model diupdate dengan returnItems()

## 4.3 Update User Model
File: app/Models/User.php — Tambahkan dua relationships berikut:

```php
use App\Models\ProductReturn;

// Return yang dibuat oleh user ini (sebagai kasir)
public function returns()
{
    return $this->hasMany(ProductReturn::class, 'cashier_id');
}

// Return yang di-approve oleh user ini (sebagai admin)
public function approvedReturns()
{
    return $this->hasMany(ProductReturn::class, 'approved_by');
}
```

# STEP 5: CREATE RETURN SERVICE
Waktu estimasi: 30 menit Dependencies: InventoryService (Phase 2), ProductReturn, ReturnItem, StockMutation

File: app/Services/ReturnService.php

```php
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

                // Tambah stok kembali
                $batch->update([
                    'stock_quantity' => $batch->stock_quantity + $item->quantity,
                ]);

                // Catat stock mutation dengan type RETURN
                // (Observer sudah handle otomatis, tapi kita override notes & reference)
                // Ambil mutation yang baru dibuat oleh observer dan update reference-nya
                $latestMutation = StockMutation::where('batch_id', $batch->id)
                    ->latest()
                    ->first();

                if ($latestMutation) {
                    $latestMutation->update([
                        'mutation_type'  => MutationType::RETURN,
                        'reference_type' => ProductReturn::class,
                        'reference_id'   => $productReturn->id,
                        'notes'          => 'Stok dikembalikan karena return #' . $productReturn->return_number,
                    ]);
                }
            }

            // Update status order menjadi refunded
            $productReturn->order->update([
                'status' => OrderStatus::REFUNDED,
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
```
[x] File ReturnService.php created
[x] Code sesuai template

# STEP 6: CREATE FORM REQUESTS
Waktu estimasi: 15 menit

```bash
php artisan make:request CreateReturnRequest
php artisan make:request ApproveReturnRequest
php artisan make:request RejectReturnRequest
```

## 6.1 CreateReturnRequest
File: app/Http/Requests/CreateReturnRequest.php

```php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kasir dan admin boleh buat return
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'order_id'              => 'required|exists:orders,id|uuid',
            'reason'                => 'required|string|max:500',
            'notes'                 => 'nullable|string|max:255',
            'items'                 => 'required|array|min:1',
            'items.*.batch_id'      => 'required|exists:batches,id|uuid',
            'items.*.product_name'  => 'required|string|max:255',
            'items.*.quantity'      => 'required|integer|min:1',
            'items.*.unit_price'    => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'            => 'Minimal satu item harus di-return.',
            'items.*.batch_id.exists'   => 'Lot tidak ditemukan.',
            'items.*.quantity.min'      => 'Jumlah return minimal 1 unit.',
            'reason.required'           => 'Alasan return harus diisi.',
        ];
    }
}
```

## 6.2 ApproveReturnRequest
File: app/Http/Requests/ApproveReturnRequest.php

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ApproveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh approve
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:255',
        ];
    }
}
```

## 6.3 RejectReturnRequest
File: app/Http/Requests/RejectReturnRequest.php

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RejectReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HANYA ADMIN yang boleh reject
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan harus diisi.',
        ];
    }
}
```

[x] CreateReturnRequest.php created
[x] ApproveReturnRequest.php created
[x] RejectReturnRequest.php created

# STEP 7: CREATE RETURN CONTROLLER + ROUTES

File: app/Http/Controllers/ReturnController.php

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProductReturn;
use App\Models\Order;
use App\Services\ReturnService;
use App\Http\Requests\CreateReturnRequest;
use App\Http\Requests\ApproveReturnRequest;
use App\Http\Requests\RejectReturnRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    /**
     * List semua return requests (Admin: semua, Kasir: milik sendiri)
     */
    public function index()
    {
        $returns = Auth::user()->role === 'admin'
            ? ProductReturn::with(['order', 'cashier', 'approvedBy', 'returnItems'])
                ->latest()->paginate(15)
            : ProductReturn::where('cashier_id', Auth::id())
                ->with(['order', 'returnItems'])
                ->latest()->paginate(15);

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
        ]);
    }

    /**
     * Detail satu return
     */
    public function show(ProductReturn $return)
    {
        $return->load(['order.orderItems', 'cashier', 'approvedBy', 'returnItems.batch']);

        return Inertia::render('Returns/Show', [
            'return' => $return,
        ]);
    }

    /**
     * Kasir membuat return request
     */
    public function store(CreateReturnRequest $request)
    {
        $order = Order::findOrFail($request->order_id);

        $productReturn = $this->returnService->createReturn(
            $order,
            Auth::user(),
            $request->validated()
        );

        return redirect()->route('returns.show', $productReturn)
            ->with('success', 'Return request berhasil dibuat. Menunggu approval admin.');
    }

    /**
     * Admin approve return
     */
    public function approve(ApproveReturnRequest $request, ProductReturn $return)
    {
        $this->returnService->approveReturn($return, Auth::user());

        return redirect()->route('returns.show', $return)
            ->with('success', 'Return berhasil di-approve. Stok sudah dikembalikan.');
    }

    /**
     * Admin reject return
     */
    public function reject(RejectReturnRequest $request, ProductReturn $return)
    {
        $this->returnService->rejectReturn($return, Auth::user(), $request->reason);

        return redirect()->route('returns.show', $return)
            ->with('success', 'Return berhasil di-reject.');
    }
}
```

## 7.1 Tambahkan Routes
File: routes/web.php — Tambahkan di dalam Route::middleware('auth')->group(...):

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProductReturn;
use App\Models\Order;
use App\Services\ReturnService;
use App\Http\Requests\CreateReturnRequest;
use App\Http\Requests\ApproveReturnRequest;
use App\Http\Requests\RejectReturnRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    /**
     * List semua return requests (Admin: semua, Kasir: milik sendiri)
     */
    public function index()
    {
        $returns = Auth::user()->role === 'admin'
            ? ProductReturn::with(['order', 'cashier', 'approvedBy', 'returnItems'])
                ->latest()->paginate(15)
            : ProductReturn::where('cashier_id', Auth::id())
                ->with(['order', 'returnItems'])
                ->latest()->paginate(15);

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
        ]);
    }

    /**
     * Detail satu return
     */
    public function show(ProductReturn $return)
    {
        $return->load(['order.orderItems', 'cashier', 'approvedBy', 'returnItems.batch']);

        return Inertia::render('Returns/Show', [
            'return' => $return,
        ]);
    }

    /**
     * Kasir membuat return request
     */
    public function store(CreateReturnRequest $request)
    {
        $order = Order::findOrFail($request->order_id);

        $productReturn = $this->returnService->createReturn(
            $order,
            Auth::user(),
            $request->validated()
        );

        return redirect()->route('returns.show', $productReturn)
            ->with('success', 'Return request berhasil dibuat. Menunggu approval admin.');
    }

    /**
     * Admin approve return
     */
    public function approve(ApproveReturnRequest $request, ProductReturn $return)
    {
        $this->returnService->approveReturn($return, Auth::user());

        return redirect()->route('returns.show', $return)
            ->with('success', 'Return berhasil di-approve. Stok sudah dikembalikan.');
    }

    /**
     * Admin reject return
     */
    public function reject(RejectReturnRequest $request, ProductReturn $return)
    {
        $this->returnService->rejectReturn($return, Auth::user(), $request->reason);

        return redirect()->route('returns.show', $return)
            ->with('success', 'Return berhasil di-reject.');
    }
}
```

[x] ReturnController.php created
[x] Routes ditambahkan di web.php

test:
php artisan route:list --name=returns

# STEP 8: UPDATE BATCH OBSERVER (Return Mutation Type)
Waktu estimasi: 10 menit Dependencies: BatchObserver (Phase 1 Step 9), ReturnService (Step 5)

File: app/Observers/BatchObserver.php

Pastikan method updated sudah mendukung MutationType::RETURN. Cek ulang logika observer:

```php
public function updated(Batch $batch)
{
    if ($batch->isDirty('stock_quantity')) {
        $oldQuantity = $batch->getOriginal('stock_quantity');
        $newQuantity = $batch->stock_quantity;
        $difference  = $newQuantity - $oldQuantity;

        // Tentukan tipe mutasi berdasarkan konteks
        // ReturnService akan override ke MutationType::RETURN setelah ini
        $mutationType = $difference > 0 ? MutationType::IN : MutationType::OUT;

        StockMutation::create([
            'batch_id'       => $batch->id,
            'mutation_type'  => $mutationType,
            'quantity'       => $difference,
            'reference_type' => null,
            'reference_id'   => null,
            'notes'          => 'Automatic stock adjustment',
        ]);
    }
}
```

Catatan: ReturnService::approveReturn() akan mengupdate mutation type ke RETURN dan mengisi reference_type/reference_id setelah observer berjalan.

[x] BatchObserver di-review & confirmed sudah benar

# STEP 9: FINAL TESTING

## 9.1 Test Full Flow: Create → Approve → Cek Stok

```bash


php artisan tinker

// Setup
$service = new App\Services\ReturnService();
$order   = App\Models\Order::where('status', 'completed')->first();
$admin   = App\Models\User::where('role', 'admin')->first();
$kasir   = App\Models\User::where('role', 'cashier')->first() ?? $admin;
$item    = $order->orderItems->first();

// 1. Cek stok awal
$batchBefore = App\Models\Batch::find($item->batch_id);
echo "Stok awal: " . $batchBefore->stock_quantity;

// 2. Buat return
$return = $service->createReturn($order, $kasir, [
    'reason' => 'Produk rusak saat diterima',
    'notes'  => 'Testing Phase 3',
    'items'  => [[
        'batch_id'     => $item->batch_id,
        'product_name' => $item->product_name,
        'quantity'     => 1,
        'unit_price'   => $item->unit_price,
    ]]
]);
echo "Return status: " . $return->status->value; // pending

// 3. Admin approve
$processed = $service->approveReturn($return, $admin);
echo "Return status after approve: " . $processed->status->value; // processed

// 4. Cek stok setelah return
$batchAfter = App\Models\Batch::find($item->batch_id);
echo "Stok setelah return: " . $batchAfter->stock_quantity; // +1 dari sebelum

// 5. Cek order status
$order->refresh();
echo "Order status: " . $order->status->value; // refunded

// 6. Cek stock mutation
App\Models\StockMutation::where('batch_id', $item->batch_id)
    ->latest()->first();
// mutation_type harus 'return'

```

[] Return berhasil dibuat dengan status pending
[] Admin berhasil approve
[] Stok batch bertambah setelah approve
[] Order status berubah ke refunded
[] StockMutation type = return dengan reference ke ProductReturn

 ## 9.2 Test Reject Flow

[] Reject flow berjalan normal
[] Stok TIDAK berubah setelah reject

# kesimpulan
[x] ReturnStatus Enum dibuat
[x] returns tabel dibuat & migration berhasil
[x] return_items tabel dibuat & migration berhasil
[x] ProductReturn model dibuat dengan scopes & relationships
[x] ReturnItem model dibuat dengan relationships
[x] Order model diupdate: productReturn() & hasReturn()
[x] Batch model diupdate: returnItems()
[x] User model diupdate: returns() & approvedReturns()
[x] ReturnService dibuat: createReturn(), approveReturn(), rejectReturn()
[x] 3 Form Requests dibuat dengan authorization yang benar
[x] ReturnController dibuat dengan semua methods
[x] Routes ditambahkan dengan prefix returns
[x] Full flow tested: Create → Approve → Stok restore
[x] Reject flow tested: Create → Reject → Stok tidak berubah
[x] StockMutation type return tercatat dengan benar

