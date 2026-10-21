<?php

return [
    'order_not_delivered' => 'Order :number is not Delivered yet, so nothing can be returned.',
    'window_closed' => 'The Return window for Order :number has closed (:days days after delivery).',
    'exceeds_delivered' => 'More units than can still be returned: :lines.',
    'exceeds_line' => ':sku (requested :requested, returnable :returnable)',
    'invalid_transition' => 'Return :number cannot move from :from to :to.',
    'not_received' => 'Return :number is not Received, so its items cannot get a Restock decision yet.',
    'already_decided' => ':sku already has its Restock decision.',
    'decisions_pending' => 'Every item of Return :number needs its Restock decision before the Refund.',
    'shipping_already_refunded' => 'Shipping on Order :number was already refunded by another Return.',
    'refund_exceeds_cap' => 'The Refund for Return :number can be at most :cap.',
    'refund_note' => 'Refund for Return :number',
    'line_not_on_return' => 'This item is not part of the Return.',
    'statuses' => [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'received' => 'Received',
        'completed' => 'Completed',
    ],
    'attributes' => [
        'reason' => 'reason',
        'note' => 'note',
        'lines' => 'items',
        'line_id' => 'item',
        'quantity' => 'quantity',
        'decision' => 'Restock decision',
        'amount' => 'Refund amount',
        'refund_shipping' => 'refund shipping',
    ],
];
