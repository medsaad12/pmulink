<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaitMarquantDraftProchaineEtape extends Model
{
    use BelongsToOrganization;

    protected $table = 'fait_marquant_draft_prochaine_etape';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fait_marquant_draft_id',
        'user_id',
        'responsable_action_id',
        'deadline',
        'etape_status_id',
        'sort_order',
        'body',
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
     * @return BelongsTo<FaitMarquantDraft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(FaitMarquantDraft::class, 'fait_marquant_draft_id');
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
     * @return BelongsTo<EtapeStatus, $this>
     */
    public function etapeStatus(): BelongsTo
    {
        return $this->belongsTo(EtapeStatus::class, 'etape_status_id');
    }
}
