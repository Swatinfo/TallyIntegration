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
        Schema::create('tally_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('name');
            $table->string('parent')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('gst_registration_type', 50)->nullable();
            $table->string('state')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('contact_person')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->default(0);
            $table->string('credit_period', 50)->nullable();
            $table->decimal('credit_limit', 18, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('address')->nullable();
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
        Schema::dropIfExists('tally_ledgers');
    }
};
