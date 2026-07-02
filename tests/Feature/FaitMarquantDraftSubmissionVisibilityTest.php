<?php

use App\Models\Department;
use App\Models\FaitMarquant;
use App\Models\FaitMarquantDraft;
use App\Models\FaitMarquantHistory;
use App\Models\FaitStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\FaitMarquantWeeklyTimelineBuilder;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

test('department-created faits stay hidden from owner until submit all drafts', function () {
    $department = Department::query()->create(['name' => 'Ops']);
    $userRole = Role::query()->where('name', 'Utilisateur')->firstOrFail();

    $departmentUser = User::factory()
        ->withDepartments($department)
        ->withRole($userRole)
        ->create();

    $faitStatusId = (int) FaitStatus::query()->value('id');
    $workflowStatusId = (int) WorkflowStatus::query()->value('id');

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.store'), [
            'title' => 'Projet brouillon',
            'fait_status_id' => $faitStatusId,
            'status_id' => $workflowStatusId,
            'deadline' => '2026-05-29',
            'department_id' => $department->id,
            'responsable_action_id' => $departmentUser->id,
            'prochaines_etapes' => ['Préparer le dossier'],
            'commentaires' => ['À valider avant soumission'],
        ])
        ->assertStatus(302);

    $fait = FaitMarquant::query()->where('title', 'Projet brouillon')->firstOrFail();

    expect($fait->submitted_at)->toBeNull();
    expect(FaitMarquantDraft::query()->where('fait_marquant_id', $fait->id)->exists())->toBeTrue();

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.drafts.submit-all'))
        ->assertStatus(302);

    $fait->refresh();

    expect($fait->submitted_at)->not->toBeNull();
    expect(FaitMarquantHistory::query()->where('fait_marquant_id', $fait->id)->count())->toBe(1);
    expect(FaitMarquantDraft::query()->where('fait_marquant_id', $fait->id)->exists())->toBeFalse();
    expect($fait->prochainesEtapes()->count())->toBe(1);
    expect($fait->commentaires()->count())->toBe(1);
    expect($fait->prochainesEtapes()->value('body'))->toBe('Préparer le dossier');
    expect($fait->commentaires()->value('body'))->toBe('À valider avant soumission');

    $weeks = app(FaitMarquantWeeklyTimelineBuilder::class)->build($fait->fresh());
    $submittedWeek = collect($weeks)->first(
        fn (array $week) => $week['snapshot'] !== null
            && $week['snapshot']['title'] === 'Projet brouillon',
    );

    expect($submittedWeek)->not->toBeNull();
    expect(collect($submittedWeek['prochaines_etapes'])->pluck('body')->all())->toBe(['Préparer le dossier']);
    expect(collect($submittedWeek['commentaires'])->pluck('body')->all())->toBe(['À valider avant soumission']);
});

test('submit all drafts keeps prochaines etapes and commentaires', function () {
    $department = Department::query()->create(['name' => 'Ops']);
    $userRole = Role::query()->where('name', 'Utilisateur')->firstOrFail();

    $departmentUser = User::factory()
        ->withDepartments($department)
        ->withRole($userRole)
        ->create();

    $faitStatusId = (int) FaitStatus::query()->value('id');
    $workflowStatusId = (int) WorkflowStatus::query()->value('id');

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.store'), [
            'title' => 'Projet brouillon 1',
            'fait_status_id' => $faitStatusId,
            'status_id' => $workflowStatusId,
            'deadline' => '2026-06-01',
            'department_id' => $department->id,
            'responsable_action_id' => $departmentUser->id,
            'prochaines_etapes' => ['Etape 1'],
            'commentaires' => ['Commentaire 1'],
        ])
        ->assertStatus(302);

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.store'), [
            'title' => 'Projet brouillon 2',
            'fait_status_id' => $faitStatusId,
            'status_id' => $workflowStatusId,
            'deadline' => '2026-06-02',
            'department_id' => $department->id,
            'responsable_action_id' => $departmentUser->id,
            'prochaines_etapes' => ['Etape 2'],
            'commentaires' => ['Commentaire 2'],
        ])
        ->assertStatus(302);

    expect(FaitMarquantDraft::query()->count())->toBe(2);

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.drafts.submit-all'))
        ->assertStatus(302);

    expect(FaitMarquantDraft::query()->count())->toBe(0);

    $faitOne = FaitMarquant::query()->where('title', 'Projet brouillon 1')->firstOrFail();
    $faitTwo = FaitMarquant::query()->where('title', 'Projet brouillon 2')->firstOrFail();

    expect($faitOne->prochainesEtapes()->value('body'))->toBe('Etape 1');
    expect($faitOne->commentaires()->value('body'))->toBe('Commentaire 1');
    expect($faitTwo->prochainesEtapes()->value('body'))->toBe('Etape 2');
    expect($faitTwo->commentaires()->value('body'))->toBe('Commentaire 2');
});

test('first submit shows draft rows in the week they were created', function () {
    $department = Department::query()->create(['name' => 'Ops']);
    $userRole = Role::query()->where('name', 'Utilisateur')->firstOrFail();

    $departmentUser = User::factory()
        ->withDepartments($department)
        ->withRole($userRole)
        ->create();

    $faitStatusId = (int) FaitStatus::query()->value('id');
    $workflowStatusId = (int) WorkflowStatus::query()->value('id');

    Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE));

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.store'), [
            'title' => 'Projet brouillon avec historique',
            'fait_status_id' => $faitStatusId,
            'status_id' => $workflowStatusId,
            'deadline' => '2026-05-29',
            'department_id' => $department->id,
            'responsable_action_id' => $departmentUser->id,
            'prochaines_etapes' => ['Préparer le dossier'],
            'commentaires' => ['À valider avant soumission'],
        ])
        ->assertStatus(302);

    Carbon::setTestNow(Carbon::parse('2026-05-20 11:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE));

    $this->actingAs($departmentUser)
        ->post(route('faits-marquants.drafts.submit-all'))
        ->assertStatus(302);

    $fait = FaitMarquant::query()->where('title', 'Projet brouillon avec historique')->firstOrFail();
    $weeks = app(FaitMarquantWeeklyTimelineBuilder::class)->build($fait->fresh());
    $createdWeek = collect($weeks)->firstWhere('week_start', '2026-05-11');

    expect($createdWeek)->not->toBeNull();
    expect(collect($createdWeek['prochaines_etapes'])->pluck('body')->all())->toBe(['Préparer le dossier']);
    expect(collect($createdWeek['commentaires'])->pluck('body')->all())->toBe(['À valider avant soumission']);

    Carbon::setTestNow();
});
