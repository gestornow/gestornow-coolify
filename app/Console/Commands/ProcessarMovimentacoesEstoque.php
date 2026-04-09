<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoProduto;
use App\Services\EstoqueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessarMovimentacoesEstoque extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'estoque:processar-movimentacoes {--dry-run : Executar em modo de teste sem alterar dados}';

    /**
     * The console command description.
     */
    protected $description = 'Processa saídas de estoque e marca locações atrasadas. Retorno é manual ao finalizar contrato.';

    protected $estoqueService;

    public function __construct(EstoqueService $estoqueService)
    {
        parent::__construct();
        $this->estoqueService = $estoqueService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $agora = Carbon::now();
        
        $this->info("Processando movimentações de estoque em {$agora->format('d/m/Y H:i:s')}");
        
        if ($dryRun) {
            $this->warn("Modo dry-run ativado - nenhuma alteração será feita.");
        }

        // 1. Processar saídas de estoque (locações que devem iniciar agora)
        $saidasProcessadas = $this->processarSaidas($agora, $dryRun);
        
        // 2. Marcar locações atrasadas
        $atrasadasMarcadas = $this->marcarLocacoesAtrasadas($agora, $dryRun);

        $this->info("Resumo:");
        $this->info("  - Saídas processadas: {$saidasProcessadas}");
        $this->info("  - Locações marcadas como atrasadas: {$atrasadasMarcadas}");
        
        Log::info('Processamento de movimentações de estoque concluído', [
            'saidas' => $saidasProcessadas,
            'atrasadas' => $atrasadasMarcadas,
            'dry_run' => $dryRun,
        ]);

        return 0;
    }

    /**
    * Processar saídas de estoque para locações aprovadas que iniciam agora
     */
    private function processarSaidas(Carbon $agora, bool $dryRun): int
    {
        $countLocacoesProcessadas = 0;

        $itensPendentes = LocacaoProduto::where('estoque_status', 0)
            ->whereHas('locacao', function ($query) {
                $query->whereIn('status', ['aprovado', 'medicao']);
            })
            ->with(['locacao', 'produto', 'patrimonio'])
            ->get();

        if ($itensPendentes->isEmpty()) {
            return 0;
        }

        $itensPorLocacao = $itensPendentes->groupBy('id_locacao');

        foreach ($itensPorLocacao as $idLocacao => $itensLocacao) {
            $locacao = $itensLocacao->first()->locacao;
            if (!$locacao) {
                continue;
            }

            $itensElegiveis = $itensLocacao->filter(function (LocacaoProduto $item) use ($locacao, $agora) {
                $dataHoraInicioItem = $this->obterDataHoraInicioItem($locacao, $item);
                return $dataHoraInicioItem && $dataHoraInicioItem->lte($agora);
            });

            if ($itensElegiveis->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $countLocacoesProcessadas++;
                $this->line("  Locação #{$locacao->numero_contrato}: {$itensElegiveis->count()} item(ns) elegível(is) para baixa");
                continue;
            }

            DB::beginTransaction();

            try {
                $itensProcessados = 0;

                foreach ($itensElegiveis as $item) {
                    if ((int) ($item->estoque_status ?? 0) !== 0) {
                        continue;
                    }

                    $this->estoqueService->registrarSaidaLocacao($item);
                    $item->estoque_status = 1;
                    $item->save();
                    $itensProcessados++;
                }

                DB::commit();

                if ($itensProcessados > 0) {
                    $countLocacoesProcessadas++;

                    Log::info("Locação #{$locacao->numero_contrato} aprovada - baixa de estoque processada por item no cron", [
                        'id_locacao' => $locacao->id_locacao,
                        'itens_processados' => $itensProcessados,
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Erro ao processar saída por item da locação #{$locacao->numero_contrato}: " . $e->getMessage());
                $this->error("  Erro na locação #{$locacao->numero_contrato}: " . $e->getMessage());
            }
        }

        return $countLocacoesProcessadas;
    }

    private function obterDataHoraInicioItem(Locacao $locacao, LocacaoProduto $item): ?Carbon
    {
        $normalizarData = static function ($valor): ?string {
            if ($valor instanceof \DateTimeInterface) {
                return $valor->format('Y-m-d');
            }

            $valor = trim((string) ($valor ?? ''));
            return $valor !== '' ? $valor : null;
        };

        $dataInicio = $normalizarData($item->data_inicio)
            ?? $normalizarData($item->data_contrato);

        $horaInicio = trim((string) ($item->hora_inicio ?? $item->hora_contrato ?? ''));

        if (!$dataInicio) {
            $datasLocacao = $this->estoqueService->obterDatasEfetivas($locacao);
            $dataInicio = $normalizarData($datasLocacao['data_inicio'] ?? null);
            $horaInicio = $horaInicio !== ''
                ? $horaInicio
                : trim((string) ($datasLocacao['hora_inicio'] ?? '00:00'));
        }

        if (!$dataInicio) {
            return null;
        }

        if ($horaInicio === '') {
            $horaInicio = '00:00';
        }

        return Carbon::parse($dataInicio . ' ' . $horaInicio);
    }

    /**
     * Marcar locações que estão atrasadas
     */
    private function marcarLocacoesAtrasadas(Carbon $agora, bool $dryRun): int
    {
        $count = 0;
        
        $locacoes = Locacao::where('status', 'em_andamento')
            ->whereNotNull('data_fim')
            ->get();
        
        foreach ($locacoes as $locacao) {
            $datasEfetivas = $this->estoqueService->obterDatasEfetivas($locacao);
            $dataHoraFim = Carbon::parse($datasEfetivas['data_fim'] . ' ' . $datasEfetivas['hora_fim']);
            
            if ($agora->greaterThan($dataHoraFim)) {
                // Verificar se há itens pendentes de retorno
                $temPendentes = $locacao->produtos()
                    ->where(function ($q) {
                        $q->whereNull('status_retorno')
                          ->orWhere('status_retorno', 'pendente');
                    })
                    ->exists();
                
                if ($temPendentes) {
                    $this->line("  Locação #{$locacao->numero_contrato} está atrasada");
                    
                    if (!$dryRun) {
                        $locacao->status = 'atrasada';
                        $locacao->save();
                        $count++;
                        
                        Log::warning("Locação #{$locacao->numero_contrato} marcada como atrasada");
                    } else {
                        $count++;
                    }
                }
            }
        }
        
        return $count;
    }
}