<?php

namespace App\Middlewares;

use App\Core\Session;

class PermissionMiddleware
{
    public static function handle(): void
    {
        AuthMiddleware::handle();

        if (!in_array(Session::get('user_tipo'), ['admin', 'atendente', 'mecanico'], true)) {
            error_response('Permissão insuficiente.', 403);
            exit;
        }
    }
}
