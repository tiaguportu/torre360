<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use App\Models\QuestionarioResposta;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;

class CompararQuestionarioRespostas extends Page
{
    protected static string $resource = QuestionarioRespostaResource::class;

    protected string $view = 'filament.resources.questionario-respostas.comparacao';

    public Collection $records;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount()
    {
        $ids = request()->query('ids');
        if (empty($ids) || ! is_array($ids)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        $this->records = QuestionarioResposta::whereIn('id', $ids)->get();

        if ($this->records->isEmpty()) {
            abort(404, 'Nenhum questionário selecionado.');
        }
    }
}
