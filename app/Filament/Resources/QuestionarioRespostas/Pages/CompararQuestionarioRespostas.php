<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class CompararQuestionarioRespostas extends Page
{
    protected static string $resource = QuestionarioRespostaResource::class;

    protected string $view = 'filament.resources.questionario-respostas.comparacao';

    public Collection $records;

    public function getMaxContentWidth(): \Filament\Support\Enums\MaxWidth|string|null
    {
        return \Filament\Support\Enums\MaxWidth::Full;
    }

    public function mount()
    {
        $ids = request()->query('ids');
        if (empty($ids) || !is_array($ids)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        $this->records = \App\Models\QuestionarioResposta::whereIn('id', $ids)->get();

        if ($this->records->isEmpty()) {
            abort(404, 'Nenhum questionário selecionado.');
        }
    }
}
