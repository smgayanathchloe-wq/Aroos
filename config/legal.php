<?php

/*
 * Who operates the platform, shown on the Terms page. Set these per deployment;
 * contact lines are hidden until a value is set.
 */
return [
    'operator' => env('LEGAL_OPERATOR', 'LetsVenture'),
    'email' => env('LEGAL_EMAIL'),
    'phone' => env('LEGAL_PHONE'),
    'address' => env('LEGAL_ADDRESS'),
    'terms_updated' => '2026-11-16',
];
