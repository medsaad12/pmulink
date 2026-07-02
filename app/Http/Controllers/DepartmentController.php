<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentStoreRequest;
use App\Http\Requests\DepartmentUpdateRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(): Response
    {
        $departments = Department::query()
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('departments/Index', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created department in storage.
     */
    public function store(DepartmentStoreRequest $request): RedirectResponse
    {
        Department::query()->create([
            'name' => $request->validated('name'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Département créé.']);

        return to_route('departments.index');
    }

    /**
     * Update the specified department in storage.
     */
    public function update(DepartmentUpdateRequest $request, Department $department): RedirectResponse
    {
        $department->update([
            'name' => $request->validated('name'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Département mis à jour.']);

        return to_route('departments.index');
    }

    /**
     * Remove the specified department from storage.
     */
    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Département supprimé.']);

        return to_route('departments.index');
    }
}
