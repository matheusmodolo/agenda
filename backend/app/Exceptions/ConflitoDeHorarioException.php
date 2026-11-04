<?php

namespace App\Exceptions;

use App\Models\Reserva;
use Carbon\CarbonInterface;

class ConflitoDeHorarioException extends RegraDeNegocioException
{
    /**
     * @param  array{inicio: CarbonInterface, fim: CarbonInterface}|null
     */
    public function __construct(
        public readonly Reserva $conflitante,
        public readonly ?array $sugestao = null,
    ) {
        $formato = 'd/m/Y H:i';

        $mensagem = sprintf(
            'Conflito de horário: a máquina "%s" já está reservada de %s até %s (reserva #%d, %s).',
            $conflitante->maquina->nome,
            $conflitante->inicio->format($formato),
            $conflitante->fim->format($formato),
            $conflitante->id,
            $conflitante->status->rotulo(),
        );

        if ($sugestao !== null) {
            $mensagem .= sprintf(
                ' Próximo horário livre com a mesma duração: de %s até %s.',
                $sugestao['inicio']->format($formato),
                $sugestao['fim']->format($formato),
            );
        }

        parent::__construct(
            message: $mensagem,
            codigo: 'conflito_de_horario',
            statusHttp: 409,
            detalhes: [
                'reserva_conflitante_id' => $conflitante->id,
                'sugestao' => $sugestao === null ? null : [
                    'inicio' => $sugestao['inicio']->toIso8601String(),
                    'fim' => $sugestao['fim']->toIso8601String(),
                ],
            ],
        );
    }
}
