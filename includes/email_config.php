<?php

require_once __DIR__ . '/../config/env.php';

define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USER', env('SMTP_FROM_EMAIL', '')));
define('SMTP_PASSWORD', env('SMTP_PASS', ''));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls'));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', ''));

define('EMAIL_LOG_ENABLED', true);
define('EMAIL_LOG_FILE', __DIR__ . '/../logs/email_log.txt');
