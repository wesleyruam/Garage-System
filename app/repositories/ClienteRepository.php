<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Cliente;

class ClienteRepository
{
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM clientes ORDER BY id DESC LIMIT 200');
        return array_map(fn (array $row) => $this->map($row)->toArray(), $stmt->fetchAll());
    }

    public function find(int $id): ?Cliente
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(array $data): Cliente
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco, created_at, updated_at)
             VALUES (:nome, :cpf_cnpj, :telefone, :email, :endereco, :created_at, :updated_at)'
        );
        $stmt->execute([
            'nome' => $data['nome'],
            'cpf_cnpj' => $data['cpf_cnpj'],
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find((int) Database::connection()->lastInsertId());
    }

    public function update(int $id, array $data): ?Cliente
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clientes SET nome = :nome, cpf_cnpj = :cpf_cnpj, telefone = :telefone,
             email = :email, endereco = :endereco, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'nome' => $data['nome'],
            'cpf_cnpj' => $data['cpf_cnpj'],
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'updated_at' => now(),
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM clientes WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    private function map(array $row): Cliente
    {
        return new Cliente(
            (int) $row['id'],
            $row['nome'],
            $row['cpf_cnpj'],
            $row['telefone'] ?? null,
            $row['email'] ?? null,
            $row['endereco'] ?? null,
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }
}
