<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação de um período aquisitivo de férias frente ao prazo legal de concessão (CLT, arts. 134 e 137): as férias
 * precisam ser concedidas nos 12 meses seguintes ao fim do período aquisitivo; depois disso, os dias não gozados são
 * pagos em dobro. É calculada, nunca gravada.
 */
enum SituacaoConcessaoFerias: string implements HasColor, HasLabel
{
    case EmAquisicao = 'em_aquisicao';
    case AConceder = 'a_conceder';
    case AVencer = 'a_vencer';
    case Vencida = 'vencida';
    case Quitada = 'quitada';
    case QuitadaForaDoPrazo = 'quitada_fora_do_prazo';
    case SemRegistro = 'sem_registro';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmAquisicao => 'Em aquisição',
            self::AConceder => 'A conceder',
            self::AVencer => 'Prazo de concessão acabando',
            self::Vencida => 'Vencida (risco de pagamento em dobro)',
            self::Quitada => 'Gozada',
            self::QuitadaForaDoPrazo => 'Gozada fora do prazo',
            self::SemRegistro => 'Período sem registro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EmAquisicao, self::Quitada => 'gray',
            self::AConceder => 'info',
            self::AVencer, self::SemRegistro => 'warning',
            self::Vencida, self::QuitadaForaDoPrazo => 'danger',
        };
    }

    /** Situações que pedem ação de quem cuida do RH. */
    public function exigeAtencao(): bool
    {
        return in_array($this, [self::AConceder, self::AVencer, self::Vencida, self::SemRegistro], true);
    }

    /** Peso para ordenar do mais grave para o menos grave. */
    public function gravidade(): int
    {
        return match ($this) {
            self::Vencida => 5,
            self::AVencer => 4,
            self::SemRegistro => 3,
            self::AConceder => 2,
            self::QuitadaForaDoPrazo => 1,
            self::EmAquisicao, self::Quitada => 0,
        };
    }
}
