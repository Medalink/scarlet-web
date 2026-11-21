<?php

// Cross-origin reads of the public API from any website. Nothing else is open: the upload API is signed and
// is not called from a browser.
return [
    'paths' => ['api/v1/public/*'],
    'allowed_methods' => ['GET', 'OPTIONS'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'If-None-Match', 'Content-Type'],
    'exposed_headers' => ['ETag', 'Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
