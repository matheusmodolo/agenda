<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

abstract class RegraDeNegocioException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $detalhes  dados extras devolvidos no JSON
     */
    public function __construct(
        string $message,
        protected string $codigo,
        protected int $statusHttp = 422,
        protected array $detalhes = [],
    ) {
        parent::__construct($message);
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            // Código estável para o front (ou um teste) decidir algo sem comparar texto.
            'codigo' => $this->codigo,
            ...$this->detalhes,
        ], $this->statusHttp);
    }
}
