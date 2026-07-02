<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\FaitMarquant;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with high-level counts.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'organizations' => Organization::query()->count(),
                'departments' => Department::query()->withoutGlobalScope(OrganizationScope::class)->count(),
                'faitsMarquants' => FaitMarquant::query()->withoutGlobalScope(OrganizationScope::class)->count(),
            ],
        ]);
    }
}
