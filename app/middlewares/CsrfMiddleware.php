<?php

namespace App\Middlewares;

use App\Services\CsrfService;

class CsrfMiddleware
{
    public static function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        if (!CsrfService::verify((string) $token)) {
            error_response('Token CSRF inválido.', 419);
            exit;
        }
    }
}
