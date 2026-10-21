<?php

// الطلبات: أسماء الحقول ورسائل الرفض.
return [
    'attributes' => [
        'customer_id' => 'العميل',
        'billing_address_id' => 'عنوان الفوترة',
        'shipping_address_id' => 'عنوان الشحن',
        'warehouse_id' => 'المستودع المنفِّذ',
        'shipping_method_id' => 'طريقة الشحن',
        'coupon_code' => 'رمز القسيمة',
        'lines' => 'البنود',
        'variant_id' => 'المتغيّر',
        'quantity' => 'الكمية',
        'note' => 'السبب',
        'status' => 'الحالة',
        'assignee_id' => 'المسؤول',
        'body' => 'الملاحظة',
        'line_id' => 'البند',
    ],

    'address_not_customers' => 'اختر أحد عناوين هذا العميل.',
    'variant_repeated' => 'هذا المتغيّر موجود في الطلب. عدّل كميته بدلاً من ذلك.',
    'variant_unavailable' => 'لا يمكن بيع هذا المتغيّر: منتجه ليس نشطاً.',
    'invalid_transition' => 'حالة الطلب :number هي :from، لذا لا يمكن أن يصبح :to.',
    'insufficient_stock' => 'لا يوجد مخزون متاح كافٍ في :warehouse لـ :lines.',
    'insufficient_line' => ':sku (المطلوب :requested، المتاح :available)',
    'locked' => 'الطلب :number حالته :status، لذا لم يعد بالإمكان تغيير بنوده أو كمياته أو عناوينه.',
    'cancel_more_than_ordered' => 'تبقّى من :sku :quantity وحدات فقط يمكن إلغاؤها.',
    'assignee_unavailable' => 'اختر مستخدماً نشطاً بدور مسؤول أو عمليات أو مبيعات.',
    'line_not_on_order' => 'هذا البند ليس في الطلب.',

    'statuses' => [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكَّد',
        'preparing' => 'قيد التحضير',
        'ready_for_shipping' => 'جاهز للشحن',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
        'cancelled' => 'ملغى',
    ],
];
