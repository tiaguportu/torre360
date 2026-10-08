<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusTurma: string implements HasColor, HasIcon, HasLabel
{
    case Planejada = 'planejada';
    case Ativa = 'ativa';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Planejada => 'Planejada',
            self::Ativa => 'Ativa',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Planejada => 'info',
            self::Ativa => 'success',
            self::Concluida => 'gray',
            self::Cancelada => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Planejada => 'heroicon-m-calendar-days',
            self::Ativa => 'heroicon-m-check-circle',
            self::Concluida => 'heroicon-m-academic-cap',
            self::Cancelada => 'heroicon-m-x-circle',
        };
    }

    /**
     * O Select do Filament com `options(StatusTurma::class)` entrega, conforme o contexto, a
     * instância do enum ou o seu valor em texto; esta conversão aceita as duas formas.
     */
    public static function resolver(self|string $valor): self
    {
        return $valor instanceof self ? $valor : self::from($valor);
    }

    /**
     * Turma que ainda pode receber matrículas (inclusive as do próximo período, ainda planejadas).
     */
    public function abertaParaMatricula(): bool
    {
        return in_array($this, self::abertasParaMatricula(), true);
    }

    /**
     * @return list<self>
     */
    public static function abertasParaMatricula(): array
    {
        return [self::Planejada, self::Ativa];
    }

    /**
     * @return list<string>
     */
    public static function valoresAbertosParaMatricula(): array
    {
        return array_map(fn (self $status) => $status->value, self::abertasParaMatricula());
    }
}
