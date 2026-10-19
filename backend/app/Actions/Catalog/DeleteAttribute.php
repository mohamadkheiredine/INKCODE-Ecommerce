<?php

namespace App\Actions\Catalog;

use App\Models\Attribute;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an Attribute and its values. One whose values any Variant uses is refused
 * with 409 `in_use`.
 */
final class DeleteAttribute
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Attribute $attribute): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($attribute): void {
                $this->auditTrail->deleted($attribute);
                $attribute->delete();
            }),
            __('catalog.attributes.in_use', ['name' => $attribute->name]),
        );
    }
}
