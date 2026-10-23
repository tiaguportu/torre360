<?php

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\NotificacaoConselhoTutelar;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FaltasConselhoTutelarService
{
    /**
     * Limite LDB art. 12, VIII: 30% do limite tolerado de 25% de faltas = 7.5% das aulas do ano.
     */
    public const PERCENTUAL_GATILHO_CONSELHO_TUTELAR = 7.5;

    /**
     * Limite preventivo interno para acompanhamento pedagógico: 5.0% das aulas do ano.
     */
    public const PERCENTUAL_ALERTA_PREVENTIVO = 5.0;

    /**
     * Limite LDB art. 24, VI para reprovação por infrequência (abaixo de 75% de frequência = 25% de faltas).
     */
    public const PERCENTUAL_REPROVACAO_FALTAS = 25.0;

    /**
     * Analisa o histórico de infrequência da matrícula em relação ao total do ano letivo.
     */
    public function calcularAtingimentoLimite(Matricula $matricula): array
    {
        $matricula->loadMissing(['pessoa', 'turma.serie.curso']);
        $turma = $matricula->turma;

        // Total de aulas previstas no ano para a turma
        $totalAulasPrevistas = CronogramaAula::where('turma_id', $turma->id)->count();

        // Se o cronograma do ano ainda não estiver todo lançado, estima com base em 200 dias letivos x 4 aulas/dia = 800
        if ($totalAulasPrevistas <= 0) {
            $totalAulasPrevistas = 800;
        }

        $frequencias = FrequenciaEscolar::where('matricula_id', $matricula->id)->get();

        $presencas = $frequencias->where('situacao', 'presente')->count();
        $faltasTotais = $frequencias->where('situacao', 'ausente')->count();
        $faltasJustificadas = $frequencias->where('situacao', 'ausente')->where('justificada', true)->count();
        $faltasInjustificadas = $faltasTotais - $faltasJustificadas;

        $percentualFaltas = round(($faltasTotais / $totalAulasPrevistas) * 100, 2);
        $percentualFaltasInjustificadas = round(($faltasInjustificadas / $totalAulasPrevistas) * 100, 2);

        $atingiuGatilhoConselho = $percentualFaltas >= self::PERCENTUAL_GATILHO_CONSELHO_TUTELAR;
        $atingiuAlertaPreventivo = $percentualFaltas >= self::PERCENTUAL_ALERTA_PREVENTIVO;
        $emRiscoReprovacao = $percentualFaltas >= self::PERCENTUAL_REPROVACAO_FALTAS;

        return [
            'matricula_id' => $matricula->id,
            'aluno_nome' => $matricula->pessoa?->nome ?? 'Aluno',
            'turma_id' => $turma->id,
            'turma_nome' => $turma->nome,
            'total_aulas_previstas' => $totalAulasPrevistas,
            'presencas' => $presencas,
            'faltas_totais' => $faltasTotais,
            'faltas_justificadas' => $faltasJustificadas,
            'faltas_injustificadas' => $faltasInjustificadas,
            'percentual_faltas' => $percentualFaltas,
            'percentual_injustificadas' => $percentualFaltasInjustificadas,
            'atingiu_alerta_preventivo' => $atingiuAlertaPreventivo,
            'atingiu_gatilho_conselho' => $atingiuGatilhoConselho,
            'em_risco_reprovacao' => $emRiscoReprovacao,
        ];
    }

    /**
     * Identifica todos os alunos de uma turma que ultrapassaram o gatilho de comunicação ao Conselho Tutelar.
     */
    public function identificarAlunosEmRisco(Turma $turma): Collection
    {
        $matriculas = $turma->matriculas()->where('situacao', 'ativa')->with('pessoa')->get();
        $emRisco = collect();

        foreach ($matriculas as $matricula) {
            $dados = $this->calcularAtingimentoLimite($matricula);
            if ($dados['atingiu_gatilho_conselho']) {
                $emRisco->push($dados);
            }
        }

        return $emRisco;
    }

    /**
     * Cria ou atualiza uma notificação formal ao Conselho Tutelar (FICAI) para a matrícula.
     */
    public function gerarNotificacao(Matricula $matricula, User $criador, array $dadosAdicionais = []): NotificacaoConselhoTutelar
    {
        $calculo = $this->calcularAtingimentoLimite($matricula);
        $ano = now()->year;

        // Se já existe uma notificação em análise no mesmo ano, atualiza os dados
        $notificacao = NotificacaoConselhoTutelar::where('matricula_id', $matricula->id)
            ->where('ano_letivo', $ano)
            ->whereIn('status', ['em_analise', 'notificado'])
            ->first();

        if ($notificacao) {
            $notificacao->update([
                'total_aulas_previstas' => $calculo['total_aulas_previstas'],
                'total_presencas' => $calculo['presencas'],
                'total_faltas' => $calculo['faltas_totais'],
                'total_faltas_justificadas' => $calculo['faltas_justificadas'],
                'total_faltas_injustificadas' => $calculo['faltas_injustificadas'],
                'percentual_faltas' => $calculo['percentual_faltas'],
            ]);

            return $notificacao;
        }

        return NotificacaoConselhoTutelar::create([
            'matricula_id' => $matricula->id,
            'turma_id' => $matricula->turma_id,
            'ano_letivo' => $ano,
            'numero_oficio' => NotificacaoConselhoTutelar::gerarNumeroOficio(),
            'total_aulas_previstas' => $calculo['total_aulas_previstas'],
            'total_presencas' => $calculo['presencas'],
            'total_faltas' => $calculo['faltas_totais'],
            'total_faltas_justificadas' => $calculo['faltas_justificadas'],
            'total_faltas_injustificadas' => $calculo['faltas_injustificadas'],
            'percentual_faltas' => $calculo['percentual_faltas'],
            'tentativas_contato' => [],
            'status' => 'em_analise',
            'nome_conselho_tutelar' => $dadosAdicionais['nome_conselho_tutelar'] ?? 'Conselho Tutelar Municipal',
            'municipio_conselho' => $dadosAdicionais['municipio_conselho'] ?? null,
            'observacoes' => $dadosAdicionais['observacoes'] ?? null,
            'criado_por_user_id' => $criador->id,
        ]);
    }

    /**
     * Registra uma tentativa de contato prévia com a família para comprovar esgotamento dos recursos escolares (ECA art. 56, II).
     */
    public function registrarTentativaContato(NotificacaoConselhoTutelar $notificacao, array $tentativa, User $usuario): void
    {
        $historico = $notificacao->tentativas_contato ?? [];

        $historico[] = [
            'data' => $tentativa['data'] ?? now()->toDateString(),
            'meio' => $tentativa['meio'] ?? 'telefone', // telefone, whatsapp, e-mail, reuniao, visita
            'responsavel_contatado' => $tentativa['responsavel_contatado'] ?? 'Responsável',
            'resultado' => $tentativa['resultado'] ?? '',
            'registrado_por' => $usuario->name,
            'registrado_em' => now()->toIso8601String(),
        ];

        $notificacao->update([
            'tentativas_contato' => $historico,
        ]);
    }

    /**
     * Registra o protocolo de entrega do ofício no Conselho Tutelar.
     */
    public function registrarProtocoloEnvio(
        NotificacaoConselhoTutelar $notificacao,
        string $protocolo,
        ?string $dataNotificacao = null,
        ?string $nomeConselho = null
    ): void {
        $notificacao->update([
            'status' => 'notificado',
            'protocolo_conselho' => $protocolo,
            'data_notificacao' => $dataNotificacao ? Carbon::parse($dataNotificacao) : now(),
            'nome_conselho_tutelar' => $nomeConselho ?: $notificacao->nome_conselho_tutelar,
        ]);
    }

    /**
     * Monta o texto oficial do ofício / Ficha de Comunicação de Aluno Infrequente (FICAI).
     */
    public function gerarTextoOficio(NotificacaoConselhoTutelar $notificacao): string
    {
        $notificacao->loadMissing(['matricula.pessoa', 'turma.serie.curso']);
        $aluno = $notificacao->matricula?->pessoa;
        $turma = $notificacao->turma;
        $hojeFormatado = now()->translatedFormat('d \d\e F \d\e Y');

        $tentativasTexto = '';
        if (! empty($notificacao->tentativas_contato)) {
            foreach ($notificacao->tentativas_contato as $i => $t) {
                $idx = $i + 1;
                $dataT = Carbon::parse($t['data'])->format('d/m/Y');
                $meio = ucfirst($t['meio'] ?? 'contato');
                $tentativasTexto .= "  {$idx}. Data: {$dataT} | Meio: {$meio} | Contatado: {$t['responsavel_contatado']} | Resultado: {$t['resultado']}\n";
            }
        } else {
            $tentativasTexto = "  Nenhuma tentativa formal registrada previamente.\n";
        }

        $nascimentoFormatado = $aluno?->data_nascimento
            ? Carbon::parse($aluno->data_nascimento)->format('d/m/Y')
            : 'Não informada';

        return <<<TEXTO
OFÍCIO DE COMUNICAÇÃO DE INFREQUÊNCIA ESCOLAR (FICAI)
Ref.: Notificação nº {$notificacao->numero_oficio}

Ao Ilustríssimo Conselho Tutelar de {$notificacao->municipio_conselho}

Em cumprimento ao Artigo 12, inciso VIII da Lei de Diretrizes e Bases da Educação Nacional (LDB - Lei nº 9.394/1996, com redação dada pela Lei nº 13.803/2019) e ao Artigo 56, inciso II da Lei Federal nº 8.069/1990 (Estatuto da Criança e do Adolescente - ECA), vimos por meio deste NOTIFICAR a reiteração de faltas injustificadas do estudante abaixo qualificado, informando que os recursos pedagógicos e escolares ao nosso alcance foram devidamente empreendidos sem o retorno desejado.

1. DADOS DO ESTUDANTE:
   Nome: {$aluno?->nome}
   Data de Nascimento: {$nascimentoFormatado}
   Turma: {$turma?->nome} ({$turma?->serie?->nome} - {$turma?->serie?->curso?->nome_externo})
   Ano Letivo: {$notificacao->ano_letivo}

2. APURAÇÃO DA FREQUÊNCIA ESCOLAR:
   Total de Aulas Previstas no Ano: {$notificacao->total_aulas_previstas}
   Total de Presenças Registradas: {$notificacao->total_presencas}
   Total de Ausências/Faltas Acumuladas: {$notificacao->total_faltas}
   Faltas Justificadas: {$notificacao->total_faltas_justificadas}
   Faltas Injustificadas: {$notificacao->total_faltas_injustificadas}
   Percentual de Faltas: {$notificacao->percentual_faltas}% (Gatilho Legal da LDB: 7.5% das aulas do ano)

3. MEDIDAS ADOTADAS PELA INSTITUIÇÃO DE ENSINO (RECURSOS ESCOLARES ESGOTADOS):
{$tentativasTexto}

Diante da gravidade da situação e do direito fundamental à educação garantido constitucionalmente, encaminhamos o presente para as providências tutelares e de proteção que esse nobre Órgão julgar pertinentes.

Local e Data: {$hojeFormatado}

___________________________________________________
DIREÇÃO E COORDENAÇÃO PEDAGÓGICA
Colégio Torre360
TEXTO;
    }
}
