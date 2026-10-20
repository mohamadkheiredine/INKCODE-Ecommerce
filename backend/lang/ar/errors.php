<?php

return [
    'unauthenticated' => 'يجب تسجيل الدخول أولاً.',
    'forbidden' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
    'not_found' => 'المورد المطلوب غير موجود.',
    'method_not_allowed' => 'هذه الطريقة غير مدعومة لهذا المورد.',
    'csrf_token_mismatch' => 'انتهت صلاحية الجلسة. يرجى تحديث الصفحة والمحاولة مجدداً.',
    'too_many_requests' => 'طلبات كثيرة جداً. يرجى التمهل والمحاولة بعد قليل.',
    'server_error' => 'حدث خطأ من جهتنا. يرجى المحاولة مرة أخرى.',
    'idempotency_key_invalid' => 'يجب ألا يتجاوز طول ترويسة Idempotency-Key :max حرفاً.',
    'idempotency_key_reused' => 'استُخدم مفتاح Idempotency-Key هذا لطلب مختلف. أرسل مفتاحاً جديداً.',
];
