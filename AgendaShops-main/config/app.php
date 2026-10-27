<?php
declare(strict_types=1);

// Shared defaults. Machine-specific overrides belong in local.php or the environment.
return [
    'APP_ENV' => 'local',
    'APP_URL' => 'http://localhost:8000',
    'DB_DSN' => '',
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    'STORAGE_PATH' => dirname(__DIR__) . '/storage',
    'MAIL_TRANSPORT' => 'file',
    'MAIL_FROM' => 'noreply@agendashops.local',
    'MAIL_PATH' => '',
    'SMTP_HOST' => '',
    'SMTP_PORT' => '587',
    'SMTP_USER' => '',
    'SMTP_PASSWORD' => '',
    'GOOGLE_CLIENT_ID' => '',
    'GOOGLE_CLIENT_SECRET' => '',
];
