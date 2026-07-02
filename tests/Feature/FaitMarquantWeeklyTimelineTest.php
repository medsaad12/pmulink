<?php

use App\Models\Department;
use App\Models\FaitMarquant;
use App\Models\FaitMarquantCommentaire;
use App\Models\FaitMarquantProchaineEtape;
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

test('weekly timeline lists pivot rows only for the week they were created', function () {
    $department = Department::query()->create(['name' => 'Ops']);
    $role = Role::query()->where('name', 'Utilisateur')->firstOrFail();
    $user = User::factory()
        ->withDepartments($department)
        ->withRole($role)
        ->create();

    $faitStatusId = (int) FaitStatus::query()->value('id');
    $statusId = (int) WorkflowStatus::query()->value('id');

    $fait = FaitMarquant::query()->create([
        'title' => 'Projet timeline',
        'fait_status_id' => $faitStatusId,
        'status_id' => $statusId,
        'department_id' => $department->id,
        'created_by' => $user->id,
        'responsable_action_id' => $user->id,
    ]);
    $created = Carbon::parse('2026-05-01 10:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE);
    $fait->created_at = $created;
    $fait->submitted_at = $created;
    $fait->save();

    $weekOneCreated = Carbon::parse('2026-05-12 10:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE);

    $etapeWeekOne = new FaitMarquantProchaineEtape([
        'fait_marquant_id' => $fait->id,
        'sort_order' => 1,
        'body' => 'Étape semaine 11–17',
        'user_id' => $user->id,
    ]);
    $etapeWeekOne->created_at = $weekOneCreated;
    $etapeWeekOne->updated_at = $weekOneCreated;
    $etapeWeekOne->save();

    $commentWeekOne = new FaitMarquantCommentaire([
        'fait_marquant_id' => $fait->id,
        'body' => 'Commentaire semaine 11–17',
        'user_id' => $user->id,
    ]);
    $commentWeekOne->created_at = $weekOneCreated;
    $commentWeekOne->updated_at = $weekOneCreated;
    $commentWeekOne->save();

    $weekTwoCreated = Carbon::parse('2026-05-20 15:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE);

    $etapeWeekTwo = new FaitMarquantProchaineEtape([
        'fait_marquant_id' => $fait->id,
        'sort_order' => 1,
        'body' => 'Étape semaine 18–24',
        'user_id' => $user->id,
    ]);
    $etapeWeekTwo->created_at = $weekTwoCreated;
    $etapeWeekTwo->updated_at = $weekTwoCreated;
    $etapeWeekTwo->save();

    $commentWeekTwo = new FaitMarquantCommentaire([
        'fait_marquant_id' => $fait->id,
        'body' => 'Commentaire semaine 18–24',
        'user_id' => $user->id,
    ]);
    $commentWeekTwo->created_at = $weekTwoCreated;
    $commentWeekTwo->updated_at = $weekTwoCreated;
    $commentWeekTwo->save();

    Carbon::setTestNow(Carbon::parse('2026-05-25 12:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE));

    $weeks = app(FaitMarquantWeeklyTimelineBuilder::class)->build($fait);

    $week1117 = collect($weeks)->firstWhere('week_start', '2026-05-11');
    $week1824 = collect($weeks)->firstWhere('week_start', '2026-05-18');

    expect($week1117)->not->toBeNull();
    expect($week1824)->not->toBeNull();

    expect(collect($week1117['prochaines_etapes'])->pluck('body')->all())
        ->toBe(['Étape semaine 11–17']);
    expect(collect($week1117['prochaines_etapes'])->pluck('sequence_number')->all())
        ->toBe([1]);
    expect(collect($week1117['commentaires'])->pluck('body')->all())
        ->toBe(['Commentaire semaine 11–17']);
    expect(collect($week1117['commentaires'])->pluck('sequence_number')->all())
        ->toBe([1]);

    expect(collect($week1824['prochaines_etapes'])->pluck('body')->all())
        ->toBe(['Étape semaine 18–24']);
    expect(collect($week1824['prochaines_etapes'])->pluck('sequence_number')->all())
        ->toBe([2]);
    expect(collect($week1824['commentaires'])->pluck('body')->all())
        ->toBe(['Commentaire semaine 18–24']);
    expect(collect($week1824['commentaires'])->pluck('sequence_number')->all())
        ->toBe([2]);

    Carbon::setTestNow();
});

test('weekly timeline starts the week of submitted_at not created_at when both differ', function () {
    $department = Department::query()->create(['name' => 'Ops']);
    $role = Role::query()->where('name', 'Utilisateur')->firstOrFail();
    $user = User::factory()
        ->withDepartments($department)
        ->withRole($role)
        ->create();

    $faitStatusId = (int) FaitStatus::query()->value('id');
    $statusId = (int) WorkflowStatus::query()->value('id');

    $fait = FaitMarquant::query()->create([
        'title' => 'Projet soumis tard',
        'fait_status_id' => $faitStatusId,
        'status_id' => $statusId,
        'department_id' => $department->id,
        'created_by' => $user->id,
        'responsable_action_id' => $user->id,
        'submitted_at' => null,
    ]);

    $fait->created_at = Carbon::parse('2026-05-01 10:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE);
    $fait->submitted_at = Carbon::parse('2026-05-20 12:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE);
    $fait->save();

    Carbon::setTestNow(Carbon::parse('2026-05-25 12:00:00', FaitMarquantWeeklyTimelineBuilder::TIMEZONE));

    $weeks = app(FaitMarquantWeeklyTimelineBuilder::class)->build($fait->fresh());

    $weekStarts = collect($weeks)->pluck('week_start')->all();

    expect($weekStarts)->not->toContain('2026-04-27');
    expect($weekStarts)->not->toContain('2026-05-04');
    expect($weekStarts)->toContain('2026-05-18');

    Carbon::setTestNow();
});
