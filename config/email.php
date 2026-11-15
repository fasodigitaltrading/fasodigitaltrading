<?php

return [
    // Set true only after a real SMTP delivery test from the deployed domain.
    'live_verified' => filter_var(env('MAIL_LIVE_VERIFIED', false), FILTER_VALIDATE_BOOL),
];
