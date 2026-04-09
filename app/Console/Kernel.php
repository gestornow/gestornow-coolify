<?php

namespace App\Console;

use App\Jobs\NotificarContasPagarJob;
use App\Jobs\NotificarContasReceberJob;
use App\Jobs\NotificarLocacoesVencidasJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpa códigos de redefinição expirados diariamente
        $schedule->command('auth:clear-expired-codes')->daily();
        
        // Bloqueia empresas em teste expirado - todo dia às 22:20
        $schedule->command('empresas:bloquear-teste-expirado')->dailyAt('00:01');
        
        // Processa movimentações de estoque automaticamente a cada minuto
        // Verifica horário exato de início/fim de locações e faz saída/entrada de estoque
        $schedule->command('estoque:processar-movimentacoes')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/estoque-movimentacoes.log'));

        // Processa inícios/conclusões de manutenção e sincroniza estoque/patrimônios
        $schedule->command('manutencoes:processar-estoque')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/manutencoes-estoque.log'));

        // Processa renovações automáticas (aditivos) de contratos vencidos
        $schedule->command('locacoes:processar-renovacoes')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/locacoes-renovacoes.log'));

        // Gera mensalidades recorrentes de assinaturas
        $schedule->command('billing:assinaturas-gerar-mensalidades')
            ->dailyAt('00:10')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-assinaturas-geracao.log'));

        // Bloqueia/desbloqueia empresas por inadimplência (regra de 5 dias)
        $schedule->command('billing:assinaturas-processar-inadimplencia --dias=5')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-assinaturas-inadimplencia.log'));

        // Processa cobranças de cartão de crédito em horários específicos
        // Tenta debitar automaticamente do cartão cadastrado no Asaas
        $schedule->command('billing:processar-cobrancas-cartao')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-cobrancas-cartao.log'));

        $schedule->command('billing:processar-cobrancas-cartao')
            ->dailyAt('12:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-cobrancas-cartao.log'));

        $schedule->command('billing:processar-cobrancas-cartao')
            ->dailyAt('18:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/billing-cobrancas-cartao.log'));

        // Notificacoes internas de rotina (locacoes e financeiro)
        $schedule->job(new NotificarLocacoesVencidasJob())
            ->dailyAt('07:00')
            ->withoutOverlapping();

        $schedule->job(new NotificarContasPagarJob())
            ->dailyAt('07:00')
            ->withoutOverlapping();

        $schedule->job(new NotificarContasReceberJob())
            ->dailyAt('07:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
