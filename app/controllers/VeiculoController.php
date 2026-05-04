<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ClienteRepository;
use App\Repositories\VeiculoRepository;

class VeiculoController extends Controller
{
    public function __construct(
        private readonly VeiculoRepository $veiculos = new VeiculoRepository(),
        private readonly ClienteRepository $clientes = new ClienteRepository()
    ) {
    }

    public function index(): void
    {
        success_response($this->veiculos->all());
    }

    public function show(string $id): void
    {
        $veiculo = $this->veiculos->find($this->routeId($id));
        $veiculo ? success_response($veiculo->toArray()) : error_response('Veículo não encontrado.', 404);
    }

    public function store(): void
    {
        $data = $this->input();
        $this->validate($data, [
            'cliente_id' => ['required', 'int'],
            'placa' => ['required', 'max:10'],
            'modelo' => ['required', 'max:120'],
            'ano' => ['int'],
            'km' => ['int'],
            'cor' => ['max:60'],
        ]);
        $data = clean_payload($data, ['placa', 'modelo', 'cor']);

        if (!$this->clientes->find((int) $data['cliente_id'])) {
            error_response('Cliente não encontrado.', 422);
            return;
        }

        success_response($this->veiculos->create($data)->toArray(), 201, 'Veículo criado.');
    }
}
