<?php

// Shipping rules (Shipping zones, Shipping methods, Carriers, quotes): field names and refusals.
return [
    'attributes' => [
        'country' => 'country',
        'city' => 'city',
        'locations' => 'countries and cities',
        'price' => 'price',
        'min_delivery_days' => 'fastest delivery (days)',
        'max_delivery_days' => 'slowest delivery (days)',
        'tracking_url_template' => 'tracking link',
    ],

    'zones' => [
        'location_repeated' => ':place is listed twice.',
        'location_taken' => ':place is already in the Shipping zone ":zone". A place can belong to one zone only.',
        'location_taken_meanwhile' => 'Another Shipping zone took one of these places just now. Reload and try again.',
        'in_use' => 'Orders already use the Shipping methods of :name, so it cannot be deleted. Deactivate its methods instead.',
    ],

    'methods' => [
        'in_use' => 'Orders already use :name, so it cannot be deleted. Deactivate it instead.',
    ],

    'carriers' => [
        'tracking_template_format' => 'Enter a web address (http or https) that contains :placeholder where the tracking number goes.',
        'in_use' => 'Shipments already use :name, so it cannot be deleted.',
    ],

    'quote' => [
        'no_zone' => 'No Shipping zone covers :city, :country. Add the city or its country to a Shipping zone first.',
        'no_methods' => 'The Shipping zone ":zone" has no active Shipping methods.',
        'method_unavailable' => 'This Shipping method is not offered for the delivery address.',
    ],
];
