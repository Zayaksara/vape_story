# PROJECT PLAN - SISTEM POS TOKO VAPE
**Project**: Vape Store POS System
**Framework**: Laravel 13 + Inertia.js + Vue.js
**Database**: SQLite
**Last Updated**: 2025-01-15
**Documentation Reference**: `C:\xampp\htdocs\Vape_stor\docs\database-structure.md`

---

## 📋 CONTEXT SAAT INI

### Status Project:
- ✅ Laravel 13 fresh installation
- ✅ Authentication (Fortify) dengan role admin/kasir
- ✅ Inertia.js + Vue.js setup
- ✅ Database (SQLite) connected
- ❌ Business logic POS (BELUM ada)

### Yang Sudah Ada:
1. **Users Table** - Dengan role (admin, kasir) dan 2FA
2. **Authentication System** - Login, register, password reset
3. **Basic Infrastructure** - Cache, jobs, sessions

### Yang Belum Ada:
- Models untuk POS (Product, Order, Batch, dll)
- Business logic transaksi penjualan
- Inventory management
- Stock tracking
- Returns system
- Chatbot system

---

## 🎯 GOAL

Membangun sistem **Point of Sale (POS)** untuk toko vape dengan fitur:
1. Transaksi penjualan (cashier)
2. Management produk dengan batch tracking
3. Inventory & stock management
4. Returns & refunds
5. Chatbot assistant untuk admin
6. Reporting & analytics

---

## 📊 MODEL DATABASE (12 TABEL)

### ✅ SUDAH ADA (1 tabel)
1. **users** - Data admin & kasir

### 🔴 AKAN DIBUAT (11 tabel)

#### 2. **categories**
Kategori produk (Liquid, Device, Coil, Pod, Aksesoris)

**Fields:**
- id (UUID, PK)
- name (string)
- slug (string, unique)
- description (text, nullable)
- is_active (boolean)
- timestamps

**Relationships:**
- hasMany(Product)

---

#### 3. **products**
Master data produk vape

**Fields:**
- id (UUID, PK)
- code (SKU, string, unique)
- name (string)
- category_id (FK ke categories)
- base_price (decimal, 10,2)
- nicotine_strength (decimal, 5,2) - kadar nicotine dalam mg

**⭐ LIQUID-SPECIFIC (nullable):**
- flavor (string) - rasa (Mango, Grape, dll)
- size_ml (decimal, 5,2) - ukuran (30ml, 60ml)

**⭐ DEVICE-SPECIFIC (nullable):**
- battery_mah (integer) - kapasitas battery (2000, 3000)
- coil_type (string) - tipe coil (Mesh, Regular)

**⭐ POD-SPECIFIC (nullable):**
- pod_type (string) - tipe pod (replaceable, refillable)
- resistance_ohm (decimal, 5,2) - resistance coil (0.8, 1.2)

**GENERAL:**
- description (text, nullable)
- is_active (boolean)
- timestamps

**Relationships:**
- belongsTo(Category)
- hasMany(Batch)

**Keputusan:** Field spesifik per jenis produk, bukan JSON metadata

---

#### 4. **batches**
Tracking batch produk dengan expiry date & harga modal per batch

**Fields:**
- id (UUID, PK)
- product_id (FK ke products)
- lot_number (string) - nomor batch dari supplier
- expired_date (date) - tanggal kadaluarsa
- stock_quantity (integer) - jumlah stok di batch ini
- cost_price (decimal, 10,2) - harga modal per unit di batch ini
- timestamps

**Relationships:**
- belongsTo(Product)
- hasMany(OrderItem)
- hasMany(StockMutation)

**Fitur Kunci:**
- FIFO (First In First Out) by expiry date
- Cost price bisa beda per batch
- Expired date tracking untuk vape liquid

---

#### 5. **orders**
Transaksi penjualan (header)

**Fields:**
- id (UUID, PK)
- invoice_number (string, unique) - contoh: "INV-2024-0001"
- cashier_id (FK ke users)
- total_amount (decimal, 10,2)
- discount_amount (decimal, 10,2, default: 0)
- tax_amount (decimal, 10,2, default: 0)
- paid_amount (decimal, 10,2)
- change_amount (decimal, 10,2)
- payment_method (enum: cash, transfer, ewallet, qris)
- status (enum: pending, completed, cancelled, refunded)
- idempotency_key (string, unique, nullable) - mencegah duplicate transaction
- notes (text, nullable)
- timestamps

**Relationships:**
- belongsTo(User, 'cashier_id')
- hasMany(OrderItem)
- hasOne(Return) - Phase 3

**Fitur Kunci:**
- Idempotency key untuk mencegah double posting
- Tidak ada customer (walk-in only)

---

#### 6. **order_items**
Detail item dalam transaksi

**Fields:**
- id (UUID, PK)
- order_id (FK ke orders)
- batch_id (FK ke batches) - ambil dari batch tertentu (FIFO)
- product_name (string) - SNAPSHOT nama produk saat transaksi
- quantity (integer)
- unit_price (decimal, 10,2) - SNAPSHOT harga saat transaksi
- discount_amount (decimal, 10,2, default: 0)
- subtotal (decimal, 10,2)
- timestamps

**Relationships:**
- belongsTo(Order)
- belongsTo(Batch)

**Fitur Kunci:**
- Snapshot data untuk histori akurat
- Meskipun produk di-update/dihapus, data transaksi tetap valid

---

#### 7. **returns**
Header pengembalian barang

**Fields:**
- id (UUID, PK)
- return_number (string, unique) - contoh: "RET-2024-0001"
- order_id (FK ke orders)
- cashier_id (FK ke users)
- reason (text)
- status (enum: pending, approved, rejected, processed)
- approved_by (FK ke users, nullable) - admin yang approve
- approved_at (timestamp, nullable)
- notes (text, nullable)
- timestamps

**Relationships:**
- belongsTo(Order)
- belongsTo(User, 'cashier_id')
- belongsTo(User, 'approved_by')
- hasMany(ReturnItem)

**Flow:**
1. Kasir buat return (status: pending)
2. Admin approve/reject (status: approved/rejected)
3. Jika approved, stok batch dikembalikan (status: processed)

---

#### 8. **return_items**
Detail item pengembalian

**Fields:**
- id (UUID, PK)
- return_id (FK ke returns)
- batch_id (FK ke batches)
- product_name (string) - SNAPSHOT nama produk
- quantity (integer)
- unit_price (decimal, 10,2) - SNAPSHOT harga saat return
- subtotal (decimal, 10,2)
- timestamps

**Relationships:**
- belongsTo(Return)
- belongsTo(Batch)

---

#### 9. **stock_mutations**
Tracking semua mutasi stok (in, out, adjustment, return)

**Fields:**
- id (UUID, PK)
- batch_id (FK ke batches)
- mutation_type (enum: in, out, adjustment, return)
- quantity (integer) - positif (in/adjustment+) atau negatif (out/adjustment-)
- reference_type (string, nullable) - polymorphic: "App\Models\Order", "App\Models\Return"
- reference_id (UUID, nullable) - ID dari reference
- notes (text, nullable)
- timestamps

**Relationships:**
- belongsTo(Batch)
- morphTo(reference) - Polymorphic ke Order/Return/Other

**Fitur Kunci:**
- Polymorphic relation untuk fleksibilitas
- Audit trail lengkap untuk setiap perubahan stok

---

#### 10. **chatbot_conversations**
Sesi percakapan chatbot (Admin Dashboard)

**Fields:**
- id (UUID, PK)
- user_id (FK ke users)
- title (string) - judul percakapan
- status (enum: active, archived)
- metadata (json, nullable) - data tambahan fleksibel
- timestamps

**Relationships:**
- belongsTo(User)
- hasMany(ChatbotMessage)

---

#### 11. **chatbot_messages**
Pesan dalam percakapan chatbot

**Fields:**
- id (UUID, PK)
- conversation_id (FK ke chatbot_conversations)
- content (text) - isi pesan
- role (enum: user, assistant)
- metadata (json, nullable) - data tambahan (token count, model, dll)
- timestamps

**Relationships:**
- belongsTo(ChatbotConversation)

---

#### 12. **chatbot_knowledge_base**
Knowledge base untuk chatbot

**Fields:**
- id (UUID, PK)
- category (string) - kategori pertanyaan
- question (string) - pertanyaan
- answer (text) - jawaban
- keywords (json) - array keywords untuk pencarian
- priority (integer, default: 0) - prioritas (semakin tinggi semakin diutamakan)
- is_active (boolean, default: true)
- timestamps

**Relationships:**
- Tidak ada relasi (standalone table)

---

## 🔗 DIAGRAM RELASI

```
┌─────────────────────────────────────────────────────────────────┐
│                    USERS (Admin/Kasir)                         │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Role: admin, kasir                                        │ │
│  └──────┬─────────────────────────────────────────────────────┘ │
│         │                                                        │
│         ├───→ ORDERS (cashier_id)                               │
│         │     └───→ ORDER_ITEMS (order_id)                      │
│         │            └───→ BATCHES (batch_id) ──┐               │
│         │                                       │               │
│         ├───→ RETURNS (cashier_id)              │               │
│         │     └───→ RETURN_ITEMS (return_id) ────┤               │
│         │                                       │               │
│         ├───→ RETURNS (approved_by)             │               │
│         │                                       ↓               │
│         └───→ CHATBOT_CONVERSATIONS (user_id)   PRODUCTS        │
│               └───→ CHATBOT_MESSAGES            │                │
│                                                   └───→ CATEGORIES
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         BATCHES                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  - lot_number                                               │ │
│  │  - expired_date (FIFO by expiry)                            │ │
│  │  - stock_quantity                                          │ │
│  │  - cost_price (per batch)                                   │ │
│  └──────┬─────────────────────────────────────────────────────┘ │
│         │                                                        │
│         ├───→ ORDER_ITEMS (batch_id)  [Mengurangi stok]         │
│         ├───→ RETURN_ITEMS (batch_id) [Mengembalikan stok]      │
│         └───→ STOCK_MUTATIONS (batch_id) [Audit trail]          │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      STOCK_MUTATIONS                             │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  - mutation_type: in, out, adjustment, return              │ │
│  │  - quantity (positive/negative)                             │ │
│  │  - reference_type (polymorphic: Order/Return/Other)         │ │
│  │  - reference_id (UUID)                                      │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 SUMMARY RELASI

### One to Many (15):
1. users → orders (cashier_id)
2. users → returns (cashier_id)
3. users → returns (approved_by)
4. users → chatbot_conversations
5. categories → products
6. products → batches
7. batches → order_items
8. batches → return_items
9. batches → stock_mutations
10. orders → order_items
11. returns → return_items
12. chatbot_conversations → chatbot_messages

### Many to One (otomatis dari One to Many di atas):
- products → categories
- batches → products
- order_items → orders
- order_items → batches
- returns → orders
- return_items → returns
- return_items → batches
- stock_mutations → batches
- chatbot_conversations → users
- chatbot_messages → chatbot_conversations

### One to One (1):
1. orders → returns (Satu order hanya bisa punya satu return)

### Polymorphic (1):
1. stock_mutations → reference (Bisa refer ke Order, Return, Adjustment)

---

## 🎯 FITUR KUNCI & KEUNGGULAN

### 1. **Batch Tracking dengan Expiry Date**
- Setiap produk punya banyak batch dengan expiry date berbeda
- **FIFO by expiry** - Jual batch yang akan expired duluan
- Cost price per batch (harga modal bisa beda meskipun produk sama)
- Penting untuk vape liquid yang punya shelf life

### 2. **Idempotency Key pada Orders**
- Mencegah **duplicate transaction** jika network error
- Client bisa retry request tanpa takut double posting
- Best practice untuk POS system

### 3. **Snapshot Data pada Order/Return Items**
- `product_name`, `unit_price` di-snapshot saat transaksi
- Meskipun produk di-update/dihapus, data transaksi tetap valid
- Histori akurat untuk audit & reporting

### 4. **Polymorphic Stock Mutations**
- Satu tabel untuk semua jenis mutasi (in, out, adjustment, return)
- Bisa refer ke Order, Return, atau Adjustment manual
- **Audit trail lengkap** untuk setiap perubahan stok

### 5. **Approval Workflow untuk Returns**
- Kasir buat return (status: pending)
- Admin approve/reject (status: approved/rejected)
- Jika approved, stok otomatis dikembalikan ke batch

### 6. **Chatbot dengan Knowledge Base**
- Sistem percakapan untuk Admin Dashboard
- Knowledge base dengan keyword matching & priority
- Metadata JSON untuk fleksibilitas

### 7. **Hard Delete Only**
- Data yang dihapus akan benar-benar hilang dari database
- Tidak bisa di-restore (sesuai keputusan user)
- Lebih hemat storage tapi perlu hati-hati

### 8. **UUID Primary Keys**
- Lebih aman daripada auto-increment ID
- Tidak bisa ditebak (security)
- Support untuk distributed system masa depan

---

## ⚙️ KEPUTUSAN TEKNIS

### 1. **Primary Keys**
✅ **UUID** - Pakai `DB::raw('UUID()')` tanpa package tambahan
- Model: `$incrementing = false`, `$keyType = 'string'`

### 2. **Soft Deletes**
❌ **TIDAK PAKAI** - Hard delete saja (keputusan user)
- Data yang dihapus benar-benar hilang
- Tidak bisa di-restore
- Lebih hemat storage

### 3. **Atribut Produk Khusus**
✅ **Field spesifik per jenis produk**
- Liquid: `flavor`, `size_ml`
- Device: `battery_mah`, `coil_type`
- Pod: `pod_type`, `resistance_ohm`
- Bukan JSON metadata

### 4. **Enums**
✅ **PHP 8.1+ Backed Enums**
- PaymentMethod (cash, transfer, ewallet, qris)
- OrderStatus (pending, completed, cancelled, refunded)
- ReturnStatus (pending, approved, rejected, processed)
- MutationType (in, out, adjustment, return)
- ChatbotConversationStatus (active, archived)
- ChatbotMessageRole (user, assistant)

### 5. **Foreign Key Constraints**
✅ **CASCADE on delete**
- Jika parent dihapus, child ikut terhapus
- Kecuali untuk business logic yang mencegah delete

### 6. **Indexing**
✅ **Index pada kolom yang sering di-query**
- Foreign keys
- Unique fields (slug, code, invoice_number)
- Status fields (is_active, status)
- Date fields (expired_date, created_at)

---

## 🚀 PRIORITAS IMPLEMENTASI

### **PHASE 1: CORE POS** (Minimum Viable Product)
**Target**: Bisa transaksi penjualan

**Models:**
1. ✅ users (SUDAH ADA)
2. 🔴 categories
3. 🔴 products
4. 🔴 batches
5. 🔴 orders
6. 🔴 order_items
7. 🔴 stock_mutations

**Features:**
- Manage kategori produk
- Manage produk dengan atribut spesifik
- Manage batch dengan expiry date
- Transaksi penjualan (POS)
- Tracking stok otomatis

**Hasil:** Sudah bisa jualan & tracking stok

---

### **PHASE 2: INVENTORY MANAGEMENT**
**Target**: Management stok masuk & adjustment

**Features:**
- Stok masuk (type: in)
- Manual adjustment (type: adjustment)
- Reporting stok

**Hasil:** Stok masuk/keluar tertrack lengkap

---

### **PHASE 3: RETURNS & REFUNDS**
**Target**: Sistem pengembalian barang

**Models:**
8. 🔴 returns
9. 🔴 return_items

**Features:**
- Create return request
- Approval workflow (kasir → admin)
- Auto restore stok jika approved
- Return items management

**Hasil:** Bisa proses return dengan approval

---

### **PHASE 4: CHATBOT SYSTEM**
**Target**: AI assistant untuk admin

**Models:**
10. 🔴 chatbot_conversations
11. 🔴 chatbot_messages
12. 🔴 chatbot_knowledge_base

**Features:**
- Chatbot conversations
- Knowledge base dengan keyword matching
- Message history
- AI-powered responses

**Hasil:** Admin bisa tanya jawab dengan AI

---

## 📁 FILE YANG AKAN DIBUAT

### **Models (11 file)**
1. `app/Models/Category.php`
2. `app/Models/Product.php`
3. `app/Models/Batch.php`
4. `app/Models/Order.php`
5. `app/Models/OrderItem.php`
6. `app/Models/Return.php` (bukan ReturnModel karena reserved keyword)
7. `app/Models/ReturnItem.php`
8. `app/Models/StockMutation.php`
9. `app/Models/ChatbotConversation.php`
10. `app/Models/ChatbotMessage.php`
11. `app/Models/ChatbotKnowledgeBase.php`

### **Migrations (11 file)**
1. `create_categories_table.php`
2. `create_products_table.php`
3. `create_batches_table.php`
4. `create_orders_table.php`
5. `create_order_items_table.php`
6. `create_returns_table.php`
7. `create_return_items_table.php`
8. `create_stock_mutations_table.php`
9. `create_chatbot_conversations_table.php`
10. `create_chatbot_messages_table.php`
11. `create_chatbot_knowledge_base_table.php`

### **Enums (6 file)**
1. `app/Enums/PaymentMethod.php`
2. `app/Enums/OrderStatus.php`
3. `app/Enums/ReturnStatus.php`
4. `app/Enums/MutationType.php`
5. `app/Enums/ChatbotConversationStatus.php`
6. `app/Enums/ChatbotMessageRole.php`

### **Observers (1 file)**
1. `app/Observers/BatchObserver.php` - Auto record stock mutations

### **Relationships Update**
1. `app/Models/User.php` - Add relationships ke Order, Return, ChatbotConversation

---

## 🔧 TEKNOLOGI & BEST PRACTICES

### 1. **UUID Primary Keys**
```php
// Di migration
$table->uuid('id')->primary()->default(DB::raw('UUID()'));

// Di model
public $incrementing = false;
protected $keyType = 'string';
```

### 2. **PHP 8.1+ Backed Enums**
```php
// app/Enums/PaymentMethod.php
enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case EWALLET = 'ewallet';
    case QRIS = 'qris';
}

// Di migration
$table->enum('payment_method', ['cash', 'transfer', 'ewallet', 'qris']);

// Di model
protected $casts = [
    'payment_method' => PaymentMethod::class,
];
```

### 3. **Polymorphic Relations**
```php
// StockMutation.php
public function reference()
{
    return $this->morphTo();
}

// Di migration
$table->uuidMorphs('reference');
```

### 4. **Query Scopes**
```php
// Product.php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}

// Batch.php - FIFO by expiry
public function scopeOldestExpiry($query)
{
    return $query->orderBy('expired_date', 'asc');
}

// Usage
$batches = Batch::oldestExpiry()->where('stock_quantity', '>', 0)->get();
```

### 5. **Model Observers**
```php
// BatchObserver.php
public function updated(Batch $batch)
{
    // Auto record stock mutation
    if ($batch->isDirty('stock_quantity')) {
        StockMutation::create([
            'batch_id' => $batch->id,
            'mutation_type' => 'adjustment',
            'quantity' => $batch->stock_quantity - $batch->getOriginal('stock_quantity'),
            'notes' => 'Automatic stock adjustment'
        ]);
    }
}
```

---

## 📝 IMPLEMENTATION CHECKLIST

Untuk implementasi detail, silakan buka file:
**`IMPLEMENTATION_CHECKLIST.md`**

File tersebut berisi:
- Step-by-step checklist
- Code template lengkap
- Test commands
- Troubleshooting guide

---

## 🎯 HASIL AKHIR

Setelah semua phases selesai, sistem akan bisa:

### ✅ Phase 1 (Core POS)
- Manage kategori produk (Liquid, Device, Pod, Coil, dll)
- Manage produk dengan atribut spesifik (flavor, battery, dll)
- Manage batch produk dengan expiry date & cost price per batch
- Transaksi penjualan (POS) dengan FIFO by expiry
- Tracking stok otomatis (stock mutations)
- History lengkap semua mutasi stok

### ✅ Phase 2 (Inventory Management)
- Stok masuk (restock)
- Manual adjustment stok
- Reporting stok

### ✅ Phase 3 (Returns & Refunds)
- Create return request
- Approval workflow
- Auto restore stok
- Return management

### ✅ Phase 4 (Chatbot System)
- Chatbot conversations
- Knowledge base
- AI-powered responses

---

## 📞 SUPPORT

Untuk pertanyaan atau kendala implementasi:
1. Cek file `IMPLEMENTATION_CHECKLIST.md` untuk troubleshooting
2. Review dokumentasi di `C:\xampp\htdocs\Vape_stor\docs\database-structure.md`
3. Test dengan `php artisan tinker` untuk quick testing

---

**Good luck dengan implementasinya! 🚀**

Mulai dari Phase 1, ikuti checklist di `IMPLEMENTATION_CHECKLIST.md`, dan kerjakan step-by-step. Jangan lompat!
