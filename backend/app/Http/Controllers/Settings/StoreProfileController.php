<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreProfileResource;
use App\Services\Settings;

/**
 * The public part of the settings (store name, logo, Store currency), which every
 * signed-in User's screens need, whatever their Role.
 */
class StoreProfileController extends Controller
{
    public function show(Settings $settings): StoreProfileResource
    {
        return new StoreProfileResource($settings->current());
    }
}
