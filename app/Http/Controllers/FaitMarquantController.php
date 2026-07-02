<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaitMarquantDraftSaveRequest;
use App\Http\Requests\FaitMarquantStickySyncRequest;
use App\Http\Requests\FaitMarquantStoreRequest;
use App\Http\Requests\FaitMarquantUpdateRequest;
use App\Models\FaitMarquant;
use App\Models\FaitMarquantDraft;
use App\Models\FaitMarquantHistory;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\FaitMarquantPivotRowsSynchronizer;
use App\Services\FaitMarquantWeeklyTimelineBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FaitMarquantController extends Controller
{
    private const DEFAULT_STATUS_ID = 1;

    private const WORKFLOW_ACTION_TO_STATUS_NAME = [
        'archive' => 'Archivé',
        'cloture' => 'Clôturé',
    ];

    public function __construct(
        private readonly FaitMarquantPivotRowsSynchronizer $pivotRowsSynchronizer,
    ) {}

    public function store(FaitMarquantStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $departmentId = (int) $validated['department_id'];

        DB::transaction(function () use ($validated, $user, $departmentId): void {
            $isGlobalUser = $user->isGlobalUser();
            $faitMarquant = FaitMarquant::query()->create([
                'title' => $validated['title'],
                'fait_status_id' => $validated['fait_status_id'],
                'status_id' => $isGlobalUser ? (int) $validated['status_id'] : self::DEFAULT_STATUS_ID,
                'deadline' => $validated['deadline'] ?? null,
                'department_id' => $departmentId,
                'created_by' => $user->id,
                'responsable_action_id' => (int) $validated['responsable_action_id'],
                'submitted_at' => $isGlobalUser ? now() : null,
            ]);

            if ($isGlobalUser) {
                FaitMarquantHistory::recordIfChanged($faitMarquant, null, (int) $user->id);
                $this->syncPublishedRows($faitMarquant, $validated, (int) $user->id);

                return;
            }

            // Pas d'historique publié tant que le brouillon n'est pas soumis (timeline = submitted_at).

            // Listes « officielles » : vides jusqu'à soumission du brouillon (merge côté whiteboard).
            $draft = FaitMarquantDraft::query()->updateOrCreate(
                [
                    'fait_marquant_id' => $faitMarquant->id,
                    'user_id' => (int) $user->id,
                ],
                [
                    'title' => $validated['title'],
                    'fait_status_id' => $validated['fait_status_id'],
                    'status_id' => (int) $validated['status_id'],
                    'deadline' => $validated['deadline'] ?? null,
                    'responsable_action_id' => (int) $validated['responsable_action_id'],
                ],
            );

            $this->syncDraftRows(
                $draft,
                $validated,
                (int) $user->id,
            );
        });

        return to_route('whiteboard');
    }

    public function update(FaitMarquantUpdateRequest $request, FaitMarquant $faitMarquant): RedirectResponse
    {
        $validated = $request->validated();
        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($validated, $faitMarquant, $userId): void {
            $before = FaitMarquantHistory::snapshotArray($faitMarquant);

            $updates = [
                'title' => $validated['title'],
                'fait_status_id' => $validated['fait_status_id'],
                'status_id' => $validated['status_id'],
                'deadline' => $validated['deadline'] ?? null,
                'responsable_action_id' => (int) $validated['responsable_action_id'],
            ];

            if (array_key_exists('department_id', $validated)) {
                $updates['department_id'] = (int) $validated['department_id'];
            }

            $faitMarquant->update($updates);

            FaitMarquantHistory::recordIfChanged($faitMarquant, $before, $userId);

            $this->syncPublishedRows($faitMarquant, $validated, $userId);
        });

        return to_route('whiteboard');
    }

    public function saveDraft(FaitMarquantDraftSaveRequest $request, FaitMarquant $faitMarquant): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $faitMarquant, $user): void {
            $userId = (int) $user->id;
            $newDepartmentId = (int) $validated['department_id'];

            if ((int) $faitMarquant->department_id !== $newDepartmentId) {
                $before = FaitMarquantHistory::snapshotArray($faitMarquant);
                $faitMarquant->update(['department_id' => $newDepartmentId]);
                FaitMarquantHistory::recordIfChanged($faitMarquant, $before, $userId);
            }

            $existingDraft = FaitMarquantDraft::query()
                ->where('fait_marquant_id', $faitMarquant->id)
                ->where('user_id', $userId)
                ->first();

            $preservedDeadline = $existingDraft?->deadline ?? $faitMarquant->deadline;

            $draft = FaitMarquantDraft::query()->updateOrCreate(
                [
                    'fait_marquant_id' => $faitMarquant->id,
                    'user_id' => $userId,
                ],
                [
                    'title' => $validated['title'],
                    'fait_status_id' => $validated['fait_status_id'],
                    'status_id' => $validated['status_id'],
                    'deadline' => $preservedDeadline,
                    'responsable_action_id' => (int) $validated['responsable_action_id'],
                ],
            );

            $this->syncDraftRows($draft, $validated, $userId);
        });

        return to_route('whiteboard');
    }

    /**
     * Soumet tous les brouillons de l'utilisateur courant vers les tables officielles (un seul aller-retour).
     */
    public function submitAllDrafts(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->isGlobalUser()) {
            abort(403);
        }

        $drafts = FaitMarquantDraft::query()
            ->where('user_id', (int) $user->id)
            ->with([
                'faitMarquant',
                'prochainesEtapes' => static fn ($q) => $q->select([
                    'id',
                    'fait_marquant_draft_id',
                    'user_id',
                    'responsable_action_id',
                    'deadline',
                    'etape_status_id',
                    'sort_order',
                    'body',
                    'created_at',
                ]),
                'commentaires' => static fn ($q) => $q->select([
                    'id',
                    'fait_marquant_draft_id',
                    'user_id',
                    'body',
                    'created_at',
                ]),
            ])
            ->get();

        $pairs = [];
        foreach ($drafts as $draft) {
            $fait = $draft->faitMarquant;
            if ($fait !== null && $fait->allowsCollaborationFrom($user)) {
                $pairs[] = [$draft, $fait];
            }
        }

        if ($pairs === []) {
            return to_route('whiteboard');
        }

        DB::transaction(function () use ($pairs, $user): void {
            foreach ($pairs as [$draft, $faitMarquant]) {
                $this->mergeUserDraftIntoPublished($draft, $faitMarquant, $user);
            }
        });

        return to_route('whiteboard');
    }

    public function destroy(FaitMarquant $faitMarquant): RedirectResponse
    {
        $user = request()->user();

        if ($user === null || ! $faitMarquant->allowsCollaborationFrom($user)) {
            abort(403);
        }

        $faitMarquant->delete();

        return to_route('whiteboard');
    }

    public function weeklyTimeline(Request $request, FaitMarquant $faitMarquant): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $faitMarquant->allowsCollaborationFrom($user)) {
            abort(403);
        }

        $weeks = app(FaitMarquantWeeklyTimelineBuilder::class)->build($faitMarquant);

        return response()->json([
            'timezone' => FaitMarquantWeeklyTimelineBuilder::TIMEZONE,
            'weeks' => $weeks,
        ]);
    }

    public function syncSticky(FaitMarquantStickySyncRequest $request, FaitMarquant $faitMarquant): Response
    {
        $validated = $request->validated();
        $user = $request->user();
        $userId = (int) $user->id;

        DB::transaction(function () use ($validated, $faitMarquant, $user, $userId): void {
            $target = $this->resolveStickySyncTarget($faitMarquant, $user);
            $publishedSnapshotBefore = FaitMarquantHistory::snapshotArray($faitMarquant);
            $updates = $this->buildStickySyncUpdates($validated);

            $target->update($updates);

            if ($target instanceof FaitMarquant) {
                FaitMarquantHistory::recordIfChanged($faitMarquant, $publishedSnapshotBefore, $userId);
            }

            $this->syncStickyRows($target, $faitMarquant, $validated, $userId);
        });

        return response()->noContent();
    }

    private function resolveStickySyncTarget(
        FaitMarquant $faitMarquant,
        User $user,
    ): FaitMarquant|FaitMarquantDraft {
        if ($user->isGlobalUser()) {
            return $faitMarquant;
        }

        return FaitMarquantDraft::query()->firstOrCreate(
            [
                'fait_marquant_id' => $faitMarquant->id,
                'user_id' => (int) $user->id,
            ],
            [
                'title' => $faitMarquant->title,
                'fait_status_id' => $faitMarquant->fait_status_id,
                'status_id' => $faitMarquant->status_id,
                'deadline' => $faitMarquant->deadline,
                'responsable_action_id' => $faitMarquant->responsable_action_id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildStickySyncUpdates(array $validated): array
    {
        $updates = [
            'title' => $validated['projectName'],
            'fait_status_id' => (int) $validated['fait_status_id'],
        ];

        $workflowStatusId = $this->resolveWorkflowStatusId($validated['workflow_action'] ?? null);
        if ($workflowStatusId !== null) {
            $updates['status_id'] = $workflowStatusId;
        }

        return $updates;
    }

    private function mergeUserDraftIntoPublished(
        FaitMarquantDraft $draft,
        FaitMarquant $faitMarquant,
        User $user,
    ): void {
        $wasUnsubmitted = $faitMarquant->submitted_at === null;
        $before = $wasUnsubmitted ? null : FaitMarquantHistory::snapshotArray($faitMarquant);

        $submittedAt = $faitMarquant->submitted_at ?? now();
        $faitMarquant->update([
            'title' => $draft->title,
            'fait_status_id' => $draft->fait_status_id,
            'status_id' => $draft->status_id,
            'deadline' => $draft->deadline,
            'responsable_action_id' => $draft->responsable_action_id,
            'submitted_at' => $submittedAt,
        ]);

        FaitMarquantHistory::recordIfChanged($faitMarquant, $before, (int) $user->id);

        $this->pivotRowsSynchronizer->mergeDraftProchainesEtapesIntoPublished(
            $faitMarquant,
            $draft->prochainesEtapes,
            (int) $user->id,
            $submittedAt,
        );

        $this->pivotRowsSynchronizer->mergeDraftCommentairesIntoPublished(
            $faitMarquant,
            $draft->commentaires()->orderBy('id')->get(),
            (int) $user->id,
            $submittedAt,
        );

        $draft->delete();
    }

    private function resolveWorkflowStatusId(?string $workflowAction): ?int
    {
        if ($workflowAction === null) {
            return null;
        }

        $statusName = self::WORKFLOW_ACTION_TO_STATUS_NAME[$workflowAction] ?? null;
        if ($statusName === null) {
            return null;
        }

        $id = WorkflowStatus::query()->where('name', $statusName)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncStickyRows(
        FaitMarquant|FaitMarquantDraft $target,
        FaitMarquant $faitMarquant,
        array $validated,
        int $userId,
    ): void {
        if (array_key_exists('faitsMarquants', $validated)) {
            if ($target instanceof FaitMarquant) {
                $this->pivotRowsSynchronizer->syncPublishedProchainesEtapes(
                    $faitMarquant,
                    array_values($validated['faitsMarquants']),
                    $userId,
                );
            } else {
                $this->pivotRowsSynchronizer->syncDraftProchainesEtapes(
                    $target,
                    array_values($validated['faitsMarquants']),
                    $userId,
                );
            }
        }

        if (array_key_exists('commentaires', $validated)) {
            if ($target instanceof FaitMarquant) {
                $this->pivotRowsSynchronizer->syncPublishedCommentaires(
                    $faitMarquant,
                    array_values($validated['commentaires']),
                    $userId,
                );
            } else {
                $this->pivotRowsSynchronizer->syncDraftCommentaires(
                    $target,
                    array_values($validated['commentaires']),
                    $userId,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncPublishedRows(FaitMarquant $faitMarquant, array $validated, int $userId): void
    {
        if (array_key_exists('prochaines_etapes', $validated)) {
            $this->pivotRowsSynchronizer->syncPublishedProchainesEtapes(
                $faitMarquant,
                array_values($validated['prochaines_etapes']),
                $userId,
            );
        }

        if (array_key_exists('commentaires', $validated)) {
            $this->pivotRowsSynchronizer->syncPublishedCommentaires(
                $faitMarquant,
                array_values($validated['commentaires']),
                $userId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncDraftRows(FaitMarquantDraft $draft, array $validated, int $userId): void
    {
        if (array_key_exists('prochaines_etapes', $validated)) {
            $this->pivotRowsSynchronizer->syncDraftProchainesEtapes(
                $draft,
                array_values($validated['prochaines_etapes']),
                $userId,
            );
        }

        if (array_key_exists('commentaires', $validated)) {
            $this->pivotRowsSynchronizer->syncDraftCommentaires(
                $draft,
                array_values($validated['commentaires']),
                $userId,
            );
        }
    }
}
