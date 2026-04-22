<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable FKs — every existing tally_connections row survives with all three null.
 * New rows can opt in to the organization/company/branch hierarchy as needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_connections', function (Blueprint $table) {
            $table->foreignId('tally_organization_id')->nullable()->after('is_active')->constrained('tally_organizations')->nullOnDelete();
            $table->foreignId('tally_company_id')->nullable()->after('tally_organization_id')->constrained('tally_companies')->nullOnDelete();
            $table->foreignId('tally_branch_id')->nullable()->after('tally_company_id')->constrained('tally_branches')->nullOnDelete();

            $table->index('tally_organization_id', 'conn_org_idx');
            $table->index('tally_company_id', 'conn_company_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tally_connections', function (Blueprint $table) {
            $table->dropForeign(['tally_organization_id']);
            $table->dropForeign(['tally_company_id']);
            $table->dropForeign(['tally_branch_id']);
            $table->dropColumn(['tally_organization_id', 'tally_company_id', 'tally_branch_id']);
        });
    }
};
