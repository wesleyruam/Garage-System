<?php

namespace App\Core;

abstract class Model
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
