<?php

namespace Database\Factories;

use App\Enums\StatusReserva;
use App\Models\Cliente;
use App\Models\Maquina;
use App\Models\Reserva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = now()->addDays(2)->startOfHour();

        return [
            'maquina_id' => Maquina::factory(),
            'cliente_id' => Cliente::factory(),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHours(2),
            'status' => StatusReserva::Pendente,
        ];
    }

    public function periodo(string $inicio, string $fim): static
    {
        return $this->state(fn() => ['inicio' => $inicio, 'fim' => $fim]);
    }

    public function status(StatusReserva $status): static
    {
        return $this->state(fn() => ['status' => $status]);
    }
}
