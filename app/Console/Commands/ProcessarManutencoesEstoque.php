<?php

namespace App\Console\Commands;

use App\Services\ManutencaoEstoqueService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessarManutencoesEstoque extends Command
{
    protected $signature = 'manutencoes:processar-estoque {--dry-run : Executar em modo de teste sem alterar dados}';

    protected $description = 'Processa início e conclusão automática de manutenções com impacto no estoque/patrimônio.';

    private $service;

    public function __construct(ManutencaoEstoqueService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $agora = Carbon::now();

        $this->info('Processando manutenções em ' . $agora->format('d/m/Y H:i:s'));

        if ($dryRun) {
            $this->warn('Modo dry-run ativado - nenhuma alteração será aplicada.');
            return 0;
        }

        $resultado = $this->service->processarAgendadas($agora);

        $this->info('Inícios processados: ' . ($resultado['iniciadas'] ?? 0));
        $this->info('Conclusões processadas: ' . ($resultado['concluidas'] ?? 0));

        Log::info('Cron de manutenções executado', [
            'iniciadas' => $resultado['iniciadas'] ?? 0,
            'concluidas' => $resultado['concluidas'] ?? 0,
        ]);

        return 0;
    }
}
