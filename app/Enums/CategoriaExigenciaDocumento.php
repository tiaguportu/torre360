<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CategoriaExigenciaDocumento: string implements HasColor, HasIcon, HasLabel
{
    case OBRIGATORIO_CONTRATO = 'obrigatorio_contrato';
    case OBRIGATORIO_HISTORICO = 'obrigatorio_historico';
    case OPCIONAL = 'opcional';
    case INTERNO = 'interno';

    public function getLabel(): string
    {
        return match ($this) {
            self::OBRIGATORIO_CONTRATO => 'Obrigatório para Contrato',
            self::OBRIGATORIO_HISTORICO => 'Obrigatório para Histórico do Aluno',
            self::OPCIONAL => 'Opcional / Complementar',
            self::INTERNO => 'Uso Interno da Secretaria',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OBRIGATORIO_CONTRATO => 'danger',
            self::OBRIGATORIO_HISTORICO => 'warning',
            self::OPCIONAL => 'success',
            self::INTERNO => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OBRIGATORIO_CONTRATO => 'heroicon-m-document-check',
            self::OBRIGATORIO_HISTORICO => 'heroicon-m-academic-cap',
            self::OPCIONAL => 'heroicon-m-paper-clip',
            self::INTERNO => 'heroicon-m-lock-closed',
        };
    }

    public function getDescricao(): string
    {
        return match ($this) {
            self::OBRIGATORIO_CONTRATO => 'Indispensável para emissão do contrato escolar e ativação da matrícula.',
            self::OBRIGATORIO_HISTORICO => 'Exigido para a vida escolar e conformidade com o MEC (não impede a geração do contrato).',
            self::OPCIONAL => 'Documento facultativo visível no Portal da Família e nos formulários de admissão.',
            self::INTERNO => 'Uso exclusivo da secretaria e arquivo escolar (não aparece para a família).',
        };
    }

    public function isVisivelPortalFamilia(): bool
    {
        return $this !== self::INTERNO;
    }

    public function isBloqueanteContrato(): bool
    {
        return $this === self::OBRIGATORIO_CONTRATO;
    }
}
