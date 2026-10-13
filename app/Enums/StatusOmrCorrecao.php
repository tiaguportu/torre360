<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusOmrCorrecao: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case AguardandoConferencia = 'aguardando_conferencia';
    case Confirmada = 'confirmada';
    case Descartada = 'descartada';
    case Falha = 'falha';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Processando => 'Processando',
            self::AguardandoConferencia => 'Aguardando Conferência',
            self::Confirmada => 'Confirmada',
            self::Descartada => 'Descartada',
            self::Falha => 'Falha',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendente => 'gray',
            self::Processando => 'info',
            self::AguardandoConferencia => 'warning',
            self::Confirmada => 'success',
            self::Descartada => 'danger',
            self::Falha => 'danger',
        };
    }
}
