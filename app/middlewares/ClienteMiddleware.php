<?php

namespace App\Middlewares;

use App\Core\Session;

class ClienteMiddleware
{
    public static function handle(): void
    {
        AuthMiddleware::handle();

        if (Session::get('user_tipo') !== 'cliente' || !Session::get('cliente_id')) {
            error_response('Permissão insuficiente.', 403);
            exit;
        }
    }
}
