<?php

return [
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port' => (int) (getenv('MAIL_PORT') ?: 587),

    'username' => getenv('MAIL_USERNAME'),
    'password' => getenv('MAIL_PASSWORD'),

    'from_email' => getenv('MAIL_FROM_EMAIL') ?: getenv('MAIL_USERNAME'),
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'B2B Marketplace',

    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',

    'charset'  => 'UTF-8',
    'encoding' => 'base64'
];
