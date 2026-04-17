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
        Schema::table('tally_connections', function (Blueprint $table) {
            $table->unsignedBigInteger('last_alter_master_id')->default(0)->after('is_active');
            $table->unsignedBigInteger('last_alter_voucher_id')->default(0)->after('last_alter_master_id');
            $table->timestamp('last_synced_at')->nullable()->after('last_alter_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('tally_connections', function (Blueprint $table) {
            $table->dropColumn(['last_alter_master_id', 'last_alter_voucher_id', 'last_synced_at']);
        });
    }
};
