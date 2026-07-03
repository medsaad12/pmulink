<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaitMarquant extends Model
{
    use BelongsToOrganization, SoftDeletes;

    /**
     * La pluralisation anglaise produit {@code fait_marquants} ; la table réelle est {@code faits_marquants}.
     */
    protected $table = 'faits_marquants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'title',
        'fait_status_id',
        'status_id',
        'deadline',
        'department_id',
        'created_by',
        'responsable_action_id',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsableAction(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_action_id');
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
     * @return HasMany<FaitMarquantProchaineEtape, $this>
     */
    public function prochainesEtapes(): HasMany
    {
        return $this->hasMany(FaitMarquantProchaineEtape::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<FaitMarquantCommentaire, $this>
     */
    public function commentaires(): HasMany
    {
        return $this->hasMany(FaitMarquantCommentaire::class);
    }

    /**
     * @return HasMany<FaitMarquantHistory, $this>
     */
    public function faitMarquantHistories(): HasMany
    {
        return $this->hasMany(FaitMarquantHistory::class)->orderBy('created_at');
    }

    /**
     * Mise à jour / suppression : utilisateur global, responsable d'action (du fait
     * ou d'une prochaine étape), ou fait rattaché à un département assigné à l'utilisateur.
     */
    public function allowsCollaborationFrom(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isGlobalUser()) {
            return true;
        }

        if ($this->isResponsableAction($user)) {
            return true;
        }

        if ($this->department_id === null) {
            return false;
        }

        return $user->belongsToDepartment((int) $this->department_id);
    }

    /**
     * L'utilisateur est-il responsable d'action, soit du fait marquant lui-même,
     * soit d'au moins une de ses prochaines étapes ?
     */
    private function isResponsableAction(User $user): bool
    {
        $userId = (int) $user->id;

        if ((int) $this->responsable_action_id === $userId) {
            return true;
        }

        return $this->prochainesEtapes()
            ->where('responsable_action_id', $userId)
            ->exists();
    }
}
