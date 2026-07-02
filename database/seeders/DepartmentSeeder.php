<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const NAMES = [
        'Finance',
        'Juridique',
        'RH',
        'ADV',
        'Achats',
        'Approvisionnement',
        'Production',
        'Maintenance & Optimisation',
        'Utilités et Moyens généraux',
        'Affaires réglementaires',
        'Pharmacovigilance',
        'R&D',
        'Logistique',
        'Supply chain',
        'Projets',
        'Commettant',
        'Commercial',
        'Marketing',
        'Medical',
        'Corporate / M&A',
        'QHSE',
        'Technologie',
        'Laboratoire de contrôle',
   
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Department::query()->updateOrCreate(['name' => $name]);
        }
    }
}
