<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTPS verification
    |--------------------------------------------------------------------------
    |
    | WhatsApp, payment gateways, and other integrations use Laravel's HTTP
    | client. On Windows/local PHP, cURL error 60 usually means CA certs are
    | missing — set CURL_CA_BUNDLE to storage/certs/cacert.pem (included).
    |
    */

    'verify_ssl' => filter_var(env('HTTP_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

    'ca_bundle' => env('CURL_CA_BUNDLE'),

];
