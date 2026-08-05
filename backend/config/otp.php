<?php

return [
    /*
    | Durée de validité d'un code (minutes).
    */
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    /*
    | Code fixe (tests / dev uniquement). Laisser vide en production.
    */
    'static' => env('OTP_STATIC'),
];
