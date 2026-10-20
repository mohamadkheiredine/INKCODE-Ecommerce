<?php

namespace App\Actions\Settings;

use App\Models\StoreSettings;
use App\Services\AuditTrail;
use App\Services\Settings;
use App\Support\LogoFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Gives the store a new logo, or removes it (null). See {@see LogoFile::replace()} for
 * how the files are kept consistent with the saved settings.
 */
final class ReplaceStoreLogo
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly Settings $settings,
    ) {}

    public function handle(?UploadedFile $logo): StoreSettings
    {
        $storeSettings = StoreSettings::query()->findOrFail(StoreSettings::ID);

        LogoFile::replace($logo, $storeSettings->logo_path, 'store', fn (?string $newPath) => DB::transaction(function () use ($storeSettings, $newPath): void {
            $before = $storeSettings->auditValues();
            $storeSettings->forceFill(['logo_path' => $newPath])->save();
            $this->auditTrail->updated($storeSettings, $before);
        }));

        $this->settings->forget();

        return $storeSettings;
    }
}
