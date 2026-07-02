<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EtapeStatus;
use App\Models\FaitMarquant;
use App\Models\FaitMarquantDraft;
use App\Models\FaitMarquantHistory;
use App\Models\FaitStatus;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Support\ProchaineEtapeSerializer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhiteboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('departments:id,name');

        $isGlobalUser = $user->isGlobalUser();
        $assignedDepartmentIds = $user->departmentIds();

        $faitStatuses = FaitStatus::query()->orderBy('sort_order')->get(['id', 'name', 'color']);
        $etapeStatuses = EtapeStatus::query()->orderBy('sort_order')->get(['id', 'name', 'color']);
        $workflowStatuses = WorkflowStatus::query()->orderBy('sort_order')->get(['id', 'name', 'color']);
        $faitStatusById = $faitStatuses->keyBy('id');
        $workflowStatusById = $workflowStatuses->keyBy('id');

        $faitsMarquants = FaitMarquant::query()
            ->when(
                $isGlobalUser,
                static fn ($q) => $q->whereNotNull('submitted_at'),
                static fn ($q) => $q->where(static fn ($q) => $q
                    ->whereNotNull('submitted_at')
                    ->orWhere(static fn ($q) => $q
                        ->whereIn('department_id', $assignedDepartmentIds)
                        ->where('created_by', (int) $user->id))),
            )
            ->with([
                'faitStatus:id,name,color',
                'workflowStatus:id,name,color',
                'department:id,name',
                'creator:id,name',
                'responsableAction:id,name',
                'prochainesEtapes' => static fn ($q) => $q
                    ->select([
                        'id',
                        'fait_marquant_id',
                        'user_id',
                        'responsable_action_id',
                        'deadline',
                        'etape_status_id',
                        'sort_order',
                        'body',
                        'created_at',
                    ])
                    ->with([
                        'user:id,name',
                        'responsableAction:id,name',
                        'etapeStatus:id,name,color',
                    ]),
                'commentaires' => static fn ($q) => $q
                    ->select(['id', 'fait_marquant_id', 'user_id', 'body', 'created_at'])
                    ->with(['user:id,name']),
                'faitMarquantHistories' => static fn ($q) => $q
                    ->select([
                        'id',
                        'fait_marquant_id',
                        'changed_by',
                        'title',
                        'fait_status_id',
                        'status_id',
                        'deadline',
                        'department_id',
                        'responsable_action_id',
                        'created_at',
                    ])
                    ->orderBy('created_at')
                    ->with([
                        'faitStatus:id,name,color',
                        'workflowStatus:id,name,color',
                        'department:id,name',
                        'changedBy:id,name',
                        'responsableAction:id,name',
                    ]),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $draftByFaitId = collect();
        if (! $isGlobalUser && $faitsMarquants->isNotEmpty()) {
            $draftByFaitId = FaitMarquantDraft::query()
                ->where('user_id', (int) $user->id)
                ->whereIn('fait_marquant_id', $faitsMarquants->pluck('id')->all())
                ->with([
                    'responsableAction:id,name',
                    'prochainesEtapes' => static fn ($q) => $q
                        ->select([
                            'id',
                            'fait_marquant_draft_id',
                            'user_id',
                            'responsable_action_id',
                            'deadline',
                            'etape_status_id',
                            'sort_order',
                            'body',
                            'created_at',
                        ])
                        ->with([
                            'user:id,name',
                            'responsableAction:id,name',
                            'etapeStatus:id,name,color',
                        ]),
                    'commentaires' => static fn ($q) => $q
                        ->select(['id', 'fait_marquant_draft_id', 'user_id', 'body', 'created_at'])
                        ->with(['user:id,name']),
                ])
                ->get()
                ->keyBy('fait_marquant_id');
        }

        $faitPayload = $faitsMarquants
            ->map(fn (FaitMarquant $fait) => $this->serializeFaitForWhiteboard(
                $fait,
                $draftByFaitId->get($fait->id),
                $faitStatusById,
                $workflowStatusById,
            ))
            ->values();

        $showDepartmentFilter = $isGlobalUser;

        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        $actionResponsibles = User::query()
            ->inOrganization((int) app('currentOrganizationId'))
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(static fn (User $actionUser) => [
                'id' => (int) $actionUser->id,
                'name' => (string) $actionUser->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Whiteboard', [
            'faitsMarquants' => $faitPayload,
            'faitStatuses' => $faitStatuses,
            'etapeStatuses' => $etapeStatuses,
            'workflowStatuses' => $workflowStatuses,
            'actionResponsibles' => $actionResponsibles,
            'showDepartmentFilter' => $showDepartmentFilter,
            'userDepartmentIds' => $assignedDepartmentIds,
            'departments' => $departments,
        ]);
    }

    private function serializeFaitForWhiteboard(
        FaitMarquant $fait,
        ?FaitMarquantDraft $draft,
        $faitStatusById,
        $workflowStatusById,
    ): array {
        $prochainesEtapes = $draft?->prochainesEtapes ?? $fait->prochainesEtapes;
        $commentaires = $draft?->commentaires ?? $fait->commentaires;
        $faitStatusId = (int) ($draft?->fait_status_id ?? $fait->fait_status_id);
        $workflowStatusId = (int) ($draft?->status_id ?? $fait->status_id);
        $responsableActionId = (int) ($draft?->responsable_action_id ?? $fait->responsable_action_id);
        $faitStatus = $faitStatusById->get($faitStatusId);
        $workflowStatus = $workflowStatusById->get($workflowStatusId);
        $responsableUser = $draft?->responsableAction ?? $fait->responsableAction;

        return [
            'id' => (int) $fait->id,
            'title' => $draft?->title ?? $fait->title,
            'fait_status_id' => $faitStatusId,
            'status_id' => $workflowStatusId,
            'deadline' => ($draft?->deadline ?? $fait->deadline)?->format('Y-m-d'),
            'department_id' => (int) $fait->department_id,
            'created_by' => (int) $fait->created_by,
            'responsable_action_id' => $responsableActionId,
            'responsable_action' => $responsableUser === null
                ? null
                : [
                    'id' => (int) $responsableUser->id,
                    'name' => (string) $responsableUser->name,
                ],
            'creator' => $fait->creator === null
                ? null
                : [
                    'id' => (int) $fait->creator->id,
                    'name' => (string) $fait->creator->name,
                ],
            'created_at' => $fait->created_at?->toISOString(),
            'updated_at' => ($draft?->updated_at ?? $fait->updated_at)?->toISOString(),
            'submitted_at' => $fait->submitted_at?->toISOString(),
            'has_unsubmitted_draft' => $draft !== null,
            'department' => $fait->department === null
                ? null
                : [
                    'id' => (int) $fait->department->id,
                    'name' => $fait->department->name,
                ],
            'fait_status' => $faitStatus === null
                ? null
                : [
                    'id' => (int) $faitStatus->id,
                    'name' => $faitStatus->name,
                    'color' => $faitStatus->color,
                ],
            'workflow_status' => $workflowStatus === null
                ? null
                : [
                    'id' => (int) $workflowStatus->id,
                    'name' => $workflowStatus->name,
                    'color' => $workflowStatus->color,
                ],
            'prochaines_etapes' => $prochainesEtapes
                ->map(static fn ($row) => ProchaineEtapeSerializer::toArray($row))
                ->values()
                ->all(),
            'commentaires' => $commentaires
                ->map(
                    fn ($row) => [
                        'id' => (int) $row->id,
                        'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                        'body' => (string) $row->body,
                        'created_at' => $row->created_at?->toISOString(),
                        'user' => $row->user === null
                            ? null
                            : [
                                'id' => (int) $row->user->id,
                                'name' => (string) $row->user->name,
                            ],
                    ],
                )
                ->values()
                ->all(),
            'fait_marquant_history' => $fait->faitMarquantHistories
                ->map(
                    static function (FaitMarquantHistory $row) {
                        return [
                            'created_at' => $row->created_at?->toISOString(),
                            'title' => (string) $row->title,
                            'fait_status_id' => (int) $row->fait_status_id,
                            'status_id' => (int) $row->status_id,
                            'deadline' => $row->deadline?->format('Y-m-d'),
                            'department_id' => (int) $row->department_id,
                            'responsable_action_id' => (int) $row->responsable_action_id,
                            'responsable_action' => $row->responsableAction === null
                                ? null
                                : [
                                    'id' => (int) $row->responsableAction->id,
                                    'name' => (string) $row->responsableAction->name,
                                ],
                            'fait_status' => $row->faitStatus === null
                                ? null
                                : [
                                    'id' => (int) $row->faitStatus->id,
                                    'name' => (string) $row->faitStatus->name,
                                    'color' => $row->faitStatus->color,
                                ],
                            'workflow_status' => $row->workflowStatus === null
                                ? null
                                : [
                                    'id' => (int) $row->workflowStatus->id,
                                    'name' => (string) $row->workflowStatus->name,
                                    'color' => $row->workflowStatus->color,
                                ],
                            'department' => $row->department === null
                                ? null
                                : [
                                    'id' => (int) $row->department->id,
                                    'name' => (string) $row->department->name,
                                ],
                            'changed_by' => $row->changedBy === null
                                ? null
                                : [
                                    'id' => (int) $row->changedBy->id,
                                    'name' => (string) $row->changedBy->name,
                                ],
                        ];
                    },
                )
                ->values()
                ->all(),
        ];
    }
}
