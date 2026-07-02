<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Statut général (table {@code statuses}) pour un fait marquant.
 */
class WorkflowStatus extends Model
{
    protected $table = 'statuses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    /**
     * @return HasMany<FaitMarquant, $this>
     */
    public function faitsMarquants(): HasMany
    {
        return $this->hasMany(FaitMarquant::class, 'status_id');
    }
}
