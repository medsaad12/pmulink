<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const NAMES = [
        'Formes sèches',
        'Formes liquides & pateuses',
        'Formes stériles',
        'Maintenance process',
        'Utilités',
        'Moyens généraux',
        'Ordonnancement',
        'Performances industrielle',
        'Gestion resources',
    ];

    public function run(): void
    {
        // Departments are tenant-owned: scope them to the bound (or first) org.
        $organizationId = Department::currentOrganizationId()
            ?? Organization::query()->orderBy('id')->value('id');

        foreach (self::NAMES as $name) {
            Department::query()->updateOrCreate([
                'organization_id' => $organizationId,
                'name' => $name,
            ]);
        }
    }
}
