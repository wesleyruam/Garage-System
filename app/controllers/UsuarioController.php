<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ClienteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioRepository $usuarios = new UsuarioRepository(),
        private readonly ClienteRepository $clientes = new ClienteRepository()
    ) {
    }

    public function index(): void
    {
        success_response($this->usuarios->all());
    }

    public function store(): void
    {
        $data = $this->input();
        $this->validate($data, [
            'nome' => ['required', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'senha' => ['required', 'min:8', 'max:255'],
            'tipo' => ['required', 'max:20'],
            'cliente_id' => ['int'],
        ]);

        $data = clean_payload($data, ['nome', 'email', 'tipo']);
        $tipos = ['admin', 'atendente', 'mecanico', 'cliente'];
        if (!in_array($data['tipo'], $tipos, true)) {
            error_response('Tipo de usuário inválido.', 422);
            return;
        }

        if ($data['tipo'] === 'cliente') {
            if (empty($data['cliente_id']) || !$this->clientes->find((int) $data['cliente_id'])) {
                error_response('Usuário cliente precisa estar vinculado a um cliente válido.', 422);
                return;
            }
        } else {
            $data['cliente_id'] = null;
        }

        $user = (new AuthService())->register($data);
        success_response($user->publicData(), 201, 'Usuário criado.');
    }
}
