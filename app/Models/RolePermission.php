<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RolePermission extends Pivot
{
    protected $table = 'permission_role';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'permission_id',
    ];
}
