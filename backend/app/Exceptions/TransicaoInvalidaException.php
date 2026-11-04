<?php

namespace App\Exceptions;

use App\Enums\StatusReserva;
use Exception;

class TransicaoInvalidaException extends RegraDeNegocioException
{
    public static function naoPermitida(StatusReserva $de, StatusReserva $para): self
    {
        return new self(
            "Não é permitido mudar o status de {$de->rotulo()} para {$para->rotulo()}.",
            'transicao_nao_permitida',
        );
    }

    public static function concluirAntesDoFim(): self
    {
        return new self(
            'Só é possível concluir a reserva depois do horário de fim.',
            'concluir_antes_do_fim',
        );
    }

    public static function cancelamentoForaDoPrazo(int $horas): self
    {
        return new self(
            "Reservas confirmadas só podem ser canceladas com pelo menos {$horas} horas de antecedência do início.",
            'cancelamento_fora_do_prazo',
        );
    }
}
