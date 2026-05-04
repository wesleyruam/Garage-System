<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ProdutoRepository;

class ProdutoController extends Controller
{
    public function __construct(private readonly ProdutoRepository $produtos = new ProdutoRepository())
    {
    }

    public function index(): void
    {
        success_response($this->produtos->all());
    }

    public function show(string $id): void
    {
        $produto = $this->produtos->find($this->routeId($id));
        $produto ? success_response($produto->toArray()) : error_response('Produto não encontrado.', 404);
    }

    public function store(): void
    {
        $data = $this->input();
        $this->validate($data, [
            'nome' => ['required', 'max:120'],
            'codigo' => ['required', 'max:60'],
            'preco_custo' => ['required', 'numeric'],
            'preco_venda' => ['required', 'numeric'],
            'estoque' => ['int'],
        ]);
        $data = clean_payload($data, ['nome', 'codigo']);

        success_response($this->produtos->create($data)->toArray(), 201, 'Produto criado.');
    }
}
