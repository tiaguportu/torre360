<?php

namespace App\Services;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusPropostaComercial;
use App\Models\PropostaComercial;
use App\Models\User;
use Filament\Notifications\Notification;

class RevenueManagementService
{
    public const LIMITE_CONSULTOR_MAX = 7.00;     // até 7% aprovação imediata

    public const LIMITE_COORDENACAO_MAX = 15.00;  // até 15% requer coordenação

    /**
     * Determina a alçada necessária com base no percentual de desconto solicitado.
     */
    public function determinarAlcada(float $percentualDesconto): NivelAlcadaComercial
    {
        return match (true) {
            $percentualDesconto <= self::LIMITE_CONSULTOR_MAX => NivelAlcadaComercial::Consultor,
            $percentualDesconto <= self::LIMITE_COORDENACAO_MAX => NivelAlcadaComercial::Coordenacao,
            default => NivelAlcadaComercial::Diretoria,
        };
    }

    /**
     * Realiza o cálculo financeiro completo da proposta comercial.
     *
     * @return array<string, mixed>
     */
    public function calcularValores(
        float $valorTabelaMensal,
        string $tipoDesconto,
        float $descontoSolicitado,
        int $quantidadeAlunos = 1,
        int $quantidadeParcelas = 12
    ): array {
        $quantidadeAlunos = max(1, $quantidadeAlunos);
        $quantidadeParcelas = max(1, $quantidadeParcelas);
        $valorTabelaMensal = max(0.0, $valorTabelaMensal);
        $descontoSolicitado = max(0.0, $descontoSolicitado);

        if ($tipoDesconto === 'fixo') {
            $valorDescontoMensal = min($valorTabelaMensal, $descontoSolicitado);
            $percentualDesconto = $valorTabelaMensal > 0
                ? round(($valorDescontoMensal / $valorTabelaMensal) * 100, 2)
                : 0.0;
        } else {
            $percentualDesconto = min(100.0, $descontoSolicitado);
            $valorDescontoMensal = round($valorTabelaMensal * ($percentualDesconto / 100), 2);
        }

        $valorLiquidoMensal = max(0.0, round($valorTabelaMensal - $valorDescontoMensal, 2));
        $valorTotalAnual = round($valorLiquidoMensal * $quantidadeAlunos * $quantidadeParcelas, 2);
        $alcada = $this->determinarAlcada($percentualDesconto);

        return [
            'valor_tabela_mensal' => $valorTabelaMensal,
            'tipo_desconto' => $tipoDesconto,
            'desconto_solicitado' => $descontoSolicitado,
            'percentual_desconto' => $percentualDesconto,
            'valor_desconto_mensal' => $valorDescontoMensal,
            'valor_liquido_mensal' => $valorLiquidoMensal,
            'quantidade_alunos' => $quantidadeAlunos,
            'quantidade_parcelas' => $quantidadeParcelas,
            'valor_total_anual' => $valorTotalAnual,
            'nivel_alcada' => $alcada,
        ];
    }

    /**
     * Processa as regras de alçada na criação da proposta.
     */
    public function processarCriacao(PropostaComercial $proposta, User $autor): PropostaComercial
    {
        $alcada = $proposta->nivel_alcada_necessario;

        // Se o usuário tem poder para aprovar a própria alçada ou é Super Admin
        $podeAutoAprovar = $autor->hasRole('super_admin')
            || ($alcada === NivelAlcadaComercial::Consultor)
            || ($alcada === NivelAlcadaComercial::Coordenacao && $autor->hasAnyRole(['admin', 'coordenador']))
            || ($alcada === NivelAlcadaComercial::Diretoria && $autor->hasRole('admin'));

        if ($podeAutoAprovar) {
            $proposta->status = $alcada === NivelAlcadaComercial::Consultor
                ? StatusPropostaComercial::AprovadaAutomatica
                : StatusPropostaComercial::Aprovada;
            $proposta->aprovado_por_user_id = $autor->id;
            $proposta->aprovado_em = now();
        } else {
            $proposta->status = StatusPropostaComercial::AguardandoAprovacao;
            $this->notificarAprovadores($proposta);
        }

        $proposta->save();

        return $proposta;
    }

    /**
     * Aprova formalmente a proposta comercial através da alçada competente.
     */
    public function aprovar(PropostaComercial $proposta, User $aprovador, ?string $observacao = null): bool
    {
        if (! $proposta->podeSerAprovadaPor($aprovador)) {
            throw new \DomainException('Você não possui nível de alçada suficiente para aprovar esta proposta.');
        }

        $proposta->status = StatusPropostaComercial::Aprovada;
        $proposta->aprovado_por_user_id = $aprovador->id;
        $proposta->aprovado_em = now();
        if ($observacao) {
            $proposta->observacoes = ($proposta->observacoes ? $proposta->observacoes."\n" : '').'[Aprovação]: '.$observacao;
        }
        $proposta->save();

        // Notifica o solicitante original
        if ($proposta->solicitante) {
            Notification::make()
                ->title('Proposta Comercial Aprovada!')
                ->success()
                ->body("A proposta {$proposta->codigo} para {$proposta->responsavel_nome} foi aprovada por {$aprovador->name}.")
                ->sendToDatabase($proposta->solicitante);
        }

        return true;
    }

    /**
     * Recusa a proposta comercial com motivo obrigatório.
     */
    public function recusar(PropostaComercial $proposta, User $aprovador, string $motivo): bool
    {
        $proposta->status = StatusPropostaComercial::Recusada;
        $proposta->aprovado_por_user_id = $aprovador->id;
        $proposta->motivo_recusa = $motivo;
        $proposta->save();

        // Notifica o consultor solicitante
        if ($proposta->solicitante) {
            Notification::make()
                ->title('Proposta Comercial Recusada')
                ->danger()
                ->body("A proposta {$proposta->codigo} para {$proposta->responsavel_nome} foi recusada. Motivo: {$motivo}")
                ->sendToDatabase($proposta->solicitante);
        }

        return true;
    }

    /**
     * Dispara notificação no painel para os usuários com perfil de aprovação da alçada.
     */
    private function notificarAprovadores(PropostaComercial $proposta): void
    {
        try {
            $rolesAlvo = match ($proposta->nivel_alcada_necessario) {
                NivelAlcadaComercial::Coordenacao => ['admin', 'coordenador', 'secretaria'],
                NivelAlcadaComercial::Diretoria => ['admin', 'super_admin'],
                default => ['admin'],
            };

            $aprovadores = User::role($rolesAlvo)->where('is_active', true)->get();

            foreach ($aprovadores as $aprovador) {
                Notification::make()
                    ->title('Proposta Pendente de Aprovação')
                    ->warning()
                    ->body("Proposta {$proposta->codigo} ({$proposta->responsavel_nome}): Desconto de {$proposta->desconto_solicitado}% requer alçada {$proposta->nivel_alcada_necessario->getLabel()}.")
                    ->sendToDatabase($aprovador);
            }
        } catch (\Throwable $e) {
            // Não bloqueia a criação da proposta se roles não estiverem criadas
        }
    }

    /**
     * Gera mensagem formatada para envio da proposta comercial via WhatsApp.
     */
    public function gerarTextoWhatsapp(PropostaComercial $proposta): string
    {
        $unidadeNome = $proposta->unidade?->nome ?? 'Nossa Escola';
        $cursoNome = $proposta->curso?->nome ?? '';
        $serieNome = $proposta->serie?->nome ?? '';
        $validade = $proposta->validade?->format('d/m/Y') ?? '5 dias';

        $valorDe = number_format((float) $proposta->valor_tabela_mensal, 2, ',', '.');
        $valorPor = number_format((float) $proposta->valor_liquido_mensal, 2, ',', '.');
        $valorAnual = number_format((float) $proposta->valor_total_anual, 2, ',', '.');

        $descontoTexto = $proposta->tipo_desconto === 'percentual'
            ? number_format((float) $proposta->desconto_solicitado, 1, ',', '.').'%'
            : 'R$ '.number_format((float) $proposta->desconto_solicitado, 2, ',', '.');

        return "Olá, {$proposta->responsavel_nome}! Tudo bem? 🎓\n\n"
            ."Preparamos uma condição comercial especial para a matrícula na *{$unidadeNome}*:\n\n"
            ."📌 *Proposta Oficial:* {$proposta->codigo}\n"
            ."📚 *Segmento:* {$cursoNome} - {$serieNome}\n"
            .'👦 *Estudante:* '.($proposta->aluno_nome ?: 'Seu filho(a)')."\n\n"
            ."💰 *Condições Financeiras:*\n"
            ."• Mensalidade de Tabela: R$ {$valorDe}\n"
            ."• Desconto Especial Aplicado: {$descontoTexto}\n"
            ."• *Mensalidade com Desconto:* 👉 *R$ {$valorPor}*\n"
            ."• Plano: {$proposta->quantidade_parcelas} parcelas (Total Anual: R$ {$valorAnual})\n\n"
            ."⏳ *Validade da Proposta:* Esta condição exclusiva é válida até *{$validade}*.\n\n"
            .'Ficamos à disposição para dar o próximo passo e garantir a vaga!';
    }
}
