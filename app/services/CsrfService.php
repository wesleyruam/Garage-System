<?php

namespace App\Services;

use App\Core\Session;

class CsrfService
{
    public static function token(): string
    {
        $token = Session::get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('csrf_token', $token);
        }

        setcookie(config('app.csrf_cookie', 'garage_csrf'), $token, [
            'expires' => 0,
            'path' => '/',
            'secure' => config('app.session_secure', false),
            'httponly' => false,
            'samesite' => 'Strict',
        ]);

        return $token;
    }

    public static function verify(string $token): bool
    {
        $sessionToken = Session::get('csrf_token');
        return is_string($sessionToken) && $token !== '' && hash_equals($sessionToken, $token);
    }

    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::put('csrf_token', $token);
        return self::token();
    }
}
