<?php

namespace App\Models;

use App\Core\Model;

class OrdemServico extends Model
{
    public function __construct(
        public ?int $id,
        public int $cliente_id,
        public int $veiculo_id,
        public string $status,
        public string $orcamento_status,
        public float $valor_inicial,
        public ?float $valor_final,
        public string $descricao_problema,
        public ?string $diagnostico,
        public ?string $agendado_para = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?string $finalizado_em = null
    ) {
        $this->created_at ??= now();
    }
}
