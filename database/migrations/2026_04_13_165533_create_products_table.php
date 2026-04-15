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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 50)->unique()->comment('Unique product code');
            $table->string('name', 255);
            $table->foreignUuid('category_id')->constrained()->onDelete('cascade');
            $table->decimal('base_price', 10, 2)->nullable()->comment('Base price before tax and discounts');
            $table->decimal('nicotine_strength', 5, 2)->nullable()->comment('Nicotine strength in mg/ml');


            $table->string('flavor', 255)->nullable()->comment('Product flavor');
            $table->decimal('size_ml', 5, 2)->nullable()->comment('Size in milliliters');

            $table->integer('battery_mah')->nullable()->comment('Battery capacity in mAh for devices');
            $table->string('coil_type', 255)->nullable()->comment('Coil type for devices');

            $table->string('brand', 255)->nullable()->comment('Product brand');
            $table->string('pod_type', 255)->nullable()->comment('Pod type for pod systems');
            $table->string('device_type', 255)->nullable()->comment('Device type: mod, pod, etc.');

            $table->text('description')->nullable()->comment('Detailed product description');
            $table->boolean('is_active')->default(true)->comment('Whether the product is active and available for sale');
            $table->timestamps();

            // Indexes
            $table->index('category_id');
            $table->index('code');
            $table->index('is_active');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
