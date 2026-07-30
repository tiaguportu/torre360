<?php

namespace App\Services\Educacenso;

use App\Models\Turma;
use Illuminate\Support\Collection;

class EducacensoTurmaExporter
{
    /**
     * Exporta uma coleção de turmas no formato Registro 20 do Educacenso (INEP).
     *
     * @param  Collection<int, Turma>  $turmas
     */
    public function export(Collection $turmas): string
    {
        $turmas->each(function (Turma $turma) {
            $turma->loadMissing([
                'serie.curso.unidade.instituicaoEnsino',
                'etapaEnsino',
                'etapaEnsinoAgregada',
                'horariosFuncionamento',
            ]);
        });

        $lines = [];

        foreach ($turmas as $turma) {
            $lines[] = $this->buildRegistro20Line($turma);
        }

        return implode("\r\n", $lines);
    }

    /**
     * Extrai o código numérico inicial quando a string for "1 - Descrição" ou mapeia descrições para seus códigos INEP.
     */
    private function extractCode(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $str = trim((string) $value);

        if (str_contains($str, '-')) {
            $parts = explode('-', $str);
            $candidate = trim($parts[0]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $map = [
            'presencial' => '1',
            'semipresencial' => '2',
            'ead' => '3',
            'educacao a distancia - ead' => '3',
            'educação a distância – ead' => '3',
            'atividade complementar' => '4',
            'atendimento educacional especializado (aee)' => '5',
            'aee' => '5',
            'curricular' => '6',
            'curricular (etapa de ensino)' => '6',
            'curricular (etapa de ensino) com atividade complementar' => '9',
            'curricular c/ ativ. comp.' => '9',
            'regular' => '1',
            'ensino regular' => '1',
            'especial' => '2',
            'educação especial' => '2',
            'eja' => '3',
            'educação de jovens e adultos (eja)' => '3',
            'profissional' => '4',
            'educação profissional' => '4',
            'somente em língua portuguesa' => '1',
            'português' => '1',
            'em língua indígena e língua portuguesa' => '2',
            'indígena + português' => '2',
            'somente em língua indígena' => '3',
            'indígena' => '3',
            'a turma não está em local de funcionamento diferenciado' => '0',
            'sala anexa' => '1',
            'unidade de atendimento socioeducativo' => '2',
            'unidade prisional' => '3',
            'série/ano' => '1',
            'série/ano (série anual)' => '1',
            'períodos semestrais' => '2',
            'semestral' => '2',
            'ciclos' => '3',
            'grupos não seriados' => '4',
            'grupos não seriados com base na idade ou competência' => '4',
            'módulos' => '5',
            'alternância' => '6',
            'alternância regular de períodos de estudos' => '6',
        ];

        $lower = mb_strtolower($str);

        return $map[$lower] ?? $str;
    }

    /**
     * Mapeia qualquer código de sub-etapa/etapa de ensino para uma Etapa Agregada válida do INEP (301, 302, 304, etc.).
     */
    private function resolveEtapaAgregada(mixed $etapaRaw, Turma $turma): string
    {
        $code = $this->extractCode($turma->etapaEnsinoAgregada?->codigo ?? $etapaRaw);

        if (empty($code)) {
            return '301';
        }

        if (is_numeric($code) && (int) $code >= 300) {
            return (string) $code;
        }

        $num = (int) $code;
        if (in_array($num, [1, 2, 3])) {
            return '301';
        }
        if ($num >= 14 && $num <= 24) {
            return '302';
        }
        if ($num >= 25 && $num <= 38) {
            return '304';
        }
        if ($num >= 65 && $num <= 74) {
            return '305';
        }

        return (string) $code;
    }

    /**
     * Constrói a linha no formato Registro 20 para uma turma específica com a ordem exata de 66 campos do Educacenso.
     */
    public function buildRegistro20Line(Turma $turma): string
    {
        $unidade = $turma->serie?->curso?->unidade;
        $instituicao = $unidade?->instituicaoEnsino;
        $codigoInepEscola = $this->extractCode($unidade?->codigo_inep ?? $instituicao?->codigo_inep);

        // 1. Tipo de registro
        $f1 = '20';

        // 2. Código INEP da Escola
        $f2 = $codigoInepEscola;

        // 3. Código da turma na escola (ID local)
        $f3 = (string) ($turma->codigo ?? $turma->id);

        // 4. Código INEP da turma (se houver)
        $f4 = $this->extractCode($turma->codigo_inep ?? '');

        // 5. Nome da turma (até 80 caracteres)
        $f5 = mb_substr(trim((string) ($turma->nome ?? '')), 0, 80);

        // 6. Tipo de mediação didático-pedagógica (1: Presencial, 2: Semipresencial, 3: EAD)
        $mediacaoCode = $this->extractCode($turma->tipo_mediacao_didatico_pedagogica ?? '1');
        $mediacao = is_numeric($mediacaoCode) ? (int) $mediacaoCode : 1;
        $f6 = (string) $mediacao;

        // Horários e Dias da Semana
        $horarios = $turma->horariosFuncionamento;
        $diasSemana = $horarios ? $horarios->pluck('dia_semana') : collect();

        // Campos 7 a 13: Dias da semana (Domingo a Sábado). Devem ser 0 ou 1 QUANDO mediação == 1, senão NULOS ("")
        if ($mediacao === 1) {
            $f7 = $diasSemana->contains(0) ? '1' : '0';
            $f8 = $diasSemana->contains(1) ? '1' : '0';
            $f9 = $diasSemana->contains(2) ? '1' : '0';
            $f10 = $diasSemana->contains(3) ? '1' : '0';
            $f11 = $diasSemana->contains(4) ? '1' : '0';
            $f12 = $diasSemana->contains(5) ? '1' : '0';
            $f13 = $diasSemana->contains(6) ? '1' : '0';
        } else {
            $f7 = $f8 = $f9 = $f10 = $f11 = $f12 = $f13 = '';
        }

        // 14. Tipo de turma (4, 5, 6 ou 9)
        $tipoTurmaCode = $this->extractCode($turma->tipo_turma ?? '6');
        $tipoTurma = is_numeric($tipoTurmaCode) ? (int) $tipoTurmaCode : 6;
        if (! in_array($tipoTurma, [4, 5, 6, 9])) {
            $tipoTurma = 6;
        }
        $f14 = (string) $tipoTurma;

        // Campos 15 a 20: Tipo de atividade complementar (Código 1 a 6)
        if (in_array($tipoTurma, [4, 9])) {
            $f15 = $this->extractCode($turma->atividade_complementar_1 ?? '');
            $f16 = $this->extractCode($turma->atividade_complementar_2 ?? '');
            $f17 = $this->extractCode($turma->atividade_complementar_3 ?? '');
            $f18 = $this->extractCode($turma->atividade_complementar_4 ?? '');
            $f19 = $this->extractCode($turma->atividade_complementar_5 ?? '');
            $f20 = $this->extractCode($turma->atividade_complementar_6 ?? '');
            if ($f15 === '' && $f16 === '' && $f17 === '' && $f18 === '' && $f19 === '' && $f20 === '') {
                $f15 = '1';
            }
        } else {
            $f15 = $f16 = $f17 = $f18 = $f19 = $f20 = '';
        }

        // 21. Local de funcionamento diferenciado da turma (0, 1, 2 ou 3)
        $f21 = $this->extractCode($turma->local_funcionamento_diferenciado ?? '0');
        if (! in_array($f21, ['0', '1', '2', '3'])) {
            $f21 = '0';
        }

        // 22. Turma de Educação Especial (0 ou 1 quando Tipo de turma for 6 ou 9, senão NULO "")
        if (in_array($tipoTurma, [6, 9])) {
            $f22 = $turma->turma_educacao_especial ? '1' : '0';
        } else {
            $f22 = '';
        }

        // 23. Etapa agregada (Código INEP de Etapa Agregada quando Tipo de turma for 6 ou 9, senão NULO "")
        $etapaInepRaw = $turma->etapaEnsino?->codigo ?? $turma->etapaEnsinoAgregada?->codigo ?? $turma->etapa_ensino_id ?? '';

        if (in_array($tipoTurma, [6, 9])) {
            $f23 = $this->resolveEtapaAgregada($etapaInepRaw, $turma);
        } else {
            $f23 = '';
        }

        // 24. Forma de organização da turma (1 a 6 ou nulo)
        $formaOrg = $this->extractCode($turma->forma_organizacao ?? '');
        $f24 = $formaOrg !== '' ? $formaOrg : '1';

        // 25. Código do eixo do curso de qualificação profissional
        $f25 = in_array($f23, ['67', '68', '73', '75']) ? $this->extractCode($turma->codigo_eixo_qualificacao ?? '') : '';

        // 26. Código do curso da Educação Profissional
        $f26 = in_array($f23, ['39', '40', '64', '74']) ? $this->extractCode($turma->codigo_curso_profissional ?? '') : '';

        // 27. Carga horária total do curso
        $f27 = '';

        // 28. Carga horária total da turma (em horas)
        $f28 = $turma->carga_horaria_total !== null ? (string) $turma->carga_horaria_total : '';

        // 29. Turma de Formação por Alternância (0 ou 1)
        $f29 = ($f24 === '6' || (bool) $turma->formacao_alternancia) ? '1' : '0';

        // Campos 30 a 32: Organização curricular (Formação geral básica, Itinerário formativo, IFTP)
        if (in_array($f23, ['304', '305'])) {
            $f30 = $turma->formacao_geral_basica ? '1' : '1';
            $f31 = $turma->itinerario_formativo ? '1' : '0';
            $f32 = $turma->itinerario_tecnico ? '1' : '0';
        } else {
            $f30 = $f31 = $f32 = '';
        }

        // Campos 33 a 36: Áreas do itinerário formativo (Linguagens, Matemática, Ciências Natureza, Ciências Humanas)
        if ($f31 === '1') {
            $f33 = $turma->ifa_linguagens ? '1' : '0';
            $f34 = $turma->ifa_matematica ? '1' : '0';
            $f35 = $turma->ifa_ciencias_natureza ? '1' : '0';
            $f36 = $turma->ifa_ciencias_humanas ? '1' : '0';
            if ($f33 === '0' && $f34 === '0' && $f35 === '0' && $f36 === '0') {
                $f33 = '1';
            }
        } else {
            $f33 = $f34 = $f35 = $f36 = '';
        }

        // 37. Modalidade de ensino (1: Regular, 2: Especial, 3: EJA, 4: Profissional)
        $f37 = $this->extractCode($turma->modalidade_ensino ?? '1');

        // 38. Código do curso técnico (quando IFTP == 1, senão NULO "")
        $f38 = ($f32 === '1') ? $this->extractCode($turma->codigo_curso_tecnico ?? '') : '';

        // Campos 39 a 65: Disciplinas / Áreas de conhecimento
        // DEVE SER 0 OU 1 QUANDO ETAPA NÃO FOR 1, 2 OU 3 (Educação Infantil/Creche/Pré-Escola/301), SENÃO DEVE SER NULO ("")
        $isEdInfantil = in_array($f23, ['301', '1', '2', '3']);

        if ($isEdInfantil) {
            // Educação Infantil / Creche / Pré-escola não possui disciplinas -> NULOS ("")
            $f39 = $f40 = $f41 = $f42 = $f43 = $f44 = $f45 = $f46 = $f47 = $f48 = $f49 = $f50 = $f51 = $f52 = $f53 = $f54 = $f55 = $f56 = $f57 = $f58 = $f59 = $f60 = $f61 = $f62 = $f63 = $f64 = $f65 = '';
        } else {
            // Para Ensino Fundamental, Ensino Médio, EJA -> 0 ou 1
            $f39 = $turma->disc_quimica ? '1' : '0';
            $f40 = $turma->disc_fisica ? '1' : '0';
            $f41 = $turma->disc_matematica ? '1' : '0';
            $f42 = $turma->disc_biologia ? '1' : '0';
            $f43 = $turma->disc_ciencias ? '1' : '0';
            $f44 = $turma->disc_portugues ? '1' : '1';
            $f45 = $turma->disc_ingles ? '1' : '0';
            $f46 = $turma->disc_espanhol ? '1' : '0';
            $f47 = $turma->disc_outra_lingua ? '1' : '0';
            $f48 = $turma->disc_arte ? '1' : '0';
            $f49 = $turma->disc_educacao_fisica ? '1' : '0';
            $f50 = $turma->disc_historia ? '1' : '0';
            $f51 = $turma->disc_geografia ? '1' : '0';
            $f52 = $turma->disc_filosofia ? '1' : '0';
            $f53 = $turma->disc_informatica ? '1' : '0';
            $f54 = $turma->disc_profissionalizante ? '1' : '0';
            $f55 = $turma->disc_libras ? '1' : '0';
            $f56 = $turma->disc_pedagogica ? '1' : '0';
            $f57 = $turma->disc_ensino_religioso ? '1' : '0';
            $f58 = $turma->disc_lingua_indigena ? '1' : '0';
            $f59 = $turma->disc_estudos_sociais ? '1' : '0';
            $f60 = $turma->disc_sociologia ? '1' : '0';
            $f61 = $turma->disc_frances ? '1' : '0';
            $f62 = $turma->disc_portugues_segunda_lingua ? '1' : '0';
            $f63 = $turma->disc_estagio_supervisionado ? '1' : '0';
            $f64 = $turma->disc_projeto_vida ? '1' : '0';
            $f65 = $turma->disc_outras ? '1' : '0';
        }

        // 66. Turma de Educação Bilíngue de Surdos (0 ou 1)
        $f66 = $turma->turma_educacao_bilingue_surdos ? '1' : '0';

        $fields = [
            $f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9, $f10,
            $f11, $f12, $f13, $f14, $f15, $f16, $f17, $f18, $f19, $f20,
            $f21, $f22, $f23, $f24, $f25, $f26, $f27, $f28, $f29, $f30,
            $f31, $f32, $f33, $f34, $f35, $f36, $f37, $f38, $f39, $f40,
            $f41, $f42, $f43, $f44, $f45, $f46, $f47, $f48, $f49, $f50,
            $f51, $f52, $f53, $f54, $f55, $f56, $f57, $f58, $f59, $f60,
            $f61, $f62, $f63, $f64, $f65, $f66,
        ];

        return implode('|', $fields);
    }
}
