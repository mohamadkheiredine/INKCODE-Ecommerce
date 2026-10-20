<?php

namespace App\Http\Resources;

use App\Models\AuditEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One audit entry: who (null for the system), what they did, to which record, the
 * values before and after (only those that changed) and when.
 *
 * @mixin AuditEntry
 */
class AuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'record_type' => $this->record_type,
            'record_id' => $this->record_id,
            'record_label' => $this->record_label,
            'before' => $this->before,
            'after' => $this->after,
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
