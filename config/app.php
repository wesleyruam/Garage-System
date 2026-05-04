<?php

return [
    'name' => env('APP_NAME', 'Garage System'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim(env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => 'America/Sao_Paulo',
    'session_name' => env('SESSION_NAME', 'garage_session'),
    'session_secure' => filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOLEAN),
    'csrf_cookie' => env('CSRF_COOKIE', 'garage_csrf'),
    'max_login_attempts' => (int) env('MAX_LOGIN_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    'uploads_path' => dirname(__DIR__) . '/storage/uploads',
    'max_upload_bytes' => 5 * 1024 * 1024,
];
