<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtapeStatus extends Model
{
    protected $table = 'etape_statuses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    /**
     * @return HasMany<FaitMarquantProchaineEtape, $this>
     */
    public function prochainesEtapes(): HasMany
    {
        return $this->hasMany(FaitMarquantProchaineEtape::class, 'etape_status_id');
    }
}
