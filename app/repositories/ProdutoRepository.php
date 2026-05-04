<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Produto;

class ProdutoRepository
{
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM produtos ORDER BY nome LIMIT 300');
        return array_map(fn (array $row) => $this->map($row)->toArray(), $stmt->fetchAll());
    }

    public function find(int $id): ?Produto
    {
        $stmt = Database::connection()->prepare('SELECT * FROM produtos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(array $data): Produto
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO produtos (nome, codigo, preco_custo, preco_venda, estoque, created_at, updated_at)
             VALUES (:nome, :codigo, :preco_custo, :preco_venda, :estoque, :created_at, :updated_at)'
        );
        $stmt->execute([
            'nome' => $data['nome'],
            'codigo' => mb_strtoupper($data['codigo']),
            'preco_custo' => $data['preco_custo'],
            'preco_venda' => $data['preco_venda'],
            'estoque' => $data['estoque'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find((int) Database::connection()->lastInsertId());
    }

    public function updateStock(int $id, int $quantity): ?Produto
    {
        $stmt = Database::connection()->prepare(
            'UPDATE produtos SET estoque = estoque + :quantity, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'quantity' => $quantity, 'updated_at' => now()]);
        return $this->find($id);
    }

    private function map(array $row): Produto
    {
        return new Produto(
            (int) $row['id'],
            $row['nome'],
            $row['codigo'],
            (float) $row['preco_custo'],
            (float) $row['preco_venda'],
            (int) $row['estoque'],
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }
}
