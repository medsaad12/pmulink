<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that hold tenant-owned data. Reference data (fait_statuses, statuses,
     * etape_statuses, permissions) stays global and is intentionally excluded.
     *
     * @var list<string>
     */
    private const TENANT_TABLES = [
        'roles',
        'departments',
        'faits_marquants',
        'fait_marquant_drafts',
        'fait_marquant_prochaine_etape',
        'fait_marquant_commentaire',
        'fait_marquant_histories',
        'fait_marquant_draft_prochaine_etape',
        'fait_marquant_draft_commentaire',
    ];

    /**
     * Added nullable + without FK here; the backfill migration sets values, then
     * adds the non-null constraint and foreign keys once data is consistent.
     */
    public function up(): void
    {
        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('organization_id')->nullable()->after('id');
                $blueprint->index('organization_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('organization_id');
            });
        }
    }
};
