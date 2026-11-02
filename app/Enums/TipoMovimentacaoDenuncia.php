<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoMovimentacaoDenuncia: string implements HasLabel
{
    case Recebida = 'recebida';
    case Apuracao = 'apuracao';
    case ParecerInterno = 'parecer_interno';
    case Devolutiva = 'devolutiva';
    case ComplementoDoAutor = 'complemento_autor';
    case ConselhoTutelar = 'conselho_tutelar';
    case Encerramento = 'encerramento';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recebida => 'Recebimento',
            self::Apuracao => 'Apuração',
            self::ParecerInterno => 'Parecer interno',
            self::Devolutiva => 'Devolutiva ao autor',
            self::ComplementoDoAutor => 'Informação do autor',
            self::ConselhoTutelar => 'Comunicação ao Conselho Tutelar',
            self::Encerramento => 'Encerramento',
        };
    }

    /**
     * O autor só vê, por padrão, o que a equipe escreveu para ele ou o que ele mesmo enviou. Parecer interno e comunicação ao
     * Conselho Tutelar nunca aparecem a quem acompanha pelo código.
     */
    public function visivelAoAutorPorPadrao(): bool
    {
        return in_array($this, [self::Recebida, self::Devolutiva, self::ComplementoDoAutor, self::Encerramento], true);
    }
}
