<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Soft deletes on the permission_role pivot break BelongsToMany::sync()
     * (detaches are soft-deleted, unique pairs block re-sync, and relations can look wrong).
     */
    public function up(): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        if (Schema::hasColumn('permission_role', 'deleted_at')) {
            DB::table('permission_role')->whereNotNull('deleted_at')->delete();

            Schema::table('permission_role', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        if (! Schema::hasColumn('permission_role', 'deleted_at')) {
            Schema::table('permission_role', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }
};
