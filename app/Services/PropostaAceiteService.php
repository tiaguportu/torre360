<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NivelAlcadaComercial;
use App\Enums\StatusBolsa;
use App\Enums\StatusPropostaComercial;
use App\Models\BolsaConcedida;
use App\Models\Matricula;
use App\Models\PropostaComercial;
use App\Models\TipoBolsa;
use App\Models\User;
use App\Notifications\PropostaComercialNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ciclo da proposta comercial depois da aprovação (Onda 87): link público, aceite da família, expiração, renovação e a passagem
 * do desconto negociado para o contrato (como bolsa), que antes não acontecia.
 *
 * O aceite é intenção, não contrato: o contrato continua nascendo da matrícula, com a assinatura de sempre.
 */
class PropostaAceiteService
{
    public const TIPO_BOLSA_COMERCIAL = 'Desconto comercial';

    /**
     * Gera (uma vez) o link público da proposta. Só proposta já aprovada tem link.
     *
     * @throws \DomainException
     */
    public function gerarLink(PropostaComercial $proposta): string
    {
        if (! $proposta->visivelPelaFamilia() || $proposta->status === StatusPropostaComercial::Expirada) {
            throw new \DomainException('Só uma proposta aprovada e dentro do prazo tem link para a família. Aprove ou renove antes.');
        }

        $proposta->forceFill([
            'token_publico' => $proposta->token_publico ?: Str::random(48),
            'enviada_em' => $proposta->enviada_em ?? now(),
        ])->save();

        return (string) $this->urlPublica($proposta);
    }

    public function urlPublica(PropostaComercial $proposta): ?string
    {
        return $proposta->token_publico ? route('proposta.publica.show', ['token' => $proposta->token_publico]) : null;
    }

    public function buscarPorToken(string $token): ?PropostaComercial
    {
        if (strlen($token) < 40) {
            return null;
        }

        return PropostaComercial::query()->where('token_publico', $token)->first();
    }

    public function registrarVisualizacao(PropostaComercial $proposta): void
    {
        $proposta->forceFill([
            'visualizada_em' => $proposta->visualizada_em ?? now(),
            'visualizacoes' => (int) $proposta->visualizacoes + 1,
        ])->saveQuietly();
    }

    /**
     * Aceite da família: grava quando, de que IP e quem digitou o nome, muda a situação e avisa o consultor. Aceitar de novo
     * uma proposta já aceita não faz nada (o link pode ser aberto duas vezes).
     *
     * @throws \DomainException proposta que não pode mais ser aceita
     */
    public function aceitar(PropostaComercial $proposta, string $nome, ?string $ip): PropostaComercial
    {
        $nome = trim($nome);

        if (mb_strlen($nome) < 5) {
            throw new \DomainException('Digite o nome completo de quem está aceitando.');
        }

        $aceitou = DB::transaction(function () use ($proposta, $nome, $ip): bool {
            $atual = PropostaComercial::query()->lockForUpdate()->findOrFail($proposta->id);

            if ($atual->status === StatusPropostaComercial::AceitaPelaFamilia) {
                return false;
            }

            if (! $atual->aceitavel()) {
                throw new \DomainException($atual->status === StatusPropostaComercial::Convertida
                    ? 'Esta proposta já virou matrícula.'
                    : 'Esta proposta não está mais disponível para aceite. Fale com a escola.');
            }

            $atual->forceFill([
                'status' => StatusPropostaComercial::AceitaPelaFamilia,
                'aceita_em' => now(),
                'aceite_nome' => mb_substr($nome, 0, 120),
                'aceite_ip' => $ip,
            ])->save();

            return true;
        });

        $proposta->refresh();

        if ($aceitou) {
            $proposta->solicitante?->notify(new PropostaComercialNotification($proposta->id, PropostaComercialNotification::ACEITA));
        }

        return $proposta;
    }

    /**
     * Marca como expirada a proposta aprovada que passou da validade. A que a família já aceitou não expira sozinha: a escola
     * prometeu a condição e quem cobra a matrícula é o consultor (fila "Propostas a vencer").
     *
     * @return int quantidade de propostas expiradas
     */
    public function expirarVencidas(): int
    {
        $total = 0;

        PropostaComercial::query()
            ->whereIn('status', [StatusPropostaComercial::Aprovada->value, StatusPropostaComercial::AprovadaAutomatica->value])
            ->whereNotNull('validade')
            ->whereDate('validade', '<', today())
            ->with('solicitante')
            ->each(function (PropostaComercial $proposta) use (&$total): void {
                $proposta->forceFill(['status' => StatusPropostaComercial::Expirada, 'expirada_em' => now()])->save();
                $proposta->solicitante?->notify(new PropostaComercialNotification($proposta->id, PropostaComercialNotification::EXPIRADA));
                $total++;
            });

        return $total;
    }

    /**
     * Avisa o consultor 3 dias e 1 dia antes de a proposta vencer (uma vez cada).
     *
     * @return int quantidade de avisos enviados
     */
    public function avisarVencimentos(): int
    {
        $total = 0;

        foreach ([3 => 'avisada_3d_em', 1 => 'avisada_1d_em'] as $dias => $campo) {
            PropostaComercial::query()
                ->whereIn('status', [StatusPropostaComercial::Aprovada->value, StatusPropostaComercial::AprovadaAutomatica->value])
                ->whereDate('validade', today()->addDays($dias)->toDateString())
                ->whereNull($campo)
                ->with('solicitante')
                ->each(function (PropostaComercial $proposta) use ($campo, &$total): void {
                    $proposta->forceFill([$campo => now()])->saveQuietly();
                    $proposta->solicitante?->notify(new PropostaComercialNotification($proposta->id, PropostaComercialNotification::VENCENDO));
                    $total++;
                });
        }

        return $total;
    }

    /**
     * Renova a proposta expirada sem refazer a aprovação, desde que o valor de tabela da turma não tenha mudado. Se mudou, a
     * condição aprovada partiu de um preço que não existe mais: é preciso criar outra proposta.
     *
     * @throws \DomainException
     */
    public function renovar(PropostaComercial $proposta, User $por, ?int $dias = null): PropostaComercial
    {
        if ($proposta->status !== StatusPropostaComercial::Expirada) {
            throw new \DomainException('Só uma proposta expirada pode ser renovada.');
        }

        if ($proposta->aprovado_em === null) {
            throw new \DomainException('Esta proposta nunca foi aprovada. Envie para aprovação.');
        }

        $tabelaAtual = $proposta->turma?->mensalidade_base;

        if ($tabelaAtual !== null && round((float) $tabelaAtual, 2) !== round((float) $proposta->valor_tabela_mensal, 2)) {
            throw new \DomainException('A mensalidade de tabela da turma mudou desde a aprovação. Crie uma nova proposta.');
        }

        $proposta->forceFill([
            'status' => $proposta->nivel_alcada_necessario === NivelAlcadaComercial::Consultor
                ? StatusPropostaComercial::AprovadaAutomatica
                : StatusPropostaComercial::Aprovada,
            'validade' => today()->addDays($dias ?? (int) config('propostas.renovacao_dias', 5)),
            'expirada_em' => null,
            'avisada_3d_em' => null,
            'avisada_1d_em' => null,
        ])->save();

        activity()->performedOn($proposta)->causedBy($por)->log('Proposta renovada sem nova aprovação (tabela inalterada).');

        return $proposta->refresh();
    }

    /**
     * A matrícula nasceu da proposta: marca-a como convertida e leva o desconto negociado ao contrato como bolsa (a mesma que o
     * gerador de faturas já aplica). Com a proposta fora do prazo ou sem aprovação, a matrícula segue sem o desconto e o motivo
     * volta ao chamador, que avisa a pessoa. A bolsa guarda percentual inteiro: o desconto fixo é convertido e arredondado.
     *
     * @return array{bolsa: ?BolsaConcedida, arredondada: bool, motivo: ?string}
     */
    public function converter(PropostaComercial $proposta, Matricula $matricula): array
    {
        $statusQueConvertem = [
            StatusPropostaComercial::Aprovada,
            StatusPropostaComercial::AprovadaAutomatica,
            StatusPropostaComercial::AceitaPelaFamilia,
            StatusPropostaComercial::Convertida,
        ];

        if (! in_array($proposta->status, $statusQueConvertem, true)) {
            return ['bolsa' => null, 'arredondada' => false, 'motivo' => 'A proposta '.$proposta->codigo.' não está aprovada ('.$proposta->status->getLabel().'): a matrícula foi criada sem o desconto.'];
        }

        if ($proposta->status !== StatusPropostaComercial::Convertida && $proposta->status !== StatusPropostaComercial::AceitaPelaFamilia && ! $proposta->aceitavel()) {
            return ['bolsa' => null, 'arredondada' => false, 'motivo' => 'A proposta '.$proposta->codigo.' passou da validade: a matrícula foi criada sem o desconto.'];
        }

        $bolsa = null;
        $arredondada = false;

        DB::transaction(function () use ($proposta, $matricula, &$bolsa, &$arredondada): void {
            if ($proposta->status !== StatusPropostaComercial::Convertida) {
                $proposta->forceFill([
                    'status' => StatusPropostaComercial::Convertida,
                    'convertida_em' => now(),
                    'matricula_id' => $matricula->id,
                ])->save();
            }

            $exato = (float) $proposta->valor_tabela_mensal > 0
                ? ((float) $proposta->valor_desconto_mensal / (float) $proposta->valor_tabela_mensal) * 100
                : 0.0;
            $percentual = (int) min(100, round($exato));
            $arredondada = abs($exato - $percentual) > 0.005;

            if ($percentual <= 0) {
                return;
            }

            $existente = BolsaConcedida::query()
                ->where('matricula_id', $matricula->id)
                ->where('observacao', 'like', 'Proposta '.$proposta->codigo.'%')
                ->first();

            if ($existente) {
                $bolsa = $existente;

                return;
            }

            $tipo = TipoBolsa::firstOrCreate(
                ['nome' => self::TIPO_BOLSA_COMERCIAL],
                ['percentual_maximo' => 100, 'exige_aprovacao' => false, 'criterio_renovacao' => 'Condição comercial de uma proposta aprovada; não renova sozinha.'],
            );

            $inicio = today();

            $bolsa = BolsaConcedida::create([
                'matricula_id' => $matricula->id,
                'tipo_bolsa_id' => $tipo->id,
                'percentual' => $percentual,
                'data_inicio' => $inicio,
                'data_fim' => $inicio->copy()->addMonths(max(1, (int) $proposta->quantidade_parcelas))->subDay(),
                'status' => StatusBolsa::Aprovada,
                'aprovado_por_user_id' => $proposta->aprovado_por_user_id,
                'observacao' => 'Proposta '.$proposta->codigo.': desconto de '.number_format($exato, 2, ',', '.').'% sobre a tabela'
                    .($arredondada ? " (arredondado para {$percentual}%)" : '').'.',
            ]);
        });

        return ['bolsa' => $bolsa, 'arredondada' => $arredondada, 'motivo' => null];
    }
}
