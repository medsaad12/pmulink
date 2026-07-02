<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scopes\OrganizationScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'is_sup'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_sup' => 'boolean',
        ];
    }

    /**
     * Organizations this user is a member of. The per-org role lives on the pivot.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Limit a user query to members of a given organization.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInOrganization(Builder $query, int $organizationId): void
    {
        $query->whereHas('organizations', static fn (Builder $q) => $q->whereKey($organizationId));
    }

    /**
     * Users reachable from an organization's user administration: members of
     * the given organization, plus "orphan" identities that currently belong
     * to no organization (e.g. detached elsewhere) so they stay manageable
     * instead of silently disappearing.
     *
     * @param  Builder<User>  $query
     */
    public function scopeManageableInOrganization(Builder $query, int $organizationId): void
    {
        $query->where(function (Builder $q) use ($organizationId): void {
            $q->whereHas('organizations', static fn (Builder $sub) => $sub->whereKey($organizationId))
                ->orWhereDoesntHave('organizations');
        });
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    /**
     * Faits marquants créés par cet utilisateur.
     *
     * @return HasMany<FaitMarquant, $this>
     */
    public function faitsMarquantsCree(): HasMany
    {
        return $this->hasMany(FaitMarquant::class, 'created_by');
    }

    /**
     * Resolve the membership role id for a given (or the current) organization.
     */
    public function roleIdForOrganization(?int $organizationId = null): ?int
    {
        $organizationId ??= self::resolveCurrentOrganizationId();

        if ($organizationId === null) {
            return null;
        }

        if ($this->relationLoaded('organizations')) {
            $organization = $this->organizations->firstWhere('id', $organizationId);
            $roleId = $organization?->getRelationValue('pivot')?->role_id;

            return $roleId === null ? null : (int) $roleId;
        }

        $roleId = $this->organizations()
            ->whereKey($organizationId)
            ->value('organization_user.role_id');

        return $roleId === null ? null : (int) $roleId;
    }

    /**
     * The user's role within a given (or the current) organization.
     */
    public function roleForOrganization(?int $organizationId = null): ?Role
    {
        $roleId = $this->roleIdForOrganization($organizationId);

        if ($roleId === null) {
            return null;
        }

        return Role::query()->withoutGlobalScope(OrganizationScope::class)->find($roleId);
    }

    /**
     * Convenience accessor: {@code $user->role} resolves the current-org role.
     */
    public function getRoleAttribute(): ?Role
    {
        return $this->roleForOrganization();
    }

    /**
     * @return list<int>
     */
    public function organizationIds(): array
    {
        return $this->organizations()
            ->pluck('organizations.id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function belongsToOrganization(int $organizationId): bool
    {
        return in_array($organizationId, $this->organizationIds(), true);
    }

    /**
     * True when the identity currently belongs to no organization at all.
     */
    public function isOrphan(): bool
    {
        return $this->organizations()->doesntExist();
    }

    /**
     * Utilisateur sans département assigné dans l'organisation courante (vue globale).
     */
    public function isGlobalUser(): bool
    {
        if ($this->relationLoaded('departments')) {
            return $this->departments->isEmpty();
        }

        return ! $this->departments()->exists();
    }

    /**
     * @return list<int>
     */
    public function departmentIds(): array
    {
        if ($this->relationLoaded('departments')) {
            return $this->departments
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->departments()
            ->pluck('departments.id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function belongsToDepartment(int $departmentId): bool
    {
        return in_array($departmentId, $this->departmentIds(), true);
    }

    /**
     * Stable permission keys granted through the user's role in the current
     * (or given) organization. Supervisors (is_sup) implicitly hold every key.
     *
     * @return list<string>
     */
    public function flattenPermissionKeys(?int $organizationId = null): array
    {
        $role = $this->roleForOrganization($organizationId);

        if ($role === null) {
            return [];
        }

        return $role->permissions
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermissionKey(string $key): bool
    {
        if ($this->isSup()) {
            return true;
        }

        return in_array($key, $this->flattenPermissionKeys(), true);
    }

    /**
     * Global support / super-admin flag (set only in the database, no UI).
     * Spans all organizations and bypasses the tenant scope.
     */
    public function isSup(): bool
    {
        return (bool) $this->is_sup;
    }

    private static function resolveCurrentOrganizationId(): ?int
    {
        if (! app()->bound('currentOrganizationId')) {
            return null;
        }

        $organizationId = app('currentOrganizationId');

        return $organizationId === null ? null : (int) $organizationId;
    }
}
