<?php

return [
    // No licensed feed or authorized Coris channel has been supplied yet.
    'coris_manual_channel_enabled' => filter_var(env('CORIS_MANUAL_CHANNEL_ENABLED', false), FILTER_VALIDATE_BOOL),
];
