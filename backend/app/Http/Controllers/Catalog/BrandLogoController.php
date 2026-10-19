<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ReplaceBrandLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandLogoRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;

/**
 * A Brand's logo, uploaded on its own (multipart) so the Brand itself stays plain JSON.
 */
class BrandLogoController extends Controller
{
    public function store(StoreBrandLogoRequest $request, Brand $brand, ReplaceBrandLogo $replaceBrandLogo): BrandResource
    {
        return new BrandResource($replaceBrandLogo->handle($brand, $request->logo()));
    }

    /**
     * Authorized by the route (`can:update,brand`).
     */
    public function destroy(Brand $brand, ReplaceBrandLogo $replaceBrandLogo): BrandResource
    {
        return new BrandResource($replaceBrandLogo->handle($brand, null));
    }
}
