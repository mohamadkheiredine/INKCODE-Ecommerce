<?php

// Orders: field names and refusals.
return [
    'attributes' => [
        'customer_id' => 'Customer',
        'billing_address_id' => 'billing address',
        'shipping_address_id' => 'shipping address',
        'warehouse_id' => 'Fulfilling warehouse',
        'shipping_method_id' => 'Shipping method',
        'coupon_code' => 'Coupon code',
        'lines' => 'lines',
        'variant_id' => 'Variant',
        'quantity' => 'quantity',
        'note' => 'reason',
        'status' => 'status',
        'assignee_id' => 'Assignee',
        'body' => 'note',
        'line_id' => 'line',
    ],

    'address_not_customers' => 'Choose one of this Customer\'s addresses.',
    'variant_repeated' => 'This Variant is already on the Order. Change its quantity instead.',
    'variant_unavailable' => 'This Variant cannot be sold: its Product is not Active.',
    'invalid_transition' => 'Order :number is :from, so it cannot become :to.',
    'insufficient_stock' => 'Not enough Available stock in :warehouse for :lines.',
    'insufficient_line' => ':sku (:requested requested, :available available)',
    'locked' => 'Order :number is :status, so its lines, quantities and addresses can no longer change.',
    'cancel_more_than_ordered' => ':sku has only :quantity units left to cancel.',
    'assignee_unavailable' => 'Choose an active Admin, Operations or Sales User.',
    'line_not_on_order' => 'This line is not on the Order.',

    'statuses' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready_for_shipping' => 'Ready for shipping',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ],
];
