<?php

namespace App\Models;

use App\Core\Model;

class Produto extends Model
{
    public function __construct(
        public ?int $id,
        public string $nome,
        public string $codigo,
        public float $preco_custo,
        public float $preco_venda,
        public int $estoque,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {
        $this->created_at ??= now();
    }
}
