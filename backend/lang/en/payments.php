<?php

return [
    'exceeds_due' => 'Order :number has only :due still due; a payment cannot be more than that.',
    'refund_exceeds_captured' => 'Order :number can be refunded at most :refundable (its Captured amount minus earlier Refunds).',
    'refund_method_unavailable' => 'Order :number has no approved Card payment to refund to. Choose another Payment method.',
    'attributes' => [
        'method' => 'Payment method',
        'amount' => 'amount',
        'card_outcome' => 'test card outcome',
        'note' => 'note',
    ],
];
