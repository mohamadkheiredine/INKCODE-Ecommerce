<?php

namespace App\Http\Controllers\Shipping;

use App\Actions\Shipping\QuoteShipping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\QuoteShippingRequest;
use App\Http\Resources\ShippingQuoteResource;

class ShippingQuoteController extends Controller
{
    /**
     * The Shipping methods offered for an address (`country_code`, `city`).
     */
    public function show(QuoteShippingRequest $request, QuoteShipping $quoteShipping): ShippingQuoteResource
    {
        return new ShippingQuoteResource($quoteShipping->handle(
            $request->string('country_code')->toString(),
            $request->string('city')->toString(),
        ));
    }
}
