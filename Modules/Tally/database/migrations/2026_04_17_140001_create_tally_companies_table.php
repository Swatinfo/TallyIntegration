<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_organization_id')->constrained('tally_organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->string('country', 3)->nullable();
            $table->string('base_currency', 10)->nullable();
            $table->string('gstin', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tally_organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_companies');
    }
};
