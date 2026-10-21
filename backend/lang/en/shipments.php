<?php

// Shipments: field names and refusals.
return [
    'attributes' => [
        'carrier_id' => 'Carrier',
        'tracking_number' => 'tracking number',
        'lines' => 'lines',
        'line_id' => 'line',
        'quantity' => 'quantity',
    ],

    'order_not_ready' => 'Order :number is :status. Only an Order Ready for shipping can be shipped.',
    'more_than_unshipped' => ':sku has only :quantity units left to ship.',
    'order_partly_shipped' => 'Order :number has already shipped some units. Cancel the units left by line instead.',
    'invalid_transition' => 'Shipment :number is :from, so it cannot become :to.',
    'tracking_number_taken' => 'This Carrier already has a Shipment with this tracking number.',

    'statuses' => [
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
    ],
];
