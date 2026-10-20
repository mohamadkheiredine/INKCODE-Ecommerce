<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Saves an Attribute's name and its whole list of values: values with an id are kept
 * (renamed and reordered), new ones are added, and the ones left out are removed. A
 * value that Variants use cannot be removed (409 `attribute_value_in_use`); renaming it
 * is fine, because Variants point at the value and Orders keep their own snapshot.
 */
final class UpdateAttribute
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, values: list<array{id: int|null, name: string, code: string}>}  $values
     */
    public function handle(Attribute $attribute, array $values): Attribute
    {
        return DatabaseErrors::refuseDuplicate(fn (): Attribute => DB::transaction(function () use ($attribute, $values): Attribute {
            Attribute::query()->whereKey($attribute->id)->lockForUpdate()->firstOrFail();
            $before = $attribute->auditValues();

            $keptIds = array_values(array_filter(array_column($values['values'], 'id')));
            $this->removeValuesExcept($attribute, $keptIds);

            // Kept values may swap names or codes; park them first so the unique indexes
            // never see two at the same moment.
            $attribute->values()->whereKey($keptIds)->update([
                'name' => DB::raw("concat('#', id)"),
                'code' => DB::raw("concat('#', id)"),
            ]);

            foreach ($values['values'] as $position => $value) {
                $fields = ['name' => $value['name'], 'code' => $value['code'], 'position' => $position];
                $value['id'] === null
                    ? $attribute->values()->create($fields)
                    : $attribute->values()->whereKey($value['id'])->update($fields);
            }

            $attribute->update(['name' => $values['name']]);
            $this->auditTrail->updated($attribute, $before);

            return $attribute->load('values');
        }), 'name', __('validation.unique', ['attribute' => 'name']));
    }

    /**
     * @param  list<int>  $keptIds
     */
    private function removeValuesExcept(Attribute $attribute, array $keptIds): void
    {
        $removed = $attribute->values()->whereKeyNot($keptIds)->get();

        $used = $attribute->values()->whereKey($removed->modelKeys())->whereHas('variants')->first();
        if ($used !== null) {
            throw new BusinessRuleViolation('attribute_value_in_use', __('catalog.attributes.value_in_use', ['name' => $used->name]));
        }

        DatabaseErrors::refuseDeleteInUse(
            fn () => AttributeValue::query()->whereKey($removed->modelKeys())->delete(),
            __('catalog.attributes.in_use', ['name' => $attribute->name]),
        );
    }
}
