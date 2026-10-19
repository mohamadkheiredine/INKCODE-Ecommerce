<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a Brand nothing depends on, and its logo file. A Brand still referenced by
 * other records (the Products of ticket #10, through restricting foreign keys) is
 * refused with 409 `in_use`.
 */
final class DeleteBrand
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Brand $brand): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($brand): void {
                $brand->delete();
                $this->auditTrail->deleted($brand);
            }),
            __('catalog.brands.in_use', ['name' => $brand->name]),
        );

        if ($brand->logo_path !== null) {
            Storage::disk(Brand::LOGO_DISK)->delete($brand->logo_path);
        }
    }
}
