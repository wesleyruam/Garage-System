<?php

namespace App\Models;

use App\Core\Model;

class Usuario extends Model
{
    public function __construct(
        public ?int $id,
        public string $nome,
        public string $email,
        public string $senha,
        public string $tipo = 'atendente',
        public ?int $cliente_id = null,
        public bool $ativo = true,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {
        $this->created_at ??= now();
    }

    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'tipo' => $this->tipo,
            'cliente_id' => $this->cliente_id,
            'ativo' => $this->ativo,
        ];
    }
}
