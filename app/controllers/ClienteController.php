<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ClienteRepository;

class ClienteController extends Controller
{
    public function __construct(private readonly ClienteRepository $clientes = new ClienteRepository())
    {
    }

    public function index(): void
    {
        success_response($this->clientes->all());
    }

    public function show(string $id): void
    {
        $cliente = $this->clientes->find($this->routeId($id));
        $cliente ? success_response($cliente->toArray()) : error_response('Cliente não encontrado.', 404);
    }

    public function store(): void
    {
        $data = $this->input();
        $this->validate($data, [
            'nome' => ['required', 'max:120'],
            'cpf_cnpj' => ['required', 'max:20'],
            'telefone' => ['max:30'],
            'email' => ['email', 'max:160'],
            'endereco' => ['max:255'],
        ]);
        $data = clean_payload($data, ['nome', 'cpf_cnpj', 'telefone', 'email', 'endereco']);

        success_response($this->clientes->create($data)->toArray(), 201, 'Cliente criado.');
    }

    public function update(string $id): void
    {
        $data = $this->input();
        $this->validate($data, [
            'nome' => ['required', 'max:120'],
            'cpf_cnpj' => ['required', 'max:20'],
            'telefone' => ['max:30'],
            'email' => ['email', 'max:160'],
            'endereco' => ['max:255'],
        ]);
        $data = clean_payload($data, ['nome', 'cpf_cnpj', 'telefone', 'email', 'endereco']);

        success_response($this->clientes->update($this->routeId($id), $data)?->toArray());
    }

    public function destroy(string $id): void
    {
        $this->clientes->delete($this->routeId($id));
        success_response(null, 200, 'Cliente removido.');
    }
}
