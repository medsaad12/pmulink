<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Seed global permissions and the per-organization default roles
     * (Administrateur / Utilisateur) for the currently bound organization.
     */
    public function run(): void
    {
        // Ensure an organization is bound so the per-org roles get stamped.
        if (! app()->bound('currentOrganizationId') || app('currentOrganizationId') === null) {
            $organization = Organization::query()->orderBy('id')->first()
                ?? Organization::query()->create([
                    'name' => 'Pmu',
                    'slug' => 'main',
                    'is_active' => true,
                ]);
            app()->instance('currentOrganizationId', $organization->id);
        }

        Permission::query()->where('name', 'dashboard.access')->forceDelete();

        $definitions = [
            [
                'name' => 'users.view',
                'description' => 'Lister les utilisateurs.',
            ],
            [
                'name' => 'users.create',
                'description' => 'Créer des comptes utilisateur.',
            ],
            [
                'name' => 'users.edit',
                'description' => 'Modifier des comptes utilisateur.',
            ],
            [
                'name' => 'users.delete',
                'description' => 'Supprimer des comptes utilisateur.',
            ],
            [
                'name' => 'roles.view',
                'description' => 'Voir la liste des rôles.',
            ],
            [
                'name' => 'roles.create',
                'description' => 'Créer des rôles.',
            ],
            [
                'name' => 'roles.edit',
                'description' => 'Renommer des rôles.',
            ],
            [
                'name' => 'roles.delete',
                'description' => 'Supprimer des rôles.',
            ],
            [
                'name' => 'roles.assign-permissions',
                'description' => 'Modifier les permissions associées à un rôle.',
            ],
            [
                'name' => 'departments.view',
                'description' => 'Voir la liste des départements.',
            ],
            [
                'name' => 'departments.create',
                'description' => 'Créer des départements.',
            ],
            [
                'name' => 'departments.edit',
                'description' => 'Modifier des départements.',
            ],
            [
                'name' => 'departments.delete',
                'description' => 'Supprimer des départements.',
            ],
        ];

        $permissionIds = collect($definitions)
            ->map(static fn (array $row) => Permission::query()->updateOrCreate(
                ['name' => $row['name']],
                ['description' => $row['description']],
            )->id)
            ->all();

        $admin = Role::query()->updateOrCreate(
            ['name' => 'Administrateur'],
            ['description' => 'Accès complet à l’application.'],
        );

        $admin->permissions()->sync($permissionIds);

        Role::query()->updateOrCreate(
            ['name' => 'Utilisateur'],
            ['description' => 'Compte standard avec accès minimal.'],
        );
    }
}
