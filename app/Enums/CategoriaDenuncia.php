<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Categorias do canal de ouvidoria (Onda 35), com as de convivência escolar da Onda 63 (Lei nº 13.185/2015).
 */
enum CategoriaDenuncia: string implements HasLabel
{
    case Bullying = 'bullying';
    case Cyberbullying = 'cyberbullying';
    case AgressaoFisica = 'agressao_fisica';
    case Discriminacao = 'discriminacao';
    case Assedio = 'assedio';
    case ExposicaoImagem = 'exposicao_imagem';
    case SuspeitaViolencia = 'suspeita_violencia';
    case DesvioConduta = 'desvio_conduta';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bullying => 'Bullying (intimidação sistemática)',
            self::Cyberbullying => 'Cyberbullying',
            self::AgressaoFisica => 'Agressão física',
            self::Discriminacao => 'Discriminação',
            self::Assedio => 'Assédio',
            self::ExposicaoImagem => 'Exposição de imagem',
            self::SuspeitaViolencia => 'Suspeita de violência contra criança ou adolescente',
            self::DesvioConduta => 'Desvio de conduta de funcionário',
            self::Outro => 'Outro assunto sensível',
        };
    }

    /**
     * Categorias do programa de convivência escolar (Onda 63).
     */
    public function ehConvivencia(): bool
    {
        return in_array($this, [self::Bullying, self::Cyberbullying, self::AgressaoFisica, self::Discriminacao, self::ExposicaoImagem], true);
    }

    /**
     * Suspeita de violência contra criança ou adolescente tem fluxo próprio de comunicação ao Conselho Tutelar
     * (ECA, art. 13; Lei nº 13.431/2017), a definir com o jurídico da escola.
     */
    public function exigeComunicacaoAoConselhoTutelar(): bool
    {
        return $this === self::SuspeitaViolencia;
    }
}
