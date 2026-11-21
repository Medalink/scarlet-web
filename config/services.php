<?php

return [

    // Sign-in. Only `openid` is requested, so no email, name or picture is ever returned.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/login/google/callback'),
    ],

    'battlenet' => [
        'client_id' => env('BATTLENET_CLIENT_ID'),
        'client_secret' => env('BATTLENET_CLIENT_SECRET'),
        'redirect' => env('BATTLENET_REDIRECT_URI', '/login/battlenet/callback'),
        'region' => env('BATTLENET_REGION', 'us'),
    ],

];
