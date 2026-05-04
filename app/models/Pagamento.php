<?php

namespace App\Models;

use App\Core\Model;

class Pagamento extends Model
{
    public function __construct(
        public ?int $id,
        public int $os_id,
        public float $valor,
        public string $forma_pagamento,
        public string $data_pagamento
    ) {
    }
}
