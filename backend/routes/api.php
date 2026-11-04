<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\MaquinaController;
use App\Http\Controllers\ReservaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/maquinas', [MaquinaController::class, 'index']);
Route::get('/clientes', [ClienteController::class, 'index']);
Route::get('/reservas', [ReservaController::class, 'index']);
Route::post('/reservas', [ReservaController::class, 'store']);
Route::patch('/reservas/{reserva}/status', [ReservaController::class, 'mudarStatus']);
