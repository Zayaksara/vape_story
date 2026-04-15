<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('product_id')->constrained()->onDelete('cascade');
            $table->string('lot_number')->comment('Batch/lot number');
            $table->date('expired_date')->comment('Product expiration date');
            $table->integer('stock_quantity')->default(0)->comment('Current stock');
            $table->decimal('cost_price', 10, 2)->nullable()->comment('Cost price for this batch');
            $table->timestamps();

            $table->index('product_id');
            $table->index('expired_date');
            $table->index('lot_number');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
