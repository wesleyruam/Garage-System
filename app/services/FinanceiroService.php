<?php

namespace App\Services;

class FinanceiroService
{
    public function calcularLucro(float $receita, float $custo): float
    {
        return round($receita - $custo, 2);
    }
}
