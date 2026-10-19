<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Brand;
use App\Services\AuditTrail;
use App\Support\ForeignKeys;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a Brand nothing depends on, and its logo file. A Brand still referenced by
 * other records (the Products of ticket #10) is refused by the database's restricting
 * foreign keys, which become a clear 409 here.
 */
final class DeleteBrand
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Brand $brand): void
    {
        try {
            DB::transaction(function () use ($brand): void {
                $brand->delete();
                $this->auditTrail->deleted($brand);
            });
        } catch (QueryException $exception) {
            if (! ForeignKeys::isRestrictViolation($exception)) {
                throw $exception;
            }

            throw new BusinessRuleViolation('in_use', __('catalog.brands.in_use', ['name' => $brand->name]));
        }

        if ($brand->logo_path !== null) {
            Storage::disk(Brand::LOGO_DISK)->delete($brand->logo_path);
        }
    }
}
