<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\OrdemServicoRepository;
use App\Services\AuthorizationService;
use App\Services\OrdemServicoService;
use App\Services\UploadService;

class OrdemServicoController extends Controller
{
    public function __construct(private readonly OrdemServicoRepository $ordens = new OrdemServicoRepository())
    {
    }

    public function index(): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        success_response($this->ordens->all());
    }

    public function show(string $id): void
    {
        $ordem = $this->ordens->find($this->routeId($id));
        if ($ordem && !AuthorizationService::canViewOrdemServico($ordem)) {
            error_response('Recurso não encontrado.', 404);
            return;
        }

        $ordem ? success_response($ordem->toArray()) : error_response('Ordem de serviço não encontrada.', 404);
    }

    public function store(): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        $data = $this->input();
        $this->validate($data, [
            'cliente_id' => ['required', 'int'],
            'veiculo_id' => ['required', 'int'],
            'valor_inicial' => ['numeric'],
            'descricao_problema' => ['required', 'max:2000'],
            'diagnostico' => ['max:2000'],
            'agendado_para' => ['max:30'],
        ]);
        $data = clean_payload($data, ['descricao_problema', 'diagnostico']);

        success_response((new OrdemServicoService())->abrir($data), 201, 'Ordem de serviço aberta.');
    }

    public function status(string $id): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        $data = $this->input();
        $allowed = ['orcamento', 'aprovada', 'aberta', 'em_andamento', 'aguardando_pecas', 'finalizada', 'cancelada'];
        if (!in_array($data['status'] ?? '', $allowed, true)) {
            error_response('Status inválido.', 422);
            return;
        }

        $valorFinal = isset($data['valor_final']) ? (float) $data['valor_final'] : null;
        success_response($this->ordens->updateStatus($this->routeId($id), $data['status'], $valorFinal, (int) Session::get('user_id'))?->toArray());
    }

    public function approve(string $id): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        success_response($this->ordens->approveBudget($this->routeId($id), (int) Session::get('user_id')));
    }

    public function items(string $id): void
    {
        $orderId = $this->routeId($id);
        $ordem = $this->ordens->find($orderId);
        if (!$ordem || !AuthorizationService::canViewOrdemServico($ordem)) {
            error_response('Recurso não encontrado.', 404);
            return;
        }

        success_response($this->ordens->items($orderId));
    }

    public function addItem(string $id): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        $data = $this->input();
        $this->validate($data, [
            'tipo' => ['required', 'max:20'],
            'descricao' => ['max:255'],
            'quantidade' => ['required', 'numeric'],
            'valor_unitario' => ['required', 'numeric'],
            'custo_unitario' => ['numeric'],
            'desconto' => ['numeric'],
            'produto_id' => ['int'],
        ]);

        if (!in_array($data['tipo'], ['peca', 'servico'], true)) {
            error_response('Tipo de item inválido.', 422);
            return;
        }

        if ($data['tipo'] === 'peca' && empty($data['produto_id'])) {
            error_response('Peça precisa estar vinculada a um produto.', 422);
            return;
        }

        $data = clean_payload($data, ['descricao']);
        success_response($this->ordens->addItem($this->routeId($id), (int) Session::get('user_id'), $data), 201, 'Item adicionado.');
    }

    public function payments(string $id): void
    {
        $orderId = $this->routeId($id);
        $ordem = $this->ordens->find($orderId);
        if (!$ordem || !AuthorizationService::canViewOrdemServico($ordem)) {
            error_response('Recurso não encontrado.', 404);
            return;
        }

        success_response($this->ordens->payments($orderId));
    }

    public function addPayment(string $id): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        $data = $this->input();
        $this->validate($data, [
            'valor' => ['required', 'numeric'],
            'forma_pagamento' => ['required', 'max:60'],
            'observacao' => ['max:255'],
        ]);
        $data = clean_payload($data, ['forma_pagamento', 'observacao']);
        success_response($this->ordens->addPayment($this->routeId($id), (int) Session::get('user_id'), $data), 201, 'Pagamento registrado.');
    }

    public function financial(string $id): void
    {
        $orderId = $this->routeId($id);
        $ordem = $this->ordens->find($orderId);
        if (!$ordem || !AuthorizationService::canViewOrdemServico($ordem)) {
            error_response('Recurso não encontrado.', 404);
            return;
        }

        success_response($this->ordens->financialSummary($orderId));
    }

    public function schedule(string $id): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        $data = $this->input();
        $this->validate($data, ['agendado_para' => ['required', 'max:30']]);
        $date = date_create($data['agendado_para']);
        if (!$date) {
            error_response('Data inválida.', 422);
            return;
        }

        success_response($this->ordens->updateSchedule(
            $this->routeId($id),
            $date->format('Y-m-d H:i:s'),
            (int) Session::get('user_id')
        )?->toArray());
    }

    public function history(string $id): void
    {
        $orderId = $this->routeId($id);
        $ordem = $this->ordens->find($orderId);
        if (!$ordem || !AuthorizationService::canViewOrdemServico($ordem)) {
            error_response('Recurso não encontrado.', 404);
            return;
        }

        success_response($this->ordens->history($orderId));
    }

    public function attachments(string $id): void
    {
        $orderId = $this->routeId($id);
        $ordem = $this->ordens->find($orderId);
        if (!$ordem || !AuthorizationService::canViewOrdemServico($ordem)) {
            error_response('Recurso não encontrado.', 404);
            return;
        }

        success_response($this->ordens->attachments($orderId));
    }

    public function upload(string $id): void
    {
        if (!AuthorizationService::isStaff()) {
            error_response('Permissão insuficiente.', 403);
            return;
        }

        $orderId = $this->routeId($id);
        if (!$this->ordens->find($orderId)) {
            error_response('Ordem de serviço não encontrada.', 404);
            return;
        }

        $file = $_FILES['arquivo'] ?? null;
        if (!$file) {
            error_response('Arquivo não enviado.', 422);
            return;
        }

        $path = (new UploadService())->store($file, 'os_' . $orderId);
        success_response($this->ordens->addAttachment($orderId, (int) Session::get('user_id'), $file, $path), 201, 'Anexo enviado.');
    }
}
