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

test('guests cannot visit the roles index', function () {
    $response = $this->get(route('roles.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users without roles.view cannot visit the roles index', function () {
    $role = Role::query()->create([
        'name' => 'Sans liste des rôles',
        'description' => null,
    ]);

    $user = User::factory()->withRole($role)->create();
    $this->actingAs($user);

    $response = $this->get(route('roles.index'));

    $response->assertForbidden();
});

test('authenticated users with roles.view can visit the roles index', function () {
    $admin = Role::query()->where('name', 'Administrateur')->firstOrFail();
    $user = User::factory()->withRole($admin)->create();

    $this->actingAs($user);

    $response = $this->get(route('roles.index'));

    $response->assertOk();
});

test('users without roles.assign-permissions cannot sync role permissions', function () {
    $viewer = Role::query()->create([
        'name' => 'Lecteur',
        'description' => null,
    ]);
    $viewPerm = Permission::query()->where('name', 'roles.view')->firstOrFail();
    $viewer->permissions()->sync([$viewPerm->id]);

    $user = User::factory()->withRole($viewer)->create();

    $this->actingAs($user);

    $response = $this->put(route('roles.permissions.sync', $viewer), [
        'permission_ids' => [],
    ]);

    $response->assertForbidden();
});
