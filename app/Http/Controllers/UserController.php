<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users that belong to the current organization.
     */
    public function index(): Response
    {
        $organizationId = $this->currentOrganizationId();

        $users = User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.created_at', 'users.updated_at'])
            ->with([
                'departments' => static fn ($query) => $query->select(['departments.id', 'name'])->orderBy('name'),
                'organizations' => static fn ($query) => $query->select(['organizations.id', 'name'])->orderBy('name'),
            ])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $user) use ($organizationId): User {
                $role = $user->roleForOrganization($organizationId);
                $user->setAttribute('role_id', $role?->id);
                $user->setRelation('role', $role);

                return $user;
            });

        $roles = Role::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created user and attach it to the current organization.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $organizationId = $this->currentOrganizationId();

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $user->organizations()->attach($organizationId, [
            'role_id' => (int) $request->validated('role_id'),
        ]);

        $this->syncDepartmentsWithinOrganization($user, $request->validated('department_ids') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur créé.']);

        return to_route('users.index');
    }

    /**
     * Update the specified user (identity + current-org role/departments).
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $organizationId = $this->currentOrganizationId();

        // User is a global identity (no tenant scope): allow edits when it
        // belongs to the current organization, or when it is an orphan (no
        // organization at all) — in which case saving re-attaches it here.
        abort_unless($user->belongsToOrganization($organizationId) || $user->isOrphan(), 404);

        $validated = $request->validated();

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

        $user->organizations()->syncWithoutDetaching([
            $organizationId => ['role_id' => (int) $validated['role_id']],
        ]);

        $this->syncDepartmentsWithinOrganization($user, $validated['department_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur mis à jour.']);

        return to_route('users.index');
    }

    /**
     * Remove the user from the current organization (detach membership and
     * this organization's departments). The identity itself is deleted only
     * when it no longer belongs to any organization and is not referenced by
     * any authored record (which restrictive foreign keys would otherwise
     * block); referenced identities are kept as orphans.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Vous ne pouvez pas supprimer votre propre compte depuis cette page.']);

            return back();
        }

        $organizationId = $this->currentOrganizationId();

        abort_unless($user->belongsToOrganization($organizationId) || $user->isOrphan(), 404);

        $user->organizations()->detach($organizationId);

        $currentOrgDepartmentIds = Department::query()->pluck('id')->all();
        $user->departments()->detach($currentOrgDepartmentIds);

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
     * Sync department assignments for the current organization only, leaving the
     * user's departments in other organizations untouched (the department_user
     * pivot spans all organizations).
     *
     * @param  list<int|string>  $departmentIds
     */
    private function syncDepartmentsWithinOrganization(User $user, array $departmentIds): void
    {
        // Departments are tenant-scoped, so this only returns the current org's.
        $currentOrgDepartmentIds = Department::query()->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $keep = array_values(array_intersect(
            array_map(static fn ($id) => (int) $id, $departmentIds),
            $currentOrgDepartmentIds,
        ));

        $user->departments()->syncWithoutDetaching($keep);

        $toDetach = array_values(array_diff($currentOrgDepartmentIds, $keep));

        if ($toDetach !== []) {
            $user->departments()->detach($toDetach);
        }
    }

    private function currentOrganizationId(): int
    {
        return (int) app('currentOrganizationId');
    }
}
