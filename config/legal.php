<?php

return [
    'operator_name' => env('LEGAL_OPERATOR_NAME'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'marchianovalensavano@gmail.com'),
    'governing_jurisdiction' => env('LEGAL_GOVERNING_JURISDICTION'),
    'hosting_providers' => env('LEGAL_HOSTING_PROVIDERS'),
    'email_provider' => env('LEGAL_EMAIL_PROVIDER'),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE'),
    'minimum_age' => env('LEGAL_MINIMUM_AGE', '13'),
];
