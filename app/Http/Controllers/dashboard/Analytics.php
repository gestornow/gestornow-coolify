<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoProduto;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\Manutencao;
use App\Domain\Cliente\Models\Cliente;
use App\Models\ContasAPagar;
use App\Models\ContasAReceber;
use App\Domain\Auth\Models\Empresa;
use App\Models\AssinaturaPlano;
use App\Models\Plano;
use App\Services\Billing\PlanoPromocaoService;
use App\Services\TesteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Analytics extends Controller
{
    public function __construct(
        private readonly PlanoPromocaoService $planoPromocaoService
    ) {
    }

    public function index()
    {
        // Configurar locale para português
        Carbon::setLocale('pt_BR');
        
        // Usar id_empresa da sessão (filial selecionada)
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa;
        $hoje = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        // Locações que iniciam hoje
        $locacoesIniciamHoje = Locacao::where('id_empresa', $idEmpresa)
            ->whereDate('data_inicio', $hoje)
            ->whereNotIn('status', ['cancelado', 'orcamento'])
            ->count();

        // Locações que terminam hoje
        $locacoesTerminamHoje = Locacao::where('id_empresa', $idEmpresa)
            ->whereDate('data_fim', $hoje)
            ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado'])
            ->count();

        // Locações em andamento
        $locacoesEmAndamento = Locacao::where('id_empresa', $idEmpresa)
            ->whereIn('status', ['aprovado', 'em_andamento', 'retirada'])
            ->count();

        // Locações atrasadas
        $locacoesAtrasadas = Locacao::where('id_empresa', $idEmpresa)
            ->where('status', 'atrasada')
            ->orWhere(function($query) use ($idEmpresa, $hoje) {
                $query->where('id_empresa', $idEmpresa)
                    ->whereDate('data_fim', '<', $hoje)
                    ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado']);
            })
            ->count();

        // Status de logística
        $logisticaParaSeparar = Locacao::where('id_empresa', $idEmpresa)
            ->where('status_logistica', 'para_separar')
            ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado'])
            ->count();

        $logisticaProntoPatio = Locacao::where('id_empresa', $idEmpresa)
            ->where('status_logistica', 'pronto_patio')
            ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado'])
            ->count();

        $logisticaEmRota = Locacao::where('id_empresa', $idEmpresa)
            ->where('status_logistica', 'em_rota')
            ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado'])
            ->count();

        $logisticaEntregue = Locacao::where('id_empresa', $idEmpresa)
            ->where('status_logistica', 'entregue')
            ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado'])
            ->count();

        $logisticaAguardandoColeta = Locacao::where('id_empresa', $idEmpresa)
            ->where('status_logistica', 'aguardando_coleta')
            ->whereNotIn('status', ['cancelado', 'orcamento', 'encerrado'])
            ->count();

        // Financeiro - Contas a Receber
        $contasReceberHoje = ContasAReceber::where('id_empresa', $idEmpresa)
            ->whereDate('data_vencimento', $hoje)
            ->where('status', '!=', 'pago')
            ->sum('valor_total');

        $contasReceberVencidas = ContasAReceber::where('id_empresa', $idEmpresa)
            ->whereDate('data_vencimento', '<', $hoje)
            ->where('status', '!=', 'pago')
            ->sum('valor_total');

        $contasReceberMes = ContasAReceber::where('id_empresa', $idEmpresa)
            ->whereBetween('data_vencimento', [$inicioMes, $fimMes])
            ->where('status', '!=', 'pago')
            ->sum('valor_total');

        $totalRecebidoMes = ContasAReceber::where('id_empresa', $idEmpresa)
            ->whereBetween('data_pagamento', [$inicioMes, $fimMes])
            ->where('status', 'pago')
            ->sum('valor_pago');

        // Financeiro - Contas a Pagar
        $contasPagarHoje = ContasAPagar::where('id_empresa', $idEmpresa)
            ->whereDate('data_vencimento', $hoje)
            ->where('status', '!=', 'pago')
            ->sum('valor_total');

        $contasPagarVencidas = ContasAPagar::where('id_empresa', $idEmpresa)
            ->whereDate('data_vencimento', '<', $hoje)
            ->where('status', '!=', 'pago')
            ->sum('valor_total');

        $contasPagarMes = ContasAPagar::where('id_empresa', $idEmpresa)
            ->whereBetween('data_vencimento', [$inicioMes, $fimMes])
            ->where('status', '!=', 'pago')
            ->sum('valor_total');

        $totalPagoMes = ContasAPagar::where('id_empresa', $idEmpresa)
            ->whereBetween('data_pagamento', [$inicioMes, $fimMes])
            ->where('status', 'pago')
            ->sum('valor_pago');

        // Manutenções em andamento
        $manutencoesEmAndamento = Manutencao::where('id_empresa', $idEmpresa)
            ->whereDate('data_manutencao', '<=', $hoje)
            ->where(function($query) use ($hoje) {
                $query->whereNull('data_previsao')
                    ->orWhereDate('data_previsao', '>=', $hoje);
            })
            ->where('status', 'em_andamento')
            ->with(['produto', 'patrimonio'])
            ->limit(10)
            ->get();

        $totalManutencoesAndamento = Manutencao::where('id_empresa', $idEmpresa)
            ->whereDate('data_manutencao', '<=', $hoje)
            ->where(function($query) use ($hoje) {
                $query->whereNull('data_previsao')
                    ->orWhereDate('data_previsao', '>=', $hoje);
            })
            ->where('status', 'em_andamento')
            ->count();

        // Top 5 Clientes que mais locam (por quantidade de locações)
        $topClientes = Cliente::where('clientes.id_empresa', $idEmpresa)
            ->join('locacao', 'clientes.id_clientes', '=', 'locacao.id_cliente')
            ->select('clientes.id_clientes', 'clientes.nome', 'clientes.cpf_cnpj', 
                DB::raw('COUNT(locacao.id_locacao) as total_locacoes'),
                DB::raw('SUM(locacao.valor_final) as valor_total'))
            ->whereNotIn('locacao.status', ['cancelado', 'orcamento'])
            ->groupBy('clientes.id_clientes', 'clientes.nome', 'clientes.cpf_cnpj')
            ->orderBy('total_locacoes', 'desc')
            ->limit(5)
            ->get();

        // Top 5 Produtos com mais saída (por quantidade locada)
        $topProdutos = Produto::where('produtos.id_empresa', $idEmpresa)
            ->join('produto_locacao', 'produtos.id_produto', '=', 'produto_locacao.id_produto')
            ->join('locacao', 'produto_locacao.id_locacao', '=', 'locacao.id_locacao')
            ->select('produtos.id_produto', 'produtos.nome', 'produtos.codigo',
                DB::raw('SUM(produto_locacao.quantidade) as total_quantidade'),
                DB::raw('COUNT(DISTINCT locacao.id_locacao) as total_locacoes'))
            ->whereNotIn('locacao.status', ['cancelado', 'orcamento'])
            ->groupBy('produtos.id_produto', 'produtos.nome', 'produtos.codigo')
            ->orderBy('total_quantidade', 'desc')
            ->limit(5)
            ->get();

        // Dados para gráfico de locações dos últimos 7 dias
        $locacoesUltimos7Dias = [];
        $labelsUltimos7Dias = [];
        for ($i = 6; $i >= 0; $i--) {
            $data = Carbon::today()->subDays($i);
            $labelsUltimos7Dias[] = $data->format('d/m');
            $locacoesUltimos7Dias[] = Locacao::where('id_empresa', $idEmpresa)
                ->whereDate('created_at', $data)
                ->whereNotIn('status', ['cancelado'])
                ->count();
        }

        // Dados para gráfico de faturamento dos últimos 6 meses
        $faturamentoUltimos6Meses = [];
        $labelsUltimos6Meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = Carbon::now()->subMonths($i);
            $labelsUltimos6Meses[] = $mes->translatedFormat('M');
            $faturamentoUltimos6Meses[] = Locacao::where('id_empresa', $idEmpresa)
                ->whereYear('data_inicio', $mes->year)
                ->whereMonth('data_inicio', $mes->month)
                ->whereNotIn('status', ['cancelado', 'orcamento'])
                ->sum('valor_final');
        }

        // Total de locações do mês
        $locacoesMes = Locacao::where('id_empresa', $idEmpresa)
            ->whereBetween('data_inicio', [$inicioMes, $fimMes])
            ->whereNotIn('status', ['cancelado', 'orcamento'])
            ->count();

        $faturamentoMes = Locacao::where('id_empresa', $idEmpresa)
            ->whereBetween('data_inicio', [$inicioMes, $fimMes])
            ->whereNotIn('status', ['cancelado', 'orcamento'])
            ->sum('valor_final');

        // Comparativo com mês anterior
        $mesAnteriorInicio = Carbon::now()->subMonth()->startOfMonth();
        $mesAnteriorFim = Carbon::now()->subMonth()->endOfMonth();

        $locacoesMesAnterior = Locacao::where('id_empresa', $idEmpresa)
            ->whereBetween('data_inicio', [$mesAnteriorInicio, $mesAnteriorFim])
            ->whereNotIn('status', ['cancelado', 'orcamento'])
            ->count();

        $faturamentoMesAnterior = Locacao::where('id_empresa', $idEmpresa)
            ->whereBetween('data_inicio', [$mesAnteriorInicio, $mesAnteriorFim])
            ->whereNotIn('status', ['cancelado', 'orcamento'])
            ->sum('valor_final');

        // Calcular variações percentuais
        $variacaoLocacoes = $locacoesMesAnterior > 0 
            ? round((($locacoesMes - $locacoesMesAnterior) / $locacoesMesAnterior) * 100, 1) 
            : 0;

        $variacaoFaturamento = $faturamentoMesAnterior > 0 
            ? round((($faturamentoMes - $faturamentoMesAnterior) / $faturamentoMesAnterior) * 100, 1) 
            : 0;

        // Dados da empresa
        $empresa = Empresa::find($idEmpresa);
        
        // Logo da empresa
        $configuracoes = is_array($empresa->configuracoes) ? $empresa->configuracoes : [];
        $logoUrl = trim((string) ($configuracoes['logo_url'] ?? ''));

        // Meses em português para garantir tradução
        $mesesPtBr = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        
        $dataHoje = $hoje->format('d') . ' de ' . $mesesPtBr[$hoje->month] . ' de ' . $hoje->format('Y');
        $mesAno = $mesesPtBr[Carbon::now()->month] . ' ' . Carbon::now()->format('Y');

        // Verificar se empresa está em período de teste ou precisa escolher plano
        $emTeste = false;
        $testeBloqueado = false;
        $semPlanoAtivo = false;
        $bloqueadoPorInadimplencia = false;
        $diasRestantesTeste = 0;
        $planos = collect();
        $assinaturaAtual = null;
        $assinaturaPendentePagamento = false;
        $onboardingDadosPendente = false;
        $onboardingContratoPendente = false;
        
        if ($empresa) {
            if (Schema::hasTable('assinaturas_planos')) {
                $assinaturaAtual = AssinaturaPlano::query()
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
                    ->latest('id')
                    ->first();

                if ($assinaturaAtual) {
                    $assinaturaPendentePagamento = $assinaturaAtual->status === AssinaturaPlano::STATUS_PENDENTE_PAGAMENTO;
                    $onboardingDadosPendente = $assinaturaAtual->status === AssinaturaPlano::STATUS_ONBOARDING_DADOS;
                    $onboardingContratoPendente = $assinaturaAtual->status === AssinaturaPlano::STATUS_ONBOARDING_CONTRATO;
                    $bloqueadoPorInadimplencia = (bool) $assinaturaAtual->bloqueada_por_inadimplencia;
                }
            }

            // Empresa em teste
            if ($empresa->status === 'teste') {
                $emTeste = true;
                
                // Calcular dias restantes
                $dataFimTeste = $empresa->data_fim_teste
                    ? Carbon::parse($empresa->data_fim_teste)
                    : Carbon::parse($empresa->created_at ?? now())->addDays(TesteService::DIAS_TESTE);

                $segundos = now()->diffInSeconds($dataFimTeste, false);
                $diasRestantesTeste = max(0, (int) ceil($segundos / 86400));
            }
            
            // Teste expirado / bloqueado (não por inadimplência)
            if (in_array($empresa->status, ['teste bloqueado', 'bloqueado']) && !$bloqueadoPorInadimplencia) {
                $testeBloqueado = true;
            }
            
            // Empresa ativa mas sem plano
            if ($empresa->status === 'ativo' && method_exists($empresa, 'semPlanoAtivo') && $empresa->semPlanoAtivo()) {
                $semPlanoAtivo = true;
            }
            
            // Se qualquer condição acima, buscar planos disponíveis
            if ($emTeste || $testeBloqueado || $semPlanoAtivo) {
                $planosQuery = Plano::where('ativo', 1)
                    ->whereNotIn('nome', ['Plano Gestor', 'Gestor']);

                if (Schema::hasColumn('planos', 'ordem')) {
                    $planosQuery->orderBy('ordem', 'asc');
                }

                $planos = $planosQuery
                    ->with(['modulos.modulo.moduloPai'])
                    ->orderBy('valor', 'asc')
                    ->orderBy('nome', 'asc')
                    ->get()
                    ->map(function (Plano $plano) use ($empresa) {
                        $precosPromocionais = $this->planoPromocaoService->calcularValoresPromocionais($plano, $empresa);

                        $plano->setAttribute(
                            'valor_exibicao',
                            (float) ($precosPromocionais['valor_mensal_final'] ?? $plano->valor ?? 0)
                        );
                        $plano->setAttribute(
                            'adesao_exibicao',
                            (float) ($precosPromocionais['valor_adesao_final'] ?? $plano->adesao ?? 0)
                        );
                        $plano->setAttribute('precos_promocionais', $precosPromocionais);

                        return $plano;
                    })
                    ->values();
            }
        }

        return view('content.dashboard.dashboards-analytics', compact(
            'locacoesIniciamHoje',
            'locacoesTerminamHoje',
            'locacoesEmAndamento',
            'locacoesAtrasadas',
            'logisticaParaSeparar',
            'logisticaProntoPatio',
            'logisticaEmRota',
            'logisticaEntregue',
            'logisticaAguardandoColeta',
            'contasReceberHoje',
            'contasReceberVencidas',
            'contasReceberMes',
            'totalRecebidoMes',
            'contasPagarHoje',
            'contasPagarVencidas',
            'contasPagarMes',
            'totalPagoMes',
            'manutencoesEmAndamento',
            'totalManutencoesAndamento',
            'topClientes',
            'topProdutos',
            'locacoesUltimos7Dias',
            'labelsUltimos7Dias',
            'faturamentoUltimos6Meses',
            'labelsUltimos6Meses',
            'locacoesMes',
            'faturamentoMes',
            'variacaoLocacoes',
            'variacaoFaturamento',
            'empresa',
            'logoUrl',
            'dataHoje',
            'mesAno',
            'emTeste',
            'testeBloqueado',
            'semPlanoAtivo',
            'bloqueadoPorInadimplencia',
            'diasRestantesTeste',
            'planos',
            'assinaturaAtual',
            'assinaturaPendentePagamento',
            'onboardingDadosPendente',
            'onboardingContratoPendente'
        ));
    }
}
