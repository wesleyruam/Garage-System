<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\CsrfService;

class AuthController extends Controller
{
    public function csrf(): void
    {
        success_response(['token' => CsrfService::token()]);
    }

    public function register(): void
    {
        $data = $this->input();
        $this->validate($data, [
            'nome' => ['required', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'senha' => ['required', 'min:8', 'max:255'],
        ]);

        $user = (new AuthService())->register($data);
        success_response($user->publicData(), 201, 'Usuário criado.');
    }

    public function login(): void
    {
        $data = $this->input();
        $this->validate($data, [
            'email' => ['required', 'email', 'max:160'],
            'senha' => ['required', 'max:255'],
        ]);

        $user = (new AuthService())->login($data['email'], $data['senha']);
        if (!$user) {
            error_response('Credenciais inválidas.', 401);
            return;
        }

        $token = CsrfService::regenerate();
        success_response(['user' => $user->publicData(), 'csrf_token' => $token], 200, 'Autenticado.');
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        success_response(null, 200, 'Sessão encerrada.');
    }

    public function me(): void
    {
        $user = (new UsuarioRepository())->findById((int) Session::get('user_id'));
        success_response($user?->publicData());
    }
}
