<?php

namespace Database\Seeders;

use App\Models\EtapeStatus;
use App\Models\FaitStatus;
use App\Models\WorkflowStatus;
use Illuminate\Database\Seeder;

class FaitMarquantReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $faitStatuses = [
            ['name' => 'En bonne voie', 'sort_order' => 10, 'color' => '#16a34a'],
            ['name' => 'Sous vigilance', 'sort_order' => 20, 'color' => '#ca8a04'],
            ['name' => 'Critique / À risque', 'sort_order' => 30, 'color' => '#dc2626'],
        ];

        foreach ($faitStatuses as $row) {
            FaitStatus::query()->updateOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $row['sort_order'], 'color' => $row['color']],
            );
        }

        $etapeStatuses = [
            ['name' => 'À faire', 'sort_order' => 10, 'color' => '#6366f1'],
            ['name' => 'En cours', 'sort_order' => 20, 'color' => '#ca8a04'],
            ['name' => 'Terminé', 'sort_order' => 30, 'color' => '#16a34a'],
        ];

        foreach ($etapeStatuses as $row) {
            EtapeStatus::query()->updateOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $row['sort_order'], 'color' => $row['color']],
            );
        }

        $statuses = [
            ['name' => 'Ouvert', 'sort_order' => 10, 'color' => '#F8B634'],
            ['name' => 'Clôturé', 'sort_order' => 20, 'color' => '#69E696'],
            ['name' => 'Archivé', 'sort_order' => 30, 'color' => '#64748b'],
        ];

        foreach ($statuses as $row) {
            WorkflowStatus::query()->updateOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $row['sort_order'], 'color' => $row['color']],
            );
        } 
    }
}
