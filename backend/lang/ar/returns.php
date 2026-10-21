<?php

return [
    'order_not_delivered' => 'لم يُسلَّم الطلب :number بعد، لذا لا يمكن إرجاع شيء منه.',
    'window_closed' => 'انتهت مهلة الإرجاع للطلب :number (:days يوماً بعد التسليم).',
    'exceeds_delivered' => 'الكمية أكبر مما يمكن إرجاعه: :lines.',
    'exceeds_line' => ':sku (المطلوب :requested، القابل للإرجاع :returnable)',
    'invalid_transition' => 'لا يمكن نقل الإرجاع :number من :from إلى :to.',
    'not_received' => 'لم يُستلم الإرجاع :number بعد، لذا لا يمكن اتخاذ قرار إعادة التخزين لعناصره.',
    'already_decided' => 'اتُّخذ قرار إعادة التخزين للعنصر :sku مسبقاً.',
    'decisions_pending' => 'يحتاج كل عنصر في الإرجاع :number إلى قرار إعادة التخزين قبل الاسترداد.',
    'shipping_already_refunded' => 'استُرد الشحن على الطلب :number مسبقاً في إرجاع آخر.',
    'refund_exceeds_cap' => 'أقصى مبلغ استرداد للإرجاع :number هو :cap.',
    'refund_note' => 'استرداد للإرجاع :number',
    'line_not_on_return' => 'هذا العنصر ليس جزءاً من الإرجاع.',
    'statuses' => [
        'requested' => 'مطلوب',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'received' => 'مستلَم',
        'completed' => 'مكتمل',
    ],
    'attributes' => [
        'reason' => 'السبب',
        'note' => 'الملاحظة',
        'lines' => 'العناصر',
        'line_id' => 'العنصر',
        'quantity' => 'الكمية',
        'decision' => 'قرار إعادة التخزين',
        'amount' => 'مبلغ الاسترداد',
        'refund_shipping' => 'استرداد الشحن',
    ],
];
