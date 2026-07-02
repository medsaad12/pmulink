<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for a fresh install (single default org).
     */
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Comex', 'is_active' => true],
        );

        // Bind the org so tenant-owned models seeded below are auto-stamped.
        app()->instance('currentOrganizationId', $organization->id);

        $this->call(FaitMarquantReferenceSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(RbacSeeder::class);

        $admin = Role::query()->where('name', 'Administrateur')->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password')],
        );

        $user->organizations()->syncWithoutDetaching([
            $organization->id => ['role_id' => $admin->id],
        ]);
    }
}
