<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public const SESSION_KEY = 'current_organization_id';

    /**
     * Sentinel bound when an authenticated non-supervisor user has no accessible
     * organization. No row uses id 0, so the tenant scope returns nothing
     * (fail-closed) instead of leaking every organization's data.
     */
    private const NO_ORGANIZATION = 0;

    /**
     * Bind the current organization for the request so the tenant global scope
     * can filter every query. Resolution order:
     *   1. A valid organization stored in the session (chosen via the switcher).
     *   2. The user's first membership.
     * Supervisors (is_sup) may select any organization; regular users are
     * restricted to organizations they belong to.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $organizationId = $this->resolveOrganizationId($request, $user);

        if ($organizationId !== null) {
            app()->instance('currentOrganizationId', $organizationId);
            $request->session()->put(self::SESSION_KEY, $organizationId);

            return $next($request);
        }

        // Non-supervisor with no accessible organization: fail closed so no
        // tenant data leaks. Supervisors are left unscoped (trusted, and only
        // when no organization exists yet).
        if (! $user->isSup()) {
            app()->instance('currentOrganizationId', self::NO_ORGANIZATION);
        }

        return $next($request);
    }

    private function resolveOrganizationId(Request $request, User $user): ?int
    {
        $sessionOrganizationId = $request->session()->get(self::SESSION_KEY);

        if ($sessionOrganizationId !== null && $this->canAccess($user, (int) $sessionOrganizationId)) {
            return (int) $sessionOrganizationId;
        }

        $firstMembershipId = $user->organizations()->value('organizations.id');

        if ($firstMembershipId !== null) {
            return (int) $firstMembershipId;
        }

        if ($user->isSup()) {
            $firstOrganizationId = Organization::query()->orderBy('id')->value('id');

            return $firstOrganizationId === null ? null : (int) $firstOrganizationId;
        }

        return null;
    }

    private function canAccess(User $user, int $organizationId): bool
    {
        if ($user->isSup()) {
            return Organization::query()->whereKey($organizationId)->exists();
        }

        return $user->belongsToOrganization($organizationId);
    }
}
