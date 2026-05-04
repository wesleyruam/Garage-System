<?php

namespace App\Services;

use App\Repositories\ProdutoRepository;

class EstoqueService
{
    public function __construct(private readonly ProdutoRepository $produtos = new ProdutoRepository())
    {
    }

    public function movimentar(int $produtoId, int $quantidade): array
    {
        $produto = $this->produtos->find($produtoId);
        if (!$produto) {
            error_response('Produto não encontrado.', 404);
            exit;
        }

        if ($produto->estoque + $quantidade < 0) {
            error_response('Estoque insuficiente.', 422);
            exit;
        }

        return $this->produtos->updateStock($produtoId, $quantidade)->toArray();
    }
}
