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

            // ✅ Pakai foreignUuid karena users.id adalah uuid
            $table->foreignUuid('cashier_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->onDelete('set null');

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