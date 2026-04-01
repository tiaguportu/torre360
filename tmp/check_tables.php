<?php

use Illuminate\Support\Facades\Schema;

$tables = [
    'pais', 'estado', 'cidade', 'endereco', 'sexo', 'cor_raca', 'perfil', 'pessoa_perfil',
    'pessoa', 'unidade', 'periodo_letivo', 'dia_nao_letivo', 'curso', 'documento_obrigatorio',
    'serie', 'turno', 'turma', 'situacao_matricula', 'matricula', 'etapa_avaliativa',
    'avaliacao', 'nota', 'area_conhecimento', 'disciplina', 'contrato', 'responsavel_financeiro',
    'titulo', 'cronograma_aula', 'coordenador', 'tributacao_curso', 'habilidade'
];

foreach ($tables as $table) {
    echo $table . ': ' . (Schema::hasTable($table) ? 'OK' : 'MISSING') . PHP_EOL;
}
