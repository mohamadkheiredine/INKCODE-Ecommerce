<?php

return [
    'exceeds_due' => 'المبلغ المستحق على الطلب :number هو :due فقط، ولا يمكن أن تتجاوزه الدفعة.',
    'refund_exceeds_captured' => 'أقصى ما يمكن استرداده للطلب :number هو :refundable (المبلغ المحصَّل ناقص المبالغ المستردة سابقاً).',
    'refund_method_unavailable' => 'لا توجد دفعة بطاقة مقبولة على الطلب :number للاسترداد إليها. اختر طريقة دفع أخرى.',
    'attributes' => [
        'method' => 'طريقة الدفع',
        'amount' => 'المبلغ',
        'card_outcome' => 'نتيجة البطاقة التجريبية',
        'note' => 'الملاحظة',
    ],
];
