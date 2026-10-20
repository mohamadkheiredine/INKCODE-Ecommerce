<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateStoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateStoreSettingsRequest;
use App\Http\Resources\StoreSettingsResource;
use App\Services\Settings;

/**
 * The store's settings, for Admins (settings.manage).
 */
class StoreSettingsController extends Controller
{
    /**
     * Authorized by the route (`can:settings.manage`).
     */
    public function show(Settings $settings): StoreSettingsResource
    {
        return new StoreSettingsResource($settings->current());
    }

    public function update(UpdateStoreSettingsRequest $request, UpdateStoreSettings $updateStoreSettings): StoreSettingsResource
    {
        return new StoreSettingsResource($updateStoreSettings->handle($request->values()));
    }
}
