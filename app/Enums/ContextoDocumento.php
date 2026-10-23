<?php

namespace App\Enums;

use App\Models\CancelamentoMatricula;
use App\Models\Funcionario;
use App\Models\Matricula;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * De que registro um modelo de documento tira os dados: define quais variáveis ({{ALUNO_NOME}}, {{CARGO}}...) o modelo
 * aceita e qual registro se escolhe na hora de emitir, de ver a prévia ou de mandar para assinatura.
 */
enum ContextoDocumento: string implements HasLabel
{
    case Matricula = 'matricula';
    case CancelamentoMatricula = 'cancelamento_matricula';
    case Funcionario = 'funcionario';

    public function getLabel(): string
    {
        return match ($this) {
            self::Matricula => 'Aluno (matrícula)',
            self::CancelamentoMatricula => 'Cancelamento de matrícula',
            self::Funcionario => 'Funcionário',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelo(): string
    {
        return match ($this) {
            self::Matricula => Matricula::class,
            self::CancelamentoMatricula => CancelamentoMatricula::class,
            self::Funcionario => Funcionario::class,
        };
    }
}
