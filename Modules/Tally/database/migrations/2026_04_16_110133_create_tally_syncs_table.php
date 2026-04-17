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
        Schema::create('tally_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('entity_type', 30); // ledger, voucher, stock_item, group
            $table->unsignedBigInteger('entity_id');
            $table->string('tally_name')->nullable();
            $table->string('tally_master_id')->nullable();
            $table->string('sync_direction', 20)->default('bidirectional'); // to_tally, from_tally, bidirectional
            $table->string('sync_status', 20)->default('pending'); // pending, in_progress, completed, failed, conflict
            $table->string('priority', 10)->default('normal'); // low, normal, high, critical
            $table->string('local_data_hash', 32)->nullable();
            $table->string('tally_data_hash', 32)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_sync_attempt')->nullable();
            $table->unsignedSmallInteger('sync_attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->json('conflict_data')->nullable();
            $table->string('resolution_strategy', 20)->nullable(); // manual, erp_wins, tally_wins, merge, newest_wins
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tally_connection_id', 'entity_type', 'entity_id']);
            $table->index(['tally_connection_id', 'sync_status']);
            $table->index(['sync_status', 'priority']);
            $table->index(['tally_connection_id', 'entity_type', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_syncs');
    }
};
