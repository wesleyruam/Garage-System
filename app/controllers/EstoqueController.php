<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EstoqueService;

class EstoqueController extends Controller
{
    public function movimentar(string $id): void
    {
        $data = $this->input();
        $this->validate($data, ['quantidade' => ['required', 'int']]);
        success_response((new EstoqueService())->movimentar($this->routeId($id), (int) $data['quantidade']));
    }
}
