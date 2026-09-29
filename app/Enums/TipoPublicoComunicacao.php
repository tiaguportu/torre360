<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoPublicoComunicacao: string implements HasLabel
{
    /**
     * Leads do CRM (`interessado`), segmentados por status/origem/campanha,
     * ou uma lista explícita de ids (usada pela ação em massa da tabela).
     */
    case Interessados = 'interessados';

    /**
     * Responsáveis dos alunos com matrícula ativa nas turmas selecionadas.
     */
    case ResponsaveisTurma = 'responsaveis_turma';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Interessados => 'Interessados (CRM)',
            self::ResponsaveisTurma => 'Responsáveis por turma',
        };
    }
}
