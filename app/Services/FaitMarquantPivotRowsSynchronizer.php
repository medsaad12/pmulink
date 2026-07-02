<?php

namespace App\Services;

use App\Models\FaitMarquant;
use App\Models\FaitMarquantCommentaire;
use App\Models\FaitMarquantDraft;
use App\Models\FaitMarquantDraftCommentaire;
use App\Models\FaitMarquantDraftProchaineEtape;
use App\Models\FaitMarquantProchaineEtape;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class FaitMarquantPivotRowsSynchronizer
{
    public function __construct(
        private readonly ProchaineEtapePayloadNormalizer $etapePayloadNormalizer,
    ) {}

    /**
     * @param  list<array<string, mixed>|string>  $items
     */
    public function syncPublishedProchainesEtapes(
        FaitMarquant $fait,
        array $items,
        int $defaultUserId,
        ?int $fallbackResponsableId = null,
    ): void {
        $payloads = $this->etapePayloadNormalizer->normalizeMany(
            $items,
            $defaultUserId,
            $fallbackResponsableId ?? (int) $fait->responsable_action_id,
        );

        $existing = $fait->prochainesEtapes()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->syncEtapesByPosition(
            $existing,
            $payloads,
            fn (array $payload, int $sortOrder) => FaitMarquantProchaineEtape::query()->create([
                'fait_marquant_id' => $fait->id,
                'sort_order' => $sortOrder,
                'body' => $payload['body'],
                'user_id' => $payload['user_id'],
                'responsable_action_id' => $payload['responsable_action_id'],
                'deadline' => $payload['deadline'],
                'etape_status_id' => $payload['etape_status_id'],
            ]),
            fn (FaitMarquantProchaineEtape $row, array $payload, int $sortOrder) => $this->patchEtapeRow(
                $row,
                $payload,
                $sortOrder,
            ),
            fn (array $keptIds) => $this->deleteExcept($fait->prochainesEtapes(), $keptIds),
        );
    }

    /**
     * @param  list<string>  $bodies
     */
    public function syncPublishedCommentaires(FaitMarquant $fait, array $bodies, int $defaultUserId): void
    {
        $existing = $fait->commentaires()->orderBy('id')->get();

        $this->syncCommentairesByPosition(
            $existing,
            $bodies,
            fn (string $body) => FaitMarquantCommentaire::query()->create([
                'fait_marquant_id' => $fait->id,
                'user_id' => $defaultUserId,
                'body' => $body,
            ]),
            fn (FaitMarquantCommentaire $row, string $body, int $userId) => $this->patchCommentaireRow($row, $body, $userId),
            fn (array $keptIds) => $this->deleteExcept($fait->commentaires(), $keptIds),
            $defaultUserId,
        );
    }

    /**
     * @param  list<array<string, mixed>|string>  $items
     */
    public function syncDraftProchainesEtapes(
        FaitMarquantDraft $draft,
        array $items,
        int $defaultUserId,
        ?int $fallbackResponsableId = null,
    ): void {
        $payloads = $this->etapePayloadNormalizer->normalizeMany(
            $items,
            $defaultUserId,
            $fallbackResponsableId ?? (int) $draft->responsable_action_id,
        );

        $existing = $draft->prochainesEtapes()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->syncEtapesByPosition(
            $existing,
            $payloads,
            fn (array $payload, int $sortOrder) => FaitMarquantDraftProchaineEtape::query()->create([
                'fait_marquant_draft_id' => $draft->id,
                'sort_order' => $sortOrder,
                'body' => $payload['body'],
                'user_id' => $payload['user_id'],
                'responsable_action_id' => $payload['responsable_action_id'],
                'deadline' => $payload['deadline'],
                'etape_status_id' => $payload['etape_status_id'],
            ]),
            fn (FaitMarquantDraftProchaineEtape $row, array $payload, int $sortOrder) => $this->patchEtapeRow(
                $row,
                $payload,
                $sortOrder,
            ),
            fn (array $keptIds) => $this->deleteExcept($draft->prochainesEtapes(), $keptIds),
        );
    }

    /**
     * @param  list<string>  $bodies
     */
    public function syncDraftCommentaires(FaitMarquantDraft $draft, array $bodies, int $defaultUserId): void
    {
        $existing = $draft->commentaires()->orderBy('id')->get();

        $this->syncCommentairesByPosition(
            $existing,
            $bodies,
            fn (string $body) => FaitMarquantDraftCommentaire::query()->create([
                'fait_marquant_draft_id' => $draft->id,
                'user_id' => $defaultUserId,
                'body' => $body,
            ]),
            fn (FaitMarquantDraftCommentaire $row, string $body, int $userId) => $this->patchCommentaireRow($row, $body, $userId),
            fn (array $keptIds) => $this->deleteExcept($draft->commentaires(), $keptIds),
            $defaultUserId,
        );
    }

    /**
     * @param  EloquentCollection<int, FaitMarquantDraftProchaineEtape>  $draftEtapes
     */
    public function mergeDraftProchainesEtapesIntoPublished(
        FaitMarquant $fait,
        EloquentCollection $draftEtapes,
        int $fallbackUserId,
        ?CarbonInterface $publishedAt = null,
    ): void {
        $published = $fait->prochainesEtapes()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $keptIds = [];

        foreach ($draftEtapes->values() as $idx => $etape) {
            $sortOrder = $idx + 1;
            $userId = $etape->user_id ?? $fallbackUserId;
            $payload = $this->etapePayloadNormalizer->normalizeOne(
                [
                    'body' => (string) $etape->body,
                    'responsable_action_id' => $etape->responsable_action_id,
                    'deadline' => $etape->deadline?->format('Y-m-d'),
                    'etape_status_id' => $etape->etape_status_id,
                ],
                (int) $userId,
                (int) ($etape->responsable_action_id ?? $userId),
            );
            $publishedRow = $published->get($idx);

            if ($publishedRow instanceof FaitMarquantProchaineEtape) {
                $keptIds[] = $publishedRow->id;
                $this->patchEtapeRow($publishedRow, $payload, $sortOrder);
            } else {
                $createdRow = $this->createPublishedEtapeWithTimestamps(
                    $fait->id,
                    $sortOrder,
                    $payload,
                    $etape->created_at ?? $publishedAt,
                );
                $keptIds[] = (int) $createdRow->id;
            }
        }

        $this->deleteExcept($fait->prochainesEtapes(), $keptIds);
    }

    /**
     * @param  EloquentCollection<int, FaitMarquantDraftCommentaire>  $draftCommentaires
     */
    public function mergeDraftCommentairesIntoPublished(
        FaitMarquant $fait,
        EloquentCollection $draftCommentaires,
        int $fallbackUserId,
        ?CarbonInterface $publishedAt = null,
    ): void {
        $published = $fait->commentaires()->orderBy('id')->get()->values();
        $keptIds = [];

        foreach ($draftCommentaires->values() as $idx => $commentaire) {
            $userId = $commentaire->user_id ?? $fallbackUserId;
            $publishedRow = $published->get($idx);

            if ($publishedRow instanceof FaitMarquantCommentaire) {
                $keptIds[] = $publishedRow->id;
                $this->patchCommentaireRow($publishedRow, (string) $commentaire->body, $userId);
            } else {
                $createdRow = $this->createPublishedCommentaireWithTimestamps(
                    $fait->id,
                    (string) $commentaire->body,
                    $userId,
                    $commentaire->created_at ?? $publishedAt,
                );
                $keptIds[] = (int) $createdRow->id;
            }
        }

        $this->deleteExcept($fait->commentaires(), $keptIds);
    }

    /**
     * @param  EloquentCollection<int, FaitMarquantProchaineEtape|FaitMarquantDraftProchaineEtape>  $existing
     * @param  list<array{body: string, user_id: int, responsable_action_id: int, deadline: ?string, etape_status_id: int}>  $payloads
     * @param  callable(array, int): Model  $create
     * @param  callable(Model, array, int): void  $patch
     * @param  callable(list<int>): void  $deleteExcept
     */
    private function syncEtapesByPosition(
        EloquentCollection $existing,
        array $payloads,
        callable $create,
        callable $patch,
        callable $deleteExcept,
    ): void {
        $keptIds = [];

        foreach (array_values($payloads) as $idx => $payload) {
            $sortOrder = $idx + 1;
            $row = $existing->get($idx);

            if ($row !== null) {
                $keptIds[] = (int) $row->id;
                $patch($row, $payload, $sortOrder);
            } else {
                $created = $create($payload, $sortOrder);
                $keptIds[] = (int) $created->id;
            }
        }

        $deleteExcept($keptIds);
    }

    /**
     * @param  EloquentCollection<int, FaitMarquantCommentaire|FaitMarquantDraftCommentaire>  $existing
     * @param  list<string>  $bodies
     * @param  callable(string): Model  $create
     * @param  callable(Model, string, int): void  $patch
     * @param  callable(list<int>): void  $deleteExcept
     */
    private function syncCommentairesByPosition(
        EloquentCollection $existing,
        array $bodies,
        callable $create,
        callable $patch,
        callable $deleteExcept,
        int $defaultUserId,
    ): void {
        $keptIds = [];

        foreach (array_values($bodies) as $idx => $body) {
            $row = $existing->get($idx);

            if ($row !== null) {
                $keptIds[] = (int) $row->id;
                $patch($row, $body, (int) ($row->user_id ?? $defaultUserId));
            } else {
                $created = $create($body);
                $keptIds[] = (int) $created->id;
            }
        }

        $deleteExcept($keptIds);
    }

    /**
     * @param  array{body: string, user_id: int, responsable_action_id: int, deadline: ?string, etape_status_id: int}  $payload
     */
    private function patchEtapeRow(
        FaitMarquantProchaineEtape|FaitMarquantDraftProchaineEtape $row,
        array $payload,
        int $sortOrder,
        ?int $userId = null,
    ): void {
        $updates = [];

        if ((string) $row->body !== $payload['body']) {
            $updates['body'] = $payload['body'];
        }

        if ((int) $row->sort_order !== $sortOrder) {
            $updates['sort_order'] = $sortOrder;
        }

        $authorId = $userId ?? $payload['user_id'];

        if ((int) ($row->user_id ?? 0) !== $authorId) {
            $updates['user_id'] = $authorId;
        }

        if ((int) ($row->responsable_action_id ?? 0) !== (int) $payload['responsable_action_id']) {
            $updates['responsable_action_id'] = $payload['responsable_action_id'];
        }

        $rowDeadline = $row->deadline?->format('Y-m-d');
        $payloadDeadline = $payload['deadline'];

        if ($rowDeadline !== $payloadDeadline) {
            $updates['deadline'] = $payloadDeadline;
        }

        if ((int) ($row->etape_status_id ?? 0) !== (int) $payload['etape_status_id']) {
            $updates['etape_status_id'] = $payload['etape_status_id'];
        }

        if ($updates !== []) {
            $row->update($updates);
        }
    }

    private function patchCommentaireRow(
        FaitMarquantCommentaire|FaitMarquantDraftCommentaire $row,
        string $body,
        int $userId,
    ): void {
        $updates = [];

        if ((string) $row->body !== $body) {
            $updates['body'] = $body;
        }

        if ((int) ($row->user_id ?? 0) !== $userId) {
            $updates['user_id'] = $userId;
        }

        if ($updates !== []) {
            $row->update($updates);
        }
    }

    /**
     * @param  array{body: string, user_id: int, responsable_action_id: int, deadline: ?string, etape_status_id: int}  $payload
     */
    private function createPublishedEtapeWithTimestamps(
        int $faitMarquantId,
        int $sortOrder,
        array $payload,
        mixed $createdAt,
    ): FaitMarquantProchaineEtape {
        $row = new FaitMarquantProchaineEtape([
            'fait_marquant_id' => $faitMarquantId,
            'sort_order' => $sortOrder,
            'body' => $payload['body'],
            'user_id' => $payload['user_id'],
            'responsable_action_id' => $payload['responsable_action_id'],
            'deadline' => $payload['deadline'],
            'etape_status_id' => $payload['etape_status_id'],
        ]);

        $this->applyCreatedTimestamp($row, $createdAt);
        $row->save();

        return $row;
    }

    private function createPublishedCommentaireWithTimestamps(
        int $faitMarquantId,
        string $body,
        int $userId,
        mixed $createdAt,
    ): FaitMarquantCommentaire {
        $row = new FaitMarquantCommentaire([
            'fait_marquant_id' => $faitMarquantId,
            'body' => $body,
            'user_id' => $userId,
        ]);

        $this->applyCreatedTimestamp($row, $createdAt);
        $row->save();

        return $row;
    }

    private function applyCreatedTimestamp(Model $row, mixed $createdAt): void
    {
        if ($createdAt === null) {
            return;
        }

        $timestamp = $createdAt instanceof Carbon
            ? $createdAt
            : Carbon::parse($createdAt);

        $row->created_at = $timestamp;
        $row->updated_at = $timestamp;
    }

    /**
     * @param  HasMany<FaitMarquantProchaineEtape|FaitMarquantCommentaire|FaitMarquantDraftProchaineEtape|FaitMarquantDraftCommentaire, *>  $relation
     * @param  list<int>  $keptIds
     */
    private function deleteExcept($relation, array $keptIds): void
    {
        if ($keptIds === []) {
            $relation->delete();

            return;
        }

        $relation->whereNotIn('id', $keptIds)->delete();
    }
}
