<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Attach the user to an organization with the given role (per-org membership).
     */
    public function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->organizations()->syncWithoutDetaching([
                $role->organization_id => ['role_id' => $role->id],
            ]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Attach one or more departments after creation.
     *
     * @param  Department|list<int>|int  $departments
     */
    public function withDepartments(Department|array|int $departments): static
    {
        return $this->afterCreating(function (User $user) use ($departments): void {
            $ids = match (true) {
                $departments instanceof Department => [$departments->id],
                is_int($departments) => [$departments],
                default => collect($departments)->map(static fn ($d) => $d instanceof Department ? $d->id : (int) $d)->all(),
            };

            $user->departments()->sync($ids);
        });
    }

    /**
     * User with dashboard access (is_sup is not mass-assignable from forms).
     */
    public function supervisor(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->forceFill(['is_sup' => true])->save();
        });
    }
}
