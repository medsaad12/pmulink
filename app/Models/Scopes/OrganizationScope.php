<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically constrains every query on a tenant-owned model to the current
 * organization. When no current organization is bound (console, seeders, or an
 * is_sup "all organizations" session), no constraint is applied.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('currentOrganizationId')) {
            return;
        }

        $organizationId = app('currentOrganizationId');

        if ($organizationId === null) {
            return;
        }

        $builder->where($model->getTable().'.organization_id', (int) $organizationId);
    }
}
