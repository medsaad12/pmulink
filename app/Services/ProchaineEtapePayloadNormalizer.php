<?php

namespace App\Services;

use App\Models\EtapeStatus;

final class ProchaineEtapePayloadNormalizer
{
    private ?int $defaultEtapeStatusId = null;

    /**
     * @param  list<array<string, mixed>|string>  $items
     * @return list<array{body: string, user_id: int, responsable_action_id: int, deadline: ?string, etape_status_id: int}>
     */
    public function normalizeMany(array $items, int $fallbackUserId, ?int $fallbackResponsableId = null): array
    {
        $responsableFallback = $fallbackResponsableId ?? $fallbackUserId;
        $defaultStatusId = $this->defaultEtapeStatusId();

        return array_values(array_map(
            fn ($item) => $this->normalizeOne($item, $fallbackUserId, $responsableFallback, $defaultStatusId),
            $items,
        ));
    }

    /**
     * @param  array<string, mixed>|string  $item
     * @return array{body: string, user_id: int, responsable_action_id: int, deadline: ?string, etape_status_id: int}
     */
    public function normalizeOne(
        array|string $item,
        int $fallbackUserId,
        int $fallbackResponsableId,
        ?int $defaultEtapeStatusId = null,
    ): array {
        if (is_string($item)) {
            return [
                'body' => $item,
                'user_id' => $fallbackUserId,
                'responsable_action_id' => $fallbackResponsableId,
                'deadline' => null,
                'etape_status_id' => $defaultEtapeStatusId ?? $this->defaultEtapeStatusId(),
            ];
        }

        $deadline = $item['deadline'] ?? null;

        return [
            'body' => (string) ($item['body'] ?? ''),
            'user_id' => $fallbackUserId,
            'responsable_action_id' => isset($item['responsable_action_id'])
                ? (int) $item['responsable_action_id']
                : $fallbackResponsableId,
            'deadline' => $deadline === null || $deadline === ''
                ? null
                : (string) $deadline,
            'etape_status_id' => isset($item['etape_status_id'])
                ? (int) $item['etape_status_id']
                : ($defaultEtapeStatusId ?? $this->defaultEtapeStatusId()),
        ];
    }

    private function defaultEtapeStatusId(): int
    {
        if ($this->defaultEtapeStatusId !== null) {
            return $this->defaultEtapeStatusId;
        }

        $this->defaultEtapeStatusId = (int) EtapeStatus::query()->orderBy('sort_order')->value('id');

        return $this->defaultEtapeStatusId;
    }
}
