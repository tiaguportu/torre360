<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Enums\ModoPonto;
use App\Models\Funcionario;
use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;

/**
 * Confere se uma unidade pode declarar um modo oficial de registro de ponto (REP-A ou REP-P, Portaria MTP 671/2021) e
 * explica, requisito por requisito, o que falta e como resolver. O modo "controle interno" não declara nada e nunca
 * tem pendência.
 *
 * Parte dos requisitos depende do CLIENTE (registro no INPI, atestado técnico, acordo coletivo, certificado) e parte do
 * DESENVOLVEDOR do sistema (leiautes oficiais de AFD e AEJ, validação do Anexo IX, assinatura ICP-Brasil); estes ficam
 * em `config('ponto.requisitos_desenvolvedor')` e só viram `true` quando realmente implementados.
 */
class ConformidadePontoService
{
    public function __construct(
        private readonly HoraLegalProvider $horaLegal,
        private readonly AssinadorPonto $assinador,
    ) {}

    /**
     * Requisitos ainda não atendidos para o modo escolhido na configuração.
     *
     * @return list<array{codigo: string, titulo: string, como_resolver: string}>
     */
    public function pendencias(PontoConfiguracao $config): array
    {
        return match ($config->modo) {
            ModoPonto::ControleInterno => [],
            ModoPonto::RepA => $this->pendenciasRepA($config),
            ModoPonto::RepP => $this->pendenciasRepP($config),
        };
    }

    /**
     * Avisos que não bloqueiam, mas que a direção precisa ver.
     *
     * @return list<array{codigo: string, mensagem: string}>
     */
    public function alertas(PontoConfiguracao $config): array
    {
        $alertas = [];
        $limite = (int) config('ponto.limite_obrigatoriedade_trabalhadores', 20);
        $trabalhadores = Funcionario::query()->where('unidade_id', $config->unidade_id)->whereNull('data_desligamento')->count();

        if (! $config->modo->ehOficial() && $trabalhadores > $limite) {
            $alertas[] = [
                'codigo' => 'obrigatoriedade',
                'mensagem' => "A unidade tem {$trabalhadores} trabalhadores ativos. Estabelecimentos com mais de {$limite} são obrigados a registrar a jornada (CLT, art. 74, § 2º) por um sistema que atenda a Portaria MTP 671/2021 (REP-C, REP-A ou REP-P). O modo \"controle interno\" não cumpre essa exigência.",
            ];
        }

        if ($config->modo->ehOficial() && ($pendencias = $this->pendencias($config)) !== []) {
            $alertas[] = [
                'codigo' => 'conformidade_perdida',
                'mensagem' => 'O modo oficial está configurado, mas deixou de cumprir: '.collect($pendencias)->pluck('titulo')->implode('; ').'.',
            ];
        }

        if ($config->exige_hora_legal) {
            $semHoraLegal = PontoRegistro::query()
                ->where('unidade_id', $config->unidade_id)
                ->where('hora_legal_ok', false)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            if ($semHoraLegal > 0) {
                $alertas[] = [
                    'codigo' => 'hora_legal',
                    'mensagem' => "{$semHoraLegal} marcação(ões) nos últimos 30 dias ficaram sem confirmação da Hora Legal Brasileira (variação acima de ".config('ponto.limite_desvio_segundos', 30).' s ou consulta indisponível). Confira a sincronização do relógio do servidor.',
                ];
            }
        }

        return $alertas;
    }

    /**
     * @return list<array{codigo: string, titulo: string, como_resolver: string}>
     */
    private function pendenciasRepP(PontoConfiguracao $config): array
    {
        $pendencias = [];

        if (blank($config->registro_inpi)) {
            $pendencias[] = [
                'codigo' => 'registro_inpi',
                'titulo' => 'Certificado de registro do programa de computador no INPI',
                'como_resolver' => 'O REP-P exige que o programa tenha certificado de registro no INPI (Portaria MTP 671/2021, art. 91). O registro é do software e é feito por quem o desenvolve ou detém os direitos sobre ele. Obtenha o certificado e informe o número.',
            ];
        }

        if (blank($config->atestado_tecnico_path) || $config->atestado_tecnico_data === null) {
            $pendencias[] = [
                'codigo' => 'atestado_tecnico',
                'titulo' => 'Atestado Técnico e Termo de Responsabilidade (Anexo VII)',
                'como_resolver' => 'O desenvolvedor emite o Atestado Técnico e Termo de Responsabilidade, em PDF, com assinatura eletrônica qualificada de pessoa física (art. 89). O empregador só pode usar o sistema se o tiver (art. 89, § 4º). Envie o PDF e informe a data de emissão.',
            ];
        } elseif (! $config->atestado_assinatura_conferida) {
            $pendencias[] = [
                'codigo' => 'atestado_assinatura',
                'titulo' => 'Conferência da assinatura qualificada do Atestado Técnico',
                'como_resolver' => 'Valide a assinatura do PDF em um verificador de assinaturas ICP-Brasil (por exemplo, o Verificador de Conformidade do ITI) e marque a conferência.',
            ];
        }

        if (! $this->horaLegal->verificaHoraLegal()) {
            $pendencias[] = [
                'codigo' => 'hora_legal',
                'titulo' => 'Sincronização com a Hora Legal Brasileira (variação máxima de 30 segundos)',
                'como_resolver' => 'Ative a consulta aos servidores NTP.br (PONTO_HORA_LEGAL=ntp) e libere a saída UDP na porta 123 no servidor.',
            ];
        }

        if (! $this->assinaturaIcpBrasilDisponivel()) {
            $pendencias[] = [
                'codigo' => 'assinatura_icp_brasil',
                'titulo' => 'Assinatura eletrônica ICP-Brasil do comprovante (art. 79)',
                'como_resolver' => 'Adquira um certificado digital ICP-Brasil (A1 ou A3) em nome da empresa e peça ao desenvolvedor a implementação de AssinadorPonto com esse certificado. Enquanto isso, o comprovante sai com a faixa "NÃO ASSINADO".',
            ];
        }

        return [...$pendencias, ...$this->pendenciasDoDesenvolvedor()];
    }

    /**
     * @return list<array{codigo: string, titulo: string, como_resolver: string}>
     */
    private function pendenciasRepA(PontoConfiguracao $config): array
    {
        $pendencias = [];

        if (blank($config->acordo_coletivo_referencia)
            || $config->acordo_coletivo_vigencia_fim === null
            || $config->acordo_coletivo_vigencia_fim->isPast()) {
            $pendencias[] = [
                'codigo' => 'acordo_coletivo',
                'titulo' => 'Acordo ou convenção coletiva vigente autorizando o REP-A',
                'como_resolver' => 'O REP-A só é válido se autorizado por acordo ou convenção coletiva de trabalho (Portaria MTP 671/2021, art. 77). Formalize o instrumento com o sindicato da categoria e informe a referência e a data final de vigência.',
            ];
        }

        $desenvolvedor = collect($this->pendenciasDoDesenvolvedor())->whereIn('codigo', ['afd_oficial', 'aej_oficial'])->values()->all();

        return [...$pendencias, ...$desenvolvedor];
    }

    /**
     * Requisitos de responsabilidade do desenvolvedor do sistema.
     *
     * @return list<array{codigo: string, titulo: string, como_resolver: string}>
     */
    private function pendenciasDoDesenvolvedor(): array
    {
        $req = (array) config('ponto.requisitos_desenvolvedor', []);
        $pendencias = [];

        if (! ($req['afd_oficial'] ?? false)) {
            $pendencias[] = [
                'codigo' => 'afd_oficial',
                'titulo' => 'Arquivo Fonte de Dados (AFD) no leiaute oficial',
                'como_resolver' => 'Implementar a geração do AFD conforme o Anexo V da Portaria MTP 671/2021 e validá-la com os testes de conformidade. É trabalho do desenvolvedor do sistema.',
            ];
        }

        if (! ($req['aej_oficial'] ?? false)) {
            $pendencias[] = [
                'codigo' => 'aej_oficial',
                'titulo' => 'Arquivo Eletrônico de Jornada (AEJ) no leiaute oficial',
                'como_resolver' => 'Implementar a geração do AEJ conforme o Anexo VI da Portaria MTP 671/2021 (art. 83). É trabalho do desenvolvedor do sistema.',
            ];
        }

        if (! ($req['anexo_ix_validado'] ?? false)) {
            $pendencias[] = [
                'codigo' => 'anexo_ix',
                'titulo' => 'Requisitos do REP-P (Anexo IX) validados item a item',
                'como_resolver' => 'Obter o texto oficial do Anexo IX, conferir cada requisito contra o sistema (inclusive coletor online/offline e relógio com horas, minutos e segundos) e só então emitir o Atestado Técnico. É trabalho do desenvolvedor do sistema.',
            ];
        }

        return $pendencias;
    }

    private function assinaturaIcpBrasilDisponivel(): bool
    {
        return (bool) config('ponto.requisitos_desenvolvedor.assinatura_icp_brasil', false) && $this->assinador->assina();
    }
}
