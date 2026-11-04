<?php

namespace App\Exceptions;

use Exception;

class PeriodoInvalidoException extends RegraDeNegocioException
{
    public static function fimAntesDoInicio(): self
    {
        return new self('A data/hora de fim deve ser depois da data/hora de início.', 'fim_antes_do_inicio');
    }

    public static function inicioNoPassado(): self
    {
        return new self('A data/hora de início não pode ser no passado.', 'inicio_no_passado');
    }

    public static function duracaoExcedida(int $dias): self
    {
        return new self('Duração excedida por ' . $dias . ' dias.', 'duracao_excedida');
    }
}
