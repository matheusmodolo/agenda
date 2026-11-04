<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained();
            $table->foreignId('cliente_id')->constrained();
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->string('status')->default('pendente');
            $table->timestamps();
            $table->index(['maquina_id', 'inicio', 'fim']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
