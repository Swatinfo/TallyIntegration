<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_company_id')->constrained('tally_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tally_company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_branches');
    }
};
