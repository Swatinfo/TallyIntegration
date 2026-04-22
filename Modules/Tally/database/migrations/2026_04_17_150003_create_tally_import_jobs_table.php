<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('entity_type', 30);                   // ledger|group|stock_item|voucher|...
            $table->string('file_disk', 50);
            $table->string('file_path', 500);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('status', 20)->default('queued');     // queued|running|completed|failed
            $table->json('result_summary')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tally_connection_id', 'status'], 'import_job_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_import_jobs');
    }
};
