<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user !== null) {
            $user->loadMissing('departments:id,name');
        }

        $currentOrganizationId = app()->bound('currentOrganizationId')
            ? app('currentOrganizationId')
            : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'is_sup' => $user?->isSup() ?? false,
                'permission_keys' => $this->permissionKeys($user),
            ],
            'tenant' => [
                'current_id' => $currentOrganizationId === null ? null : (int) $currentOrganizationId,
                'current' => $this->currentOrganization($currentOrganizationId),
                'available' => $this->availableOrganizations($user),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Supervisors implicitly hold every permission; everyone else gets the keys
     * granted by their role in the current organization.
     *
     * @return list<string>
     */
    private function permissionKeys(?\App\Models\User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->isSup()) {
            return Permission::query()->pluck('name')->values()->all();
        }

        return $user->flattenPermissionKeys();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function currentOrganization(?int $organizationId): ?array
    {
        if ($organizationId === null) {
            return null;
        }

        $organization = Organization::query()->find($organizationId, ['id', 'name']);

        return $organization === null
            ? null
            : ['id' => (int) $organization->id, 'name' => (string) $organization->name];
    }

    /**
     * Organizations the user can switch into (their memberships, or all of them
     * for a supervisor).
     *
     * @return list<array{id: int, name: string}>
     */
    private function availableOrganizations(?\App\Models\User $user): array
    {
        if ($user === null) {
            return [];
        }

        $query = $user->isSup()
            ? Organization::query()
            : $user->organizations()->getQuery();

        return $query
            ->orderBy('name')
            ->get(['organizations.id', 'organizations.name'])
            ->map(static fn (Organization $organization) => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
            ])
            ->values()
            ->all();
    }
}

