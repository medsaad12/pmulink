<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaitMarquantDraft extends Model
{
    use BelongsToOrganization;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fait_marquant_id',
        'user_id',
        'title',
        'fait_status_id',
        'status_id',
        'deadline',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsableAction(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_action_id');
    }

    /**
     * @return HasMany<FaitMarquantDraftProchaineEtape, $this>
     */
    public function prochainesEtapes(): HasMany
    {
        return $this->hasMany(FaitMarquantDraftProchaineEtape::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<FaitMarquantDraftCommentaire, $this>
     */
    public function commentaires(): HasMany
    {
        return $this->hasMany(FaitMarquantDraftCommentaire::class);
    }
}
