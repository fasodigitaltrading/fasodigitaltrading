<?php

return [
    'operator_name' => env('LEGAL_OPERATOR_NAME'),
    'operator_address' => env('LEGAL_OPERATOR_ADDRESS'),
    'operator_registration' => env('LEGAL_OPERATOR_REGISTRATION'),
    'operator_email' => env('LEGAL_OPERATOR_EMAIL'),
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL'),
    'disclosures_approved' => (bool) env('LEGAL_DISCLOSURES_APPROVED', false),
    'terms_version' => env('TERMS_VERSION', 'terms-v2'),
    'contact_notice_version' => env('CONTACT_NOTICE_VERSION', 'contact-v2'),
    'manual_payment_terms_version' => env('MANUAL_PAYMENT_TERMS_VERSION', 'manual-payment-v1'),
];
