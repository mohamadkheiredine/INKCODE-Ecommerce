<?php

namespace App\Models\Concerns;

use App\Contracts\Auditable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Sensible defaults for {@see Auditable}: the record type is the model's
 * name in snake case ("category"), its label is its `name`, and its audited values are
 * its visible attributes without the key and timestamps. Hidden attributes (passwords,
 * tokens) are never recorded.
 */
trait Audited
{
    public function auditRecordType(): string
    {
        return Str::snake(class_basename($this));
    }

    public function auditLabel(): string
    {
        return (string) $this->getAttribute('name');
    }

    /**
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return Arr::except(
            $this->attributesToArray(),
            [$this->getKeyName(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn(), ...$this->auditExcept()],
        );
    }

    /**
     * Visible attributes the trail leaves out, for a model to override.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return [];
    }
}
