<?php

namespace App\Repositories;

use App\Core\Database;
use App\Models\Usuario;
use PDO;

class UsuarioRepository
{
    public function findByEmail(string $email): ?Usuario
    {
        $stmt = Database::connection()->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower($email)]);
        $row = $stmt->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(array $data): Usuario
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuarios (nome, email, senha, tipo, cliente_id, ativo, created_at, updated_at)
             VALUES (:nome, :email, :senha, :tipo, :cliente_id, :ativo, :created_at, :updated_at)'
        );
        $stmt->execute([
            'nome' => $data['nome'],
            'email' => mb_strtolower($data['email']),
            'senha' => password_hash($data['senha'], PASSWORD_DEFAULT),
            'tipo' => $data['tipo'] ?? 'atendente',
            'cliente_id' => $data['cliente_id'] ?? null,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findById((int) Database::connection()->lastInsertId());
    }

    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM usuarios ORDER BY id DESC LIMIT 200');
        return array_map(fn (array $row) => $this->map($row)->publicData(), $stmt->fetchAll());
    }

    public function findById(int $id): ?Usuario
    {
        $stmt = Database::connection()->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->map($row) : null;
    }

    private function map(array $row): Usuario
    {
        return new Usuario(
            (int) $row['id'],
            $row['nome'],
            $row['email'],
            $row['senha'],
            $row['tipo'],
            isset($row['cliente_id']) ? (int) $row['cliente_id'] : null,
            (bool) $row['ativo'],
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }
}
