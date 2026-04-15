<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
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