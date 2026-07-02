<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaitMarquantProchaineEtape extends Model
{
    use BelongsToOrganization;

    protected $table = 'fait_marquant_prochaine_etape';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fait_marquant_id',
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
     * @return BelongsTo<EtapeStatus, $this>
     */
    public function etapeStatus(): BelongsTo
    {
        return $this->belongsTo(EtapeStatus::class, 'etape_status_id');
    }
}
