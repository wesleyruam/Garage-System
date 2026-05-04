<?php

namespace App\Services;

class RelatorioService
{
    public function periodo(string $inicio, string $fim): array
    {
        return ['inicio' => $inicio, 'fim' => $fim];
    }
}
