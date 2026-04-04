<?php

namespace App\Filament\Schemas\Components;

use App\Models\Matricula;
use Filament\Schemas\Components\Component;

class BoletimeHeader extends Component
{
    protected string $view = 'filament.schemas.components.boletime-header';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getMatricula()
    {
        $record = $this->getRecord();

        if ($record instanceof Matricula) {
            return $record;
        }

        return $record?->matriculas?->first();
    }

    public function getAluno()
    {
        return $this->getMatricula()?->pessoa;
    }

    public function getTurma()
    {
        return $this->getMatricula()?->turma;
    }

    public function getSerie()
    {
        return $this->getTurma()?->serie;
    }

    public function getCurso()
    {
        return $this->getSerie()?->curso;
    }
}
