<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoTemplateDocumento: string implements HasColor, HasIcon, HasLabel
{
    case DeclaracaoMatricula = 'declaracao_matricula';
    case DeclaracaoFrequencia = 'declaracao_frequencia';
    case DeclaracaoQuitacao = 'declaracao_quitacao';
    case DeclaracaoConclusao = 'declaracao_conclusao';
    case DeclaracaoTransferencia = 'declaracao_transferencia';
    case DeclaracaoTransporte = 'declaracao_transporte';
    case DeclaracaoHorario = 'declaracao_horario';
    case HistoricoEscolar = 'historico_escolar';
    case Personalizado = 'personalizado';
    case DistratoMatricula = 'distrato_matricula';
    case ContratoTrabalho = 'contrato_trabalho';
    case TermoConfidencialidade = 'termo_confidencialidade';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DeclaracaoMatricula => 'Declaração de Matrícula',
            self::DeclaracaoFrequencia => 'Declaração de Frequência',
            self::DeclaracaoQuitacao => 'Declaração de Quitação de Débitos',
            self::DeclaracaoConclusao => 'Declaração de Conclusão de Série/Curso',
            self::DeclaracaoTransferencia => 'Declaração de Transferência / Vaga',
            self::DeclaracaoTransporte => 'Declaração de Transporte / Passe Escolar',
            self::DeclaracaoHorario => 'Declaração de Turno e Horário de Aulas',
            self::HistoricoEscolar => 'Histórico Escolar',
            self::Personalizado => 'Documento Personalizado (aluno)',
            self::DistratoMatricula => 'Distrato de Matrícula',
            self::ContratoTrabalho => 'Contrato de Trabalho',
            self::TermoConfidencialidade => 'Termo de Confidencialidade e Proteção de Dados',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DeclaracaoMatricula => 'primary',
            self::DeclaracaoFrequencia => 'info',
            self::DeclaracaoQuitacao => 'success',
            self::DeclaracaoConclusao => 'warning',
            self::DeclaracaoTransferencia => 'danger',
            self::DeclaracaoTransporte => 'info',
            self::DeclaracaoHorario => 'primary',
            self::HistoricoEscolar => 'warning',
            self::Personalizado => 'gray',
            self::DistratoMatricula => 'danger',
            self::ContratoTrabalho => 'primary',
            self::TermoConfidencialidade => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DeclaracaoMatricula => 'heroicon-m-academic-cap',
            self::DeclaracaoFrequencia => 'heroicon-m-calendar-days',
            self::DeclaracaoQuitacao => 'heroicon-m-banknotes',
            self::DeclaracaoConclusao => 'heroicon-m-check-badge',
            self::DeclaracaoTransferencia => 'heroicon-m-arrows-right-left',
            self::DeclaracaoTransporte => 'heroicon-m-truck',
            self::DeclaracaoHorario => 'heroicon-m-clock',
            self::HistoricoEscolar => 'heroicon-m-document-text',
            self::Personalizado => 'heroicon-m-document-duplicate',
            self::DistratoMatricula => 'heroicon-m-document-minus',
            self::ContratoTrabalho => 'heroicon-m-briefcase',
            self::TermoConfidencialidade => 'heroicon-m-shield-check',
        };
    }

    /**
     * De que registro o modelo tira os dados. Os tipos antigos são todos de matrícula (as variáveis do aluno); os novos
     * têm o seu próprio contexto.
     */
    public function contexto(): ContextoDocumento
    {
        return match ($this) {
            self::DistratoMatricula => ContextoDocumento::CancelamentoMatricula,
            self::ContratoTrabalho, self::TermoConfidencialidade => ContextoDocumento::Funcionario,
            default => ContextoDocumento::Matricula,
        };
    }

    /**
     * Tipos que a secretaria emite e a família pede como declaração (com protocolo e QR Code): os de matrícula. O
     * distrato e os documentos de funcionário só saem pelo fluxo próprio e pela assinatura eletrônica.
     *
     * @return list<string>
     */
    public static function valoresParaEmissao(): array
    {
        return array_values(array_map(
            fn (self $tipo): string => $tipo->value,
            array_filter(self::cases(), fn (self $tipo): bool => $tipo->contexto() === ContextoDocumento::Matricula),
        ));
    }
}
