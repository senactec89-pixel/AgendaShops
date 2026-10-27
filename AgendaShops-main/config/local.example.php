<?php
declare(strict_types=1);

// Copy to config/local.php. Never commit passwords or private credentials.
// Process environment and .env take precedence over these local values.
return [
    'APP_ENV' => 'local',
    'APP_URL' => 'http://localhost:8000',
    'DB_DSN' => '', // Empty: SQLite in storage/agendashops.sqlite.
    'MAIL_TRANSPORT' => 'file', // Local recovery emails: storage/mail/.
];
