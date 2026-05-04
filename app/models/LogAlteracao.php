<?php

namespace App\Models;

use App\Core\Model;

class LogAlteracao extends Model
{
    public function __construct(
        public ?int $id,
        public int $os_id,
        public int $usuario_id,
        public string $motivo,
        public ?string $valor_antigo,
        public ?string $valor_novo,
        public ?string $created_at = null
    ) {
        $this->created_at ??= now();
    }
}
