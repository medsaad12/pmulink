<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrganizationProvisioner
{
    /**
     * Create a new organization and bootstrap its default per-org roles
     * (Administrateur with every permission, Utilisateur with none).
     */
    public function create(string $name): Organization
    {
        return DB::transaction(function () use ($name): Organization {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'is_active' => true,
            ]);

            $this->provisionDefaultRoles($organization);

            return $organization;
        });
    }

    /**
     * Ensure the default roles exist for an organization.
     */
    public function provisionDefaultRoles(Organization $organization): void
    {
        $admin = Role::query()->withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Administrateur'],
            ['description' => 'Accès complet à l’organisation.'],
        );

        $admin->permissions()->sync(Permission::query()->pluck('id')->all());

        Role::query()->withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Utilisateur'],
            ['description' => 'Compte standard avec accès minimal.'],
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $suffix = 1;

        while (Organization::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
