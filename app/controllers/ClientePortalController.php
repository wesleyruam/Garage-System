<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\OrdemServicoRepository;

class ClientePortalController extends Controller
{
    public function __construct(private readonly OrdemServicoRepository $ordens = new OrdemServicoRepository())
    {
    }

    public function minhasOrdens(): void
    {
        success_response($this->ordens->allByCliente((int) Session::get('cliente_id')));
    }

    public function ordem(string $id): void
    {
        $ordem = $this->ordens->findForCliente($this->routeId($id), (int) Session::get('cliente_id'));
        $ordem ? success_response($ordem->toArray()) : error_response('Recurso não encontrado.', 404);
    }
}
