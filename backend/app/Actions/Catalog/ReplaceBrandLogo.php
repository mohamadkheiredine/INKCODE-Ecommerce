<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Services\AuditTrail;
use App\Support\LogoFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Gives a Brand a new logo, or removes it (null). See {@see LogoFile::replace()} for how
 * the files are kept consistent with the saved Brand.
 */
final class ReplaceBrandLogo
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Brand $brand, ?UploadedFile $logo): Brand
    {
        LogoFile::replace($logo, $brand->logo_path, 'brands', fn (?string $newPath) => DB::transaction(function () use ($brand, $newPath): void {
            $before = $brand->auditValues();
            $brand->forceFill(['logo_path' => $newPath])->save();
            $this->auditTrail->updated($brand, $before);
        }));

        return $brand;
    }
}
