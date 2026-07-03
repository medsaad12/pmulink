<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users that belong to the current organization.
     */
    public function index(): Response
    {
        $users = User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.created_at', 'users.updated_at'])
            ->with([
                'departments' => static fn ($query) => $query
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->select(['departments.id', 'departments.name', 'departments.organization_id'])
                    ->orderBy('name'),
                'organizations' => static fn ($query) => $query
                    ->select(['organizations.id', 'organizations.name'])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $user): User {
                $user->setAttribute(
                    'memberships',
                    $user->organizations->map(static function (Organization $organization): array {
                        $roleId = $organization->getRelationValue('pivot')?->role_id;
                        $role = $roleId === null
                            ? null
                            : Role::query()
                                ->withoutGlobalScope(OrganizationScope::class)
                                ->find((int) $roleId, ['id', 'name']);

                        return [
                            'organization_id' => (int) $organization->id,
                            'organization_name' => (string) $organization->name,
                            'role_id' => $roleId === null ? null : (int) $roleId,
                            'role_name' => $role?->name,
                        ];
                    })->values()->all(),
                );

                return $user;
            });

        return Inertia::render('users/Index', [
            'users' => $users,
            'organizations' => $this->formOrganizations(),
        ]);
    }

    /**
     * Store a newly created user and attach it to the chosen organizations.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $organizationIds = $request->validated('organization_ids');
        $roleName = $this->roleNameFromId((int) $request->validated('role_id'));

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $user->organizations()->attach($this->organizationRolePivot($organizationIds, $roleName));

        foreach ($organizationIds as $organizationId) {
            $this->syncDepartmentsWithinOrganization(
                $user,
                $request->validated('department_ids') ?? [],
                (int) $organizationId,
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur créé.']);

        return to_route('users.index');
    }

    /**
     * Update the specified user (identity + memberships/departments for chosen organizations).
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $organizationIds = array_map(static fn ($id) => (int) $id, $request->validated('organization_ids'));
        $accessibleOrganizationIds = $this->accessibleOrganizationIds();
        $managedOrganizationIds = array_values(array_intersect($organizationIds, $accessibleOrganizationIds));

        abort_unless(
            $user->isOrphan()
            || array_intersect($user->organizationIds(), $managedOrganizationIds) !== [],
            404,
        );

        $validated = $request->validated();
        $roleName = $this->roleNameFromId((int) $validated['role_id']);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->syncOrganizationMemberships($user, $managedOrganizationIds, $roleName);

        foreach ($managedOrganizationIds as $organizationId) {
            $this->syncDepartmentsWithinOrganization(
                $user,
                $validated['department_ids'] ?? [],
                $organizationId,
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur mis à jour.']);

        return to_route('users.index');
    }

    /**
     * Remove the user from the chosen organization (detach membership and
     * that organization's departments). The identity itself is deleted only
     * when it no longer belongs to any organization and is not referenced by
     * any authored record (which restrictive foreign keys would otherwise
     * block); referenced identities are kept as orphans.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Vous ne pouvez pas supprimer votre propre compte depuis cette page.']);

            return back();
        }

        $validated = $request->validate([
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
        ]);

        $organizationId = (int) $validated['organization_id'];

        abort_unless($request->user()?->isSup() || $request->user()?->belongsToOrganization($organizationId), 403);
        abort_unless($user->belongsToOrganization($organizationId) || $user->isOrphan(), 404);

        $user->organizations()->detach($organizationId);

        $organizationDepartmentIds = Department::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->pluck('id')
            ->all();
        $user->departments()->detach($organizationDepartmentIds);

        $identityDeleted = false;

        if ($user->organizations()->count() === 0) {
            // The identity is now an orphan. Faits marquants (and their history
            // and pivot rows) keep restrictive foreign keys back to users to
            // preserve authorship, so hard-deleting an author would raise a
            // constraint violation. Only delete when nothing references the
            // identity; otherwise leave it as an orphan instead of crashing.
            try {
                $user->delete();
                $identityDeleted = true;
            } catch (QueryException) {
                // Still referenced by authored records: keep the orphan identity.
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $identityDeleted
                ? 'Utilisateur supprimé.'
                : 'Utilisateur retiré de l’organisation.',
        ]);

        return to_route('users.index');
    }

    /**
     * Sync organization memberships the current actor can manage. Memberships in
     * organizations outside the actor's reach are left untouched.
     *
     * @param  list<int>  $selectedOrganizationIds
     */
    private function syncOrganizationMemberships(User $user, array $selectedOrganizationIds, string $roleName): void
    {
        $accessibleOrganizationIds = $this->accessibleOrganizationIds();

        foreach ($selectedOrganizationIds as $organizationId) {
            if (! in_array($organizationId, $accessibleOrganizationIds, true)) {
                continue;
            }

            $roleId = $this->roleIdForOrganizationByName($organizationId, $roleName);

            $user->organizations()->syncWithoutDetaching([
                $organizationId => ['role_id' => $roleId],
            ]);
        }

        $toDetach = array_values(array_diff(
            array_intersect($user->organizationIds(), $accessibleOrganizationIds),
            $selectedOrganizationIds,
        ));

        if ($toDetach === []) {
            return;
        }

        $user->organizations()->detach($toDetach);

        $departmentIdsToDetach = Department::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereIn('organization_id', $toDetach)
            ->pluck('id')
            ->all();

        if ($departmentIdsToDetach !== []) {
            $user->departments()->detach($departmentIdsToDetach);
        }
    }

    /**
     * @param  list<int|string>  $organizationIds
     * @return array<int, array{role_id: int}>
     */
    private function organizationRolePivot(array $organizationIds, string $roleName): array
    {
        $pivot = [];

        foreach ($organizationIds as $organizationId) {
            $pivot[(int) $organizationId] = [
                'role_id' => $this->roleIdForOrganizationByName((int) $organizationId, $roleName),
            ];
        }

        return $pivot;
    }

    private function roleNameFromId(int $roleId): string
    {
        $role = Role::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->findOrFail($roleId, ['name']);

        return (string) $role->name;
    }

    private function roleIdForOrganizationByName(int $organizationId, string $roleName): int
    {
        return (int) Role::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('name', $roleName)
            ->valueOrFail('id');
    }

    /**
     * @return list<int>
     */
    private function accessibleOrganizationIds(): array
    {
        return array_map(
            static fn (array $organization) => (int) $organization['id'],
            $this->formOrganizations(),
        );
    }

    /**
     * Sync department assignments for a specific organization only, leaving the
     * user's departments in other organizations untouched (the department_user
     * pivot spans all organizations).
     *
     * @param  list<int|string>  $departmentIds
     */
    private function syncDepartmentsWithinOrganization(User $user, array $departmentIds, int $organizationId): void
    {
        $organizationDepartmentIds = Department::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $keep = array_values(array_intersect(
            array_map(static fn ($id) => (int) $id, $departmentIds),
            $organizationDepartmentIds,
        ));

        $user->departments()->syncWithoutDetaching($keep);

        $toDetach = array_values(array_diff($organizationDepartmentIds, $keep));

        if ($toDetach !== []) {
            $user->departments()->detach($toDetach);
        }
    }

    /**
     * Organizations available in the user form, each with its roles and departments.
     *
     * @return list<array{id: int, name: string, roles: list<array{id: int, name: string}>, departments: list<array{id: int, name: string}>}>
     */
    private function formOrganizations(): array
    {
        $user = request()->user();

        $query = $user !== null && $user->isSup()
            ? Organization::query()
            : ($user?->organizations()->getQuery() ?? Organization::query()->whereRaw('0 = 1'));

        return $query
            ->orderBy('name')
            ->get(['organizations.id', 'organizations.name'])
            ->map(function (Organization $organization): array {
                $roles = Role::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn (Role $role) => [
                        'id' => (int) $role->id,
                        'name' => (string) $role->name,
                    ])
                    ->all();

                $departments = Department::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(static fn (Department $department) => [
                        'id' => (int) $department->id,
                        'name' => (string) $department->name,
                    ])
                    ->all();

                return [
                    'id' => (int) $organization->id,
                    'name' => (string) $organization->name,
                    'roles' => $roles,
                    'departments' => $departments,
                ];
            })
            ->values()
            ->all();
    }
}
