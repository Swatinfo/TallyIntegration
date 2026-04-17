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
        Schema::create('tally_response_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->nullable()->constrained('tally_connections')->cascadeOnDelete();
            $table->string('endpoint', 100);
            $table->unsignedInteger('response_time_ms');
            $table->string('status', 20); // success, error, timeout
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_response_metrics');
    }
};
