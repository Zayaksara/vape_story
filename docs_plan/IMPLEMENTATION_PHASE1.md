# IMPLEMENTATION CHECKLIST - PHASE 1: CORE POS
**Status**: Siap dikerjakan
**Last Updated**: 2025-01-15
**Target**: Sistem bisa transaksi penjualan & tracking stok

---

## 🚀 CARA PAKAI
1. Baca checklist dari atas ke bawah
2. Kerjakan SATU item dalam waktu
3. Centang [x] jika sudah selesai
4. Test dengan command yang disediakan
5. LANJUT ke item berikutnya (JANGAN LOMPAT!)

---

## ⚠️ PENTING: URUTAN TIDAK BOLEH DIUBAH!
Setiap item punya dependencies. Kalau diubah urutannya → ERROR!

---

## 📋 CHECKLIST

### **STEP 1: SETUP ENUMS**
**Waktu estimasi**: 10 menit

#### 1.1 Buat folder Enums
```bash
mkdir -p app/Enums
```

- [ v ] Folder `app/Enums` created

**Test:**
```bash
ls app/Enums
```

---

#### 1.2 Buat PaymentMethod Enum
**File**: `app/Enums/PaymentMethod.php`

```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case EWALLET = 'ewallet';
    case QRIS = 'qris';
}
```

- [ v ] File `app/Enums/PaymentMethod.php` created
- [ v ] Syntax valid

**Test:**
```bash
php -l app/Enums/PaymentMethod.php
php artisan tinker
>>> App\Enums\PaymentMethod::cases();
>>> App\Enums\PaymentMethod::CASH->value;
```

---

#### 1.3 Buat OrderStatus Enum
**File**: `app/Enums/OrderStatus.php`

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
}
```

- [ v ] File `app/Enums/OrderStatus.php` created
- [ v ] Syntax valid

**Test:**
```bash
php -l app/Enums/OrderStatus.php
php artisan tinker
>>> App\Enums\OrderStatus::cases();
>>> App\Enums\OrderStatus::COMPLETED->value;
```

---

#### 1.4 Buat MutationType Enum
**File**: `app/Enums/MutationType.php`

```php
<?php

namespace App\Enums;

enum MutationType: string
{
    case IN = 'in';
    case OUT = 'out';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
}
```

- [ v ] File `app/Enums/MutationType.php` created
- [ v ] Syntax valid

**Test:**
```bash
php -l app/Enums/MutationType.php
php artisan tinker
>>> App\Enums\MutationType::cases();
>>> App\Enums\MutationType::OUT->value;
```

---

### **STEP 2: CATEGORY MODEL & MIGRATION**
**Waktu estimasi**: 20 menit
**Dependencies**: Enums (Step 1)

#### 2.1 Buat Category Migration
**File**: `database/migrations/xxxx_xx_xx_create_categories_table.php`

```bash
php artisan make:migration create_categories_table
```

**Isi file migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ v ] Migration file created
- [ v ] Code sesuai template di atas

---

#### 2.2 Buat Category Model
**File**: `app/Models/Category.php`

```bash
php artisan make:model Category
```

**Isi file model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ v ] Model file created
- [ v ] Code sesuai template di atas

**CATATAN PENTING**: `hasMany(Product::class)` akan ERROR dulu karena Product belum dibuat. **INI NORMAL!** Nanti setelah Product dibuat (Step 3), error akan hilang.

---

#### 2.3 Run Migration
```bash
php artisan migrate
```

- [ v ] Migration berhasil
- [ v ] Tabel `categories` created di database

**Test:**
```bash
php artisan tinker
>>> App\Models\Category::create(['name' => 'Liquid', 'slug' => 'liquid']);
>>> App\Models\Category::first();
```

---

### **STEP 3: PRODUCT MODEL & MIGRATION**
**Waktu estimasi**: 30 menit
**Dependencies**: Category (Step 2)

#### 3.1 Buat Product Migration
**File**: `database/migrations/xxxx_xx_xx_create_products_table.php`

```bash
php artisan make:migration create_products_table
```

**Isi file migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->decimal('base_price', 10, 2);
            $table->decimal('nicotine_strength', 5, 2)->nullable();

            // Liquid-specific
            $table->string('flavor')->nullable();
            $table->decimal('size_ml', 5, 2)->nullable();

            // Device-specific
            $table->integer('battery_mah')->nullable();
            $table->string('coil_type')->nullable();

            // Pod-specific
            $table->string('pod_type')->nullable();
            $table->decimal('resistance_ohm', 5, 2)->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category_id');
            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

- [ v ] Migration file created
- [ v ] Code sesuai template di atas

*NOTE SAYA MENAMBAHKAN:
            $table->string('brand', 255)->nullable()->comment('Product brand');
            $table->string('pod_type', 255)->nullable()->comment('Pod type for pod systems');
            $table->string('device_type', 255)->nullable()->comment('Device type: mod, pod, etc.');
---

#### 3.2 Buat Product Model
**File**: `app/Models/Product.php`

```bash
php artisan make:model Product
```

**Isi file model:**
```php
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
        'base_price',
        'nicotine_strength',
        'flavor',
        'size_ml',
        'battery_mah',
        'coil_type',
        'pod_type',
        'resistance_ohm',
        'description',
        'is_active'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'nicotine_strength' => 'decimal:2',
        'flavor' => 'string',
        'size_ml' => 'decimal:2',
        'battery_mah' => 'integer',
        'coil_type' => 'string',
        'pod_type' => 'string',
        'resistance_ohm' => 'decimal:2',
        'is_active' => 'boolean',
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
}
```

- [ ] Model file created
- [ ] Code sesuai template di atas

**CATATAN PENTING**: `hasMany(Batch::class)` akan ERROR dulu karena Batch belum dibuat. **INI NORMAL!** Nanti setelah Batch dibuat (Step 4), error akan hilang.

---

#### 3.3 Run Migration
```bash
php artisan migrate
```

- [ v ] Migration berhasil
- [ v ] Tabel `products` created di database

**Test:**
```bash
php artisan tinker
>>> $cat = App\Models\Category::first();
>>> $cat->products()->create([
... 'code' => 'VGOD-001',
... 'name' => 'VGOD TrickLyfe',
... 'category_id' => $cat->id,
... 'base_price' => 150000,
... 'nicotine_strength' => 3.00,
... 'flavor' => 'Mango'
... ]);
>>> App\Models\Product::first()->category;
```

---

### **STEP 4: BATCH MODEL & MIGRATION**
**Waktu estimasi**: 25 menit
**Dependencies**: Product (Step 3)

#### 4.1 Buat Batch Migration
**File**: `database/migrations/xxxx_xx_xx_create_batches_table.php`

```bash
php artisan make:migration create_batches_table
```

**Isi file migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('lot_number');
            $table->date('expired_date');
            $table->integer('stock_quantity')->default(0);
            $table->decimal('cost_price', 10, 2);
            $table->timestamps();

            $table->index('product_id');
            $table->index('expired_date');
            $table->index('lot_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
```

- [ v ] Migration file created
- [ v ] Code sesuai template di atas

---

#### 4.2 Buat Batch Model
**File**: `app/Models/Batch.php`

```bash
php artisan make:model Batch
```

**Isi file model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'lot_number',
        'expired_date',
        'stock_quantity',
        'cost_price'
    ];

    protected $casts = [
        'expired_date' => 'date',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }

    public function scopeOldestExpiry($query)
    {
        return $query->orderBy('expired_date', 'asc');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }
}
```

- [ v] Model file created
- [ v] Code sesuai template di atas

**CATATAN PENTING**: `hasMany(OrderItem::class)` dan `hasMany(StockMutation::class)` akan ERROR dulu karena belum dibuat. **INI NORMAL!**

---

#### 4.3 Run Migration
```bash
php artisan migrate
```

- [ v] Migration berhasil
- [ v] Tabel `batches` created di database

**Test:**
```bash
php artisan tinker
>>> $prod = App\Models\Product::first();
>>> $prod->batches()->create([
... 'lot_number' => 'LOT-2024-001',
... 'expired_date' => '2025-12-31',
... 'stock_quantity' => 100,
... 'cost_price' => 100000
... ]);
>>> App\Models\Batch::oldestExpiry()->get();
>>> App\Models\Batch::inStock()->get();
```

---

### **STEP 5: ORDER MODEL & MIGRATION**
**Waktu estimasi**: 30 menit
**Dependencies**: Enums (Step 1), User (sudah ada)

#### 5.1 Buat Order Migration
**File**: `database/migrations/xxxx_xx_xx_create_orders_table.php`

```bash
php artisan make:migration create_orders_table
```

**Isi file migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->string('invoice_number')->unique();
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2);
            $table->decimal('change_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'ewallet', 'qris']);
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded']);
            $table->string('idempotency_key')->unique()->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('cashier_id');
            $table->index('invoice_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

- [ v] Migration file created
- [ v] Code sesuai template di atas

---

#### 5.2 Buat Order Model
**File**: `app/Models/Order.php`

```bash
php artisan make:model Order
```

**Isi file model:**
```php
<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'invoice_number',
        'cashier_id',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'idempotency_key',
        'notes'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'status' => OrderStatus::class,
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }
}
```

- [ v] Model file created
- [ v] Code sesuai template di atas

**CATATAN PENTING**: `hasMany(OrderItem::class)` akan ERROR dulu. **INI NORMAL!**

---

#### 5.3 Run Migration
```bash
php artisan migrate
```

- [ v] Migration berhasil
- [ v] Tabel `orders` created di database

**Test:**
```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> $order = App\Models\Order::create([
... 'invoice_number' => 'INV-2024-0001',
... 'cashier_id' => $user->id,
... 'total_amount' => 150000,
... 'paid_amount' => 200000,
... 'change_amount' => 50000,
... 'payment_method' => 'cash',
... 'status' => 'completed'
... ]);
>>> $order->cashier;
>>> $order->payment_method->value;
```

---

### **STEP 6: ORDERITEM MODEL & MIGRATION**
**Waktu estimasi**: 25 menit
**Dependencies**: Order (Step 5), Batch (Step 4)

#### 6.1 Buat OrderItem Migration
**File**: `database/migrations/xxxx_xx_xx_create_order_items_table.php`

```bash
php artisan make:migration create_order_items_table
```

**Isi file migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->index('order_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
```

- [ v] Migration file created
- [ v] Code sesuai template di atas

---

#### 6.2 Buat OrderItem Model
**File**: `app/Models/OrderItem.php`

```bash
php artisan make:model OrderItem
```

**Isi file model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'batch_id',
        'product_name',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
```

- [ v] Model file created
- [ v] Code sesuai template di atas

---

#### 6.3 Run Migration
```bash
php artisan migrate
```

- [ ] Migration berhasil
- [ ] Tabel `order_items` created di database

**Test:**
```bash
php artisan tinker
>>> $order = App\Models\Order::first();
>>> $batch = App\Models\Batch::first();
>>> $order->orderItems()->create([
... 'batch_id' => $batch->id,
... 'product_name' => 'VGOD TrickLyfe',
... 'quantity' => 1,
... 'unit_price' => 150000,
... 'subtotal' => 150000
... ]);
>>> App\Models\OrderItem::first()->order;
>>> App\Models\OrderItem::first()->batch;
```

---

### **STEP 7: STOCKMUTATION MODEL & MIGRATION**
**Waktu estimasi**: 25 menit
**Dependencies**: Batch (Step 4), Enums (Step 1)

#### 7.1 Buat StockMutation Migration
**File**: `database/migrations/xxxx_xx_xx_create_stock_mutations_table.php`

```bash
php artisan make:migration create_stock_mutations_table
```

**Isi file migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->enum('mutation_type', ['in', 'out', 'adjustment', 'return']);
            $table->integer('quantity');
            $table->uuidMorphs('reference');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('mutation_type');
            $table->index('reference_type');
            $table->index('reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
```

- [ v] Migration file created
- [ v] Code sesuai template di atas

---

#### 7.2 Buat StockMutation Model
**File**: `app/Models/StockMutation.php`

```bash
php artisan make:model StockMutation
```

**Isi file model:**
```php
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
```

- [ v] Model file created
- [ v] Code sesuai template di atas

---

#### 7.3 Run Migration
```bash
php artisan migrate
```

- [ v] Migration berhasil
- [ v] Tabel `stock_mutations` created di database

**Test:**
```bash
php artisan tinker
>>> $batch = App\Models\Batch::first();
>>> $batch->stockMutations()->create([
... 'mutation_type' => 'out',
... 'quantity' => -1,
... 'reference_type' => null,
... 'reference_id' => null,
... 'notes' => 'Manual adjustment'
... ]);
>>> App\Models\StockMutation::first()->batch;
>>> App\Models\StockMutation::out()->get();
```

---

### **STEP 8: UPDATE USER MODEL**
**Waktu estimasi**: 10 menit
**Dependencies**: Order (Step 5) - SUDAH SELESAI

#### 8.1 Update User Model
**File**: `app/Models/User.php`

**Buka file dan TAMBAHKAN ini di bagian atas (setelah use statements yang sudah ada):**
```php
use App\Models\Order;
```

**Lalu di dalam class User, TAMBAHKAN methods ini:**
```php
public function orders()
{
    return $this->hasMany(Order::class, 'cashier_id');
}
```

- [ v ] User model updated
- [ v ] `use App\Models\Order;` added
- [ v ] `orders()` method added

**CATATAN**: Return, ChatbotConversation akan ditambah di Phase 3 & 4. Jangan ditambah sekarang!

---

**Test:**
```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> $user->orders;
>>> $user->orders()->get();
```

---

### **STEP 9: BATCH OBSERVER**
**Waktu estimasi**: 20 menit
**Dependencies**: Batch (Step 4), StockMutation (Step 7)

#### 9.1 Buat BatchObserver
**File**: `app/Observers/BatchObserver.php`

```bash
mkdir -p app/Observers
```

**Isi file observer:**
```php
<?php

namespace App\Observers;

use App\Models\Batch;
use App\Models\StockMutation;
use App\Enums\MutationType;

class BatchObserver
{
    public function updated(Batch $batch)
    {
        if ($batch->isDirty('stock_quantity')) {
            $oldQuantity = $batch->getOriginal('stock_quantity');
            $newQuantity = $batch->stock_quantity;
            $difference = $newQuantity - $oldQuantity;

            $mutationType = $difference > 0 ? MutationType::IN : MutationType::OUT;

            StockMutation::create([
                'batch_id' => $batch->id,
                'mutation_type' => $mutationType,
                'quantity' => $difference,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => 'Automatic stock adjustment'
            ]);
        }
    }
}
```

- [ v ] Observer file created
- [ v ] Code sesuai template di atas

---

#### 9.2 Register Observer
**File**: `app/Providers/EventServiceProvider.php`

**Buka file dan TAMBAHKAN:**

Di bagian atas (after namespace):
```php
use App\Models\Batch;
use App\Observers\BatchObserver;
```

Di dalam `boot()` method:
```php
Batch::observe(BatchObserver::class);
```

- [ ] EventServiceProvider updated
- [ ] Observer registered

**Test:**
```bash
php artisan tinker
>>> $batch = App\Models\Batch::first();
>>> $batch->stock_quantity = 50;
>>> $batch->save();
>>> App\Models\StockMutation::where('batch_id', $batch->id)->latest()->first();
```

---

### **STEP 10: FINAL TESTING**
**Waktu estimasi**: 15 menit

#### 10.1 Test Semua Relationships
```bash
php artisan tinker

# Category -> Product
>>> $cat = App\Models\Category::with('products')->first();
>>> $cat->products;

# Product -> Batch
>>> $prod = App\Models\Product::with('batches')->first();
>>> $prod->batches;

# Batch -> OrderItem & StockMutation
>>> $batch = App\Models\Batch::with(['orderItems', 'stockMutations'])->first();
>>> $batch->orderItems;
>>> $batch->stockMutations;

# Order -> OrderItem -> Batch
>>> $order = App\Models\Order::with(['orderItems.batch'])->first();
>>> $order->orderItems->first()->batch;

# User -> Order
>>> $user = App\Models\User::with('orders')->first();
>>> $user->orders;
```

- [ ] Semua relationships berjalan normal
- [ ] Tidak ada error

---

#### 10.2 Test Semua Scopes
```bash
php artisan tinker

# Scopes
>>> App\Models\Category::active()->get();
>>> App\Models\Product::active()->get();
>>> App\Models\Batch::oldestExpiry()->get();
>>> App\Models\Batch::inStock()->get();
>>> App\Models\Order::completed()->get();
>>> App\Models\StockMutation::out()->get();
>>> App\Models\StockMutation::in()->get();
```

- [ ] Semua scopes berjalan normal
- [ ] Hasil sesuai expected

---

#### 10.3 Test Enum Casting
```bash
php artisan tinker

>>> $order = App\Models\Order::first();
>>> $order->payment_method; // Harus return enum instance
>>> $order->payment_method->value; // 'cash', 'transfer', dll

>>> $mutation = App\Models\StockMutation::first();
>>> $mutation->mutation_type->value; // 'in', 'out', dll
```

- [ ] Enum casting berjalan normal
- [ ] Bisa akses enum value

---

#### 10.4 Test Observer
```bash
php artisan tinker

>>> $batch = App\Models\Batch::first();
>>> $oldStock = $batch->stock_quantity;
>>> $batch->update(['stock_quantity' => $oldStock - 5]);
>>> App\Models\StockMutation::where('batch_id', $batch->id)->latest()->first();
```

- [ ] Stock mutation auto-created
- [ ] Mutation type = 'out'
- [ ] Quantity = -5

---

## ✅ COMPLETION CHECKLIST

Setelah semua steps selesai, pastikan:

- [ ] 6 Enums created (PaymentMethod, OrderStatus, MutationType)
- [ ] 6 Models created (Category, Product, Batch, Order, OrderItem, StockMutation)
- [ ] 6 Migrations created & run
- [ ] 6 Tabel ada di database
- [ ] User model updated dengan relationship ke Order
- [ ] BatchObserver created & registered
- [ ] Semua relationships tested
- [ ] Semua scopes tested
- [ ] Semua enums casting tested
- [ ] Observer auto-record stock mutations

---

## 🎯 RESULT

Setelah selesai, sistem Anda bisa:
- ✅ Manage kategori produk
- ✅ Manage produk dengan atribut Liquid/Device/Pod
- ✅ Manage batch dengan expiry date & stok
- ✅ Transaksi penjualan (POS)
- ✅ Tracking stok otomatis (stock mutations)

---

## 📞 NEXT STEPS

Kalau semua steps di atas SUDAH SELESAI dan TIDAK ADA ERROR:

1. **Beri tahu saya** - Saya akan audit hasil kerja Anda
2. **Siap untuk Phase 2** - Inventory management
3. **Atau tanya saya** - Kalau ada error atau bingung

---

## 🐛 TROUBLESHOOTING

### Error: Class not found
**Cause**: File belum dibuat atau namespace salah
**Solution**: Cek file sudah dibuat? Cek namespace sesuai path?

### Error: SQLSTATE[HY000]: General error
**Cause**: Tabel belum dibuat
**Solution**: Run `php artisan migrate`

### Error: Relationship method not found
**Cause**: Method belum ditambah di model
**Solution**: Tambah method relationship di model

### Error: Invalid enum
**Cause**: Enum value salah
**Solution**: Cek enum definition di file Enum

---

## 💡 TIPS

1. **Jangan lompat step** - Setiap step punya dependencies
2. **Test setiap step** - Jangan lanjut kalau test gagal
3. **Baca error message** - Biasanya jelas masalahnya di mana
4. **Gunakan tinker** - Cara cepat test code
5. **Simpan progress** - Centang [x] setiap step yang selesai

---

**Good luck! Semangat! 🚀**

Kalau ada error, copy paste error-nya dan cek bagian Troubleshooting.
