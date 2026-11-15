<?php

return [
    'email' => mb_strtolower(trim((string) (env('ADMIN_EMAIL') ?: 'fasodigitaltrading@gmail.com'))),
];
