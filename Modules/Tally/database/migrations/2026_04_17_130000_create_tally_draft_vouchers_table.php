<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_draft_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('voucher_type', 30);
            $table->json('voucher_data');                            // the full payload
            $table->string('narration', 500)->nullable();
            $table->decimal('amount', 18, 2)->default(0);            // extracted for threshold queries
            $table->string('status', 20)->default('draft');          // draft|submitted|approved|rejected|pushed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->json('push_result')->nullable();
            $table->string('tally_master_id')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->index(['tally_connection_id', 'status'], 'draft_vch_status_idx');
            $table->index(['tally_connection_id', 'amount'], 'draft_vch_amount_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_draft_vouchers');
    }
};
