<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->nullable()->constrained('tally_connections')->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 500);
            $table->string('secret', 64);                  // for HMAC signing
            $table->json('events');                         // ['voucher.created', 'sync.completed', ...]
            $table->json('headers')->nullable();            // custom headers
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'tally_connection_id'], 'webhook_ep_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_webhook_endpoints');
    }
};
