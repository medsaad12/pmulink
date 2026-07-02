<?php

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaitMarquantCommentaire extends Model
{
    use BelongsToOrganization;

    protected $table = 'fait_marquant_commentaire';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'fait_marquant_id',
        'user_id',
        'body',
    ];

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
}
