<?php

namespace App\Console\Commands;

use App\Domain\Auth\Models\Empresa;
use App\Models\AssinaturaPlano;
use App\Models\AssinaturaPlanoPagamento;
use App\Services\Billing\AsaasGatewayService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessarCobrancasCartao extends Command
{
    protected $signature = 'billing:processar-cobrancas-cartao
        {--empresa= : ID de empresa específica (opcional)}
        {--dry-run : Apenas lista as cobranças sem processar}
        {--force : Força processamento mesmo se já tentou recentemente}';

    protected $description = 'Força tentativa de débito em cobranças de cartão que vencem hoje (não antecipa datas)';

    public function __construct(
        private readonly AsaasGatewayService $asaasGateway
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('assinaturas_planos') || !Schema::hasTable('assinaturas_planos_pagamentos')) {
            $this->error('Tabelas de billing não encontradas.');
            return self::FAILURE;
        }

        if (!$this->asaasGateway->isConfigured()) {
            $this->error('API do Asaas não configurada (services.asaas.api_key).');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $idEmpresa = $this->option('empresa');

        $this->info('=== FORÇAR DÉBITO DE CARTÃO (VENCIMENTO HOJE) ===');
        $this->line('Data/Hora: ' . now()->format('d/m/Y H:i:s'));
        
        if ($dryRun) {
            $this->warn('Modo DRY-RUN: nenhuma cobrança será processada.');
        }

        $hoje = now()->toDateString();

        // Busca pagamentos de cartão que vencem HOJE e ainda não foram pagos
        $query = AssinaturaPlanoPagamento::query()
            ->where('metodo_pagamento', AssinaturaPlano::METODO_CREDIT_CARD)
            ->where('data_vencimento', $hoje)
            ->whereIn('status', [
                AssinaturaPlanoPagamento::STATUS_GERADO,
                AssinaturaPlanoPagamento::STATUS_PENDENTE,
            ])
            ->whereNotNull('asaas_payment_id')
            ->where('asaas_payment_id', '!=', '');

        if ($idEmpresa) {
            $query->where('id_empresa', (int) $idEmpresa);
        }

        $pagamentos = $query->orderBy('id_empresa')->get();

        if ($pagamentos->isEmpty()) {
            $this->info('Nenhuma cobrança de cartão pendente vencendo hoje.');
            return self::SUCCESS;
        }

        $this->line("Cobranças encontradas: {$pagamentos->count()}");
        $this->newLine();

        $processadas = 0;
        $sucesso = 0;
        $erros = 0;
        $ignoradas = 0;

        foreach ($pagamentos as $pagamento) {
            $empresa = Empresa::find($pagamento->id_empresa);
            $nomeEmpresa = $empresa ? ($empresa->razao_social ?: $empresa->nome_empresa) : "Empresa #{$pagamento->id_empresa}";

            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("Empresa: {$nomeEmpresa} (ID: {$pagamento->id_empresa})");
            $this->line("Pagamento Local ID: {$pagamento->id}");
            $this->line("Asaas Payment ID: {$pagamento->asaas_payment_id}");
            $this->line("Valor: R$ " . number_format((float) $pagamento->valor, 2, ',', '.'));

            if ($dryRun) {
                $this->line("  ⚪ [DRY-RUN] Seria processado.");
                continue;
            }

            // Verifica se já tentou recentemente (evita spam na API)
            // Usa updated_at para saber quando foi a última modificação
            if (!$force) {
                $ultimaAtualizacao = $pagamento->updated_at;
                if ($ultimaAtualizacao && $ultimaAtualizacao->diffInMinutes(now()) < 60) {
                    $this->line("  ⏭ Já tentou há menos de 1 hora. Use --force para forçar.");
                    $ignoradas++;
                    continue;
                }
            }

            try {
                $processadas++;

                // Busca status atual do payment no Asaas
                $paymentAsaas = $this->asaasGateway->getPayment($pagamento->asaas_payment_id);
                $statusAtual = strtoupper((string) ($paymentAsaas['status'] ?? ''));

                $this->line("  Status atual no Asaas: {$statusAtual}");

                // Se já foi pago ou confirmado, ignora
                if (in_array($statusAtual, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'])) {
                    $this->info("  ✓ Já está pago no Asaas. Atualizando local...");
                    $pagamento->status = AssinaturaPlanoPagamento::STATUS_PAGO;
                    $pagamento->data_pagamento = now();
                    $pagamento->save();
                    $sucesso++;
                    continue;
                }

                // Se não é PENDING ou OVERDUE, não pode forçar
                if (!in_array($statusAtual, ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'])) {
                    $this->warn("  ⚠ Status '{$statusAtual}' não permite tentativa de cobrança.");
                    $ignoradas++;
                    continue;
                }

                // Busca o token do cartão na assinatura
                $assinatura = AssinaturaPlano::query()
                    ->where('id_empresa', $pagamento->id_empresa)
                    ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
                    ->whereNotNull('asaas_subscription_id')
                    ->where('asaas_subscription_id', '!=', '')
                    ->latest('id')
                    ->first();

                if (!$assinatura) {
                    $this->error("  ✗ Assinatura com cartão não encontrada.");
                    $erros++;
                    continue;
                }

                // Busca dados do cartão tokenizado da assinatura no Asaas
                $subscriptionData = $this->asaasGateway->getSubscriptionWithCard($assinatura->asaas_subscription_id);
                $creditCardToken = $subscriptionData['creditCardToken'] ?? '';

                if (empty($creditCardToken)) {
                    $this->error("  ✗ Token de cartão não encontrado na assinatura Asaas.");
                    $erros++;
                    continue;
                }

                $this->line("  Cartão: **** **** **** " . ($subscriptionData['last4'] ?: '????'));
                $this->line("  Forçando tentativa de débito...");

                // Força a cobrança usando o token do cartão
                $resultado = $this->asaasGateway->payWithCreditCardToken(
                    $pagamento->asaas_payment_id,
                    $creditCardToken
                );

                // Registra tentativa (incrementa contador e atualiza updated_at)
                $pagamento->tentativas = ((int) $pagamento->tentativas) + 1;
                $pagamento->save();

                $novoStatus = strtoupper((string) ($resultado['status'] ?? 'UNKNOWN'));

                if (in_array($novoStatus, ['CONFIRMED', 'RECEIVED'])) {
                    $this->info("  ✓ Débito realizado com sucesso! Status: {$novoStatus}");
                    $pagamento->status = AssinaturaPlanoPagamento::STATUS_PAGO;
                    $pagamento->data_pagamento = now();
                    $pagamento->save();
                    $sucesso++;
                } elseif (in_array($novoStatus, ['PENDING', 'AWAITING_RISK_ANALYSIS'])) {
                    $this->warn("  ⏳ Aguardando processamento. Status: {$novoStatus}");
                    $sucesso++;
                } else {
                    $this->error("  ⚠ Débito retornou: {$novoStatus}");
                    $erros++;
                }

                Log::info('Tentativa de débito de cartão processada', [
                    'id_empresa' => $pagamento->id_empresa,
                    'pagamento_id' => $pagamento->id,
                    'asaas_payment_id' => $pagamento->asaas_payment_id,
                    'status_resultado' => $novoStatus,
                ]);

            } catch (\Throwable $e) {
                $erros++;
                $this->error("  ✗ Erro: " . $e->getMessage());

                // Registra tentativa mesmo com erro
                $pagamento->tentativas = ((int) $pagamento->tentativas) + 1;
                $pagamento->save();

                Log::error('Erro ao forçar débito de cartão', [
                    'id_empresa' => $pagamento->id_empresa,
                    'pagamento_id' => $pagamento->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('=== RESUMO ===');
        $this->table(['Métrica', 'Valor'], [
            ['Cobranças encontradas', $pagamentos->count()],
            ['Processadas', $processadas],
            ['Sucesso', $sucesso],
            ['Erros', $erros],
            ['Ignoradas', $ignoradas],
        ]);

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }
}
