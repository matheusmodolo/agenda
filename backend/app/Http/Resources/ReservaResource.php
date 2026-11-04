<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // whenLoaded: só inclui se o relacionamento foi carregado com with().
            'maquina' => $this->whenLoaded('maquina', fn() => [
                'id' => $this->maquina->id,
                'nome' => $this->maquina->nome,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn() => [
                'id' => $this->cliente->id,
                'nome' => $this->cliente->nome,
            ]),
            // ISO 8601 COM o deslocamento: "2026-11-10T08:00:00-03:00".
            // Sem isso, o Laravel serializa datas em UTC ("2026-11-10T11:00:00.000000Z"):
            'inicio' => $this->inicio->toIso8601String(),
            'fim' => $this->fim->toIso8601String(),
            'status' => $this->status->value,
            'status_rotulo' => $this->status->rotulo(),
        ];
    }
}
