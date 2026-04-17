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
        Schema::create('tally_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('name');
            $table->string('parent')->nullable();
            $table->string('nature', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('tally_raw_data')->nullable();
            $table->string('data_hash', 32)->nullable();
            $table->timestamps();

            $table->unique(['tally_connection_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tally_groups');
    }
};
