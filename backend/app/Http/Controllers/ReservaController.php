<?php

namespace App\Http\Controllers;

use App\Http\Requests\CriarReservaRequest;
use App\Http\Requests\ListarReservasRequest;
use App\Http\Requests\MudarStatusReservaRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function __construct(private ReservaService $reservaService) {}

    public function index(ListarReservasRequest $request)
    {
        $reservas = Reserva::query()
            ->with(['maquina', 'cliente'])
            ->when($request->filled('maquina_id'), fn($q) => $q->where('maquina_id', $request->integer('maquina_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->orderBy('inicio')
            ->get();

        return ReservaResource::collection($reservas);
    }

    public function store(CriarReservaRequest $request)
    {
        $reserva = $this->reservaService->criar(
            $request->integer('maquina_id'),
            $request->integer('cliente_id'),
            $request->string('inicio')->toString(),
            $request->string('fim')->toString(),
        );

        return ReservaResource::make($reserva->load(['maquina', 'cliente']))->response()->setStatusCode(201);
    }

    public function mudarStatus(MudarStatusReservaRequest $request, Reserva $reserva)   // id inexistente → 404 sozinho
    {
        return ReservaResource::make(
            $this->reservaService->mudarStatus($reserva, $request->novoStatus())->load(['maquina', 'cliente'])
        );
    }
}
