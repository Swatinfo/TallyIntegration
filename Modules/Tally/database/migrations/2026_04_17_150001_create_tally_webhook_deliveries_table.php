<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_webhook_endpoint_id')->constrained('tally_webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 50);
            $table->json('payload');
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status', 20)->default('pending');  // pending|delivered|failed
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['tally_webhook_endpoint_id', 'status'], 'webhook_delivery_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_webhook_deliveries');
    }
};
