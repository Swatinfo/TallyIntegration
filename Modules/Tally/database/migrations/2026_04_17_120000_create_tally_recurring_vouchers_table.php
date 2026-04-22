<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_recurring_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('name');
            $table->string('voucher_type', 30);                      // VoucherType enum value
            $table->string('frequency', 20);                         // daily|weekly|monthly|quarterly|yearly
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-28 for monthly/quarterly/yearly
            $table->unsignedTinyInteger('day_of_week')->nullable();  // 0-6 (Sun-Sat) for weekly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_at');
            $table->timestamp('last_run_at')->nullable();
            $table->json('last_run_result')->nullable();
            $table->json('voucher_template');                        // {DATE will be auto-injected} payload
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tally_connection_id', 'is_active', 'next_run_at'], 'recurring_vch_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_recurring_vouchers');
    }
};
