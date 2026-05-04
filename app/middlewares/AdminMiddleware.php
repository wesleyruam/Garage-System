<?php

namespace App\Middlewares;

use App\Core\Session;

class AdminMiddleware
{
    public static function handle(): void
    {
        AuthMiddleware::handle();

        if (Session::get('user_tipo') !== 'admin') {
            error_response('Permissão insuficiente.', 403);
            exit;
        }
    }
}
