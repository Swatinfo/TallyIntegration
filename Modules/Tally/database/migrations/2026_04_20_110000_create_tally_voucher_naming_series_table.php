<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_voucher_naming_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_type', 64);
            $table->string('series_name', 64);
            $table->string('prefix', 32)->nullable();
            $table->string('suffix', 32)->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tally_connection_id', 'voucher_type', 'series_name'], 'vns_conn_type_series_uq');
            $table->index(['tally_connection_id', 'voucher_type'], 'vns_conn_type_idx');
        });

        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->string('naming_series', 64)->nullable()->after('voucher_type');
            $table->index(['tally_connection_id', 'naming_series'], 'vouchers_conn_naming_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_conn_naming_idx');
            $table->dropColumn('naming_series');
        });
        Schema::dropIfExists('tally_voucher_naming_series');
    }
};
