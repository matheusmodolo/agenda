<?php

namespace App\Enums;

enum StatusReserva: string
{
    case Pendente = 'pendente';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';
    case Concluida = 'concluida';

    public function podeMudarPara(StatusReserva $status): bool
    {
        // Concluída e Cancelada são estados finais, não podem mudar
        $permitidos = match ($this) {
            self::Pendente => [self::Confirmada, self::Cancelada],
            self::Confirmada => [self::Concluida, self::Cancelada],
            default => [],
        };

        return in_array($status, $permitidos, true);
    }

    public static function ativos(): array
    {
        return [self::Pendente, self::Confirmada];
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Confirmada => 'Confirmada',
            self::Cancelada => 'Cancelada',
            self::Concluida => 'Concluída',
        };
    }
}
