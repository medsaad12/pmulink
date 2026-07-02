<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

test('users without users.view cannot open the users index', function () {
    $role = Role::query()->create([
        'name' => 'Sans utilisateurs',
        'description' => null,
    ]);
    $role->permissions()->sync([
        Permission::query()->where('name', 'roles.view')->firstOrFail()->id,
    ]);

    $user = User::factory()->withRole($role)->create();
    $this->actingAs($user);

    $this->get(route('users.index'))->assertForbidden();
});

test('users with users.view can open the users index', function () {
    $admin = Role::query()->where('name', 'Administrateur')->firstOrFail();
    $user = User::factory()->withRole($admin)->create();
    $this->actingAs($user);

    $this->get(route('users.index'))->assertOk();
});
