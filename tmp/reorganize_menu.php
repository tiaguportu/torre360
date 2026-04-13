<?php

$mapping = [
    // CRM / Comercial
    'InteressadoResource' => 'CRM / Comercial',

    // Acadêmico
    'MatriculaResource' => 'Acadêmico',
    'AlunoResource' => 'Acadêmico',
    'TurmaResource' => 'Acadêmico',
    'CursoResource' => 'Acadêmico',
    'SerieResource' => 'Acadêmico',
    'DisciplinaResource' => 'Acadêmico',
    'FrequenciaEscolarResource' => 'Acadêmico',
    'AreaConhecimentoResource' => 'Acadêmico',
    'SituacaoMatriculaResource' => 'Acadêmico',

    // Avaliações
    'AvaliacaoResource' => 'Avaliações',
    'NotaResource' => 'Avaliações',
    'EtapaAvaliativaResource' => 'Avaliações',
    'CategoriaAvaliacaoResource' => 'Avaliações',

    // Calendário e Horários
    'CronogramaAulaResource' => 'Calendário e Horários',
    'PeriodoLetivoResource' => 'Calendário e Horários',
    'DiaNaoLetivoResource' => 'Calendário e Horários',
    'TurnoResource' => 'Calendário e Horários',

    // Financeiro
    'ContratoResource' => 'Financeiro',
    'FaturaResource' => 'Financeiro',
    'TransacaoBancariaResource' => 'Financeiro',
    'PlanoContaResource' => 'Financeiro',
    'CentroCustoResource' => 'Financeiro',
    'BancoResource' => 'Financeiro',
    'FornecedorResource' => 'Financeiro',
    'TributacaoCursoResource' => 'Financeiro',
    'CodigoBacenResource' => 'Financeiro',

    // Pessoas
    'PessoaResource' => 'Pessoas',
    'ResponsavelFinanceiroResource' => 'Pessoas',
    'CoordenadorResource' => 'Pessoas',

    // Documentos
    'DocumentoInseridoResource' => 'Documentos',
    'TipoDocumentoResource' => 'Documentos',
    'SituacaoDocumentoInseridoResource' => 'Documentos',

    // Operacional
    'OrdemServicoResource' => 'Operacional',
    'CategoriaOsResource' => 'Operacional',

    // Localização e Cadastros
    'CidadeResource' => 'Localização e Cadastros',
    'EstadoResource' => 'Localização e Cadastros',
    'PaisResource' => 'Localização e Cadastros',
    'EnderecoResource' => 'Localização e Cadastros',
    'UnidadeResource' => 'Localização e Cadastros',
    'SexoResource' => 'Localização e Cadastros',
    'CorRacaResource' => 'Localização e Cadastros',
    'TipoVinculoResource' => 'Localização e Cadastros',

    // Sistema e Segurança
    'UserResource' => 'Sistema e Segurança',
    'PerfilResource' => 'Sistema e Segurança',
    'ActivityLogResource' => 'Sistema e Segurança',
    'EmailLogResource' => 'Sistema e Segurança',
    'ConfiguracaoResource' => 'Sistema e Segurança',
];

$directory = new RecursiveDirectoryIterator('app/Filament/Resources');
$iterator = new RecursiveIteratorIterator($directory);
$files = new RegexIterator($iterator, '/^.+Resource\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($files as $file) {
    $path = $file[0];
    $filename = basename($path, '.php');

    if (isset($mapping[$filename])) {
        $content = file_get_contents($path);
        $newGroup = $mapping[$filename];

        // Regex para encontrar a definição de $navigationGroup
        // Pode ser: protected static ?string $navigationGroup = '...';
        // Ou: protected static string|\UnitEnum|null $navigationGroup = '...';
        // Ou: public static function getNavigationGroup(): ?string { return '...'; }

        $pattern = '/(navigationGroup\s*=\s*[\'"])(.*?)([\'"])/';
        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, "$1$newGroup$3", $content);
            if ($newContent !== $content) {
                file_put_contents($path, $newContent);
                echo "Updated $filename -> $newGroup\n";
            }
        } else {
            // Se não existe, podemos tentar inserir após o $model
            $modelPattern = '/(protected static \?string \$model\s*=\s*.*?;)/';
            if (preg_match($modelPattern, $content, $matches)) {
                $replacement = $matches[1]."\n\n    protected static ?string \$navigationGroup = '$newGroup';";
                $newContent = str_replace($matches[1], $replacement, $content);
                file_put_contents($path, $newContent);
                echo "Added $filename -> $newGroup\n";
            }
        }
    }
}
