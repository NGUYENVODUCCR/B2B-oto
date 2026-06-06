<?php

return [
    'host' => getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com',
    'port' => (int) (getenv('MAIL_PORT') ?: 2525),

    'username' => getenv('MAIL_USERNAME'),
    'password' => getenv('MAIL_PASSWORD'),

    'from_email' => getenv('MAIL_FROM_EMAIL') ?: 'servercnpm@gmail.com',
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'B2B Marketplace',

    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',

    'charset'  => 'UTF-8',
    'encoding' => 'base64'
];