<?php

// Customer rules: validation details and refusals.
return [
    'email_or_phone' => 'Enter an email or a phone number so the Customer can be reached.',
    'phone_format' => 'Enter a phone number using digits, spaces, dashes or brackets, for example +961 3 123 456.',
    'has_orders' => ':name has Orders, so the Customer cannot be deleted.',

    'addresses' => [
        'keeps_default' => 'A Customer always has a default :purpose address. Make another address the default instead.',
        'billing' => 'billing',
        'shipping' => 'shipping',
    ],
];
