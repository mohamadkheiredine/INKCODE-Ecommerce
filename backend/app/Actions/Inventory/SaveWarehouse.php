<?php

namespace App\Actions\Inventory;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Warehouse;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a Warehouse, or saves an existing one's name, code, address and active state.
 * A Warehouse that still holds stock cannot be deactivated (409 `warehouse_holds_stock`):
 * its row is locked first, so stock cannot arrive while the check runs.
 */
final class SaveWarehouse
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, code: string, address: string, is_active: bool}  $values
     *
     * @throws ValidationException on a name or code taken by a simultaneous save
     */
    public function handle(?Warehouse $warehouse, array $values): Warehouse
    {
        return DatabaseErrors::refuseDuplicateOnIndexes(fn (): Warehouse => DB::transaction(function () use ($warehouse, $values): Warehouse {
            if ($warehouse === null) {
                $warehouse = Warehouse::query()->create($values);
                $this->auditTrail->created($warehouse);

                return $warehouse;
            }

            $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($warehouse->id);
            if ($warehouse->is_active && ! $values['is_active'] && $warehouse->holdsStock()) {
                throw new BusinessRuleViolation('warehouse_holds_stock', __('inventory.warehouses.holds_stock', ['name' => $warehouse->name]));
            }

            $before = $warehouse->auditValues();
            $warehouse->update($values);
            $this->auditTrail->updated($warehouse, $before);

            return $warehouse;
        }), [
            'warehouses_name_unique' => ['name', __('validation.unique', ['attribute' => 'name'])],
            'warehouses_code_unique' => ['code', __('validation.unique', ['attribute' => __('inventory.attributes.code')])],
        ]);
    }
}
