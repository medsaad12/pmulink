<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveTenant;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Organization picker shown after login when a user belongs to several
     * organizations (e.g. a director who is a member of two companies).
     */
    public function select(Request $request): Response|RedirectResponse
    {
        $organizations = $this->accessibleOrganizations($request)
            ->map(static fn (Organization $organization) => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
            ])
            ->values()
            ->all();

        if (count($organizations) <= 1) {
            $only = $organizations[0]['id'] ?? null;

            if ($only !== null) {
                $request->session()->put(ResolveTenant::SESSION_KEY, $only);
            }

            return to_route('whiteboard');
        }

        return Inertia::render('organizations/Select', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Switch the active organization for the current session.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
        ]);

        $organizationId = (int) $validated['organization_id'];

        if (! $this->canAccess($request, $organizationId)) {
            abort(403);
        }

        $request->session()->put(ResolveTenant::SESSION_KEY, $organizationId);

        return to_route('whiteboard');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    private function accessibleOrganizations(Request $request)
    {
        $user = $request->user();

        if ($user !== null && $user->isSup()) {
            return Organization::query()->orderBy('name')->get();
        }

        return $user === null
            ? collect()
            : $user->organizations()->orderBy('name')->get();
    }

    private function canAccess(Request $request, int $organizationId): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isSup()) {
            return Organization::query()->whereKey($organizationId)->exists();
        }

        return $user->belongsToOrganization($organizationId);
    }
}
