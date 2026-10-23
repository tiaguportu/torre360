<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Schemas;

use App\Enums\StatusCandidato;
use App\Models\User;
use App\Services\CurriculoIaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class CandidatoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('📄 Currículo do Candidato & Extração com IA')
                    ->description('Faça o upload do currículo em PDF, Word (.doc, .docx), LibreOffice (.odt) ou Imagem. A IA interpretará os dados cadastrais, profissionais e extrairá a foto da pessoa.')
                    ->columns(3)
                    ->schema([
                        FileUpload::make('curriculo_path')
                            ->label('Arquivo do Currículo (PDF, Word, LibreOffice ou Imagem)')
                            ->disk('public')
                            ->directory('candidatos/curriculos')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.oasis.opendocument.text',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(20480)
                            ->openable()
                            ->downloadable()
                            ->storeFileNamesIn('curriculo_nome_original')
                            ->helperText('Formatos: PDF, Word (.docx, .doc), LibreOffice (.odt) e Imagens. Clique em "Interpretar Currículo com IA ✨".')
                            ->columnSpan(2)
                            ->hintAction(
                                Action::make('interpretarCurriculoIa')
                                    ->label('Interpretar Currículo com IA ✨')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('purple')
                                    ->action(function (Get $get, Set $set) {
                                        $state = $get('curriculo_path');

                                        if (empty($state)) {
                                            Notification::make()
                                                ->title('Nenhum arquivo de currículo anexado')
                                                ->body('Por favor, faça o upload do arquivo do currículo antes de executar a interpretação com IA.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $caminho = null;
                                        $mime = null;
                                        $nomeOriginal = null;

                                        if ($state instanceof TemporaryUploadedFile) {
                                            $caminho = $state->getRealPath();
                                            $mime = $state->getMimeType();
                                            $nomeOriginal = $state->getClientOriginalName();
                                        } elseif (is_string($state)) {
                                            $caminho = Storage::disk('public')->path($state);
                                            $mime = @mime_content_type($caminho) ?: 'application/octet-stream';
                                            $nomeOriginal = basename($state);
                                        }

                                        if (! $caminho || ! file_exists($caminho)) {
                                            Notification::make()
                                                ->title('Arquivo inacessível')
                                                ->body('Não foi possível acessar o arquivo físico do currículo para análise.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        try {
                                            /** @var CurriculoIaService $service */
                                            $service = app(CurriculoIaService::class);
                                            $dados = $service->analisarCurriculo($caminho, $mime, $nomeOriginal);

                                            if (! empty($dados['nome_completo'])) {
                                                $set('nome_completo', $dados['nome_completo']);
                                            }
                                            if (! empty($dados['email'])) {
                                                $set('email', $dados['email']);
                                            }
                                            if (! empty($dados['telefone'])) {
                                                $set('telefone', $dados['telefone']);
                                            }
                                            if (! empty($dados['data_nascimento'])) {
                                                $set('data_nascimento', $dados['data_nascimento']);
                                            }
                                            if (! empty($dados['idade'])) {
                                                $set('idade', (int) $dados['idade']);
                                            }
                                            if (! empty($dados['cargo_pretendido'])) {
                                                $set('cargo_pretendido', $dados['cargo_pretendido']);
                                            }
                                            if (! empty($dados['escola_atual'])) {
                                                $set('escola_atual', $dados['escola_atual']);
                                            }
                                            if (! empty($dados['local_moradia'])) {
                                                $set('local_moradia', $dados['local_moradia']);
                                            }
                                            if (isset($dados['pretensao_salarial']) && is_numeric($dados['pretensao_salarial'])) {
                                                $set('pretensao_salarial', (float) $dados['pretensao_salarial']);
                                            }
                                            if (! empty($dados['formacao_academica'])) {
                                                $set('formacao_academica', $dados['formacao_academica']);
                                            }
                                            if (! empty($dados['experiencias_profissionais'])) {
                                                $set('experiencias_profissionais', $dados['experiencias_profissionais']);
                                            }
                                            if (! empty($dados['habilidades'])) {
                                                $set('habilidades', $dados['habilidades']);
                                            }
                                            if (! empty($dados['resumo_perfil'])) {
                                                $set('resumo_perfil', $dados['resumo_perfil']);
                                            }

                                            // Foto extraída pela IA (em PDF, imagens, DOCX, ODT ou DOC)
                                            if (! empty($dados['foto_path'])) {
                                                $set('foto_path', $dados['foto_path']);
                                            }

                                            $set('dados_ia', $dados);

                                            $mensagemFoto = ! empty($dados['foto_path'])
                                                ? ' Foto do candidato identificada e extraída com sucesso!'
                                                : '';

                                            Notification::make()
                                                ->title('✨ Currículo interpretado com sucesso!')
                                                ->body('Os dados foram preenchidos nos campos correspondentes.'.$mensagemFoto)
                                                ->success()
                                                ->send();
                                        } catch (Throwable $e) {
                                            Notification::make()
                                                ->title('Falha ao interpretar currículo com IA')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),

                        FileUpload::make('foto_path')
                            ->label('Foto do Candidato')
                            ->image()
                            ->disk('public')
                            ->directory('candidatos/fotos')
                            ->helperText('Extraída automaticamente do arquivo do currículo pela IA ou atualizada manualmente.')
                            ->columnSpan(1),
                    ]),

                Section::make('👤 Dados Pessoais e de Contato')
                    ->columns(3)
                    ->schema([
                        TextInput::make('nome_completo')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('telefone')
                            ->label('Telefone / WhatsApp')
                            ->tel()
                            ->maxLength(50),

                        DatePicker::make('data_nascimento')
                            ->label('Data de Nascimento')
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state && blank($get('idade'))) {
                                    $idade = Carbon::parse($state)->age;
                                    $set('idade', $idade);
                                }
                            }),

                        TextInput::make('idade')
                            ->label('Idade')
                            ->numeric()
                            ->minValue(16)
                            ->maxValue(100),

                        TextInput::make('local_moradia')
                            ->label('Local onde mora (Bairro / Cidade)')
                            ->placeholder('Ex: Bairro Gutierrez, Belo Horizonte - MG')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('🎯 Vaga Pretendida & Histórico Atual')
                    ->columns(3)
                    ->schema([
                        TextInput::make('cargo_pretendido')
                            ->label('Pretensão de Vaga / Cargo')
                            ->placeholder('Ex: Professor de Matemática, Pedagoga, Monitor')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('escola_atual')
                            ->label('Escola onde trabalha atualmente')
                            ->placeholder('Ex: Colégio Santo Agostinho')
                            ->maxLength(255),

                        TextInput::make('pretensao_salarial')
                            ->label('Pretensão Salarial')
                            ->prefix('R$')
                            ->numeric()
                            ->minValue(0),

                        Select::make('status')
                            ->label('Status do Processo')
                            ->options(StatusCandidato::class)
                            ->default(StatusCandidato::Novo)
                            ->required(),

                        Select::make('entrevistador_user_id')
                            ->label('Entrevistador Responsável')
                            ->relationship('entrevistador', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),
                    ]),

                Section::make('🎓 Formação Acadêmica & Experiências')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Textarea::make('formacao_academica')
                            ->label('Formação Acadêmica')
                            ->rows(3)
                            ->placeholder('Graduações, licenciaturas, pós-graduações, cursos e certificações.'),

                        Textarea::make('experiencias_profissionais')
                            ->label('Experiências Profissionais Anteriores')
                            ->rows(3)
                            ->placeholder('Escolas anteriores, anos de atuação, funções exercidas.'),

                        Textarea::make('habilidades')
                            ->label('Habilidades e Diferenciais')
                            ->rows(3)
                            ->placeholder('Metodologias pedagógicas, ferramentas de ensino, idiomas, etc.'),

                        Textarea::make('resumo_perfil')
                            ->label('Síntese do Perfil (IA / RH)')
                            ->rows(3)
                            ->placeholder('Resumo executivo do perfil do profissional.'),
                    ]),

                Section::make('🎙️ Entrevista de RH & Análise de Áudio (WhatsApp)')
                    ->description('Registre as impressões do entrevistador. Utilize o botão "Entrevista de RH / Áudio WhatsApp 🎙️✨" no topo da página de edição para enviar a gravação e deixar a IA sintetizar o parecer e complementar dados do formulário.')
                    ->headerActions([
                        Action::make('abrirModalAudioSecao')
                            ->label('Interpretar Áudio de WhatsApp 🎙️✨')
                            ->icon('heroicon-o-microphone')
                            ->color('purple')
                            ->modalHeading('🎙️ Entrevista de RH & Análise de Áudio (WhatsApp)')
                            ->modalDescription('Faça upload da mensagem de voz do WhatsApp gravada pelo entrevistador. A IA sintetizará as impressões, gerará a transcrição dos pontos-chave e atualizará dados cadastrais citados no áudio.')
                            ->modalSubmitActionLabel('Interpretar Áudio com IA ✨')
                            ->form([
                                FileUpload::make('modal_audio_arquivo')
                                    ->label('Gravação de Áudio do WhatsApp')
                                    ->disk('public')
                                    ->directory('candidatos/audios')
                                    ->acceptedFileTypes([
                                        'audio/ogg',
                                        'audio/mpeg',
                                        'audio/mp3',
                                        'audio/wav',
                                        'audio/x-m4a',
                                        'audio/aac',
                                        'audio/opus',
                                        'application/ogg',
                                    ])
                                    ->maxSize(30720)
                                    ->required()
                                    ->helperText('Formatos: áudio de WhatsApp (.ogg, .opus), .mp3, .m4a, .wav.'),

                                Select::make('modal_entrevistador_id')
                                    ->label('Entrevistador Responsável')
                                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                                    ->default(fn () => auth()->id())
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->action(function (array $data, Set $set, Get $get) {
                                $caminhoRelativo = $data['modal_audio_arquivo'] ?? null;
                                if (! $caminhoRelativo) {
                                    return;
                                }

                                $caminhoFisico = Storage::disk('public')->path($caminhoRelativo);
                                $mime = @mime_content_type($caminhoFisico) ?: 'audio/ogg';

                                try {
                                    /** @var CurriculoIaService $service */
                                    $service = app(CurriculoIaService::class);
                                    $dadosAtuais = [
                                        'nome_completo' => $get('nome_completo'),
                                        'cargo_pretendido' => $get('cargo_pretendido'),
                                    ];

                                    $resultado = $service->analisarAudioEntrevista($caminhoFisico, $mime, $dadosAtuais);

                                    $set('audio_entrevista_path', $caminhoRelativo);
                                    if (! empty($data['modal_entrevistador_id'])) {
                                        $set('entrevistador_user_id', $data['modal_entrevistador_id']);
                                    }

                                    if (! empty($resultado['impressoes_entrevistador'])) {
                                        $set('impressoes_entrevistador', $resultado['impressoes_entrevistador']);
                                    }

                                    if (! empty($resultado['audio_transcricao_resumo'])) {
                                        $set('audio_transcricao_resumo', $resultado['audio_transcricao_resumo']);
                                    }

                                    // Complementa campos cadastrais citados no áudio
                                    $cadastrais = $resultado['dados_cadastrais_extraidos'] ?? [];
                                    if (is_array($cadastrais)) {
                                        if (! empty($cadastrais['nome_completo']) && blank($get('nome_completo'))) {
                                            $set('nome_completo', $cadastrais['nome_completo']);
                                        }
                                        if (! empty($cadastrais['idade']) && blank($get('idade'))) {
                                            $set('idade', (int) $cadastrais['idade']);
                                        }
                                        if (! empty($cadastrais['data_nascimento']) && blank($get('data_nascimento'))) {
                                            $set('data_nascimento', $cadastrais['data_nascimento']);
                                        }
                                        if (! empty($cadastrais['cargo_pretendido']) && blank($get('cargo_pretendido'))) {
                                            $set('cargo_pretendido', $cadastrais['cargo_pretendido']);
                                        }
                                        if (! empty($cadastrais['escola_atual']) && blank($get('escola_atual'))) {
                                            $set('escola_atual', $cadastrais['escola_atual']);
                                        }
                                        if (! empty($cadastrais['local_moradia']) && blank($get('local_moradia'))) {
                                            $set('local_moradia', $cadastrais['local_moradia']);
                                        }
                                        if (isset($cadastrais['pretensao_salarial']) && is_numeric($cadastrais['pretensao_salarial']) && blank($get('pretensao_salarial'))) {
                                            $set('pretensao_salarial', (float) $cadastrais['pretensao_salarial']);
                                        }
                                        if (! empty($cadastrais['formacao_academica']) && blank($get('formacao_academica'))) {
                                            $set('formacao_academica', $cadastrais['formacao_academica']);
                                        }
                                        if (! empty($cadastrais['experiencias_profissionais']) && blank($get('experiencias_profissionais'))) {
                                            $set('experiencias_profissionais', $cadastrais['experiencias_profissionais']);
                                        }
                                        if (! empty($cadastrais['habilidades']) && blank($get('habilidades'))) {
                                            $set('habilidades', $cadastrais['habilidades']);
                                        }
                                    }

                                    if (! empty($resultado['parecer_sugerido'])) {
                                        $parecer = strtolower((string) $resultado['parecer_sugerido']);
                                        $statusEnum = match ($parecer) {
                                            'aprovado' => StatusCandidato::Aprovado,
                                            'reprovado' => StatusCandidato::Reprovado,
                                            'banco_talentos' => StatusCandidato::BancoTalentos,
                                            'entrevistado' => StatusCandidato::Entrevistado,
                                            'em_triagem' => StatusCandidato::EmTriagem,
                                            default => null,
                                        };
                                        if ($statusEnum) {
                                            $set('status', $statusEnum);
                                        }
                                    }

                                    Notification::make()
                                        ->title('🎙️ Áudio de WhatsApp processado com sucesso!')
                                        ->body('As impressões do entrevistador foram consolidadas e os campos foram atualizados no formulário.')
                                        ->success()
                                        ->send();
                                } catch (Throwable $e) {
                                    Notification::make()
                                        ->title('Falha ao processar áudio com IA')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])
                    ->columns(2)
                    ->schema([
                        FileUpload::make('audio_entrevista_path')
                            ->label('Gravação de Áudio do WhatsApp')
                            ->disk('public')
                            ->directory('candidatos/audios')
                            ->acceptedFileTypes([
                                'audio/ogg',
                                'audio/mpeg',
                                'audio/mp3',
                                'audio/wav',
                                'audio/x-m4a',
                                'audio/aac',
                                'audio/opus',
                                'application/ogg',
                            ])
                            ->maxSize(30720)
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull()
                            ->helperText('Gravação de áudio arquivada para consulta e auditoria.'),

                        Textarea::make('impressoes_entrevistador')
                            ->label('Impressões do Entrevistador de RH')
                            ->rows(5)
                            ->placeholder('Resumo detalhado das impressões sobre o candidato: postura, pontos fortes, alinhamento cultural e pontos de atenção.')
                            ->columnSpanFull(),

                        Textarea::make('audio_transcricao_resumo')
                            ->label('Resumo e Transcrição dos Tópicos do Áudio (IA)')
                            ->rows(4)
                            ->placeholder('Tópicos essenciais extraídos da gravação de voz.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
