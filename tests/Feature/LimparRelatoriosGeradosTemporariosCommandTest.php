<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LimparRelatoriosGeradosTemporariosCommandTest extends TestCase
{
    public function test_comando_expurga_relatorios_com_mais_de_sete_dias_e_preserva_recentes(): void
    {
        Storage::fake('local');

        // Cria relatório PDF antigo (> 7 dias / 1 semana)
        Storage::disk('local')->put('relatorios_gerados/relatorio_antigo_10_dias.pdf', '%PDF-1.4 Mock PDF');
        touch(Storage::disk('local')->path('relatorios_gerados/relatorio_antigo_10_dias.pdf'), now()->subDays(10)->timestamp);

        // Cria relatório PDF recente (< 7 dias)
        Storage::disk('local')->put('relatorios_gerados/relatorio_recente_2_dias.pdf', '%PDF-1.4 Mock PDF');
        touch(Storage::disk('local')->path('relatorios_gerados/relatorio_recente_2_dias.pdf'), now()->subDays(2)->timestamp);

        $this->artisan('relatorios:limpar-temporarios')
            ->expectsOutputToContain('Limpeza de relatórios temporários concluída: 1 arquivo(s) removido(s)')
            ->expectsOutputToContain('1 arquivo(s) recente(s) preservado(s)')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('relatorios_gerados/relatorio_antigo_10_dias.pdf');
        Storage::disk('local')->assertExists('relatorios_gerados/relatorio_recente_2_dias.pdf');
    }

    public function test_comando_respeita_opcao_dias_customizada(): void
    {
        Storage::fake('local');

        // Cria relatório com 4 dias
        Storage::disk('local')->put('relatorios_gerados/relatorio_4_dias.pdf', '%PDF-1.4 Mock PDF');
        touch(Storage::disk('local')->path('relatorios_gerados/relatorio_4_dias.pdf'), now()->subDays(4)->timestamp);

        // Cria relatório com 1 dia
        Storage::disk('local')->put('relatorios_gerados/relatorio_1_dia.pdf', '%PDF-1.4 Mock PDF');
        touch(Storage::disk('local')->path('relatorios_gerados/relatorio_1_dia.pdf'), now()->subDays(1)->timestamp);

        // Executa com --dias=3
        $this->artisan('relatorios:limpar-temporarios --dias=3')
            ->expectsOutputToContain('Limpeza de relatórios temporários concluída: 1 arquivo(s) removido(s)')
            ->expectsOutputToContain('(limite: 3 dias)')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('relatorios_gerados/relatorio_4_dias.pdf');
        Storage::disk('local')->assertExists('relatorios_gerados/relatorio_1_dia.pdf');
    }

    public function test_comando_modo_dry_run_nao_exclui_arquivos_fisicos(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('relatorios_gerados/relatorio_expirado.pdf', '%PDF-1.4 Mock PDF');
        touch(Storage::disk('local')->path('relatorios_gerados/relatorio_expirado.pdf'), now()->subDays(15)->timestamp);

        $this->artisan('relatorios:limpar-temporarios --dry-run')
            ->expectsOutputToContain('[DRY-RUN] Seria removido: relatorios_gerados/relatorio_expirado.pdf')
            ->expectsOutputToContain('1 arquivo(s) identificado(s) para remoção')
            ->assertSuccessful();

        // O arquivo físico NÃO deve ter sido removido
        Storage::disk('local')->assertExists('relatorios_gerados/relatorio_expirado.pdf');
    }

    public function test_comando_executa_sem_erros_quando_diretorio_nao_existe_ou_esta_vazio(): void
    {
        Storage::fake('local');

        $this->artisan('relatorios:limpar-temporarios')
            ->assertSuccessful();
    }

    public function test_comando_ignora_arquivos_que_nao_sao_pdf(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('relatorios_gerados/arquivo_nao_pdf.txt', 'Arquivo de texto');
        touch(Storage::disk('local')->path('relatorios_gerados/arquivo_nao_pdf.txt'), now()->subDays(20)->timestamp);

        $this->artisan('relatorios:limpar-temporarios')
            ->expectsOutputToContain('0 arquivo(s) removido(s)')
            ->assertSuccessful();

        // Arquivo que não é PDF continua existindo
        Storage::disk('local')->assertExists('relatorios_gerados/arquivo_nao_pdf.txt');
    }
}
