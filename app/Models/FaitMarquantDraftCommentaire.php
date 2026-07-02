<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaitMarquantDraftCommentaire extends Model
{
    use BelongsToOrganization;

    protected $table = 'fait_marquant_draft_commentaire';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fait_marquant_draft_id',
        'user_id',
        'body',
    ];

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
}
