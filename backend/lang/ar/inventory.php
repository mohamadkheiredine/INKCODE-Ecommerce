<?php

// المخزون (المستودعات، المخزون، الاستلامات): أسماء الحقول ورسائل الرفض.
return [
    'attributes' => [
        'code' => 'الرمز',
        'address' => 'العنوان',
        'warehouse_id' => 'المستودع',
        'supplier_reference' => 'مرجع المورّد',
        'lines' => 'البنود',
        'variant_id' => 'المتغير',
        'quantity' => 'الكمية',
        'unit_cost' => 'تكلفة الوحدة',
        'reason' => 'السبب',
        'source_warehouse_id' => 'المستودع المصدر',
        'destination_warehouse_id' => 'المستودع الوجهة',
    ],

    'warehouses' => [
        'inactive' => 'المستودع ":name" معطّل، لذا لا يمكنه استلام مخزون.',
        'holds_stock' => 'المستودع ":name" ما زال يحتوي على مخزون. انقله أو اضبطه قبل التعطيل.',
        'code_format' => 'استخدم من 2 إلى 16 حرفاً لاتينياً كبيراً أو رقماً أو شرطة للرمز.',
    ],

    'stock' => [
        'insufficient' => 'لا يوجد مخزون كافٍ من :sku في :warehouse: المتاح :available فقط.',
    ],

    'receipts' => [
        'warehouse_unavailable' => 'اختر مستودعاً نشطاً.',
        'variant_repeated' => 'هذا المتغير موجود في الاستلام بالفعل. عدّل كميته بدلاً من ذلك.',
    ],

    'adjustments' => [
        'not_zero' => 'أدخل كمية للإضافة (موجبة) أو للخصم (سالبة).',
    ],

    'transfers' => [
        'same_warehouse' => 'اختر مستودعاً وجهةً مختلفاً عن المصدر.',
        'invalid_transition' => 'لا يمكن أن يصبح التحويل :from :to.',
        'status' => [
            'draft' => 'مسودة',
            'in_transit' => 'قيد النقل',
            'received' => 'مستلَم',
            'cancelled' => 'ملغى',
        ],
    ],
];
