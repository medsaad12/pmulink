<?php

namespace App\Services;

use App\Models\FaitMarquant;
use App\Models\FaitMarquantCommentaire;
use App\Models\FaitMarquantHistory;
use App\Models\FaitMarquantProchaineEtape;
use App\Models\WorkflowStatus;
use App\Support\ProchaineEtapeSerializer;
use Carbon\Carbon;

final class FaitMarquantWeeklyTimelineBuilder
{
    public const TIMEZONE = 'Africa/Casablanca';

    /**
     * @return list<array<string, mixed>>
     */
    public function build(FaitMarquant $fait): array
    {
        $fait->loadMissing([
            'faitStatus:id,name,color',
            'workflowStatus:id,name,color',
            'department:id,name',
            'responsableAction:id,name',
        ]);

        $closedIds = WorkflowStatus::query()
            ->whereIn('name', ['Clôturé', 'Archivé'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $historyNotBeforeUtc = $this->historyNotBeforeUtc($fait);
        $fallbackSnapshot = $this->serializeFaitSnapshot($fait);

        $firstClose = FaitMarquantHistory::query()
            ->where('fait_marquant_id', $fait->id)
            ->when($historyNotBeforeUtc !== null, static fn ($q) => $q->where('created_at', '>=', $historyNotBeforeUtc))
            ->when($closedIds !== [], fn ($q) => $q->whereIn('status_id', $closedIds))
            ->orderBy('created_at')
            ->first();

        $nowMorocco = Carbon::now(self::TIMEZONE);
        $timelineEndMorocco = $firstClose !== null
            ? Carbon::parse($firstClose->created_at)->timezone(self::TIMEZONE)
            : $nowMorocco;

        $timelineStartMorocco = $this->timelineStartMorocco($fait);

        $firstWeekMonday = $timelineStartMorocco->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $lastWeekMonday = $timelineEndMorocco->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $etapeSequenceById = $this->pivotSequenceById($fait->id, 'etapes');
        $commentaireSequenceById = $this->pivotSequenceById($fait->id, 'commentaires');

        $weeks = [];
        for ($cursor = $firstWeekMonday->copy(); $cursor <= $lastWeekMonday; $cursor->addWeek()) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $weeks[] = $this->serializeWeek(
                $fait->id,
                $weekStart,
                $weekEnd,
                $historyNotBeforeUtc,
                $etapeSequenceById,
                $commentaireSequenceById,
                $fallbackSnapshot,
            );
        }

        return array_values(array_reverse($weeks));
    }

    /**
     * Début de la chronologie : première apparition « officielle » (soumission), sinon création du fait.
     */
    private function timelineStartMorocco(FaitMarquant $fait): Carbon
    {
        $anchor = $fait->submitted_at ?? $fait->created_at;
        $earliestPivotCreatedAt = $this->earliestPivotCreatedAt($fait->id);

        if ($earliestPivotCreatedAt !== null && $earliestPivotCreatedAt->lessThan($anchor)) {
            return $earliestPivotCreatedAt->timezone(self::TIMEZONE);
        }

        return Carbon::parse($anchor)->timezone(self::TIMEZONE);
    }

    private function earliestPivotCreatedAt(int $faitMarquantId): ?Carbon
    {
        $earliestEtape = FaitMarquantProchaineEtape::query()
            ->where('fait_marquant_id', $faitMarquantId)
            ->min('created_at');

        $earliestCommentaire = FaitMarquantCommentaire::query()
            ->where('fait_marquant_id', $faitMarquantId)
            ->min('created_at');

        $dates = collect([$earliestEtape, $earliestCommentaire])
            ->filter()
            ->map(fn (string $date) => Carbon::parse($date));

        return $dates->isEmpty() ? null : $dates->min();
    }

    /**
     * Ne pas exposer d'historique ni de pivots publiés avant la soumission du brouillon.
     */
    private function historyNotBeforeUtc(FaitMarquant $fait): ?Carbon
    {
        if ($fait->submitted_at === null) {
            return null;
        }

        return Carbon::parse($fait->submitted_at)->utc();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, int>  $etapeSequenceById
     * @param  array<int, int>  $commentaireSequenceById
     * @param  array<string, mixed>  $fallbackSnapshot
     */
    private function serializeWeek(
        int $faitMarquantId,
        Carbon $weekStartMorocco,
        Carbon $weekEndMorocco,
        ?Carbon $historyNotBeforeUtc,
        array $etapeSequenceById,
        array $commentaireSequenceById,
        array $fallbackSnapshot,
    ): array {
        $history = FaitMarquantHistory::query()
            ->where('fait_marquant_id', $faitMarquantId)
            ->when($historyNotBeforeUtc !== null, static fn ($q) => $q->where('created_at', '>=', $historyNotBeforeUtc))
            ->where('created_at', '<=', $weekEndMorocco->copy()->utc())
            ->orderByDesc('created_at')
            ->with([
                'faitStatus:id,name,color',
                'workflowStatus:id,name,color',
                'department:id,name',
                'changedBy:id,name',
                'responsableAction:id,name',
            ])
            ->first();

        if ($history === null) {
            $history = FaitMarquantHistory::query()
                ->where('fait_marquant_id', $faitMarquantId)
                ->when($historyNotBeforeUtc !== null, static fn ($q) => $q->where('created_at', '>=', $historyNotBeforeUtc))
                ->orderBy('created_at')
                ->with([
                    'faitStatus:id,name,color',
                    'workflowStatus:id,name,color',
                    'department:id,name',
                    'changedBy:id,name',
                    'responsableAction:id,name',
                ])
                ->first();
        }

        return [
            'week_start' => $weekStartMorocco->format('Y-m-d'),
            'week_end' => $weekEndMorocco->format('Y-m-d'),
            'week_label' => $this->formatWeekLabel($weekStartMorocco, $weekEndMorocco),
            'snapshot' => $history === null ? $fallbackSnapshot : $this->serializeHistorySnapshot($history),
            'prochaines_etapes' => $this->pivotRowsInWeek(
                $faitMarquantId,
                $weekStartMorocco,
                $weekEndMorocco,
                'etapes',
                $etapeSequenceById,
            ),
            'commentaires' => $this->pivotRowsInWeek(
                $faitMarquantId,
                $weekStartMorocco,
                $weekEndMorocco,
                'commentaires',
                $commentaireSequenceById,
            ),
        ];
    }

    private function formatWeekLabel(Carbon $weekStartMorocco, Carbon $weekEndMorocco): string
    {
        $a = $weekStartMorocco->copy()->locale('fr');
        $b = $weekEndMorocco->copy()->locale('fr');

        return $a->isoFormat('D MMM').' — '.$b->isoFormat('D MMM YYYY');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeHistorySnapshot(FaitMarquantHistory $h): array
    {
        return [
            'created_at' => $h->created_at?->toISOString(),
            'title' => (string) $h->title,
            'fait_status_id' => (int) $h->fait_status_id,
            'status_id' => (int) $h->status_id,
            'deadline' => $h->deadline?->format('Y-m-d'),
            'department_id' => (int) $h->department_id,
            'responsable_action_id' => (int) $h->responsable_action_id,
            'responsable_action' => $h->responsableAction === null
                ? null
                : [
                    'id' => (int) $h->responsableAction->id,
                    'name' => (string) $h->responsableAction->name,
                ],
            'fait_status' => $h->faitStatus === null
                ? null
                : [
                    'id' => (int) $h->faitStatus->id,
                    'name' => (string) $h->faitStatus->name,
                    'color' => $h->faitStatus->color,
                ],
            'workflow_status' => $h->workflowStatus === null
                ? null
                : [
                    'id' => (int) $h->workflowStatus->id,
                    'name' => (string) $h->workflowStatus->name,
                    'color' => $h->workflowStatus->color,
                ],
            'department' => $h->department === null
                ? null
                : [
                    'id' => (int) $h->department->id,
                    'name' => (string) $h->department->name,
                ],
            'changed_by' => $h->changedBy === null
                ? null
                : [
                    'id' => (int) $h->changedBy->id,
                    'name' => (string) $h->changedBy->name,
                ],
        ];
    }

    /**
     * Fallback for already-submitted records that were created before the initial submit snapshot fix.
     *
     * @return array<string, mixed>
     */
    private function serializeFaitSnapshot(FaitMarquant $fait): array
    {
        return [
            'created_at' => ($fait->submitted_at ?? $fait->created_at)?->toISOString(),
            'title' => (string) $fait->title,
            'fait_status_id' => (int) $fait->fait_status_id,
            'status_id' => (int) $fait->status_id,
            'deadline' => $fait->deadline?->format('Y-m-d'),
            'department_id' => (int) $fait->department_id,
            'responsable_action_id' => (int) $fait->responsable_action_id,
            'responsable_action' => $fait->responsableAction === null
                ? null
                : [
                    'id' => (int) $fait->responsableAction->id,
                    'name' => (string) $fait->responsableAction->name,
                ],
            'fait_status' => $fait->faitStatus === null
                ? null
                : [
                    'id' => (int) $fait->faitStatus->id,
                    'name' => (string) $fait->faitStatus->name,
                    'color' => $fait->faitStatus->color,
                ],
            'workflow_status' => $fait->workflowStatus === null
                ? null
                : [
                    'id' => (int) $fait->workflowStatus->id,
                    'name' => (string) $fait->workflowStatus->name,
                    'color' => $fait->workflowStatus->color,
                ],
            'department' => $fait->department === null
                ? null
                : [
                    'id' => (int) $fait->department->id,
                    'name' => (string) $fait->department->name,
                ],
            'changed_by' => null,
        ];
    }

    /**
     * Prochaines étapes / commentaires créés pendant la semaine (fuseau Maroc).
     *
     * @param  array<int, int>  $sequenceById
     * @return list<array<string, mixed>>
     */
    private function pivotRowsInWeek(
        int $faitMarquantId,
        Carbon $weekStartMorocco,
        Carbon $weekEndMorocco,
        string $kind,
        array $sequenceById,
    ): array {
        $weekStartUtc = $weekStartMorocco->copy()->startOfDay()->utc();
        $weekEndUtc = $weekEndMorocco->copy()->endOfDay()->utc();

        if ($kind === 'etapes') {
            return FaitMarquantProchaineEtape::query()
                ->where('fait_marquant_id', $faitMarquantId)
                ->whereBetween('created_at', [$weekStartUtc, $weekEndUtc])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with([
                    'user:id,name',
                    'responsableAction:id,name',
                    'etapeStatus:id,name,color',
                ])
                ->get()
                ->map(fn (FaitMarquantProchaineEtape $row) => ProchaineEtapeSerializer::toWeeklyPivotArray(
                    $row,
                    $sequenceById[(int) $row->id] ?? null,
                ))
                ->all();
        }

        return FaitMarquantCommentaire::query()
            ->where('fait_marquant_id', $faitMarquantId)
            ->whereBetween('created_at', [$weekStartUtc, $weekEndUtc])
            ->orderBy('id')
            ->with(['user:id,name'])
            ->get()
            ->map(fn (FaitMarquantCommentaire $row) => $this->serializePivotRow(
                $row,
                $sequenceById[(int) $row->id] ?? null,
            ))
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function pivotSequenceById(int $faitMarquantId, string $kind): array
    {
        $query = $kind === 'etapes'
            ? FaitMarquantProchaineEtape::query()
                ->where('fait_marquant_id', $faitMarquantId)
                ->orderBy('created_at')
                ->orderBy('sort_order')
                ->orderBy('id')
            : FaitMarquantCommentaire::query()
                ->where('fait_marquant_id', $faitMarquantId)
                ->orderBy('created_at')
                ->orderBy('id');

        $sequenceById = [];
        foreach ($query->get(['id']) as $idx => $row) {
            $sequenceById[(int) $row->id] = $idx + 1;
        }

        return $sequenceById;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePivotRow(FaitMarquantProchaineEtape|FaitMarquantCommentaire $row, ?int $sequenceNumber): array
    {
        return [
            'id' => (int) $row->id,
            'sequence_number' => $sequenceNumber,
            'body' => (string) $row->body,
            'created_at' => $row->created_at?->toISOString(),
            'user' => $row->user === null
                ? null
                : [
                    'id' => (int) $row->user->id,
                    'name' => (string) $row->user->name,
                ],
        ];
    }
}
