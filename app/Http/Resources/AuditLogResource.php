<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = null;

        if ($this->auditable_type) {
            $segments = explode('\\', $this->auditable_type);
            $type = strtolower(end($segments));
        }

        $entityName = ($this->auditable && method_exists($this->auditable, 'getAuditRepresentation'))
            ? $this->auditable->getAuditRepresentation()
            : ($this->auditable->name ?? $this->auditable_id);

        $actor = $this->user;

        $filterValues = fn(?array $values) => $values ? collect($values)->reject(
            fn($v, $k) => $k === 'id' || str_ends_with($k, '_id')
        )->toArray() : [];

        return [
            'uuid'        => $this->uuid,
            'action'      => strtolower($this->event),
            'entity_type' => $type,
            'entity_uuid' => $this->auditable?->uuid ?? $this->auditable_id,
            'entity_name' => (string) $entityName,
            'actor_uuid'  => $actor?->uuid,
            'actor_name'  => $actor?->name,
            'actor_email' => $actor?->email,
            'old_values'  => $filterValues($this->old_values),
            'new_values'  => $filterValues($this->new_values),
            'tags'        => $this->tags ?? [],
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
