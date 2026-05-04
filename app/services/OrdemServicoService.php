<?php

namespace App\Services;

use App\Repositories\ClienteRepository;
use App\Repositories\OrdemServicoRepository;
use App\Repositories\VeiculoRepository;

class OrdemServicoService
{
    public function __construct(
        private readonly OrdemServicoRepository $ordens = new OrdemServicoRepository(),
        private readonly ClienteRepository $clientes = new ClienteRepository(),
        private readonly VeiculoRepository $veiculos = new VeiculoRepository()
    ) {
    }

    public function abrir(array $data): array
    {
        if (!$this->clientes->find((int) $data['cliente_id'])) {
            error_response('Cliente não encontrado.', 422);
            exit;
        }

        $veiculo = $this->veiculos->find((int) $data['veiculo_id']);
        if (!$veiculo || $veiculo->cliente_id !== (int) $data['cliente_id']) {
            error_response('Veículo inválido para o cliente informado.', 422);
            exit;
        }

        return $this->ordens->create($data)->toArray();
    }
}
