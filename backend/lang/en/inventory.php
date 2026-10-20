<?php

// Inventory (Warehouses, stock, Receipts): field names and refusals.
return [
    'attributes' => [
        'code' => 'code',
        'address' => 'address',
        'warehouse_id' => 'Warehouse',
        'supplier_reference' => 'supplier reference',
        'lines' => 'lines',
        'variant_id' => 'Variant',
        'quantity' => 'quantity',
        'unit_cost' => 'unit cost',
        'reason' => 'reason',
        'source_warehouse_id' => 'source Warehouse',
        'destination_warehouse_id' => 'destination Warehouse',
    ],

    'warehouses' => [
        'inactive' => 'The Warehouse ":name" is deactivated, so it cannot receive stock.',
        'holds_stock' => 'The Warehouse ":name" still holds stock. Move or adjust it out before deactivating.',
        'code_format' => 'Use 2 to 16 capital letters, digits or dashes for the code.',
    ],

    'stock' => [
        'insufficient' => 'Not enough stock of :sku in :warehouse: only :available available.',
    ],

    'receipts' => [
        'warehouse_unavailable' => 'Choose an active Warehouse.',
        'variant_repeated' => 'This Variant is already on the Receipt. Change its quantity instead.',
    ],

    'adjustments' => [
        'not_zero' => 'Enter a quantity to add (positive) or remove (negative).',
    ],

    'transfers' => [
        'same_warehouse' => 'Choose a destination different from the source.',
        'invalid_transition' => 'A :from Transfer cannot become :to.',
        'status' => [
            'draft' => 'Draft',
            'in_transit' => 'In transit',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
        ],
    ],
];
