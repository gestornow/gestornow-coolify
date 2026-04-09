<?php

namespace App\Console\Commands;

use App\Domain\Auth\Models\Empresa;
use App\Models\AssinaturaPlanoPagamento;
use App\Models\AssinaturaPlano;
use App\Services\Billing\AssinaturaPlanoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestarSistemaBilling extends Command
{
    protected $signature = 'billing:testar-sistema
        {--empresa= : ID da empresa para testar}
        {--acao=status : Ação: status, simular-vencimento, simular-pagamento, gerar-mensalidade, processar-inadimplencia, forcar-cobranca}
        {--dias=5 : Dias de atraso para simular vencimento}';

    protected $description = 'Testa o sistema de billing: geração de mensalidades, bloqueio e desbloqueio';

    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('assinaturas_planos')) {
            $this->error('Tabelas de billing não encontradas.');
            return self::FAILURE;
        }

        $acao = (string) $this->option('acao');

        return match ($acao) {
            'status' => $this->mostrarStatus(),
            'simular-vencimento' => $this->simularVencimento(),
            'simular-pagamento' => $this->simularPagamento(),
            'gerar-mensalidade' => $this->gerarMensalidade(),
            'processar-inadimplencia' => $this->processarInadimplencia(),
            'forcar-cobranca' => $this->forcarCobranca(),
            default => $this->mostrarAjuda(),
        };
    }

    private function mostrarStatus(): int
    {
        $idEmpresa = $this->option('empresa');

        $this->info('=== STATUS DO SISTEMA DE BILLING ===');
        $this->newLine();

        // Stats globais
        $totalAssinaturas = AssinaturaPlano::count();
        $assinaturasAtivas = AssinaturaPlano::where('status', AssinaturaPlano::STATUS_ATIVA)->count();
        $assinaturasSuspensas = AssinaturaPlano::where('status', AssinaturaPlano::STATUS_SUSPENSA)->count();
        $assinaturasBloqueadas = AssinaturaPlano::where('bloqueada_por_inadimplencia', 1)->count();

        $this->table(['Métrica', 'Valor'], [
            ['Total de Assinaturas', $totalAssinaturas],
            ['Assinaturas Ativas', $assinaturasAtivas],
            ['Assinaturas Suspensas', $assinaturasSuspensas],
            ['Bloqueadas por Inadimplência', $assinaturasBloqueadas],
        ]);

        $this->newLine();
        $this->info('=== PAGAMENTOS PENDENTES ===');

        $pagamentosPendentes = AssinaturaPlanoPagamento::query()
            ->whereIn('status', [
                AssinaturaPlanoPagamento::STATUS_GERADO,
                AssinaturaPlanoPagamento::STATUS_PENDENTE,
                AssinaturaPlanoPagamento::STATUS_VENCIDO,
            ])
            ->orderBy('data_vencimento')
            ->limit(20)
            ->get();

        if ($pagamentosPendentes->isEmpty()) {
            $this->line('Nenhum pagamento pendente.');
        } else {
            $hoje = Carbon::today();
            $dados = $pagamentosPendentes->map(function ($p) use ($hoje) {
                $vencimento = Carbon::parse($p->data_vencimento)->startOfDay();

                $situacao = $vencimento->lt($hoje)
                    ? 'VENCIDO'
                    : ($vencimento->eq($hoje) ? 'VENCE HOJE' : 'EM DIA');

                return [
                    $p->id,
                    $p->id_empresa,
                    $p->tipo_cobranca,
                    $p->metodo_pagamento,
                    'R$ ' . number_format((float) $p->valor, 2, ',', '.'),
                    $vencimento->format('d/m/Y'),
                    $p->status,
                    $situacao,
                ];
            })->toArray();

            $this->table(['ID', 'Empresa', 'Tipo', 'Método', 'Valor', 'Vencimento', 'Status', 'Situação'], $dados);
        }

        // Se especificou empresa, mostra detalhes
        if ($idEmpresa) {
            $this->newLine();
            $this->info("=== DETALHES EMPRESA #{$idEmpresa} ===");
            $this->mostrarDetalhesEmpresa((int) $idEmpresa);
        }

        $this->newLine();
        $this->info('=== COMANDOS DISPONÍVEIS ===');
        $this->line('php artisan billing:testar-sistema --acao=status');
        $this->line('php artisan billing:testar-sistema --empresa=1 --acao=status');
        $this->line('php artisan billing:testar-sistema --empresa=1 --acao=simular-vencimento --dias=10');
        $this->line('php artisan billing:testar-sistema --empresa=1 --acao=simular-pagamento');
        $this->line('php artisan billing:testar-sistema --acao=gerar-mensalidade');
        $this->line('php artisan billing:testar-sistema --acao=processar-inadimplencia --dias=5');
        $this->line('php artisan billing:testar-sistema --empresa=1 --acao=forcar-cobranca');

        return self::SUCCESS;
    }

    private function mostrarDetalhesEmpresa(int $idEmpresa): void
    {
        $empresa = Empresa::find($idEmpresa);
        if (!$empresa) {
            $this->error("Empresa #{$idEmpresa} não encontrada.");
            return;
        }

        $this->table(['Campo', 'Valor'], [
            ['Nome', $empresa->razao_social ?: $empresa->nome_empresa],
            ['Status', $empresa->status],
            ['Data Bloqueio', $empresa->data_bloqueio?->format('d/m/Y H:i') ?? '-'],
        ]);

        $assinatura = AssinaturaPlano::where('id_empresa', $idEmpresa)
            ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
            ->latest('id')
            ->first();

        if ($assinatura) {
            $this->newLine();
            $this->line('Assinatura atual:');
            $this->table(['Campo', 'Valor'], [
                ['ID', $assinatura->id],
                ['Status', $assinatura->status],
                ['Método Mensal', $assinatura->metodo_mensal],
                ['Próxima Cobrança', $assinatura->proxima_cobranca_em?->format('d/m/Y') ?? '-'],
                ['Bloqueada Inadimplência', $assinatura->bloqueada_por_inadimplencia ? 'SIM' : 'NÃO'],
                ['Inadimplente Desde', $assinatura->inadimplente_desde?->format('d/m/Y') ?? '-'],
            ]);
        }

        $pagamentos = AssinaturaPlanoPagamento::where('id_empresa', $idEmpresa)
            ->orderByDesc('data_vencimento')
            ->limit(10)
            ->get();

        if ($pagamentos->isNotEmpty()) {
            $this->newLine();
            $this->line('Últimos pagamentos:');
            $dados = $pagamentos->map(fn ($p) => [
                $p->id,
                $p->tipo_cobranca,
                $p->metodo_pagamento,
                'R$ ' . number_format((float) $p->valor, 2, ',', '.'),
                Carbon::parse($p->data_vencimento)->format('d/m/Y'),
                $p->data_pagamento ? Carbon::parse($p->data_pagamento)->format('d/m/Y') : '-',
                $p->status,
            ])->toArray();

            $this->table(['ID', 'Tipo', 'Método', 'Valor', 'Vencimento', 'Pagamento', 'Status'], $dados);
        }
    }

    private function simularVencimento(): int
    {
        $idEmpresa = $this->option('empresa');
        $dias = (int) $this->option('dias') ?: 10;

        if (!$idEmpresa) {
            $this->error('Informe o ID da empresa com --empresa=X');
            return self::FAILURE;
        }

        $empresa = Empresa::find($idEmpresa);
        if (!$empresa) {
            $this->error("Empresa #{$idEmpresa} não encontrada.");
            return self::FAILURE;
        }

        $this->warn("Simulando vencimento de {$dias} dias atrás para empresa #{$idEmpresa}...");

        // Busca pagamentos pendentes e ajusta vencimento
        $pagamentosAtualizados = AssinaturaPlanoPagamento::where('id_empresa', $idEmpresa)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereIn('status', [
                AssinaturaPlanoPagamento::STATUS_GERADO,
                AssinaturaPlanoPagamento::STATUS_PENDENTE,
            ])
            ->update([
                'data_vencimento' => now()->subDays($dias)->toDateString(),
                'status' => AssinaturaPlanoPagamento::STATUS_VENCIDO,
            ]);

        $this->info("Pagamentos atualizados: {$pagamentosAtualizados}");

        if ($pagamentosAtualizados > 0) {
            $this->newLine();
            $this->line('Agora execute o comando de inadimplência para verificar o bloqueio:');
            $this->line("php artisan billing:testar-sistema --acao=processar-inadimplencia --dias=5");
        }

        return self::SUCCESS;
    }

    private function simularPagamento(): int
    {
        $idEmpresa = $this->option('empresa');

        if (!$idEmpresa) {
            $this->error('Informe o ID da empresa com --empresa=X');
            return self::FAILURE;
        }

        $empresa = Empresa::find($idEmpresa);
        if (!$empresa) {
            $this->error("Empresa #{$idEmpresa} não encontrada.");
            return self::FAILURE;
        }

        $this->warn("Simulando pagamento de todas as pendências da empresa #{$idEmpresa}...");

        // Busca todos pagamentos pendentes/vencidos e marca como pago
        $pagamentosAtualizados = AssinaturaPlanoPagamento::where('id_empresa', $idEmpresa)
            ->whereIn('status', [
                AssinaturaPlanoPagamento::STATUS_GERADO,
                AssinaturaPlanoPagamento::STATUS_PENDENTE,
                AssinaturaPlanoPagamento::STATUS_VENCIDO,
                AssinaturaPlanoPagamento::STATUS_FALHOU,
            ])
            ->update([
                'status' => AssinaturaPlanoPagamento::STATUS_PAGO,
                'data_pagamento' => now(),
            ]);

        $this->info("Pagamentos quitados: {$pagamentosAtualizados}");

        if ($pagamentosAtualizados > 0) {
            $this->newLine();
            $this->line('Agora execute o comando de inadimplência para verificar o desbloqueio:');
            $this->line("php artisan billing:testar-sistema --acao=processar-inadimplencia --dias=5");
        }

        return self::SUCCESS;
    }

    private function gerarMensalidade(): int
    {
        $this->info('Executando geração de mensalidades...');

        try {
            $resultado = $this->assinaturaPlanoService->gerarMensalidadesPendentes();

            $this->info('Mensalidades geradas com sucesso.');
            $this->table(['Métrica', 'Valor'], [
                ['Assinaturas analisadas', $resultado['assinaturas_analisadas'] ?? 0],
                ['Mensalidades geradas', $resultado['mensalidades_geradas'] ?? 0],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function processarInadimplencia(): int
    {
        $dias = (int) $this->option('dias') ?: 5;

        $this->info("Processando inadimplência (dias de atraso: {$dias})...");

        try {
            $resultado = $this->assinaturaPlanoService->processarInadimplencia($dias);

            $this->info('Processamento concluído.');
            $this->table(['Métrica', 'Valor'], [
                ['Cancelamentos finalizados', $resultado['cancelamentos_finalizados'] ?? 0],
                ['Empresas bloqueadas', $resultado['empresas_bloqueadas'] ?? 0],
                ['Empresas desbloqueadas', $resultado['empresas_desbloqueadas'] ?? 0],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function forcarCobranca(): int
    {
        $idEmpresa = $this->option('empresa');

        if (!$idEmpresa) {
            $this->error('Informe o ID da empresa com --empresa=X');
            return self::FAILURE;
        }

        $empresa = Empresa::find($idEmpresa);
        if (!$empresa) {
            $this->error("Empresa #{$idEmpresa} não encontrada.");
            return self::FAILURE;
        }

        $this->info("Antecipando próxima cobrança da assinatura no Asaas para hoje (empresa #{$idEmpresa})...");

        try {
            $resultado = $this->assinaturaPlanoService->anteciparProximaCobrancaAsaas($empresa);

            $this->table(['Campo', 'Valor'], [
                ['Assinatura local', $resultado['assinatura_id'] ?? '-'],
                ['Assinatura Asaas', $resultado['subscription_id'] ?? '-'],
                ['Próximo vencimento (Asaas)', $resultado['next_due_date'] ?? '-'],
                ['Método (Asaas)', $resultado['billing_type'] ?? '-'],
                ['Cobrança Asaas (payment id)', $resultado['payment_id'] ?? '-'],
                ['Status da cobrança', $resultado['payment_status'] ?? '-'],
                ['Vencimento da cobrança', $resultado['payment_due_date'] ?? '-'],
                ['ID local da cobrança', $resultado['pagamento_local_id'] ?? '-'],
            ]);

            if (!empty($resultado['invoice_url'])) {
                $this->line('Invoice URL: ' . $resultado['invoice_url']);
            }

            $this->newLine();
            $this->warn('Observação: a tentativa de débito automático no cartão é processada pelo Asaas e pode levar alguns instantes após a antecipação.');
            $this->line('Acompanhe pelo webhook e pela tela Meu Financeiro (status/final do cartão).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro ao forçar cobrança: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function mostrarAjuda(): int
    {
        $this->info('Ações disponíveis:');
        $this->line('  status               - Mostra status geral do billing');
        $this->line('  simular-vencimento   - Simula vencimento de pagamentos (requer --empresa)');
        $this->line('  simular-pagamento    - Simula pagamento de pendências (requer --empresa)');
        $this->line('  gerar-mensalidade    - Executa geração de mensalidades');
        $this->line('  processar-inadimplencia - Executa bloqueio/desbloqueio por inadimplência');
        $this->line('  forcar-cobranca      - Antecipa próxima cobrança no Asaas para hoje (requer --empresa)');

        return self::SUCCESS;
    }
}
