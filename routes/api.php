<?php

use App\Controllers\AuthController;
use App\Controllers\ClienteController;
use App\Controllers\ClientePortalController;
use App\Controllers\DashboardController;
use App\Controllers\EstoqueController;
use App\Controllers\OrdemServicoController;
use App\Controllers\ProdutoController;
use App\Controllers\UsuarioController;
use App\Controllers\VeiculoController;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\ClienteMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\StaffMiddleware;

$router->get('/', [DashboardController::class, 'index']);
$router->get('/api/health', [DashboardController::class, 'index']);
$router->get('/api/csrf', [AuthController::class, 'csrf']);
$router->post('/api/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->post('/api/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
$router->post('/api/register', [AuthController::class, 'register'], [AdminMiddleware::class, CsrfMiddleware::class]);

$router->get('/api/admin/usuarios', [UsuarioController::class, 'index'], [AdminMiddleware::class]);
$router->post('/api/admin/usuarios', [UsuarioController::class, 'store'], [AdminMiddleware::class, CsrfMiddleware::class]);

$router->get('/api/cliente/os', [ClientePortalController::class, 'minhasOrdens'], [ClienteMiddleware::class]);
$router->get('/api/cliente/os/{id}', [ClientePortalController::class, 'ordem'], [ClienteMiddleware::class]);

$router->get('/api/clientes', [ClienteController::class, 'index'], [StaffMiddleware::class]);
$router->get('/api/clientes/{id}', [ClienteController::class, 'show'], [StaffMiddleware::class]);
$router->post('/api/clientes', [ClienteController::class, 'store'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->put('/api/clientes/{id}', [ClienteController::class, 'update'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->delete('/api/clientes/{id}', [ClienteController::class, 'destroy'], [AdminMiddleware::class, CsrfMiddleware::class]);

$router->get('/api/veiculos', [VeiculoController::class, 'index'], [StaffMiddleware::class]);
$router->get('/api/veiculos/{id}', [VeiculoController::class, 'show'], [StaffMiddleware::class]);
$router->post('/api/veiculos', [VeiculoController::class, 'store'], [StaffMiddleware::class, CsrfMiddleware::class]);

$router->get('/api/produtos', [ProdutoController::class, 'index'], [StaffMiddleware::class]);
$router->get('/api/produtos/{id}', [ProdutoController::class, 'show'], [StaffMiddleware::class]);
$router->post('/api/produtos', [ProdutoController::class, 'store'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/produtos/{id}/estoque', [EstoqueController::class, 'movimentar'], [StaffMiddleware::class, CsrfMiddleware::class]);

$router->get('/api/os', [OrdemServicoController::class, 'index'], [AuthMiddleware::class]);
$router->get('/api/os/{id}', [OrdemServicoController::class, 'show'], [AuthMiddleware::class]);
$router->get('/api/os/{id}/historico', [OrdemServicoController::class, 'history'], [AuthMiddleware::class]);
$router->get('/api/os/{id}/anexos', [OrdemServicoController::class, 'attachments'], [AuthMiddleware::class]);
$router->get('/api/os/{id}/itens', [OrdemServicoController::class, 'items'], [AuthMiddleware::class]);
$router->get('/api/os/{id}/pagamentos', [OrdemServicoController::class, 'payments'], [AuthMiddleware::class]);
$router->get('/api/os/{id}/financeiro', [OrdemServicoController::class, 'financial'], [AuthMiddleware::class]);
$router->post('/api/os', [OrdemServicoController::class, 'store'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/os/{id}/aprovar', [OrdemServicoController::class, 'approve'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/os/{id}/itens', [OrdemServicoController::class, 'addItem'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/os/{id}/pagamentos', [OrdemServicoController::class, 'addPayment'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/os/{id}/anexos', [OrdemServicoController::class, 'upload'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->patch('/api/os/{id}/status', [OrdemServicoController::class, 'status'], [StaffMiddleware::class, CsrfMiddleware::class]);
$router->patch('/api/os/{id}/agenda', [OrdemServicoController::class, 'schedule'], [StaffMiddleware::class, CsrfMiddleware::class]);
