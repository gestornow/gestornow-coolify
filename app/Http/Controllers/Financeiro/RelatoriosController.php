<?php

namespace App\Http\Controllers\Financeiro;

use App\Facades\Perm;
use App\Http\Controllers\Controller;
use App\Models\ContasAReceber;
use App\Models\ContasAPagar;
use App\Domain\Cliente\Models\Cliente;
use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoDespesa;
use App\Models\Fornecedor;
use App\Models\CategoriaContas;
use App\Models\Banco;
use App\Models\FormaPagamento;
use App\Models\RegistroAtividade;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RelatoriosController extends Controller
{
    // ============================================
    // CONTAS A RECEBER
    // ============================================
    
    public function contasReceberIndex()
    {
        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $clientes = Cliente::where('id_empresa', $id_empresa)
            ->orderBy('razao_social')
            ->get();
            
        $categorias = CategoriaContas::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();
            
        $bancos = Banco::where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();
            
        $formasPagamento = FormaPagamento::orderBy('nome')->get();
        
        $usuarios = User::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();
        
        return view('financeiro.relatorios.contas-a-receber', compact(
            'clientes',
            'categorias',
            'bancos',
            'formasPagamento',
            'usuarios'
        ));
    }
    
    public function contasReceberGerar(Request $request)
    {
        $dados = $this->buscarContasReceber($request);
        return view('financeiro.relatorios.visualizar-contas-receber', $dados);
    }
    
    public function contasReceberPDF(Request $request)
    {
        $dados = $this->buscarContasReceber($request);
        
        // Formatar dados para JSON
        $contas_formatadas = $dados['contas']->map(function($conta) {
            return [
                'data_vencimento' => $conta->data_vencimento ? date('d/m/Y', strtotime($conta->data_vencimento)) : '-',
                'descricao' => $conta->descricao ?? '-',
                'cliente' => $conta->cliente->razao_social ?? '-',
                'documento' => $conta->documento ?? '-',
                'categoria' => $conta->categoria->nome ?? '-',
                'banco' => $conta->banco->nome_banco ?? '-',
                'forma_pagamento' => $conta->formaPagamento->nome ?? '-',
                'valor_total' => $conta->valor_total,
                'valor_pago' => $conta->valor_pago ?: 0,
                'valor_restante' => $conta->valor_total - ($conta->valor_pago ?: 0),
                'status' => $conta->status_label,
                'observacoes' => $conta->observacoes ?? ''
            ];
        });
        
        return response()->json([
            'contas' => $contas_formatadas,
            'totais' => [
                'total_geral' => $dados['total_geral'],
                'total_pago' => $dados['total_pago'],
                'total_restante' => $dados['total_restante'],
                'total_pendente' => $dados['total_pendente'],
                'total_vencido' => $dados['total_vencido']
            ],
            'filtros' => $dados['filtros'],
            'data_geracao' => date('d/m/Y H:i:s')
        ]);
    }
    
    public function contasReceberExcel(Request $request)
    {
        $dados = $this->buscarContasReceber($request);
        
        // Formatar dados para JSON
        $contas_formatadas = $dados['contas']->map(function($conta) {
            return [
                'data_vencimento' => $conta->data_vencimento ? date('d/m/Y', strtotime($conta->data_vencimento)) : '-',
                'descricao' => $conta->descricao ?? '-',
                'cliente' => $conta->cliente->razao_social ?? '-',
                'documento' => $conta->documento ?? '-',
                'categoria' => $conta->categoria->nome ?? '-',
                'banco' => $conta->banco->nome_banco ?? '-',
                'forma_pagamento' => $conta->formaPagamento->nome ?? '-',
                'valor_total' => $conta->valor_total,
                'valor_pago' => $conta->valor_pago ?: 0,
                'valor_restante' => $conta->valor_total - ($conta->valor_pago ?: 0),
                'status' => $conta->status_label,
                'observacoes' => $conta->observacoes ?? ''
            ];
        });
        
        return response()->json([
            'contas' => $contas_formatadas,
            'totais' => [
                'total_geral' => $dados['total_geral'],
                'total_pago' => $dados['total_pago'],
                'total_restante' => $dados['total_restante'],
                'total_pendente' => $dados['total_pendente'],
                'total_vencido' => $dados['total_vencido']
            ],
            'filtros' => $dados['filtros'],
            'data_geracao' => date('d/m/Y H:i:s')
        ]);
    }
    
    private function buscarContasReceber(Request $request)
    {
        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $query = ContasAReceber::with(['cliente', 'categoria', 'banco', 'formaPagamento', 'usuario'])
            ->where('id_empresa', $id_empresa);
        
        // Filtros
        $this->aplicarFiltros($query, $request, 'id_cliente');
        
        // Agrupamento
        $agrupar_por = $request->input('agrupar_por');
        
        // Ordenação
        $ordenar_por = $request->input('ordenar_por', 'data_vencimento');
        $ordem = $request->input('ordem', 'asc');
        $query->orderBy($ordenar_por, $ordem);
        
        $contas = $query->get();
        
        // Calcular totais
        $totais = $this->calcularTotais($contas);
        
        return array_merge([
            'contas' => $contas,
            'filtros' => $request->all(),
            'agrupar_por' => $agrupar_por,
        ], $totais);
    }
    
    // ============================================
    // CONTAS A PAGAR
    // ============================================
    
    public function contasPagarIndex()
    {
        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $fornecedores = Fornecedor::where('id_empresa', $id_empresa)
            ->orderBy('razao_social')
            ->get();
            
        $categorias = CategoriaContas::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();
            
        $bancos = Banco::where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();
            
        $formasPagamento = FormaPagamento::orderBy('nome')->get();
        
        $usuarios = User::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();
        
        return view('financeiro.relatorios.contas-a-pagar', compact(
            'fornecedores',
            'categorias',
            'bancos',
            'formasPagamento',
            'usuarios'
        ));
    }
    
    public function contasPagarGerar(Request $request)
    {
        $dados = $this->buscarContasPagar($request);
        return view('financeiro.relatorios.visualizar-contas-pagar', $dados);
    }
    
    public function contasPagarPDF(Request $request)
    {
        $dados = $this->buscarContasPagar($request);
        
        // Formatar dados para JSON
        $contas_formatadas = $dados['contas']->map(function($conta) {
            return [
                'data_vencimento' => $conta->data_vencimento ? date('d/m/Y', strtotime($conta->data_vencimento)) : '-',
                'descricao' => $conta->descricao ?? '-',
                'fornecedor' => $conta->fornecedor->razao_social ?? '-',
                'documento' => $conta->documento ?? '-',
                'categoria' => $conta->categoria->nome ?? '-',
                'banco' => $conta->banco->nome_banco ?? '-',
                'forma_pagamento' => $conta->formaPagamento->nome ?? '-',
                'valor_total' => $conta->valor_total,
                'valor_pago' => $conta->valor_pago ?: 0,
                'valor_restante' => $conta->valor_total - ($conta->valor_pago ?: 0),
                'status' => $conta->status_label,
                'observacoes' => $conta->observacoes ?? ''
            ];
        });
        
        return response()->json([
            'contas' => $contas_formatadas,
            'totais' => [
                'total_geral' => $dados['total_geral'],
                'total_pago' => $dados['total_pago'],
                'total_restante' => $dados['total_restante'],
                'total_pendente' => $dados['total_pendente'],
                'total_vencido' => $dados['total_vencido']
            ],
            'filtros' => $dados['filtros'],
            'data_geracao' => date('d/m/Y H:i:s')
        ]);
    }
    
    public function contasPagarExcel(Request $request)
    {
        $dados = $this->buscarContasPagar($request);
        
        // Formatar dados para JSON
        $contas_formatadas = $dados['contas']->map(function($conta) {
            return [
                'data_vencimento' => $conta->data_vencimento ? date('d/m/Y', strtotime($conta->data_vencimento)) : '-',
                'descricao' => $conta->descricao ?? '-',
                'fornecedor' => $conta->fornecedor->razao_social ?? '-',
                'documento' => $conta->documento ?? '-',
                'categoria' => $conta->categoria->nome ?? '-',
                'banco' => $conta->banco->nome_banco ?? '-',
                'forma_pagamento' => $conta->formaPagamento->nome ?? '-',
                'valor_total' => $conta->valor_total,
                'valor_pago' => $conta->valor_pago ?: 0,
                'valor_restante' => $conta->valor_total - ($conta->valor_pago ?: 0),
                'status' => $conta->status_label,
                'observacoes' => $conta->observacoes ?? ''
            ];
        });
        
        return response()->json([
            'contas' => $contas_formatadas,
            'totais' => [
                'total_geral' => $dados['total_geral'],
                'total_pago' => $dados['total_pago'],
                'total_restante' => $dados['total_restante'],
                'total_pendente' => $dados['total_pendente'],
                'total_vencido' => $dados['total_vencido']
            ],
            'filtros' => $dados['filtros'],
            'data_geracao' => date('d/m/Y H:i:s')
        ]);
    }
    
    private function buscarContasPagar(Request $request)
    {
        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $query = ContasAPagar::with(['fornecedor', 'categoria', 'banco', 'formaPagamento', 'usuario'])
            ->where('id_empresa', $id_empresa);
        
        // Filtros
        $this->aplicarFiltros($query, $request, 'id_fornecedor');
        
        // Agrupamento
        $agrupar_por = $request->input('agrupar_por');
        
        // Ordenação
        $ordenar_por = $request->input('ordenar_por', 'data_vencimento');
        $ordem = $request->input('ordem', 'asc');
        $query->orderBy($ordenar_por, $ordem);
        
        $contas = $query->get();
        
        // Calcular totais
        $totais = $this->calcularTotais($contas);
        
        return array_merge([
            'contas' => $contas,
            'filtros' => $request->all(),
            'agrupar_por' => $agrupar_por,
        ], $totais);
    }
    
    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================
    
    private function aplicarFiltros($query, Request $request, $campoClienteFornecedor)
    {
        // Filtro de data
        $tipo_data = $request->input('tipo_data', 'vencimento');
        $campoData = $tipo_data === 'emissao' ? 'data_emissao' 
            : ($tipo_data === 'pagamento' ? 'data_pagamento' : 'data_vencimento');
        
        if ($request->filled('data_inicio')) {
            $query->where($campoData, '>=', $request->data_inicio);
        }
        
        if ($request->filled('data_fim')) {
            $query->where($campoData, '<=', $request->data_fim);
        }
        
        // Filtro de status com lógica especial
        if ($request->filled('status')) {
            $status = $request->status;
            
            // Status "vencido" - não é um status armazenado, mas calculado
            if ($status === 'vencido') {
                $query->where('status', '!=', 'pago')
                      ->where('status', '!=', 'cancelado')
                      ->where('data_vencimento', '<', now()->toDateString());
            } 
            // Status "parcelado" - filtrar por contas que têm id_parcelamento
            elseif ($status === 'parcelado') {
                $query->whereNotNull('id_parcelamento');
            }
            // Outros status - filtro direto
            else {
                $query->where('status', $status);
            }
        }
        
        if ($request->filled($campoClienteFornecedor)) {
            $query->where($campoClienteFornecedor, $request->input($campoClienteFornecedor));
        }
        
        if ($request->filled('id_categoria_contas')) {
            $query->where('id_categoria_contas', $request->id_categoria_contas);
        }
        
        if ($request->filled('id_bancos')) {
            $query->where('id_bancos', $request->id_bancos);
        }
        
        if ($request->filled('id_forma_pagamento')) {
            $query->where('id_forma_pagamento', $request->id_forma_pagamento);
        }
        
        if ($request->filled('id_usuario')) {
            $query->where('id_usuario', $request->id_usuario);
        }
        
        if ($request->filled('documento')) {
            $query->where('documento', 'like', '%'.$request->documento.'%');
        }
    }
    
    private function calcularTotais($contas)
    {
        $total_geral = $contas->sum('valor_total');
        $total_pago = $contas->sum('valor_pago');
        $total_restante = $total_geral - $total_pago;
        
        $total_pendente = $contas->where('status', 'pendente')->sum('valor_total');
        $total_vencido = $contas->where('status', 'vencido')->sum('valor_total');
        
        return compact('total_geral', 'total_pago', 'total_restante', 'total_pendente', 'total_vencido');
    }
    
    // ============================================
    // NOVOS RELATÓRIOS - GESTÃO DE LOCAÇÕES
    // ============================================
    
    /**
     * Dashboard de KPIs
     */
    public function dashboardKPIsIndex()
    {
        return view('financeiro.relatorios.dashboard-kpis');
    }
    
    public function dashboardKPIsDados(Request $request)
    {
        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $periodo = $request->input('periodo', 'mes');
        
        // Calcular KPIs baseado em Locações
        $locacoes_ativas = Locacao::where('id_empresa', $id_empresa)
            ->whereIn('status', ['aprovado', 'retirada', 'em_andamento'])
            ->count();
        
        $locacoes_atrasadas = Locacao::where('id_empresa', $id_empresa)
            ->where('status', 'atrasada')
            ->count();
        
        $receita_mes_atual = Locacao::where('id_empresa', $id_empresa)
            ->whereIn('status', ['aprovado', 'retirada', 'em_andamento', 'encerrado'])
            ->whereMonth('data_inicio', now()->month)
            ->whereYear('data_inicio', now()->year)
            ->sum('valor_final');
        
        // Taxa de inadimplência (contas vencidas / total contas)
        $total_contas = ContasAReceber::where('id_empresa', $id_empresa)
            ->whereMonth('data_vencimento', now()->month)
            ->count();
        $contas_vencidas = ContasAReceber::where('id_empresa', $id_empresa)
            ->where('status', 'vencido')
            ->whereMonth('data_vencimento', now()->month)
            ->count();
        $taxa_inadimplencia = $total_contas > 0 ? ($contas_vencidas / $total_contas) * 100 : 0;
        
        $kpis = [
            'total_locacoes_ativas' => $locacoes_ativas,
            'locacoes_ativas_variacao' => 5.2, // TODO: Calcular variação real comparando com mês anterior
            'taxa_inadimplencia' => round($taxa_inadimplencia, 1),
            'taxa_inadimplencia_variacao' => -2.1,
            'receita_mensal' => $receita_mes_atual,
            'receita_variacao' => 8.5,
            'locacoes_atrasadas' => $locacoes_atrasadas,
            'atrasadas_variacao' => -3.2
        ];
        
        // Resumo financeiro
        $total_recebido = ContasAReceber::where('id_empresa', $id_empresa)
            ->where('status', 'pago')
            ->whereMonth('data_pagamento', now()->month)
            ->sum('valor_pago');
        
        $total_pago = ContasAPagar::where('id_empresa', $id_empresa)
            ->where('status', 'pago')
            ->whereMonth('data_pagamento', now()->month)
            ->sum('valor_pago');
        
        $total_pendente = ContasAReceber::where('id_empresa', $id_empresa)
            ->whereIn('status', ['pendente', 'parcialmente_pago'])
            ->whereMonth('data_vencimento', now()->month)
            ->sum('valor_total');
        
        $resumo = [
            'total_recebido' => $total_recebido,
            'total_pago' => $total_pago,
            'saldo_periodo' => $total_recebido - $total_pago,
            'total_pendente' => $total_pendente
        ];
        
        // Fluxo de caixa (últimos 6 meses) - usando tabela fluxo_caixa
        $fluxo_ultimos_6_meses = DB::table('fluxo_caixa')
            ->where('id_empresa', $id_empresa)
            ->where('data_movimentacao', '>=', now()->subMonths(6)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(data_movimentacao, '%b') as mes"),
                DB::raw("SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas"),
                DB::raw("SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saidas")
            )
            ->groupBy('mes')
            ->orderBy(DB::raw("DATE_FORMAT(data_movimentacao, '%Y-%m')"))
            ->get();
        
        $meses = [];
        $entradas = [];
        $saidas = [];
        $saldo = [];
        $saldo_acumulado = 0;
        
        foreach ($fluxo_ultimos_6_meses as $item) {
            $meses[] = $item->mes;
            $entradas[] = floatval($item->entradas);
            $saidas[] = floatval($item->saidas);
            $saldo_acumulado += (floatval($item->entradas) - floatval($item->saidas));
            $saldo[] = $saldo_acumulado;
        }
        
        $fluxo_caixa = [
            'meses' => $meses,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $saldo
        ];
        
        // Status de recebimentos
        $recebido = ContasAReceber::where('id_empresa', $id_empresa)
            ->where('status', 'pago')
            ->whereMonth('data_vencimento', now()->month)
            ->sum('valor_pago');
        
        $a_vencer = ContasAReceber::where('id_empresa', $id_empresa)
            ->where('status', 'pendente')
            ->where('data_vencimento', '>', now())
            ->whereMonth('data_vencimento', now()->month)
            ->sum('valor_total');
        
        $vencido = ContasAReceber::where('id_empresa', $id_empresa)
            ->where('status', 'vencido')
            ->whereMonth('data_vencimento', now()->month)
            ->sum('valor_total');
        
        $inadimplente = ContasAReceber::where('id_empresa', $id_empresa)
            ->where('status', 'vencido')
            ->where('data_vencimento', '<', now()->subDays(30))
            ->sum('valor_total');
        
        $recebimentos = [
            'recebido' => $recebido,
            'a_vencer' => $a_vencer,
            'vencido' => $vencido,
            'inadimplente' => $inadimplente
        ];
        
        // Top 5 locações por valor (no último mês)
        $top_locacoes = Locacao::where('id_empresa', $id_empresa)
            ->whereIn('status', ['aprovado', 'retirada', 'em_andamento', 'encerrado'])
            ->where('data_inicio', '>=', now()->subMonth())
            ->with('cliente')
            ->orderBy('valor_final', 'desc')
            ->limit(5)
            ->get()
            ->map(function($locacao) {
                return [
                    'nome' => $locacao->numero_contrato,
                    'cliente' => $locacao->cliente->nome ?? 'Cliente não identificado',
                    'valor' => $locacao->valor_final,
                    'periodo' => ($locacao->data_inicio ? $locacao->data_inicio->format('d/m') : '-') . ' a ' . ($locacao->data_fim ? $locacao->data_fim->format('d/m') : '-')
                ];
            });
        
        // Alertas
        $alertas = [];
        
        // Locações encerrando em 30 dias
        $encerrando = Locacao::where('id_empresa', $id_empresa)
            ->whereBetween('data_fim', [now(), now()->addDays(30)])
            ->whereIn('status', ['aprovado', 'retirada', 'em_andamento'])
            ->count();
        
        if ($encerrando > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'mensagem' => "$encerrando locação(ões) encerrando nos próximos 30 dias",
                'link' => null
            ];
        }
        
        // Contas vencidas
        if ($contas_vencidas > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'mensagem' => "$contas_vencidas recebimento(s) vencido(s) neste mês",
                'link' => route('relatorios.recebimentos-status')
            ];
        }
        
        return response()->json([
            'kpis' => $kpis,
            'resumo' => $resumo,
            'fluxo_caixa' => $fluxo_caixa,
            'recebimentos' => $recebimentos,
            'top_propriedades' => $top_locacoes, // Mantido nome original para compatibilidade com frontend
            'alertas' => $alertas
        ]);
    }
    
    /**
     * Fluxo de Caixa Consolidado
     */
    public function fluxoCaixaIndex()
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.fluxo-caixa'), 403);

        $id_empresa = session('id_empresa');
        
        // Buscar bancos da empresa
        $bancos = DB::table('bancos')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();

        $clientes = Cliente::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $fornecedores = Fornecedor::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $categoriasDespesa = CategoriaContas::where('id_empresa', $id_empresa)
            ->where('tipo', 'despesa')
            ->orderBy('nome')
            ->get();

        $categoriasReceita = CategoriaContas::where('id_empresa', $id_empresa)
            ->where('tipo', 'receita')
            ->orderBy('nome')
            ->get();

        $formasPagamento = FormaPagamento::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();
        
        return view('financeiro.fluxo-caixa.index', compact(
            'bancos',
            'clientes',
            'fornecedores',
            'categoriasDespesa',
            'categoriasReceita',
            'formasPagamento'
        ));
    }
    
    public function fluxoCaixaDados(Request $request)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.fluxo-caixa'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $data_inicio = $request->input('data_inicio');
        $data_fim = $request->input('data_fim');
        $tipo_visualizacao = $request->input('tipo_visualizacao', 'mensal');
        $saldo_inicial = floatval(str_replace(['.', ','], ['', '.'], $request->input('saldo_inicial', '0')));
        
        // Buscar movimentações REAIS da tabela fluxo_caixa (contas pagas/recebidas)
        $movimentacoes = DB::table('fluxo_caixa')
            ->where('id_empresa', $id_empresa)
            ->whereBetween('data_movimentacao', [$data_inicio, $data_fim])
            ->orderBy('data_movimentacao')
            ->get();
        
        // Calcular totais
        $total_entradas = $movimentacoes->where('tipo', 'entrada')->sum('valor');
        $total_saidas = $movimentacoes->where('tipo', 'saida')->sum('valor');
        $saldo_final = $saldo_inicial + $total_entradas - $total_saidas;
        
        // Agrupar por período conforme tipo de visualização
        $dados_agrupados = $this->agruparMovimentacoesPorPeriodo($movimentacoes, $tipo_visualizacao);
        
        // Calcular saldo acumulado
        $saldo_acumulado = $saldo_inicial;
        $periodos = [];
        $valores_entradas = [];
        $valores_saidas = [];
        $valores_saldo = [];
        
        foreach ($dados_agrupados as $item) {
            $periodos[] = $item['periodo'];
            $valores_entradas[] = $item['entradas'];
            $valores_saidas[] = $item['saidas'];
            $saldo_acumulado += ($item['entradas'] - $item['saidas']);
            $valores_saldo[] = $saldo_acumulado;
        }
        
        // Dados do gráfico de fluxo
        $fluxo = [
            'periodos' => $periodos,
            'entradas' => $valores_entradas,
            'saidas' => $valores_saidas,
            'saldo' => $valores_saldo
        ];
        
        // Categorização REAL de entradas (agrupadas por categoria)
        $entradas_por_categoria = DB::table('fluxo_caixa')
            ->leftJoin('categoria_contas', 'fluxo_caixa.id_categoria_fluxo', '=', 'categoria_contas.id_categoria_contas')
            ->where('fluxo_caixa.id_empresa', $id_empresa)
            ->where('fluxo_caixa.tipo', 'entrada')
            ->whereBetween('fluxo_caixa.data_movimentacao', [$data_inicio, $data_fim])
            ->select(
                DB::raw('COALESCE(categoria_contas.nome, "Sem Categoria") as categoria'),
                DB::raw('SUM(fluxo_caixa.valor) as total')
            )
            ->groupBy('categoria_contas.nome')
            ->get();
        
        $entradas_cat = [
            'categorias' => $entradas_por_categoria->pluck('categoria')->toArray(),
            'valores' => $entradas_por_categoria->pluck('total')->toArray()
        ];
        
        // Categorização REAL de saídas (agrupadas por categoria)
        $saidas_por_categoria = DB::table('fluxo_caixa')
            ->leftJoin('categoria_contas', 'fluxo_caixa.id_categoria_fluxo', '=', 'categoria_contas.id_categoria_contas')
            ->where('fluxo_caixa.id_empresa', $id_empresa)
            ->where('fluxo_caixa.tipo', 'saida')
            ->whereBetween('fluxo_caixa.data_movimentacao', [$data_inicio, $data_fim])
            ->select(
                DB::raw('COALESCE(categoria_contas.nome, "Sem Categoria") as categoria'),
                DB::raw('SUM(fluxo_caixa.valor) as total')
            )
            ->groupBy('categoria_contas.nome')
            ->get();
        
        $saidas_cat = [
            'categorias' => $saidas_por_categoria->pluck('categoria')->toArray(),
            'valores' => $saidas_por_categoria->pluck('total')->toArray()
        ];
        
        // Lançamentos detalhados REAIS (últimos 100)
        $lancamentos_raw = DB::table('fluxo_caixa')
            ->leftJoin('categoria_contas', 'fluxo_caixa.id_categoria_fluxo', '=', 'categoria_contas.id_categoria_contas')
            ->leftJoin('forma_pagamento', 'fluxo_caixa.id_forma_pagamento', '=', 'forma_pagamento.id_forma_pagamento')
            ->leftJoin('bancos', 'fluxo_caixa.id_bancos', '=', 'bancos.id_bancos')
            ->where('fluxo_caixa.id_empresa', $id_empresa)
            ->whereBetween('fluxo_caixa.data_movimentacao', [$data_inicio, $data_fim])
            ->select(
                'fluxo_caixa.id_fluxo',
                'fluxo_caixa.data_movimentacao as data',
                'fluxo_caixa.descricao',
                DB::raw('COALESCE(categoria_contas.nome, "Sem Categoria") as categoria'),
                'fluxo_caixa.tipo',
                'fluxo_caixa.valor',
                'fluxo_caixa.id_conta_pagar',
                'fluxo_caixa.id_conta_receber',
                'fluxo_caixa.referencia_tipo',
                'fluxo_caixa.referencia_id',
                'forma_pagamento.nome as forma_pagamento',
                'bancos.nome_banco as banco'
            )
            ->orderBy('fluxo_caixa.data_movimentacao', 'desc')
            ->limit(100)
            ->get();
        
        $lancamentos = $lancamentos_raw->map(function($item) {
            return [
                'id_fluxo_caixa' => $item->id_fluxo,
                'data' => $item->data,
                'descricao' => $item->descricao,
                'categoria' => $item->categoria,
                'tipo' => $item->tipo,
                'valor' => floatval($item->valor),
                'id_conta_pagar' => $item->id_conta_pagar,
                'id_conta_receber' => $item->id_conta_receber,
                'referencia_tipo' => $item->referencia_tipo,
                'referencia_id' => $item->referencia_id,
                'forma_pagamento' => $item->forma_pagamento,
                'banco' => $item->banco
            ];
        })->toArray();
        
        // Comparativo (se solicitado)
        $comparativo = null;
        if ($request->input('comparar_periodo')) {
            $comparativo = [
                'atual' => [
                    'entradas' => $total_entradas,
                    'saidas' => $total_saidas,
                    'saldo' => $saldo_final
                ],
                'comparado' => [
                    'entradas' => 0,
                    'saidas' => 0,
                    'saldo' => 0
                ],
                'variacao' => [
                    'entradas' => 0,
                    'saidas' => 0,
                    'saldo' => 0
                ]
            ];
        }
        
        return response()->json([
            'resumo' => [
                'saldo_inicial' => $saldo_inicial,
                'total_entradas' => $total_entradas,
                'total_saidas' => $total_saidas,
                'saldo_final' => $saldo_final
            ],
            'fluxo' => $fluxo,
            'entradas' => $entradas_cat,
            'saidas' => $saidas_cat,
            'lancamentos' => $lancamentos,
            'comparativo' => $comparativo
        ]);
    }

    /**
     * Retorna o log de atividades de um lançamento do fluxo de caixa
     */
    public function fluxoCaixaLogsAtividades(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.fluxo-caixa'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $lancamento = DB::table('fluxo_caixa')
                ->where('id_fluxo', $id)
                ->first();

            if (!$lancamento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lançamento não encontrado.'
                ], 404);
            }

            if ((int) $lancamento->id_empresa !== (int) $idEmpresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar os logs deste lançamento.'
                ], 403);
            }

            $logs = RegistroAtividade::query()
                ->where('id_empresa', $idEmpresa)
                ->where('entidade_tipo', 'fluxo_caixa')
                ->where('entidade_id', (int) $lancamento->id_fluxo)
                ->orderByDesc('ocorrido_em')
                ->limit(50)
                ->get([
                    'id_registro',
                    'acao',
                    'descricao',
                    'nome_responsavel',
                    'email_responsavel',
                    'contexto',
                    'antes',
                    'depois',
                    'icone',
                    'cor',
                    'tags',
                    'ocorrido_em',
                ]);

            return response()->json([
                'success' => true,
                'lancamento' => [
                    'id_fluxo' => (int) $lancamento->id_fluxo,
                    'descricao' => $lancamento->descricao,
                    'tipo' => $lancamento->tipo,
                    'valor' => (float) $lancamento->valor,
                ],
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar log de atividades do fluxo de caixa', [
                'id_fluxo' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar log de atividades: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    public function fluxoCaixaPDF(Request $request)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.fluxo-caixa'), 403);

        $dados = $this->buscarDadosFluxoCaixa($request);
        
        // Formatar lançamentos para PDF
        $lancamentos_formatados = $dados['lancamentos']->map(function($lanc) {
            return [
                'data' => date('d/m/Y', strtotime($lanc->data_movimentacao)),
                'descricao' => $lanc->descricao ?? '-',
                'categoria' => $lanc->categoria ?? 'Sem Categoria',
                'tipo' => $lanc->tipo,
                'valor' => floatval($lanc->valor),
                'forma_pagamento' => $lanc->forma_pagamento ?? '-',
                'banco' => $lanc->banco ?? '-',
                'saldo' => 0
            ];
        });
        
        return response()->json([
            'resumo' => [
                'saldo_inicial' => floatval($dados['saldo_inicial']),
                'total_entradas' => floatval($dados['total_entradas']),
                'total_saidas' => floatval($dados['total_saidas']),
                'saldo_final' => floatval($dados['saldo_final'])
            ],
            'lancamentos' => $lancamentos_formatados,
            'filtros' => $dados['filtros'],
            'data_geracao' => date('d/m/Y H:i:s')
        ]);
    }
    
    private function buscarDadosFluxoCaixa(Request $request)
    {
        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $data_inicio = $request->input('data_inicio');
        $data_fim = $request->input('data_fim');
        $tipo_visualizacao = $request->input('tipo_visualizacao', 'mensal');
        $saldo_inicial = floatval(str_replace(['.', ','], ['', '.'], $request->input('saldo_inicial', '0')));
        
        // Buscar movimentações
        $movimentacoes = DB::table('fluxo_caixa')
            ->where('id_empresa', $id_empresa)
            ->whereBetween('data_movimentacao', [$data_inicio, $data_fim])
            ->orderBy('data_movimentacao')
            ->get();
        
        // Calcular totais
        $total_entradas = $movimentacoes->where('tipo', 'entrada')->sum('valor');
        $total_saidas = $movimentacoes->where('tipo', 'saida')->sum('valor');
        $saldo_final = $saldo_inicial + $total_entradas - $total_saidas;
        $lucratividade = $total_entradas - $total_saidas;
        
        // Formatar lançamentos
        $lancamentos = DB::table('fluxo_caixa')
            ->leftJoin('categoria_contas', 'fluxo_caixa.id_categoria_fluxo', '=', 'categoria_contas.id_categoria_contas')
            ->leftJoin('forma_pagamento', 'fluxo_caixa.id_forma_pagamento', '=', 'forma_pagamento.id_forma_pagamento')
            ->leftJoin('bancos', 'fluxo_caixa.id_bancos', '=', 'bancos.id_bancos')
            ->where('fluxo_caixa.id_empresa', $id_empresa)
            ->whereBetween('fluxo_caixa.data_movimentacao', [$data_inicio, $data_fim])
            ->select(
                'fluxo_caixa.data_movimentacao',
                'fluxo_caixa.descricao',
                DB::raw('COALESCE(categoria_contas.nome, "Sem Categoria") as categoria'),
                'fluxo_caixa.tipo',
                'fluxo_caixa.valor',
                'forma_pagamento.nome as forma_pagamento',
                'bancos.nome_banco as banco'
            )
            ->orderBy('fluxo_caixa.data_movimentacao', 'desc')
            ->get();
        
        return [
            'saldo_inicial' => $saldo_inicial,
            'total_entradas' => $total_entradas,
            'total_saidas' => $total_saidas,
            'saldo_final' => $saldo_final,
            'lucratividade' => $lucratividade,
            'lancamentos' => $lancamentos,
            'filtros' => [
                'data_inicio' => $data_inicio,
                'data_fim' => $data_fim,
                'tipo_visualizacao' => $tipo_visualizacao,
                'saldo_inicial' => $saldo_inicial
            ],
            'empresa' => auth()->user()->empresa->razao_social ?? 'GestorNow'
        ];
    }
    
    public function fluxoCaixaExcel(Request $request)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.fluxo-caixa'), 403);

        $dados = $this->buscarDadosFluxoCaixa($request);
        
        // Formatar lançamentos para Excel
        $lancamentos_formatados = $dados['lancamentos']->map(function($lanc) {
            return [
                'data' => date('d/m/Y', strtotime($lanc->data_movimentacao)),
                'descricao' => $lanc->descricao ?? '-',
                'categoria' => $lanc->categoria ?? 'Sem Categoria',
                'tipo' => $lanc->tipo,
                'valor' => floatval($lanc->valor),
                'forma_pagamento' => $lanc->forma_pagamento ?? '-',
                'banco' => $lanc->banco ?? '-',
                'saldo' => 0
            ];
        });
        
        return response()->json([
            'resumo' => [
                'saldo_inicial' => floatval($dados['saldo_inicial']),
                'total_entradas' => floatval($dados['total_entradas']),
                'total_saidas' => floatval($dados['total_saidas']),
                'saldo_final' => floatval($dados['saldo_final'])
            ],
            'lancamentos' => $lancamentos_formatados,
            'filtros' => $dados['filtros'],
            'data_geracao' => date('d/m/Y H:i:s')
        ]);
    }
    
    /**
     * Recebimentos por Status
     */
    public function recebimentosStatusIndex()
    {
        return view('financeiro.relatorios.recebimentos-status');
    }
    
    public function recebimentosStatusDados(Request $request)
    {
        // Implementar lógica de recebimentos por status
        return response()->json([]);
    }
    
    public function recebimentosStatusPDF(Request $request)
    {
        return response()->json([]);
    }
    
    public function recebimentosStatusExcel(Request $request)
    {
        return response()->json([]);
    }
    
    /**
     * Análise por Propriedade
     */
    public function analisePropriedadeIndex()
    {
        return view('financeiro.relatorios.analise-propriedade');
    }
    
    public function analisePropriedadeDados(Request $request)
    {
        // Implementar lógica de análise por propriedade
        return response()->json([]);
    }
    
    public function analisePropriedadePDF(Request $request)
    {
        return response()->json([]);
    }
    
    public function analisePropriedadeExcel(Request $request)
    {
        return response()->json([]);
    }
    
    /**
     * Projeção de Fluxo de Caixa
     */
    public function projecaoFluxoIndex()
    {
        return view('financeiro.relatorios.projecao-fluxo');
    }
    
    public function projecaoFluxoDados(Request $request)
    {
        // Implementar lógica de projeção
        return response()->json([]);
    }
    
    public function projecaoFluxoPDF(Request $request)
    {
        return response()->json([]);
    }
    
    public function projecaoFluxoExcel(Request $request)
    {
        return response()->json([]);
    }
    
    /**
     * Método auxiliar para agrupar movimentações por período
     */
    private function agruparMovimentacoesPorPeriodo($movimentacoes, $tipo_visualizacao)
    {
        $formato_grupo = match($tipo_visualizacao) {
            'diario' => 'Y-m-d',
            'semanal' => 'Y-W',
            'mensal' => 'Y-m',
            'anual' => 'Y',
            default => 'Y-m'
        };
        
        $formato_exibicao = match($tipo_visualizacao) {
            'diario' => 'd/m',
            'semanal' => '\S\e\m W/Y',
            'mensal' => 'M/Y',
            'anual' => 'Y',
            default => 'M/Y'
        };
        
        $agrupados = [];
        
        foreach ($movimentacoes as $mov) {
            $data = \Carbon\Carbon::parse($mov->data_movimentacao);
            $chave = $data->format($formato_grupo);
            
            if (!isset($agrupados[$chave])) {
                $agrupados[$chave] = [
                    'periodo' => $data->format($formato_exibicao),
                    'entradas' => 0,
                    'saidas' => 0
                ];
            }
            
            if ($mov->tipo === 'entrada') {
                $agrupados[$chave]['entradas'] += $mov->valor;
            } else {
                $agrupados[$chave]['saidas'] += $mov->valor;
            }
        }
        
        return array_values($agrupados);
    }
}
