<?php

namespace App\Middlewares;

use App\Core\Session;

class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Session::get('user_id')) {
            error_response('Autenticação necessária.', 401);
            exit;
        }
    }
}
