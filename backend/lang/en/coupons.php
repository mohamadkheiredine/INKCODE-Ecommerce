<?php

// Coupons: field names, refusals, and the reasons a Coupon does not apply to an Order.
return [
    'attributes' => [
        'code' => 'code',
        'type' => 'discount type',
        'value' => 'discount',
        'starts_on' => 'start date',
        'ends_on' => 'end date',
        'usage_limit' => 'total usage limit',
        'per_customer_limit' => 'usage limit per Customer',
        'minimum_order_amount' => 'minimum order amount',
        'product_ids' => 'eligible Products',
        'category_ids' => 'eligible Categories',
    ],

    'code_format' => 'Use letters, digits, - and _ only.',
    'in_use' => 'Orders use Coupon :code, so it cannot be deleted. Set an end date instead.',

    'invalid' => [
        'not_found' => 'There is no Coupon with the code :code.',
        'not_started' => 'Coupon :code can be used from :date.',
        'expired' => 'Coupon :code expired on :date.',
        'limit_reached' => 'Coupon :code has reached its usage limit.',
        'customer_limit_reached' => 'This Customer has already used Coupon :code as many times as allowed.',
        'below_minimum' => 'Coupon :code needs items worth at least :amount :currency.',
        'no_eligible_items' => 'None of the items in this Order are eligible for Coupon :code.',
    ],
];
