<?php

use App\Models\Department;
use App\Models\FaitMarquant;
use App\Models\FaitMarquantCommentaire;
use App\Models\FaitMarquantProchaineEtape;
use App\Models\FaitStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowStatus;
use App\Services\FaitMarquantPivotRowsSynchronizer;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

test('syncing published pivot rows preserves original created_at timestamps', function () {
    $department = Department::query()->create(['name' => 'Ops']);
    $role = Role::query()->where('name', 'Utilisateur')->firstOrFail();
    $user = User::factory()
        ->withDepartments($department)
        ->withRole($role)
        ->create();

    $faitStatusId = (int) FaitStatus::query()->value('id');
    $statusId = (int) WorkflowStatus::query()->value('id');

    $fait = FaitMarquant::query()->create([
        'title' => 'Projet test',
        'fait_status_id' => $faitStatusId,
        'status_id' => $statusId,
        'department_id' => $department->id,
        'created_by' => $user->id,
        'responsable_action_id' => $user->id,
    ]);

    $etapeCreated = Carbon::parse('2026-01-10 09:00:00');
    $commentCreated = Carbon::parse('2026-01-12 14:30:00');

    $etape = new FaitMarquantProchaineEtape([
        'fait_marquant_id' => $fait->id,
        'sort_order' => 1,
        'body' => 'Première étape',
        'user_id' => $user->id,
    ]);
    $etape->created_at = $etapeCreated;
    $etape->updated_at = $etapeCreated;
    $etape->save();

    $comment = new FaitMarquantCommentaire([
        'fait_marquant_id' => $fait->id,
        'body' => 'Premier commentaire',
        'user_id' => $user->id,
    ]);
    $comment->created_at = $commentCreated;
    $comment->updated_at = $commentCreated;
    $comment->save();

    $synchronizer = app(FaitMarquantPivotRowsSynchronizer::class);

    $synchronizer->syncPublishedProchainesEtapes($fait, ['Première étape'], (int) $user->id);
    $synchronizer->syncPublishedCommentaires($fait, ['Premier commentaire'], (int) $user->id);

    $fait->update(['title' => 'Projet test mis à jour']);

    $synchronizer->syncPublishedProchainesEtapes(
        $fait->fresh(),
        ['Première étape', 'Deuxième étape'],
        (int) $user->id,
    );
    $synchronizer->syncPublishedCommentaires(
        $fait->fresh(),
        ['Premier commentaire', 'Nouveau commentaire'],
        (int) $user->id,
    );

    $etape->refresh();
    $comment->refresh();

    expect($etape->created_at?->toDateTimeString())->toBe($etapeCreated->toDateTimeString());
    expect($comment->created_at?->toDateTimeString())->toBe($commentCreated->toDateTimeString());

    $newEtape = FaitMarquantProchaineEtape::query()
        ->where('fait_marquant_id', $fait->id)
        ->where('body', 'Deuxième étape')
        ->first();

    $newComment = FaitMarquantCommentaire::query()
        ->where('fait_marquant_id', $fait->id)
        ->where('body', 'Nouveau commentaire')
        ->first();

    expect($newEtape)->not->toBeNull();
    expect($newComment)->not->toBeNull();
    expect($newEtape->created_at?->greaterThan($etapeCreated))->toBeTrue();
    expect($newComment->created_at?->greaterThan($commentCreated))->toBeTrue();
});
