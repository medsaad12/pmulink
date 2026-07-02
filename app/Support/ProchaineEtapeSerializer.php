<?php

namespace App\Support;

use App\Models\FaitMarquantDraftProchaineEtape;
use App\Models\FaitMarquantProchaineEtape;

final class ProchaineEtapeSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(FaitMarquantProchaineEtape|FaitMarquantDraftProchaineEtape $row): array
    {
        return [
            'id' => (int) $row->id,
            'sort_order' => (int) ($row->sort_order ?? 0),
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'body' => (string) $row->body,
            'responsable_action_id' => $row->responsable_action_id === null
                ? null
                : (int) $row->responsable_action_id,
            'deadline' => $row->deadline?->format('Y-m-d'),
            'etape_status_id' => $row->etape_status_id === null ? null : (int) $row->etape_status_id,
            'created_at' => $row->created_at?->toISOString(),
            'user' => $row->user === null
                ? null
                : [
                    'id' => (int) $row->user->id,
                    'name' => (string) $row->user->name,
                ],
            'responsable_action' => $row->responsableAction === null
                ? null
                : [
                    'id' => (int) $row->responsableAction->id,
                    'name' => (string) $row->responsableAction->name,
                ],
            'etape_status' => $row->etapeStatus === null
                ? null
                : [
                    'id' => (int) $row->etapeStatus->id,
                    'name' => (string) $row->etapeStatus->name,
                    'color' => $row->etapeStatus->color,
                ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toWeeklyPivotArray(
        FaitMarquantProchaineEtape $row,
        ?int $sequenceNumber,
    ): array {
        return [
            'id' => (int) $row->id,
            'sequence_number' => $sequenceNumber,
            'body' => (string) $row->body,
            'responsable_action_id' => $row->responsable_action_id === null
                ? null
                : (int) $row->responsable_action_id,
            'deadline' => $row->deadline?->format('Y-m-d'),
            'etape_status_id' => $row->etape_status_id === null ? null : (int) $row->etape_status_id,
            'created_at' => $row->created_at?->toISOString(),
            'user' => $row->user === null
                ? null
                : [
                    'id' => (int) $row->user->id,
                    'name' => (string) $row->user->name,
                ],
            'responsable_action' => $row->responsableAction === null
                ? null
                : [
                    'id' => (int) $row->responsableAction->id,
                    'name' => (string) $row->responsableAction->name,
                ],
            'etape_status' => $row->etapeStatus === null
                ? null
                : [
                    'id' => (int) $row->etapeStatus->id,
                    'name' => (string) $row->etapeStatus->name,
                    'color' => $row->etapeStatus->color,
                ],
        ];
    }
}
