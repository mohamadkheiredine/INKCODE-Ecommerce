<?php

namespace App\Actions\Catalog;

use App\Enums\ProductBulkAction;
use App\Enums\ProductStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Product;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Activates, archives or deletes several Products. A status change is all or nothing
 * and records each Product that changed. Deleting goes Product by Product: the ones on
 * Orders are skipped with the reason, the others are deleted.
 */
final class ApplyProductBulkAction
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly DeleteProduct $deleteProduct,
    ) {}

    /**
     * @param  list<int>  $productIds
     * @return array{changed: int, deleted: int, skipped: list<array{id: int, name: string, reason: string}>}
     */
    public function handle(ProductBulkAction $action, array $productIds): array
    {
        $status = $action->status();

        return $status === null
            ? $this->delete($productIds)
            : ['changed' => $this->changeStatus($productIds, $status), 'deleted' => 0, 'skipped' => []];
    }

    /**
     * @param  list<int>  $productIds
     */
    private function changeStatus(array $productIds, ProductStatus $status): int
    {
        return DB::transaction(function () use ($productIds, $status): int {
            $products = Product::query()->whereKey($productIds)->where('status', '!=', $status)->lockForUpdate()->get();

            foreach ($products as $product) {
                $before = $product->auditValues();
                $product->update(['status' => $status]);
                $this->auditTrail->updated($product, $before);
            }

            return $products->count();
        });
    }

    /**
     * @param  list<int>  $productIds
     * @return array{changed: int, deleted: int, skipped: list<array{id: int, name: string, reason: string}>}
     */
    private function delete(array $productIds): array
    {
        $deleted = 0;
        $skipped = [];

        foreach (Product::query()->whereKey($productIds)->get() as $product) {
            try {
                $this->deleteProduct->handle($product);
                $deleted++;
            } catch (BusinessRuleViolation $refusal) {
                $skipped[] = ['id' => $product->id, 'name' => $product->name, 'reason' => $refusal->getMessage()];
            }
        }

        return ['changed' => 0, 'deleted' => $deleted, 'skipped' => $skipped];
    }
}
