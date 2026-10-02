<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de pendência acompanhados na listagem de matrículas.
 */
enum TipoPendenciaMatricula: string implements HasColor, HasIcon, HasLabel
{
    case SEM_RESPONSAVEL = 'sem_responsavel';
    case CADASTRO_INCOMPLETO = 'cadastro_incompleto';
    case DOCUMENTOS_FALTANDO = 'documentos_faltando';
    case DOCUMENTOS_REJEITADOS = 'documentos_rejeitados';
    case CONTRATO_NAO_GERADO = 'contrato_nao_gerado';
    case CONTRATO_NAO_ASSINADO = 'contrato_nao_assinado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SEM_RESPONSAVEL => 'Sem responsável',
            self::CADASTRO_INCOMPLETO => 'Cadastro incompleto',
            self::DOCUMENTOS_FALTANDO => 'Documentos faltando',
            self::DOCUMENTOS_REJEITADOS => 'Documentos rejeitados',
            self::CONTRATO_NAO_GERADO => 'Contrato não gerado',
            self::CONTRATO_NAO_ASSINADO => 'Contrato não assinado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SEM_RESPONSAVEL, self::DOCUMENTOS_FALTANDO => 'danger',
            self::CADASTRO_INCOMPLETO, self::DOCUMENTOS_REJEITADOS, self::CONTRATO_NAO_GERADO => 'warning',
            self::CONTRATO_NAO_ASSINADO => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SEM_RESPONSAVEL => 'heroicon-m-user-minus',
            self::CADASTRO_INCOMPLETO => 'heroicon-m-identification',
            self::DOCUMENTOS_FALTANDO => 'heroicon-m-document-text',
            self::DOCUMENTOS_REJEITADOS => 'heroicon-m-document-minus',
            self::CONTRATO_NAO_GERADO => 'heroicon-m-document-plus',
            self::CONTRATO_NAO_ASSINADO => 'heroicon-m-pencil-square',
        };
    }
}
