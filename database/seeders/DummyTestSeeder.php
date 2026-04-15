<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Batch;
use Illuminate\Database\Seeder;

class DummyTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Admin User (cek dulu - pake factory untuk password)
        User::firstOrCreate(
            ['email' => 'admin@vapestore.test'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'role' => 'admin',
            ]
        );
        $admin = User::where('email', 'admin@vapestore.test')->first();
        echo "Admin: {$admin->id}\n";

        // 2. Create Cashier User (cek dulu)
        User::firstOrCreate(
            ['email' => 'kasir@vapestore.test'],
            [
                'name' => 'Kasir Test',
                'password' => 'password',
                'role' => 'cashier',
            ]
        );
        $cashier = User::where('email', 'kasir@vapestore.test')->first();
        echo "Cashier: {$cashier->id}\n";

        // 3. Get existing Category
        $category = Category::first();
        if (!$category) {
            $category = Category::create([
                'name' => 'Vape Device',
                'slug' => 'vape-device-default',
                'is_active' => true,
            ]);
        }
        echo "Category: {$category->id}\n";

        // 4. Get existing Products or create new
        $product1 = Product::where('code', 'PROD-TEST-001')->first();
        if (!$product1) {
            $product1 = Product::create([
                'category_id' => $category->id,
                'code' => 'PROD-TEST-001',
                'name' => 'Vape Mod Test',
                'base_price' => 150000,
                'brand' => 'Test Brand',
                'is_active' => true,
            ]);
        }

        $product2 = Product::where('code', 'PROD-TEST-002')->first();
        if (!$product2) {
            $product2 = Product::create([
                'category_id' => $category->id,
                'code' => 'PROD-TEST-002',
                'name' => 'Liquid Vape Test',
                'base_price' => 50000,
                'flavor' => 'Strawberry',
                'is_active' => true,
            ]);
        }
        echo "Products: {$product1->id}, {$product2->id}\n";

        // 5. Create Batches dengan different expiry dates (untuk FIFO test)
        // Batch 1 - Expired Mei (lebih dulu - harus diambil duluan)
        Batch::firstOrCreate(
            ['lot_number' => 'LOT-MAY-TEST'],
            [
                'product_id' => $product1->id,
                'expired_date' => '2026-05-01',
                'stock_quantity' => 10,
                'cost_price' => 100000,
            ]
        );

        // Batch 2 - Expired Juni (lebih belakang)
        Batch::firstOrCreate(
            ['lot_number' => 'LOT-JUN-TEST'],
            [
                'product_id' => $product1->id,
                'expired_date' => '2026-06-01',
                'stock_quantity' => 15,
                'cost_price' => 100000,
            ]
        );

        // Batch 3 - Expired Juli (paling belakang)
        Batch::firstOrCreate(
            ['lot_number' => 'LOT-JUL-TEST'],
            [
                'product_id' => $product1->id,
                'expired_date' => '2026-07-01',
                'stock_quantity' => 20,
                'cost_price' => 100000,
            ]
        );

        // Batch untuk product 2
        Batch::firstOrCreate(
            ['lot_number' => 'LOT-LIQ-TEST'],
            [
                'product_id' => $product2->id,
                'expired_date' => '2026-12-01',
                'stock_quantity' => 50,
                'cost_price' => 30000,
            ]
        );

        $batches = Batch::all();
        echo "Batches: ";
        foreach($batches as $b) { echo $b->id . ' '; }
        echo "\n";
        echo "\n=== DUMMY DATA READY ===\n";
        echo "Admin: {$admin->email} ({$admin->id})\n";
        echo "Cashier: {$cashier->email} ({$cashier->id})\n";
        echo "Product 1: {$product1->name} ({$product1->id})\n";
        echo "FIFO Test: Batch Mei harus dipake duluan\n";
    }
}