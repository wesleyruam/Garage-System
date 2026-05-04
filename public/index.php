<?php

declare(strict_types=1);

use App\Core\Router;

require_once dirname(__DIR__) . '/app/helpers/utils.php';
load_env(base_path('.env'));

date_default_timezone_set(config('app.timezone', 'UTC'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $parts = explode('/', $relative);
    $parts[0] = strtolower($parts[0]);
    $relative = implode('/', $parts);
    $path = base_path('app/' . $relative . '.php');
    if (is_file($path)) {
        require_once $path;
    }
});

require_once base_path('app/helpers/response.php');
require_once base_path('app/helpers/validator.php');

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true) && !str_starts_with($requestPath, '/api')) {
    App\Middlewares\SecurityMiddleware::headers();
    header('Content-Type: text/html; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        readfile(__DIR__ . '/index.html');
    }
    return;
}

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);

    if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
        error_response('Registro duplicado ou relacionamento inválido.', 409);
        return;
    }

    error_response('Erro interno.', 500);
});

$router = new Router();
require base_path('routes/api.php');

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $exception) {
    error_log((string) $exception);

    if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
        error_response('Registro duplicado ou relacionamento inválido.', 409);
        return;
    }

    error_response('Erro interno.', 500);
}
