<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaitMarquantHistory extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fait_marquant_id',
        'changed_by',
        'title',
        'fait_status_id',
        'status_id',
        'deadline',
        'department_id',
        'responsable_action_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FaitMarquant, $this>
     */
    public function faitMarquant(): BelongsTo
    {
        return $this->belongsTo(FaitMarquant::class);
    }

    /**
     * @return BelongsTo<FaitStatus, $this>
     */
    public function faitStatus(): BelongsTo
    {
        return $this->belongsTo(FaitStatus::class, 'fait_status_id');
    }

    /**
     * @return BelongsTo<WorkflowStatus, $this>
     */
    public function workflowStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'status_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsableAction(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_action_id');
    }

    /**
     * Snapshot des champs suivis sur le fait publié (pour comparaison / persistance).
     *
     * @return array{title: string, fait_status_id: int, status_id: int, deadline: ?string, department_id: int, responsable_action_id: int}
     */
    public static function snapshotArray(FaitMarquant $fait): array
    {
        $deadline = $fait->deadline;
        $deadlineKey = $deadline === null
            ? null
            : ($deadline instanceof CarbonInterface
                ? $deadline->format('Y-m-d')
                : (string) $deadline);

        return [
            'title' => (string) $fait->title,
            'fait_status_id' => (int) $fait->fait_status_id,
            'status_id' => (int) $fait->status_id,
            'deadline' => $deadlineKey,
            'department_id' => (int) $fait->department_id,
            'responsable_action_id' => (int) $fait->responsable_action_id,
        ];
    }

    /**
     * @param  array{title: string, fait_status_id: int, status_id: int, deadline: ?string, department_id: int, responsable_action_id: int}  $a
     * @param  array{title: string, fait_status_id: int, status_id: int, deadline: ?string, department_id: int, responsable_action_id: int}  $b
     */
    public static function snapshotsEqual(array $a, array $b): bool
    {
        return $a === $b;
    }

    /**
     * Enregistre un snapshot si {@code $before} est null (création) ou si l'état publié a changé.
     *
     * @param  array{title: string, fait_status_id: int, status_id: int, deadline: ?string, department_id: int, responsable_action_id: int}|null  $before
     */
    public static function recordIfChanged(FaitMarquant $fait, ?array $before, ?int $changedById): void
    {
        $after = static::snapshotArray($fait);
        if ($before !== null && static::snapshotsEqual($before, $after)) {
            return;
        }

        static::query()->create([
            'organization_id' => $fait->organization_id,
            'fait_marquant_id' => $fait->id,
            'changed_by' => $changedById,
            'title' => $after['title'],
            'fait_status_id' => $after['fait_status_id'],
            'status_id' => $after['status_id'],
            'deadline' => $after['deadline'],
            'department_id' => $after['department_id'],
            'responsable_action_id' => $after['responsable_action_id'],
        ]);
    }
}
