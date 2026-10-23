<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Pages;

use App\Filament\Resources\Candidatos\CandidatoResource;
use App\Models\Candidato;
use App\Services\CurriculoIaService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListCandidatos extends ListRecords
{
    protected static string $resource = CandidatoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo Candidato'),

            Action::make('cadastrarComIa')
                ->label('Cadastrar com IA ✨')
                ->icon('heroicon-o-sparkles')
                ->color('purple')
                ->visible(fn () => auth()->user()?->can('Create:Candidato'))
                ->modalHeading('✨ Cadastrar Candidato a partir do Currículo (IA)')
                ->modalDescription('Faça upload do arquivo de currículo (PDF, JPG, PNG). A IA interpretará os dados profissionais, preencherá o cadastro e extrairá a foto da pessoa automaticamente.')
                ->modalSubmitActionLabel('Analisar e Criar Candidato')
                ->form([
                    FileUpload::make('curriculo_arquivo')
                        ->label('Arquivo do Currículo')
                        ->disk('public')
                        ->directory('candidatos/curriculos')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(15360)
                        ->required()
                        ->helperText('Selecione o arquivo do currículo para interpretação e auditoria.'),
                ])
                ->action(function (array $data) {
                    $caminhoRelativo = $data['curriculo_arquivo'] ?? null;
                    if (empty($caminhoRelativo)) {
                        return;
                    }

                    $caminhoFisico = Storage::disk('public')->path($caminhoRelativo);
                    $mime = @mime_content_type($caminhoFisico) ?: 'application/pdf';
                    $nomeOriginal = basename($caminhoRelativo);

                    try {
                        /** @var CurriculoIaService $service */
                        $service = app(CurriculoIaService::class);
                        $extraido = $service->analisarCurriculo($caminhoFisico, $mime, $nomeOriginal);

                        $candidato = Candidato::create([
                            'nome_completo' => $extraido['nome_completo'] ?? 'Candidato (Via Currículo IA)',
                            'email' => $extraido['email'] ?? null,
                            'telefone' => $extraido['telefone'] ?? null,
                            'data_nascimento' => $extraido['data_nascimento'] ?? null,
                            'idade' => isset($extraido['idade']) ? (int) $extraido['idade'] : null,
                            'cargo_pretendido' => $extraido['cargo_pretendido'] ?? 'A Definir',
                            'escola_atual' => $extraido['escola_atual'] ?? null,
                            'local_moradia' => $extraido['local_moradia'] ?? null,
                            'pretensao_salarial' => isset($extraido['pretensao_salarial']) && is_numeric($extraido['pretensao_salarial'])
                                ? (float) $extraido['pretensao_salarial']
                                : null,
                            'formacao_academica' => $extraido['formacao_academica'] ?? null,
                            'experiencias_profissionais' => $extraido['experiencias_profissionais'] ?? null,
                            'habilidades' => $extraido['habilidades'] ?? null,
                            'resumo_perfil' => $extraido['resumo_perfil'] ?? null,
                            'curriculo_path' => $caminhoRelativo,
                            'curriculo_nome_original' => $nomeOriginal,
                            'foto_path' => $extraido['foto_path'] ?? null,
                            'dados_ia' => $extraido,
                            'cadastrado_por_user_id' => auth()->id(),
                        ]);

                        $mensagemFoto = ! empty($extraido['foto_path']) ? ' Foto extraída com sucesso.' : '';

                        Notification::make()
                            ->title('✨ Candidato cadastrado com sucesso via IA!')
                            ->body("Currículo de {$candidato->nome_completo} processado.{$mensagemFoto}")
                            ->success()
                            ->send();

                        $this->redirect(CandidatoResource::getUrl('edit', ['record' => $candidato]));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Falha ao processar currículo com IA')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('ViewAny:Candidato')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        $podeCriar = $user->can('Create:Candidato');
        $podeEditar = $user->can('Update:Candidato');
        $podeExcluir = $user->can('Delete:Candidato');

        $html = '<div class="space-y-4 text-sm">';
        $html .= '<h3 class="font-bold text-base text-gray-900 dark:text-gray-100">Banco de Talentos & Currículos (RH)</h3>';
        $html .= '<p class="text-gray-600 dark:text-gray-400">Gerencie os currículos de interessados em colaborar com a escola, com suporte a extração automática por inteligência artificial.</p>';

        $html .= '<div class="space-y-2">';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-200">Funcionalidades disponíveis para você:</h4>';
        $html .= '<ul class="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-400">';
        $html .= '<li><strong>Listagem Geral:</strong> Visualize candidatos cadastrados, foto de perfil, vaga pretendida, escola atual, local de moradia e pretensão salarial.</li>';
        $html .= '<li><strong>Filtros:</strong> Filtre por status (Novo, Em Triagem, Entrevistado, Banco de Talentos, Aprovado), por vaga ou se possui currículo/áudio anexado.</li>';

        if ($podeCriar) {
            $html .= '<li><strong>Cadastrar com IA ✨:</strong> Envie o currículo em PDF ou imagem para que a IA preencha os dados e extraia a foto do candidato automaticamente.</li>';
            $html .= '<li><strong>Novo Candidato:</strong> Formulário completo de cadastro manual ou assistido por IA.</li>';
        }

        if ($podeEditar) {
            $html .= '<li><strong>Edição & Áudio de WhatsApp:</strong> Abra qualquer candidato para ouvir o áudio do entrevistador, sintetizar impressões e editar dados.</li>';
        }

        if ($podeExcluir) {
            $html .= '<li><strong>Exclusão:</strong> Remova candidatos desqualificados ou duplicados com segurança (soft delete).</li>';
        }

        $html .= '</ul></div>';
        $html .= '<p class="text-xs text-gray-500 dark:text-gray-400">Dica: Áudios de WhatsApp gravados pelo entrevistador podem ser enviados no cadastro para consolidar as impressões de RH automaticamente com IA.</p>';
        $html .= '</div>';

        return $html;
    }
}
