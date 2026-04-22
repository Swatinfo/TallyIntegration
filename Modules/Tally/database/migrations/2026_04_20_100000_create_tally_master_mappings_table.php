<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_master_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->string('tally_name');
            $table->string('erp_name');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tally_connection_id', 'entity_type', 'tally_name'], 'master_map_conn_type_tally_uq');
            $table->index(['tally_connection_id', 'entity_type', 'erp_name'], 'master_map_conn_type_erp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_master_mappings');
    }
};
