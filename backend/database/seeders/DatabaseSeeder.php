<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Maquina;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Cliente::factory()->create();
        Maquina::factory()->create();
        Reserva::factory()->create([
            'inicio' => now(),
            'fim' => now()->addDay()->setTime(8, 0)
        ]);
    }
}
