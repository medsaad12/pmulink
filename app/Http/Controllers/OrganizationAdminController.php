<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-organization backoffice, restricted to supervisors (is_sup) via the
 * 'sup' middleware. Used to create organizations and attach members across
 * tenant boundaries (e.g. granting an existing user admin access to a new org).
 */
class OrganizationAdminController extends Controller
{
    public function index(): Response
    {
        $organizations = Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
                'slug' => (string) $organization->slug,
                'is_active' => (bool) $organization->is_active,
                'members_count' => (int) $organization->users_count,
                'roles' => Role::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn (Role $role) => ['id' => (int) $role->id, 'name' => (string) $role->name])
                    ->all(),
                'members' => $organization->users()
                    ->orderBy('name')
                    ->get(['users.id', 'users.name', 'users.email'])
                    ->map(static fn (User $user) => [
                        'id' => (int) $user->id,
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                        'role_id' => $user->getRelationValue('pivot')?->role_id === null
                            ? null
                            : (int) $user->getRelationValue('pivot')->role_id,
                    ])
                    ->all(),
            ])
            ->all();

        return Inertia::render('admin/Organizations', [
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request, OrganizationProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $provisioner->create($validated['name']);

        return back();
    }

    /**
     * Attach an existing user identity (looked up by email) to an organization
     * with a chosen role — the cross-org membership action.
     */
    public function attachMember(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('organization_id', $organization->id),
            ],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null) {
            return back()->withErrors(['email' => "Aucun utilisateur n'existe avec cet e-mail."]);
        }

        $user->organizations()->syncWithoutDetaching([
            $organization->id => ['role_id' => (int) $validated['role_id']],
        ]);

        return back();
    }

    public function detachMember(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $user->organizations()->detach($organization->id);

        return back();
    }
}
