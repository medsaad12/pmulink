<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaitStatus extends Model
{
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
        return $this->hasMany(FaitMarquant::class, 'fait_status_id');
    }
}
