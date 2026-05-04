<?php

function validate(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? null;
        foreach ($fieldRules as $rule) {
            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

            if ($name === 'required' && ($value === null || $value === '')) {
                $errors[$field][] = 'Campo obrigatório.';
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($name === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field][] = 'E-mail inválido.';
            }

            if ($name === 'max' && mb_strlen((string) $value) > (int) $parameter) {
                $errors[$field][] = "Máximo de {$parameter} caracteres.";
            }

            if ($name === 'min' && mb_strlen((string) $value) < (int) $parameter) {
                $errors[$field][] = "Mínimo de {$parameter} caracteres.";
            }

            if ($name === 'numeric' && !is_numeric($value)) {
                $errors[$field][] = 'Valor numérico inválido.';
            }

            if ($name === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                $errors[$field][] = 'Valor inteiro inválido.';
            }
        }
    }

    return $errors;
}

function only(array $data, array $fields): array
{
    return array_intersect_key($data, array_flip($fields));
}
