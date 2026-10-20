<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\ReplaceStoreLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreStoreLogoRequest;
use App\Http\Resources\StoreSettingsResource;

/**
 * The store's logo, uploaded on its own (multipart) so the settings stay plain JSON.
 */
class StoreLogoController extends Controller
{
    public function store(StoreStoreLogoRequest $request, ReplaceStoreLogo $replaceStoreLogo): StoreSettingsResource
    {
        return new StoreSettingsResource($replaceStoreLogo->handle($request->logo()));
    }

    /**
     * Authorized by the route (`can:settings.manage`).
     */
    public function destroy(ReplaceStoreLogo $replaceStoreLogo): StoreSettingsResource
    {
        return new StoreSettingsResource($replaceStoreLogo->handle(null));
    }
}
