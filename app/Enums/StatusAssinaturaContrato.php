<?php

namespace App\Enums;

use App\Models\Contrato;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status de assinatura digital do contrato (coluna contrato.assinafy_status).
 *
 * A coluna é texto livre; este enum descreve os valores conhecidos e como exibi-los. Valores
 * desconhecidos continuam funcionando (use {@see self::rotuloDe()} e {@see self::corDe()}).
 *
 * Estados do documento no Assinafy (https://github.com/assinafy/php-sdk): `ready` = todos os
 * signatários assinaram; `certificating` = certificado digital em geração; `certificated` =
 * certificado gerado (estado final). Cada um é mantido como status próprio e todos contam como
 * "assinado" (todas as assinaturas coletadas) — veja {@see Contrato::STATUS_ASSINADO}.
 */
enum StatusAssinaturaContrato: string implements HasColor, HasIcon, HasLabel
{
    case PENDENTE = 'pendente';
    case PENDING = 'pending';
    case ENVIADO = 'enviado';
    case ERRO_ENVIO = 'erro_envio';
    case READY = 'ready';
    case CERTIFICATING = 'certificating';
    case CERTIFICATED = 'certificated';
    case SIGNED = 'signed';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case REFUSED = 'refused';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDENTE => 'Não enviado',
            self::PENDING, self::ENVIADO => 'Pendente',
            self::ERRO_ENVIO => 'Erro no envio',
            self::READY => 'Todos assinaram',
            self::CERTIFICATING => 'Certificando',
            self::CERTIFICATED => 'Certificado',
            self::SIGNED, self::COMPLETED => 'Assinado',
            self::REJECTED, self::REFUSED => 'Recusado',
            self::CANCELED => 'Cancelado',
            self::EXPIRED => 'Expirado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDENTE => 'gray',
            self::PENDING, self::ENVIADO => 'warning',
            self::READY, self::CERTIFICATING => 'info',
            self::CERTIFICATED, self::SIGNED, self::COMPLETED => 'success',
            self::ERRO_ENVIO, self::REJECTED, self::REFUSED, self::CANCELED, self::EXPIRED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDENTE => 'heroicon-m-paper-airplane',
            self::PENDING, self::ENVIADO => 'heroicon-m-clock',
            self::READY => 'heroicon-m-check',
            self::CERTIFICATING => 'heroicon-m-arrow-path',
            self::CERTIFICATED => 'heroicon-m-check-badge',
            self::SIGNED, self::COMPLETED => 'heroicon-m-check-circle',
            self::ERRO_ENVIO, self::REJECTED, self::REFUSED, self::CANCELED, self::EXPIRED => 'heroicon-m-x-circle',
        };
    }

    /**
     * Todas as assinaturas já foram coletadas (inclui as etapas de certificação).
     */
    public function foiAssinado(): bool
    {
        return in_array($this->value, Contrato::STATUS_ASSINADO, true);
    }

    /**
     * Rótulo de um valor de status; para valores desconhecidos usa $padrao ou, sem ele, o próprio valor.
     */
    public static function rotuloDe(?string $valor, ?string $padrao = null): string
    {
        if (blank($valor)) {
            return $padrao ?? 'Não enviado';
        }

        $status = self::tryFrom($valor);

        return $status ? (string) $status->getLabel() : ($padrao ?? ucfirst($valor));
    }

    public static function corDe(?string $valor): string
    {
        $cor = filled($valor) ? self::tryFrom($valor)?->getColor() : null;

        return is_string($cor) ? $cor : 'gray';
    }
}
