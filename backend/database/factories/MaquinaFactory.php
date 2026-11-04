<?php

namespace Database\Factories;

use App\Models\Maquina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maquina>
 */
class MaquinaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'tipo' => fake()->randomElement(['trator', 'pulverizador', 'colheitadeira']),
            'ativo' => true
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn() => ['ativo' => false]);
    }
}
