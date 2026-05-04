<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Veiculo;

class VeiculoRepository
{
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM veiculos ORDER BY id DESC LIMIT 200');
        return array_map(fn (array $row) => $this->map($row)->toArray(), $stmt->fetchAll());
    }

    public function find(int $id): ?Veiculo
    {
        $stmt = Database::connection()->prepare('SELECT * FROM veiculos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(array $data): Veiculo
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO veiculos (cliente_id, placa, modelo, ano, cor, km, created_at, updated_at)
             VALUES (:cliente_id, :placa, :modelo, :ano, :cor, :km, :created_at, :updated_at)'
        );
        $stmt->execute([
            'cliente_id' => $data['cliente_id'],
            'placa' => mb_strtoupper($data['placa']),
            'modelo' => $data['modelo'],
            'ano' => $data['ano'] ?? null,
            'cor' => $data['cor'] ?? null,
            'km' => $data['km'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find((int) Database::connection()->lastInsertId());
    }

    private function map(array $row): Veiculo
    {
        return new Veiculo(
            (int) $row['id'],
            (int) $row['cliente_id'],
            $row['placa'],
            $row['modelo'],
            isset($row['ano']) ? (int) $row['ano'] : null,
            $row['cor'] ?? null,
            isset($row['km']) ? (int) $row['km'] : null,
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }
}
