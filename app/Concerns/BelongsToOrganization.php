<?php

namespace App\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned: queries are auto-scoped to the current
 * organization and new records are auto-stamped with it.
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') !== null) {
                return;
            }

            $organizationId = self::currentOrganizationId();

            if ($organizationId !== null) {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }

    /**
     * The organization currently bound to the request, or null (console / is_sup
     * "all organizations" mode).
     */
    public static function currentOrganizationId(): ?int
    {
        if (! app()->bound('currentOrganizationId')) {
            return null;
        }

        $organizationId = app('currentOrganizationId');

        return $organizationId === null ? null : (int) $organizationId;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
