<?php

namespace App\Actions\Catalog;

use App\Models\Attribute;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Adds an Attribute and its values, in the order given.
 */
final class CreateAttribute
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, values: list<array{id: int|null, name: string, code: string}>}  $values
     */
    public function handle(array $values): Attribute
    {
        return DatabaseErrors::refuseDuplicate(fn (): Attribute => DB::transaction(function () use ($values): Attribute {
            $attribute = Attribute::query()->create(['name' => $values['name']]);

            foreach ($values['values'] as $position => $value) {
                $attribute->values()->create(['name' => $value['name'], 'code' => $value['code'], 'position' => $position]);
            }

            $this->auditTrail->created($attribute);

            return $attribute->load('values');
        }), 'name', __('validation.unique', ['attribute' => 'name']));
    }
}
