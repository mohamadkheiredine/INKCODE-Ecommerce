<?php

namespace App\Http\Resources;

use App\Models\CustomerNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A note with its author (null once that User is gone) and when it was written.
 *
 * @mixin CustomerNote
 */
class CustomerNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author === null ? null : [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
