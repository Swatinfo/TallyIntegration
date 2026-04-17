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
        Schema::create('tally_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('voucher_number')->nullable();
            $table->string('tally_master_id')->nullable();
            $table->string('voucher_type', 30);
            $table->date('date');
            $table->string('party_name')->nullable();
            $table->string('narration', 500)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->json('ledger_entries')->nullable();
            $table->json('inventory_entries')->nullable();
            $table->json('bill_allocations')->nullable();
            $table->json('tally_raw_data')->nullable();
            $table->string('data_hash', 32)->nullable();
            $table->timestamps();

            $table->index(['tally_connection_id', 'voucher_type']);
            $table->index(['tally_connection_id', 'date']);
            $table->index(['tally_connection_id', 'party_name']);
            $table->index(['tally_connection_id', 'voucher_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_vouchers');
    }
};
