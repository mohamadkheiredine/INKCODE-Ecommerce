<?php

return [
    'unauthenticated' => 'Unauthenticated.',
    'forbidden' => 'You are not allowed to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'method_not_allowed' => 'This method is not supported for this resource.',
    'csrf_token_mismatch' => 'Your session has expired. Please refresh the page and try again.',
    'too_many_requests' => 'Too many requests. Please slow down and try again shortly.',
    'server_error' => 'Something went wrong on our side. Please try again.',
    'idempotency_key_invalid' => 'The Idempotency-Key header can be at most :max characters long.',
    'idempotency_key_reused' => 'This Idempotency-Key was already used for a different request. Send a new key.',
];
