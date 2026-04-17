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
        Schema::create('tally_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('name');
            $table->string('parent')->nullable();
            $table->string('base_unit', 50)->nullable();
            $table->decimal('opening_balance_qty', 18, 4)->default(0);
            $table->decimal('opening_balance_value', 18, 2)->default(0);
            $table->decimal('opening_rate', 18, 2)->default(0);
            $table->decimal('closing_balance_qty', 18, 4)->default(0);
            $table->decimal('closing_balance_value', 18, 2)->default(0);
            $table->boolean('has_batches')->default(false);
            $table->string('hsn_code', 20)->nullable();
            $table->json('tally_raw_data')->nullable();
            $table->string('data_hash', 32)->nullable();
            $table->timestamps();

            $table->unique(['tally_connection_id', 'name']);
            $table->index(['tally_connection_id', 'parent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_stock_items');
    }
};
