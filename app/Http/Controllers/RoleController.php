<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleSyncPermissionsRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(): Response
    {
        $roles = Role::query()
            ->with([
                'permissions' => static fn ($query) => $query
                    ->select(['permissions.id', 'permissions.name', 'permissions.description']),
            ])
            ->withCount('permissions')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $permissions = Permission::query()
            ->select(['id', 'name', 'description'])
            ->orderBy('name')
            ->get();

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        Role::query()->create([
            'name' => $name,
            'description' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rôle créé.']);

        return to_route('roles.index');
    }

    /**
     * Update the specified role in storage.
     */
    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $name = $request->validated('name');

        $role->update([
            'name' => $name,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rôle mis à jour.']);

        return to_route('roles.index');
    }

    /**
     * Replace all permissions attached to the role.
     */
    public function syncPermissions(RoleSyncPermissionsRequest $request, Role $role): RedirectResponse
    {
        $raw = $request->validated('permission_ids', []);

        if (! is_array($raw)) {
            $raw = [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $raw),
            static fn (int $id) => $id > 0,
        )));

        $role->permissions()->sync($ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permissions du rôle mises à jour.']);

        return to_route('roles.index');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rôle supprimé.']);

        return to_route('roles.index');
    }
}
