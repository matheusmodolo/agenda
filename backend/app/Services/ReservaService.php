<?php

namespace App\Services;

use App\Enums\StatusReserva;
use App\Exceptions\ConflitoDeHorarioException;
use App\Exceptions\PeriodoInvalidoException;
use App\Exceptions\TransicaoInvalidaException;
use App\Models\Reserva;
use Carbon\CarbonImmutable;

class ReservaService
{
    public function __construct(Reserva $reserva) {}

    public function criar(int $maquinaId, int $clienteId, string $inicio, string $fim): Reserva
    {
        $inicio = CarbonImmutable::parse($inicio)->setTimezone(config('app.timezone'));
        $fim = CarbonImmutable::parse($fim)->setTimezone(config('app.timezone'));

        // Período
        if ($fim->lte($inicio)) {
            throw PeriodoInvalidoException::fimAntesDoInicio();
        }
        if ($inicio->lt(now())) {
            throw PeriodoInvalidoException::inicioNoPassado();
        }

        if ($fim->gt($inicio->addDays(7))) {
            throw PeriodoInvalidoException::duracaoExcedida(7);
        }

        // Conflitos
        $conflitante = Reserva::query()
            ->where('maquina_id', $maquinaId)
            ->whereIn('status', StatusReserva::ativos())
            ->where('inicio', '<', $fim)     // estrito!
            ->where('fim', '>', $inicio)     // estrito!
            ->first();

        if ($conflitante) throw new ConflitoDeHorarioException($conflitante);

        // Grava sempre como Pendente
        return Reserva::create([
            'maquina_id' => $maquinaId,
            'cliente_id' => $clienteId,
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => StatusReserva::Pendente,
        ]);
    }

    public function mudarStatus(Reserva $reserva, StatusReserva $novo): Reserva
    {
        if (! $reserva->status->podeMudarPara($novo)) {
            throw TransicaoInvalidaException::naoPermitida($reserva->status, $novo);
        }
        if ($novo === StatusReserva::Concluida && now()->lt($reserva->fim)) {
            throw TransicaoInvalidaException::concluirAntesDoFim();
        }
        if (
            $novo === StatusReserva::Cancelada
            && $reserva->status === StatusReserva::Confirmada
            && now()->addHours(24)->gt($reserva->inicio)
        ) {
            throw TransicaoInvalidaException::cancelamentoForaDoPrazo(24);
        }

        $reserva->update(['status' => $novo]);

        return $reserva;
    }
}
