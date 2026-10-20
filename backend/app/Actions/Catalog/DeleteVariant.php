<?php

namespace App\Actions\Catalog;

use App\Models\Variant;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Removes one Variant from its Product. A Variant on any Order (its lines restrict
 * deletes, #16) is refused with 409 `in_use`.
 */
final class DeleteVariant
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Variant $variant): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($variant): void {
                $variant->delete();
                $this->auditTrail->deleted($variant);
            }),
            __('catalog.variants.in_use', ['sku' => $variant->sku]),
        );
    }
}
