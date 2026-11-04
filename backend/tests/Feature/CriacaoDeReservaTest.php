<?php

namespace Tests\Feature;

use App\Enums\StatusReserva;
use App\Exceptions\ConflitoDeHorarioException;
use App\Exceptions\PeriodoInvalidoException;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Cliente;
use App\Models\Maquina;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CriacaoDeReservaTest extends TestCase
{
    use RefreshDatabase;

    private ReservaService $service;
    private Maquina $trator;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        // "Agora" fixo: 09/11/2026 08:00, para rodar igual em qualquer dia.
        $this->travelTo('2026-11-09 08:00:00');

        $this->service = $this->app->make(ReservaService::class);
        $this->trator = Maquina::factory()->create(['nome' => 'Trator 01']);
        $this->cliente = Cliente::factory()->create();

        // Cenário comum: reserva existente das 10:00 às 12:00 do dia seguinte, Pendente.
        Reserva::factory()
            ->for($this->trator)
            ->periodo('2026-11-10 10:00', '2026-11-10 12:00')
            ->create();
    }

    private function criar(string $inicio, string $fim): Reserva
    {
        return $this->service->criar($this->trator->id, $this->cliente->id, $inicio, $fim);
    }

    private function esperarRecusa(string $classe, string $codigo, callable $acao): void
    {
        try {
            $acao();
        } catch (RegraDeNegocioException $e) {
            $this->assertInstanceOf($classe, $e);
            $this->assertSame($codigo, $e->codigo());
            return;
        }

        $this->fail("Esperava {$classe} ({$codigo}), mas a reserva foi aceita.");
    }

    public function test_permite_reserva_que_termina_exatamente_quando_a_outra_comeca(): void
    {
        $reserva = $this->criar('2026-11-10 08:00', '2026-11-10 10:00');

        $this->assertSame(StatusReserva::Pendente, $reserva->status);
        $this->assertDatabaseCount('reservas', 2);
    }

    public function test_permite_reserva_que_comeca_exatamente_quando_a_outra_termina(): void
    {
        $this->criar('2026-11-10 12:00', '2026-11-10 14:00');

        $this->assertDatabaseCount('reservas', 2);
    }

    public function test_permite_mesmo_horario_quando_a_reserva_existente_foi_cancelada(): void
    {
        Reserva::query()->update(['status' => StatusReserva::Cancelada]);

        $this->criar('2026-11-10 10:00', '2026-11-10 12:00');

        $this->assertDatabaseCount('reservas', 2);
    }

    public function test_permite_mesmo_horario_em_outra_maquina(): void
    {
        $outraMaquina = Maquina::factory()->create();

        $this->service->criar($outraMaquina->id, $this->cliente->id, '2026-11-10 10:00', '2026-11-10 12:00');

        $this->assertDatabaseCount('reservas', 2);
    }

    // Sobreposição
    public static function periodosQueSobrepoem(): array
    {
        return [
            'parcial no início (09–11)' => ['2026-11-10 09:00', '2026-11-10 11:00'],
            'parcial no fim (11–13)' => ['2026-11-10 11:00', '2026-11-10 13:00'],
            'contida (10:30–11:30)' => ['2026-11-10 10:30', '2026-11-10 11:30'],
            'envolvendo (09–13)' => ['2026-11-10 09:00', '2026-11-10 13:00'],
            'idêntica (10–12)' => ['2026-11-10 10:00', '2026-11-10 12:00'],
            'um minuto de sobra (11:59–14)' => ['2026-11-10 11:59', '2026-11-10 14:00'],
        ];
    }

    #[DataProvider('periodosQueSobrepoem')]
    public function test_recusa_periodo_que_cruza_reserva_ativa(string $inicio, string $fim): void
    {
        $this->esperarRecusa(
            ConflitoDeHorarioException::class,
            'conflito_de_horario',
            fn() => $this->criar($inicio, $fim),
        );

        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_reserva_confirmada_tambem_bloqueia_o_horario(): void
    {
        Reserva::query()->update(['status' => StatusReserva::Confirmada]);

        $this->esperarRecusa(
            ConflitoDeHorarioException::class,
            'conflito_de_horario',
            fn() => $this->criar('2026-11-10 11:00', '2026-11-10 13:00'),
        );
    }

    public function test_data_em_utc_vinda_do_javascript_e_convertida_para_o_horario_local(): void
    {
        $this->esperarRecusa(
            ConflitoDeHorarioException::class,
            'conflito_de_horario',
            fn() => $this->criar('2026-11-10T13:00:00Z', '2026-11-10T14:00:00Z'),
        );
    }

    // Período válido
    public function test_recusa_fim_antes_do_inicio(): void
    {
        $this->esperarRecusa(
            PeriodoInvalidoException::class,
            'fim_antes_do_inicio',
            fn() => $this->criar('2026-11-11 10:00', '2026-11-11 09:00'),
        );
    }

    public function test_recusa_fim_igual_ao_inicio(): void
    {
        $this->esperarRecusa(
            PeriodoInvalidoException::class,
            'fim_antes_do_inicio',
            fn() => $this->criar('2026-11-11 10:00', '2026-11-11 10:00'),
        );
    }

    public function test_recusa_inicio_no_passado(): void
    {
        $this->esperarRecusa(
            PeriodoInvalidoException::class,
            'inicio_no_passado',
            fn() => $this->criar('2026-11-09 07:59', '2026-11-09 09:00'),
        );
    }

    public function test_permite_inicio_exatamente_agora(): void
    {
        $this->criar('2026-11-09 08:00', '2026-11-09 09:00');

        $this->assertDatabaseCount('reservas', 2);
    }

    public function test_recusa_reserva_com_mais_de_7_dias(): void
    {
        $this->esperarRecusa(
            PeriodoInvalidoException::class,
            'duracao_excedida',
            fn() => $this->criar('2026-11-11 08:00', '2026-11-18 08:01'),
        );
    }

    public function test_permite_reserva_de_exatamente_7_dias(): void
    {
        $this->criar('2026-11-11 08:00', '2026-11-18 08:00');

        $this->assertDatabaseCount('reservas', 2);
    }

    public function test_status_enviado_e_ignorado_e_toda_reserva_nasce_pendente(): void
    {
        $reserva = $this->criar('2026-11-12 08:00', '2026-11-12 10:00');

        $this->assertSame(StatusReserva::Pendente, $reserva->fresh()->status);
    }
}
