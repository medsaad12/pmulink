<?php

use App\Models\Department;
use App\Models\FaitMarquant;
use App\Models\FaitStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\OrganizationProvisioner;
use Database\Seeders\FaitMarquantReferenceSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seeds global permissions + org #1 with its default roles, and the
    // global reference statuses used by faits marquants.
    $this->seed(RbacSeeder::class);
    $this->seed(FaitMarquantReferenceSeeder::class);

    $this->org1 = Organization::query()->firstOrFail();
    $this->org2 = app(OrganizationProvisioner::class)->create('Deuxième organisation');

    $this->faitStatusId = (int) FaitStatus::query()->value('id');
    $this->workflowStatusId = (int) WorkflowStatus::query()->value('id');
});

function adminRole(int $organizationId): Role
{
    return Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organizationId)
        ->where('name', 'Administrateur')
        ->firstOrFail();
}

function makeFait(int $organizationId, int $departmentId, int $userId, int $faitStatusId, int $workflowStatusId, string $title): FaitMarquant
{
    return FaitMarquant::query()->create([
        'organization_id' => $organizationId,
        'title' => $title,
        'fait_status_id' => $faitStatusId,
        'status_id' => $workflowStatusId,
        'department_id' => $departmentId,
        'created_by' => $userId,
        'responsable_action_id' => $userId,
        'submitted_at' => now(),
    ]);
}

test('faits are isolated between organizations on the whiteboard', function () {
    $admin1 = User::factory()->withRole(adminRole($this->org1->id))->create();

    $dept1 = Department::query()->create(['organization_id' => $this->org1->id, 'name' => 'Dept 1']);
    $dept2 = Department::query()->create(['organization_id' => $this->org2->id, 'name' => 'Dept 2']);

    makeFait($this->org1->id, $dept1->id, $admin1->id, $this->faitStatusId, $this->workflowStatusId, 'Fait Org1');
    makeFait($this->org2->id, $dept2->id, $admin1->id, $this->faitStatusId, $this->workflowStatusId, 'Fait Org2');

    $this->actingAs($admin1)
        ->get(route('whiteboard'))
        ->assertInertia(fn ($page) => $page
            ->has('faitsMarquants', 1)
            ->where('faitsMarquants.0.title', 'Fait Org1'));
});

test('a user cannot update a fait from another organization', function () {
    $admin1 = User::factory()->withRole(adminRole($this->org1->id))->create();
    $dept2 = Department::query()->create(['organization_id' => $this->org2->id, 'name' => 'Dept 2']);
    $admin2 = User::factory()->withRole(adminRole($this->org2->id))->create();

    $fait2 = makeFait($this->org2->id, $dept2->id, $admin2->id, $this->faitStatusId, $this->workflowStatusId, 'Fait Org2');

    $this->actingAs($admin1)
        ->put(route('faits-marquants.update', $fait2), [
            'title' => 'Hacked',
            'fait_status_id' => $this->faitStatusId,
            'status_id' => $this->workflowStatusId,
            'department_id' => $dept2->id,
            'responsable_action_id' => $admin1->id,
        ])
        ->assertNotFound();

    expect($fait2->fresh()->title)->toBe('Fait Org2');
});

test('an org admin cannot edit a user from another organization', function () {
    $admin1 = User::factory()->withRole(adminRole($this->org1->id))->create();
    $userRole2 = Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $this->org2->id)->where('name', 'Utilisateur')->firstOrFail();
    $foreignUser = User::factory()->withRole($userRole2)->create();

    $org1UserRole = Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $this->org1->id)->where('name', 'Utilisateur')->firstOrFail();

    $this->actingAs($admin1)
        ->put(route('users.update', $foreignUser), [
            'organization_ids' => [$this->org1->id],
            'name' => 'Takeover',
            'email' => $foreignUser->email,
            'role_id' => $org1UserRole->id,
        ])
        ->assertNotFound();

    expect($foreignUser->fresh()->name)->not->toBe('Takeover');
});

test('editing a multi-org user preserves department assignments in other orgs', function () {
    $admin1 = User::factory()->withRole(adminRole($this->org1->id))->create();

    $dept1 = Department::query()->create(['organization_id' => $this->org1->id, 'name' => 'Dept 1']);
    $dept2 = Department::query()->create(['organization_id' => $this->org2->id, 'name' => 'Dept 2']);

    $org1UserRole = Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $this->org1->id)->where('name', 'Utilisateur')->firstOrFail();
    $org2UserRole = Role::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $this->org2->id)->where('name', 'Utilisateur')->firstOrFail();

    $multiOrgUser = User::factory()->create();
    $multiOrgUser->organizations()->attach($this->org1->id, ['role_id' => $org1UserRole->id]);
    $multiOrgUser->organizations()->attach($this->org2->id, ['role_id' => $org2UserRole->id]);
    $multiOrgUser->departments()->attach([$dept1->id, $dept2->id]);

    // Acting in org1, remove dept1 (send empty department list).
    $this->actingAs($admin1)
        ->put(route('users.update', $multiOrgUser), [
            'organization_ids' => [$this->org1->id],
            'name' => $multiOrgUser->name,
            'email' => $multiOrgUser->email,
            'role_id' => $org1UserRole->id,
            'department_ids' => [],
        ])
        ->assertRedirect();

    // Read the pivot directly to avoid the tenant scope on the departments table.
    $departmentIds = DB::table('department_user')
        ->where('user_id', $multiOrgUser->id)
        ->pluck('department_id')
        ->map(static fn ($id) => (int) $id)
        ->all();

    expect($departmentIds)->not->toContain($dept1->id); // removed in org1
    expect($departmentIds)->toContain($dept2->id);       // preserved in org2
});
