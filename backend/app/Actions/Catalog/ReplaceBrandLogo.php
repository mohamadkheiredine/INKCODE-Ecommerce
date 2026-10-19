<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Services\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gives a Brand a new logo, or removes it (null). The file is stored under a generated
 * name first; the old file is removed only once the change is saved, so a failure
 * never leaves the Brand pointing at a missing file.
 */
final class ReplaceBrandLogo
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Brand $brand, ?UploadedFile $logo): Brand
    {
        $disk = Storage::disk(Brand::LOGO_DISK);
        $oldPath = $brand->logo_path;
        $newPath = $logo?->store('brands', Brand::LOGO_DISK) ?: null;

        try {
            DB::transaction(function () use ($brand, $newPath): void {
                $before = $brand->auditValues();
                $brand->forceFill(['logo_path' => $newPath])->save();
                $this->auditTrail->updated($brand, $before);
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $disk->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        return $brand;
    }
}
