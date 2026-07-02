<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_ORG_ID = 1;

    /**
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

    public function up(): void
    {
        $now = now();

        // 1. Ensure the default organization (the existing company) exists as org #1.
        if (! DB::table('organizations')->where('id', self::DEFAULT_ORG_ID)->exists()) {
            DB::table('organizations')->insert([
                'id' => self::DEFAULT_ORG_ID,
                'name' => 'Comex',
                'slug' => 'main',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Stamp every existing tenant row with the default organization.
        foreach (self::TENANT_TABLES as $table) {
            DB::table($table)->whereNull('organization_id')->update([
                'organization_id' => self::DEFAULT_ORG_ID,
            ]);
        }

        // 3. Create one membership per existing user, carrying their current role.
        if (Schema::hasColumn('users', 'role_id')) {
            $users = DB::table('users')->select('id', 'role_id')->get();
            foreach ($users as $user) {
                $exists = DB::table('organization_user')
                    ->where('organization_id', self::DEFAULT_ORG_ID)
                    ->where('user_id', $user->id)
                    ->exists();

                if (! $exists) {
                    DB::table('organization_user')->insert([
                        'organization_id' => self::DEFAULT_ORG_ID,
                        'user_id' => $user->id,
                        'role_id' => $user->role_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 4. Roles are now per-organization: unique by (organization_id, name).
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['organization_id', 'name'], 'roles_organization_id_name_unique');
        });

        // 5. Lock down organization_id (non-null + FK) now that data is consistent.
        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
            });
        }

        // 6. Role now lives on the membership, not on the user identity.
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }

    public function down(): void
    {
        // Restore users.role_id from the default-org membership.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('id');
        });

        $memberships = DB::table('organization_user')
            ->where('organization_id', self::DEFAULT_ORG_ID)
            ->get();
        foreach ($memberships as $membership) {
            DB::table('users')->where('id', $membership->user_id)->update([
                'role_id' => $membership->role_id,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });

        foreach (self::TENANT_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['organization_id']);
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_organization_id_name_unique');
        });
    }
};
