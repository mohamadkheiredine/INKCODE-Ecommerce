<?php

// Shipments: field names and refusals.
return [
    'attributes' => [
        'carrier_id' => 'شركة الشحن',
        'tracking_number' => 'رقم التتبع',
        'lines' => 'البنود',
        'line_id' => 'البند',
        'quantity' => 'الكمية',
    ],

    'order_not_ready' => 'الطلب :number حالته :status. لا يُشحن إلا طلب جاهز للشحن.',
    'more_than_unshipped' => 'تبقّى من :sku :quantity وحدات فقط للشحن.',
    'order_partly_shipped' => 'شُحنت بعض وحدات الطلب :number. ألغِ الوحدات المتبقية حسب البند بدلاً من ذلك.',
    'invalid_transition' => 'حالة الشحنة :number هي :from، لذا لا يمكن أن تصبح :to.',
    'tracking_number_taken' => 'لدى شركة الشحن هذه شحنة بالرقم نفسه.',

    'statuses' => [
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
    ],
];
