<?php

namespace App\Console\Commands;

use App\Services\LocacaoRenovacaoService;
use Illuminate\Console\Command;

class ProcessarRenovacoesLocacao extends Command
{
    protected $signature = 'locacoes:processar-renovacoes {--empresa= : Processa apenas uma empresa específica}';

    protected $description = 'Processa renovações automáticas (aditivos) de locações vencidas.';

    public function __construct(private LocacaoRenovacaoService $renovacaoService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $idEmpresa = $this->option('empresa') ? (int) $this->option('empresa') : null;

        $this->info('Processando renovações automáticas de locações...');

        $resultado = $this->renovacaoService->processarRenovacoesAutomaticas($idEmpresa);

        $this->info('Renovações processadas: ' . (int) ($resultado['processadas'] ?? 0));
        $this->info('Contratos encerrados no horário: ' . (int) ($resultado['encerradas'] ?? 0));
        $this->info('Saídas iniciadas de aditivos: ' . (int) ($resultado['saidas_iniciadas'] ?? 0));

        if ((int) ($resultado['erros'] ?? 0) > 0) {
            $this->warn('Erros durante o processamento: ' . (int) $resultado['erros']);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
