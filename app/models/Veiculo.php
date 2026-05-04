<?php

namespace App\Models;

use App\Core\Model;

class Veiculo extends Model
{
    public function __construct(
        public ?int $id,
        public int $cliente_id,
        public string $placa,
        public string $modelo,
        public ?int $ano,
        public ?string $cor,
        public ?int $km,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {
        $this->created_at ??= now();
    }
}
