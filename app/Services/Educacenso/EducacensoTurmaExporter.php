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
     * Constrói a linha no formato Registro 20 para uma turma específica.
     */
    public function buildRegistro20Line(Turma $turma): string
    {
        $unidade = $turma->serie?->curso?->unidade;
        $instituicao = $unidade?->instituicaoEnsino;
        $codigoInepEscola = $unidade?->codigo_inep ?? $instituicao?->codigo_inep ?? '';

        $horarios = $turma->horariosFuncionamento;
        $diasSemana = $horarios->pluck('dia_semana');

        $horaInicioMin = $horarios->whereNotNull('hora_inicio')->min('hora_inicio');
        $horaFimMax = $horarios->whereNotNull('hora_fim')->max('hora_fim');

        $horaInicioStr = $horaInicioMin ? substr((string) $horaInicioMin, 0, 5) : '';
        $horaFimStr = $horaFimMax ? substr((string) $horaFimMax, 0, 5) : '';

        $etapaInep = $turma->etapaEnsino?->codigo ?? $turma->etapaEnsinoAgregada?->codigo ?? '';

        $fields = [
            '20', // 1. Tipo de Registro
            $codigoInepEscola, // 2. Código INEP da Escola
            $turma->codigo ?? (string) $turma->id, // 3. Código da Turma na Escola
            '', // 4. Código INEP da Turma (se houver)
            $turma->nome ?? '', // 5. Nome da Turma
            $turma->tipo_mediacao_didatico_pedagogica !== null ? (string) $turma->tipo_mediacao_didatico_pedagogica : '', // 6. Tipo de Mediação
            $turma->tipo_turma !== null ? (string) $turma->tipo_turma : '', // 7. Tipo de Turma
            $horaInicioStr, // 8. Hora Inicial
            $horaFimStr, // 9. Hora Final
            $diasSemana->contains(0) ? '1' : '0', // 10. Domingo
            $diasSemana->contains(1) ? '1' : '0', // 11. Segunda-feira
            $diasSemana->contains(2) ? '1' : '0', // 12. Terça-feira
            $diasSemana->contains(3) ? '1' : '0', // 13. Quarta-feira
            $diasSemana->contains(4) ? '1' : '0', // 14. Quinta-feira
            $diasSemana->contains(5) ? '1' : '0', // 15. Sexta-feira
            $diasSemana->contains(6) ? '1' : '0', // 16. Sábado
            $turma->local_funcionamento_diferenciado !== null ? (string) $turma->local_funcionamento_diferenciado : '0', // 17. Local Diferenciado
            $turma->forma_organizacao !== null ? (string) $turma->forma_organizacao : '', // 18. Forma de Organização
            $turma->carga_horaria_total !== null ? (string) $turma->carga_horaria_total : '', // 19. Carga Horária Total
            $turma->turma_educacao_especial ? '1' : '0', // 20. Turma Educação Especial
            $turma->modalidade_ensino !== null ? (string) $turma->modalidade_ensino : '', // 21. Modalidade de Ensino
            $etapaInep, // 22. Etapa de Ensino (Código INEP)
            $turma->tipo_lingua_ministrada !== null ? (string) $turma->tipo_lingua_ministrada : '1', // 23. Língua Ministrada
            $turma->codigo_lingua_indigena ?? '', // 24. Código Língua Indígena
            $turma->turma_educacao_bilingue_surdos ? '1' : '0', // 25. Bilíngue de Surdos
            $turma->flag_aee_ensino_libras ? '1' : '0', // 26. AEE Libras
            $turma->flag_aee_ensino_soroba ? '1' : '0', // 27. AEE Braille/Sorobã
            '0', // 28. AEE Recursos Ópticos
            $turma->flag_aee_processos_cognitivos ? '1' : '0', // 29. AEE Processos Cognitivos
            $turma->flag_aee_orientacao_mobilidade ? '1' : '0', // 30. AEE Orientação e Mobilidade
            '0', // 31. AEE Vida Autônoma
            $turma->flag_aee_ensino_caa ? '1' : '0', // 32. AEE CAA
            $turma->flag_aee_enriquecimento_curricular ? '1' : '0', // 33. AEE Enriquecimento Curricular
            $turma->flag_aee_ensino_informatica_acessivel ? '1' : '0', // 34. AEE Informática Acessível
            $turma->flag_aee_portugues_segunda_lingua ? '1' : '0', // 35. AEE Português 2ª Língua
            $turma->flag_aee_tecnologia_assistiva ? '1' : '0', // 36. AEE Tecnologia Assistiva
            // Campos 37 a 66: Disciplinas / Áreas de Conhecimento / Cursos / Atividades Complementares
            '', // 37. Química
            '', // 38. Física
            '', // 39. Biologia
            '', // 40. Ciências
            '', // 41. Matemática
            '', // 42. Língua / Literatura Portuguesa
            '', // 43. Língua Estrangeira - Inglês
            '', // 44. Língua Estrangeira - Espanhol
            '', // 45. Língua Estrangeira - Francês
            '', // 46. Língua Estrangeira - Outra
            '', // 47. Arte (Artes Plásticas, Música, Teatro, Dança, etc.)
            '', // 48. Educação Física
            '', // 49. História
            '', // 50. Geografia
            '', // 51. Filosofia
            '', // 52. Ensino Religioso
            '', // 53. Estudos Sociais
            '', // 54. Sociologia
            '', // 55. Informática / Computação
            '', // 56. Disciplina Profissionalizante
            '', // 57. Libras
            '', // 58. Disciplinas Pedagógicas
            '', // 59. Outras Disciplinas
            '', // 60. Código do Curso Técnico
            '', // 61. Código do Curso FIC / Formação Inicial e Continuada
            '', // 62. Tipo de Atendimento Complementar
            '', // 63. Atividade Complementar 1
            '', // 64. Atividade Complementar 2
            '', // 65. Atividade Complementar 3
            '', // 66. Atividade Complementar 4
        ];

        return implode('|', $fields);
    }
}
