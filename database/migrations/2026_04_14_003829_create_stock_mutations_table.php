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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('batch_id')->constrained()->onDelete('cascade');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
