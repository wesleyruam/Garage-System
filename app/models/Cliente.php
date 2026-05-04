<?php

namespace App\Models;

use App\Core\Model;

class Cliente extends Model
{
    public function __construct(
        public ?int $id,
        public string $nome,
        public string $cpf_cnpj,
        public ?string $telefone,
        public ?string $email,
        public ?string $endereco,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {
        $this->created_at ??= now();
    }
}
