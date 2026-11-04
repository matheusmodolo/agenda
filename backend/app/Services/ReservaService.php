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
        // O status inicial é definido aqui, nunca vem do request.
        // Mesmo que o front mande "status": "confirmada", ele é ignorado.
        $conflitante = Reserva::query()
            ->where('maquina_id', $maquinaId)
            ->whereIn('status', StatusReserva::ativos())
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $inicio)
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
        // O caminho existe no diagrama de estados? (regra "estática", no enum)
        if (! $reserva->status->podeMudarPara($novo)) {
            throw TransicaoInvalidaException::naoPermitida($reserva->status, $novo);
        }

        // Regras que dependem do relógio.
        // Concluir exige now() >= fim. Exatamente no horário de fim já pode concluir,
        // porque o uso da máquina terminou naquele instante.
        if ($novo === StatusReserva::Concluida && now()->lt($reserva->fim)) {
            throw TransicaoInvalidaException::concluirAntesDoFim();
        }

        // A regra das 24h vale só para Confirmada (Pendente pode cancelar a qualquer
        // momento). "Pelo menos 24h" => permitido quando now() + 24h <= início.
        // Com exatamente 24h de antecedência, now()+24h == início, então é permitido.
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

    /**
     * @throws PeriodoInvalidoException
     */
    private function validarPeriodo(CarbonImmutable $inicio, CarbonImmutable $fim): void
    {
        // lte (<=): fim IGUAL ao início é uma reserva de duração zero, também inválida.
        if ($fim->lte($inicio)) {
            throw PeriodoInvalidoException::fimAntesDoInicio();
        }

        if ($inicio->lt(now())) {
            throw PeriodoInvalidoException::inicioNoPassado();
        }

        // Exatamente 7 dias é permitido ("no máximo 7 dias").
        if ($fim->gt($inicio->addDays(self::DURACAO_MAXIMA_DIAS))) {
            throw PeriodoInvalidoException::duracaoExcedida(self::DURACAO_MAXIMA_DIAS);
        }
    }

    /**
     * A REGRA DE SOBREPOSIÇÃO.
     *
     * Dois intervalos A_inicio, A_fim e B_inicio, B_fim se cruzam se:
     * A_inicio < B_fim  E  A_fim > B_inicio
     *
     * Essa única condição cobre todos os casos (parcial no início, parcial no fim,
     * contida, envolvendo, idêntica).
     * O intervalo é "fechado no início e aberto no fim", igual a um horário de agenda.
     *
     * Só reservas ativas (Pendente/Confirmada) contam; Cancelada/Concluída liberam o horário.
     *
     * @throws ConflitoDeHorarioException
     */
    private function garantirSemConflito(int $maquinaId, CarbonImmutable $inicio, CarbonImmutable $fim): void
    {
        $conflitante = $this->buscarConflitante($maquinaId, $inicio, $fim);

        if ($conflitante !== null) {
            $sugestao = $this->sugerirProximoHorarioLivre(
                $maquinaId,
                $conflitante->fim->toImmutable(),
                (int) $inicio->diffInSeconds($fim),
            );

            throw new ConflitoDeHorarioException($conflitante->load('maquina'), $sugestao);
        }
    }

    /**
     * Primeira reserva ativa da máquina que cruza [inicio, fim), ou null se o horário está livre.
     */
    private function buscarConflitante(int $maquinaId, CarbonImmutable $inicio, CarbonImmutable $fim): ?Reserva
    {
        return Reserva::query()
            ->where('maquina_id', $maquinaId)
            ->whereIn('status', StatusReserva::ativos())
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $inicio)
            ->orderBy('inicio')
            ->first();
    }
}
