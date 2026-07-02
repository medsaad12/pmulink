<?php

namespace App\Http\Controllers;

use App\Models\Department;
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
 * 'sup' middleware. Used to create, edit and delete organizations and to
 * manage their members across tenant boundaries.
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
                'departments' => $organization->departments()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn (Department $department) => [
                        'id' => (int) $department->id,
                        'name' => (string) $department->name,
                    ])
                    ->all(),
            ])
            ->all();

        return Inertia::render('admin/Organizations', [
            'organizations' => $organizations,
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(static fn (User $user) => [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                ])
                ->all(),
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

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $organization->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
        ]);

        return back();
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->delete();

        return back();
    }

    /**
     * Attach one or more existing user identities to an organization with a
     * chosen role. Users are picked from a list (by id) rather than by e-mail.
     */
    public function attachMember(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('organization_id', $organization->id),
            ],
        ]);

        $attach = [];
        foreach ($validated['user_ids'] as $userId) {
            $attach[(int) $userId] = ['role_id' => (int) $validated['role_id']];
        }

        $organization->users()->syncWithoutDetaching($attach);

        return back();
    }

    public function updateMember(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('organization_id', $organization->id),
            ],
        ]);

        $organization->users()->updateExistingPivot($user->id, [
            'role_id' => (int) $validated['role_id'],
        ]);

        return back();
    }

    public function detachMember(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $organization->users()->detach($user->id);

        return back();
    }

    /**
     * Create a department for a specific organization, bypassing the tenant
     * scope so a supervisor can manage any organization's departments from the
     * backoffice regardless of the currently bound organization.
     */
    public function storeDepartment(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $organization->departments()->create(['name' => $validated['name']]);

        return back();
    }

    public function updateDepartment(Request $request, Organization $organization, string $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->resolveDepartment($organization, (int) $department)
            ->update(['name' => $validated['name']]);

        return back();
    }

    public function destroyDepartment(Request $request, Organization $organization, string $department): RedirectResponse
    {
        $this->resolveDepartment($organization, (int) $department)->delete();

        return back();
    }

    /**
     * Fetch a department by id without the tenant scope and ensure it belongs
     * to the given organization (fail-closed with a 404 otherwise).
     */
    private function resolveDepartment(Organization $organization, int $departmentId): Department
    {
        $department = Department::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->findOrFail($departmentId);

        abort_unless((int) $department->organization_id === (int) $organization->id, 404);

        return $department;
    }
}
