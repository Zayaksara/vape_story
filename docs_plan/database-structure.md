# Struktur Database & Relasi ERD - POS Vape Store

## Tabel Database (12 Tabel)

### 1. users
**Tujuan**: Menyimpan data pengguna (Admin & Kasir)

**Field Utama**:
- id (uuid, primary key)
- name
- email (unique)
- password
- role (enum: admin, kasir)
- email_verified_at
- timestamps
- soft_deletes

---

### 2. categories
**Tujuan**: Kategori produk (Liquid, Device, Coil, dll)

**Field Utama**:
- id (uuid, primary key)
- name
- slug (unique)
- description
- is_active (boolean)
- timestamps
- soft_deletes

---

### 3. products
**Tujuan**: Master data produk vape

**Field Utama**:
- id (uuid, primary key)
- code (SKU, unique)
- name
- category_id (foreign key)
- base_price (decimal)
- nicotine_strength (decimal, mg)
- description
- is_active (boolean)
- timestamps
- soft_deletes

---

### 4. batches
**Tujuan**: Tracking batch produk dengan expiry date

**Field Utama**:
- id (uuid, primary key)
- product_id (foreign key)
- lot_number
- expired_date
- stock_quantity (integer)
- cost_price (decimal)
- timestamps
- soft_deletes

---

### 5. orders
**Tujuan**: Transaksi penjualan (header)

**Field Utama**:
- id (uuid, primary key)
- invoice_number (unique)
- cashier_id (foreign key ke users)
- total_amount (decimal)
- discount_amount (decimal)
- tax_amount (decimal)
- paid_amount (decimal)
- change_amount (decimal)
- payment_method (enum: cash, transfer, ewallet, qris)
- status (enum: pending, completed, cancelled, refunded)
- idempotency_key (unique)
- notes
- timestamps
- soft_deletes

---

### 6. order_items
**Tujuan**: Detail item dalam transaksi

**Field Utama**:
- id (uuid, primary key)
- order_id (foreign key)
- batch_id (foreign key)
- product_name (snapshot)
- quantity (integer)
- unit_price (decimal)
- discount_amount (decimal)
- subtotal (decimal)
- timestamps

---

### 7. returns
**Tujuan**: Header pengembalian barang

**Field Utama**:
- id (uuid, primary key)
- return_number (unique)
- order_id (foreign key)
- cashier_id (foreign key ke users)
- reason (text)
- status (enum: pending, approved, rejected, processed)
- approved_by (foreign key ke users, nullable)
- approved_at (timestamp, nullable)
- notes
- timestamps
- soft_deletes

---

### 8. return_items
**Tujuan**: Detail item pengembalian

**Field Utama**:
- id (uuid, primary key)
- return_id (foreign key)
- batch_id (foreign key)
- product_name (snapshot)
- quantity (integer)
- unit_price (decimal)
- subtotal (decimal)
- timestamps

---

### 9. stock_mutations
**Tujuan**: Tracking semua mutasi stok

**Field Utama**:
- id (uuid, primary key)
- batch_id (foreign key)
- mutation_type (enum: in, out, adjustment, return)
- quantity (integer)
- reference_type (polymorphic: Order, Return, etc)
- reference_id (uuid, nullable)
- notes
- timestamps

---

### 10. chatbot_conversations
**Tujuan**: Sesi percakapan chatbot (Admin Dashboard)

**Field Utama**:
- id (uuid, primary key)
- user_id (foreign key ke users)
- title
- status (active, archived)
- metadata (json)
- timestamps

---

### 11. chatbot_messages
**Tujuan**: Pesan dalam percakapan chatbot

**Field Utama**:
- id (uuid, primary key)
- conversation_id (foreign key)
- content (text)
- role (enum: user, assistant)
- metadata (json)
- timestamps

---

### 12. chatbot_knowledge_base
**Tujuan**: Knowledge base untuk chatbot

**Field Utama**:
- id (uuid, primary key)
- category
- question
- answer
- keywords (array)
- priority (integer)
- is_active (boolean)
- timestamps
- soft_deletes

---

## RELASI ANTAR TABEL (ERD)

### Diagram Relasi

```
users (Admin & Kasir)
  ├──→ orders (cashier_id) [One to Many]
  │     └──→ order_items (order_id) [One to Many]
  │
  ├──→ returns (cashier_id) [One to Many]
  │     └──→ return_items (return_id) [One to Many]
  │
  ├──→ returns (approved_by) [One to Many]
  │
  └──→ chatbot_conversations (user_id) [One to Many]
        └──→ chatbot_messages (conversation_id) [One to Many]

categories
  └──→ products (category_id) [One to Many]
        └──→ batches (product_id) [One to Many]
              ├──→ order_items (batch_id) [One to Many]
              ├──→ return_items (batch_id) [One to Many]
              └──→ stock_mutations (batch_id) [One to Many]

orders
  ├──→ order_items (order_id) [One to Many]
  └──→ returns (order_id) [One to One]

returns
  └──→ return_items (return_id) [One to Many]
```

---

## DETAIL RELASI (LENGKAP)

### 1. users

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → orders | orders | One to Many | Satu user (kasir) bisa membuat banyak order |
| → processed_returns | returns | One to Many | Satu user (kasir) bisa memproses banyak return |
| → approved_returns | returns | One to Many | Satu user (admin) bisa menyetujui banyak return |
| → chatbot_conversations | chatbot_conversations | One to Many | Satu user bisa memiliki banyak percakapan |

---

### 2. categories

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → products | products | One to Many | Satu kategori memiliki banyak produk |

---

### 3. products

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → category | categories | Many to One | Banyak produk belong to satu kategori |
| → batches | batches | One to Many | Satu produk memiliki banyak batch |

---

### 4. batches

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → product | products | Many to One | Banyak batch belong to satu produk |
| → order_items | order_items | One to Many | Satu batch bisa terjual di banyak order item |
| → return_items | return_items | One to Many | Satu batch bisa direturn di banyak return item |
| → stock_mutations | stock_mutations | One to Many | Satu batch memiliki banyak mutasi stok |

---

### 5. orders

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → cashier | users | Many to One | Banyak order dibuat oleh satu kasir |
| → items | order_items | One to Many | Satu order memiliki banyak item |
| → return | returns | One to One | Satu order hanya bisa memiliki satu return |

---

### 6. order_items

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → order | orders | Many to One | Banyak item belong to satu order |
| → batch | batches | Many to One | Banyak item diambil dari satu batch |

---

### 7. returns

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → order | orders | Many to One | Banyak return belong to satu order |
| → cashier | users | Many to One | Banyak return diproses oleh satu kasir |
| → approver | users | Many to One | Banyak return disetujui oleh satu admin |
| → items | return_items | One to Many | Satu return memiliki banyak item |

---

### 8. return_items

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → return | returns | Many to One | Banyak item belong to satu return |
| → batch | batches | Many to One | Banyak item dikembalikan ke satu batch |

---

### 9. stock_mutations

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → batch | batches | Many to One | Banyak mutasi belong to satu batch |
| → reference | polymorphic | - | Bisa refer ke Order, Return, atau lainnya |

---

### 10. chatbot_conversations

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → user | users | Many to One | Banyak percakapan belong to satu user |
| → messages | chatbot_messages | One to Many | Satu percakapan memiliki banyak pesan |

---

### 11. chatbot_messages

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| → conversation | chatbot_conversations | Many to One | Banyak pesan belong to satu percakapan |

---

### 12. chatbot_knowledge_base

| Relasi | Tabel Tujuan | Tipe | Keterangan |
|--------|-------------|------|-----------|
| - | - | - | Tidak ada relasi (standalone table) |

---

## RINGKASAN RELASI

### Total Relasi:

**One to Many (15 relasi)**:
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

**Many to One (9 relasi)**:
1. products → categories
2. batches → products
3. order_items → orders
4. order_items → batches
5. returns → orders
6. returns → users (cashier)
7. returns → users (approver)
8. return_items → returns
9. return_items → batches
10. stock_mutations → batches
11. chatbot_conversations → users
12. chatbot_messages → chatbot_conversations

**One to One (1 relasi)**:
1. orders → returns

**Polymorphic (1 relasi)**:
1. stock_mutations → reference (Order, Return, etc)

---

## ERD DIAGRAM (SIMPLIFIED)

```
┌─────────────┐
│   users     │
│ (Admin/Kasir)│
└──────┬──────┘
       │
       ├─── orders ──── order_items ──── batches ──── products ──── categories
       │      │           │                  │
       │      │           │                  └─── stock_mutations
       │      │           │
       │      │           └─── return_items ──┐
       │      │                                │
       │      └─── returns ────────────────────┘
       │
       └─── chatbot_conversations ──── chatbot_messages

chatbot_knowledge_base (standalone)
```

---

## FLOW UTAMA

### Alur Penjualan:
1. users (kasir) → buat order
2. products → pilih batch (FIFO by expiry)
3. batches → decrease stock
4. stock_mutations → record mutation (type: out)
5. order_items → simpan detail transaksi

### Alur Return:
1. users (kasir) → buat return
2. returns → pending approval
3. users (admin) → approve/reject
4. jika approved: batches → increase stock
5. stock_mutations → record mutation (type: return)
6. return_items → simpan detail return

### Alur Chatbot:
1. users (admin) → buat conversation
2. chatbot_messages → kirim pesan
3. chatbot_knowledge_base → cari jawaban
