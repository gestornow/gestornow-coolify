<?php

namespace App\Http\Controllers\Venda;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Domain\Venda\Models\Venda;
use App\Domain\Venda\Models\VendaItem;
use App\Domain\Produto\Models\ProdutoVenda;
use App\Domain\Auth\Models\Empresa;
use App\Facades\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PDVController extends Controller
{
    /**
     * Exibe a tela do PDV
     */
    public function index()
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        // Buscar formas de pagamento
        $formasPagamento = DB::table('forma_pagamento')
            ->where('id_empresa', $idEmpresa)
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get();

        return view('pdv.index', compact('formasPagamento'));
    }

    /**
     * Buscar produtos para o PDV
     */
    public function buscarProduto(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $termo = $request->input('termo', '');

            $produtos = ProdutoVenda::where('id_empresa', $idEmpresa)
                ->where('status', 'ativo')
                ->where(function ($query) use ($termo) {
                    $query->where('codigo', 'like', '%' . $termo . '%')
                        ->orWhere('nome', 'like', '%' . $termo . '%');
                })
                ->select([
                    'id_produto_venda',
                    'nome',
                    'codigo',
                    'preco_venda',
                    'quantidade',
                    'foto_url'
                ])
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'produtos' => $produtos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar produto por código de barras
     */
    public function buscarPorCodigo(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $codigo = $request->input('codigo', '');

            $produto = ProdutoVenda::where('id_empresa', $idEmpresa)
                ->where('status', 'ativo')
                ->where('codigo', $codigo)
                ->select([
                    'id_produto_venda',
                    'nome',
                    'codigo',
                    'preco_venda',
                    'quantidade',
                    'foto_url'
                ])
                ->first();

            if (!$produto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado com este código.'
                ], 404);
            }

            if ($produto->quantidade <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto sem estoque disponível.'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'produto' => $produto
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar estoque do produto
     */
    public function verificarEstoque(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idProduto = $request->input('id_produto_venda');
            $quantidade = $request->input('quantidade', 1);

            $produto = ProdutoVenda::where('id_empresa', $idEmpresa)
                ->where('id_produto_venda', $idProduto)
                ->first();

            if (!$produto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado.'
                ], 404);
            }

            $disponivel = ($produto->quantidade ?? 0) >= $quantidade;

            return response()->json([
                'success' => true,
                'disponivel' => $disponivel,
                'estoque_atual' => $produto->quantidade ?? 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalizar a venda
     */
    public function finalizarVenda(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario;

            $itens = $request->input('itens', []);
            $desconto = floatval($request->input('desconto', 0));
            $acrescimo = floatval($request->input('acrescimo', 0));
            $idFormaPagamento = $request->input('id_forma_pagamento');
            $valorRecebido = floatval($request->input('valor_recebido', 0));
            $observacoes = $request->input('observacoes', '');
            $idCliente = $request->input('id_cliente');

            if (empty($itens)) {
                throw new \Exception('Nenhum item no carrinho.');
            }

            // Validar estoque e produto ativo para todos os itens
            $subtotal = 0;
            $itensValidados = [];

            foreach ($itens as $item) {
                $produto = ProdutoVenda::where('id_empresa', $idEmpresa)
                    ->where('id_produto_venda', $item['id_produto_venda'])
                    ->first();

                if (!$produto) {
                    throw new \Exception("Produto '{$item['nome']}' não encontrado.");
                }

                if (!$produto->estaAtivo()) {
                    throw new \Exception("Produto '{$produto->nome}' está inativo.");
                }

                $quantidadeSolicitada = intval($item['quantidade']);

                if (!$produto->temEstoque($quantidadeSolicitada)) {
                    throw new \Exception("Estoque insuficiente para '{$produto->nome}'. Disponível: {$produto->quantidade}");
                }

                $precoUnitario = floatval($item['preco_unitario']);
                $subtotalItem = $precoUnitario * $quantidadeSolicitada;
                $subtotal += $subtotalItem;

                $itensValidados[] = [
                    'produto' => $produto,
                    'quantidade' => $quantidadeSolicitada,
                    'preco_unitario' => $precoUnitario,
                    'subtotal' => $subtotalItem,
                    'nome' => $produto->nome,
                    'codigo' => $produto->codigo
                ];
            }

            // Calcular total
            $total = $subtotal - $desconto + $acrescimo;

            if ($total < 0) {
                throw new \Exception('O total da venda não pode ser negativo.');
            }

            // Calcular troco
            $troco = $valorRecebido - $total;
            if ($troco < 0) {
                $troco = 0;
            }

            // Gerar número da venda
            $numeroVenda = Venda::gerarNumeroVenda($idEmpresa);

            // Criar a venda
            $venda = Venda::create([
                'id_empresa' => $idEmpresa,
                'id_cliente' => $idCliente,
                'id_usuario' => $idUsuario,
                'id_forma_pagamento' => $idFormaPagamento,
                'numero_venda' => $numeroVenda,
                'data_venda' => Carbon::now(),
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'total' => $total,
                'valor_recebido' => $valorRecebido,
                'troco' => $troco,
                'observacoes' => $observacoes,
                'status' => 'finalizada',
            ]);

            // Criar itens da venda e baixar estoque
            foreach ($itensValidados as $itemValidado) {
                VendaItem::create([
                    'id_venda' => $venda->id_venda,
                    'id_produto_venda' => $itemValidado['produto']->id_produto_venda,
                    'nome_produto' => $itemValidado['nome'],
                    'codigo_produto' => $itemValidado['codigo'],
                    'quantidade' => $itemValidado['quantidade'],
                    'preco_unitario' => $itemValidado['preco_unitario'],
                    'desconto' => 0,
                    'subtotal' => $itemValidado['subtotal'],
                ]);

                // Baixar estoque
                $itemValidado['produto']->diminuirEstoque($itemValidado['quantidade']);
            }

            // Registrar no fluxo de caixa
            $this->registrarFluxoCaixa($venda, $idEmpresa);

            ActionLogger::log($venda, 'finalizacao');

            DB::commit();

            Log::info('=== VENDA FINALIZADA COM SUCESSO ===', [
                'id_venda' => $venda->id_venda,
                'numero_venda' => $venda->numero_venda,
                'total' => $venda->total,
                'id_empresa' => $idEmpresa
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venda finalizada com sucesso!',
                'venda' => [
                    'id_venda' => $venda->id_venda,
                    'numero_venda' => $venda->numero_venda,
                    'total' => $venda->total,
                    'troco' => $troco
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== ERRO AO FINALIZAR VENDA ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar no fluxo de caixa
     */
    private function registrarFluxoCaixa(Venda $venda, $idEmpresa)
    {
        // Buscar nome da forma de pagamento
        $formaPagamento = DB::table('forma_pagamento')
            ->where('id_forma_pagamento', $venda->id_forma_pagamento)
            ->first();

        $descricao = "Venda PDV #{$venda->numero_venda}";
        if ($formaPagamento) {
            $descricao .= " - {$formaPagamento->nome}";
        }

        // Inserir no fluxo de caixa como entrada
        DB::table('fluxo_caixa')->insert([
            'id_empresa' => $idEmpresa,
            'tipo' => 'entrada',
            'descricao' => $descricao,
            'valor' => $venda->total,
            'data_movimentacao' => $venda->data_venda,
            'id_forma_pagamento' => $venda->id_forma_pagamento,
            'referencia_tipo' => 'venda',
            'referencia_id' => $venda->id_venda,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Gerar cupom não fiscal (HTML para impressão)
     */
    public function cupom($idVenda)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $venda = Venda::with(['itens', 'empresa', 'usuario', 'formaPagamento'])
            ->where('id_venda', $idVenda)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$venda) {
            abort(404, 'Venda não encontrada.');
        }

        // Buscar dados da empresa
        $empresa = DB::table('empresa')
            ->where('id_empresa', $idEmpresa)
            ->first();

        return view('pdv.cupom', compact('venda', 'empresa'));
    }

    /**
     * Obter dados do cupom via AJAX
     */
    public function dadosCupom($idVenda)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $venda = Venda::with(['itens', 'formaPagamento', 'usuario'])
                ->where('id_venda', $idVenda)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$venda) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venda não encontrada.'
                ], 404);
            }

            // Buscar dados da empresa
            $empresa = DB::table('empresa')
                ->where('id_empresa', $idEmpresa)
                ->first();

            // Formatar dados para o modal
            $vendaFormatada = [
                'id_venda' => $venda->id_venda,
                'numero_venda' => $venda->numero_venda,
                'data_venda' => $venda->data_venda,
                'data_venda_formatada' => $venda->data_venda ? $venda->data_venda->format('d/m/Y H:i') : '-',
                'status' => $venda->status,
                'subtotal' => $venda->subtotal,
                'desconto' => $venda->desconto,
                'total' => $venda->total,
                'valor_pago' => $venda->valor_pago,
                'forma_pagamento' => $venda->formaPagamento->nome ?? null,
                'operador' => $venda->usuario->nome ?? null,
                'itens' => $venda->itens->map(function($item) {
                    return [
                        'nome_produto' => $item->nome_produto,
                        'quantidade' => $item->quantidade,
                        'preco_unitario' => $item->preco_unitario,
                        'subtotal' => $item->subtotal,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'venda' => $vendaFormatada,
                'empresa' => $empresa
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Histórico de vendas
     */
    public function historico(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.acessar'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $filters = $request->all();

        $query = Venda::with(['itens', 'usuario', 'formaPagamento'])
            ->where('id_empresa', $idEmpresa);

        // Filtro por data
        if (!empty($filters['data_inicio'])) {
            $query->whereDate('data_venda', '>=', $filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $query->whereDate('data_venda', '<=', $filters['data_fim']);
        }

        // Filtro por número da venda
        if (!empty($filters['numero_venda'])) {
            $query->where('numero_venda', $filters['numero_venda']);
        }

        $query->orderBy('data_venda', 'desc');

        $vendas = $query->paginate(30);

        // Estatísticas do período
        $statsQuery = Venda::where('id_empresa', $idEmpresa);
        
        if (!empty($filters['data_inicio'])) {
            $statsQuery->whereDate('data_venda', '>=', $filters['data_inicio']);
        }
        if (!empty($filters['data_fim'])) {
            $statsQuery->whereDate('data_venda', '<=', $filters['data_fim']);
        }

        $stats = [
            'total_vendas' => $statsQuery->count(),
            'valor_total' => $statsQuery->sum('total'),
        ];

        return view('pdv.historico', compact('vendas', 'filters', 'stats'));
    }

    /**
     * Relatório completo de vendas
     */
    public function relatorioVendas(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.relatorio'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $filters = $request->all();

        // Query base
        $baseQuery = Venda::where('id_empresa', $idEmpresa)
            ->where('status', 'finalizada');

        // Aplicar filtros de data
        if (!empty($filters['data_inicio'])) {
            $baseQuery->whereDate('data_venda', '>=', $filters['data_inicio']);
        }
        if (!empty($filters['data_fim'])) {
            $baseQuery->whereDate('data_venda', '<=', $filters['data_fim']);
        }

        // Filtro por forma de pagamento
        if (!empty($filters['forma_pagamento'])) {
            $baseQuery->where('id_forma_pagamento', $filters['forma_pagamento']);
        }

        // Estatísticas gerais
        $totalVendas = (clone $baseQuery)->count();
        $valorTotal = (clone $baseQuery)->sum('total');
        $ticketMedio = $totalVendas > 0 ? $valorTotal / $totalVendas : 0;

        // Total de itens vendidos
        $totalItens = VendaItem::whereIn('id_venda', (clone $baseQuery)->pluck('id_venda'))
            ->sum('quantidade');

        // Vendas canceladas
        $canceladasQuery = Venda::where('id_empresa', $idEmpresa)
            ->where('status', 'cancelada');
        if (!empty($filters['data_inicio'])) {
            $canceladasQuery->whereDate('data_venda', '>=', $filters['data_inicio']);
        }
        if (!empty($filters['data_fim'])) {
            $canceladasQuery->whereDate('data_venda', '<=', $filters['data_fim']);
        }
        $vendasCanceladas = $canceladasQuery->count();
        $valorCancelado = $canceladasQuery->sum('total');

        $stats = [
            'total_vendas' => $totalVendas,
            'valor_total' => $valorTotal,
            'ticket_medio' => $ticketMedio,
            'total_itens' => $totalItens,
            'vendas_canceladas' => $vendasCanceladas,
            'valor_cancelado' => $valorCancelado,
        ];

        // Vendas por dia (para o gráfico)
        $vendasPorDia = Venda::select(
                DB::raw('DATE(data_venda) as data'),
                DB::raw('COUNT(*) as total_vendas'),
                DB::raw('SUM(total) as total_valor')
            )
            ->where('id_empresa', $idEmpresa)
            ->where('status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('data_venda', '<=', $filters['data_fim']);
            })
            ->when(!empty($filters['forma_pagamento']), function($q) use ($filters) {
                $q->where('id_forma_pagamento', $filters['forma_pagamento']);
            })
            ->groupBy(DB::raw('DATE(data_venda)'))
            ->orderBy('data')
            ->get();

        // Vendas por forma de pagamento
        $vendasPorFormaPagamento = DB::table('vendas')
            ->select(
                'forma_pagamento.nome as forma_pagamento',
                DB::raw('COUNT(vendas.id_venda) as total_vendas'),
                DB::raw('SUM(vendas.total) as total_valor')
            )
            ->join('forma_pagamento', 'vendas.id_forma_pagamento', '=', 'forma_pagamento.id_forma_pagamento')
            ->where('vendas.id_empresa', $idEmpresa)
            ->where('vendas.status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '<=', $filters['data_fim']);
            })
            ->groupBy('forma_pagamento.id_forma_pagamento', 'forma_pagamento.nome')
            ->orderByDesc('total_valor')
            ->get();

        // Top 10 produtos mais vendidos
        $topProdutos = DB::table('venda_itens')
            ->select(
                'venda_itens.nome_produto',
                DB::raw('SUM(venda_itens.quantidade) as total_quantidade'),
                DB::raw('SUM(venda_itens.subtotal) as total_valor')
            )
            ->join('vendas', 'venda_itens.id_venda', '=', 'vendas.id_venda')
            ->where('vendas.id_empresa', $idEmpresa)
            ->where('vendas.status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '<=', $filters['data_fim']);
            })
            ->when(!empty($filters['forma_pagamento']), function($q) use ($filters) {
                $q->where('vendas.id_forma_pagamento', $filters['forma_pagamento']);
            })
            ->groupBy('venda_itens.nome_produto')
            ->orderByDesc('total_quantidade')
            ->limit(10)
            ->get();

        // Vendas por operador
        $vendasPorOperador = DB::table('vendas')
            ->select(
                'usuarios.nome as nome_operador',
                DB::raw('COUNT(vendas.id_venda) as total_vendas'),
                DB::raw('SUM(vendas.total) as total_valor')
            )
            ->leftJoin('usuarios', 'vendas.id_usuario', '=', 'usuarios.id_usuario')
            ->where('vendas.id_empresa', $idEmpresa)
            ->where('vendas.status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '<=', $filters['data_fim']);
            })
            ->when(!empty($filters['forma_pagamento']), function($q) use ($filters) {
                $q->where('vendas.id_forma_pagamento', $filters['forma_pagamento']);
            })
            ->groupBy('vendas.id_usuario', 'usuarios.nome')
            ->orderByDesc('total_valor')
            ->get();

        // Formas de pagamento para o filtro
        $formasPagamento = DB::table('forma_pagamento')
            ->where('id_empresa', $idEmpresa)
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get();

        return view('pdv.relatorio-vendas', compact(
            'stats',
            'filters',
            'vendasPorDia',
            'vendasPorFormaPagamento',
            'topProdutos',
            'vendasPorOperador',
            'formasPagamento'
        ));
    }

    /**
     * Gerar PDF do relatório de vendas
     */
    public function relatorioVendasPdf(Request $request)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.relatorio'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $filters = $request->all();

        // Query base
        $baseQuery = Venda::where('id_empresa', $idEmpresa)
            ->where('status', 'finalizada');

        // Aplicar filtros de data
        if (!empty($filters['data_inicio'])) {
            $baseQuery->whereDate('data_venda', '>=', $filters['data_inicio']);
        }
        if (!empty($filters['data_fim'])) {
            $baseQuery->whereDate('data_venda', '<=', $filters['data_fim']);
        }

        // Filtro por forma de pagamento
        if (!empty($filters['forma_pagamento'])) {
            $baseQuery->where('id_forma_pagamento', $filters['forma_pagamento']);
        }

        // Estatísticas gerais
        $totalVendas = (clone $baseQuery)->count();
        $valorTotal = (clone $baseQuery)->sum('total');
        $ticketMedio = $totalVendas > 0 ? $valorTotal / $totalVendas : 0;

        // Total de itens vendidos
        $totalItens = VendaItem::whereIn('id_venda', (clone $baseQuery)->pluck('id_venda'))
            ->sum('quantidade');

        // Vendas canceladas
        $canceladasQuery = Venda::where('id_empresa', $idEmpresa)
            ->where('status', 'cancelada');
        if (!empty($filters['data_inicio'])) {
            $canceladasQuery->whereDate('data_venda', '>=', $filters['data_inicio']);
        }
        if (!empty($filters['data_fim'])) {
            $canceladasQuery->whereDate('data_venda', '<=', $filters['data_fim']);
        }
        $vendasCanceladas = $canceladasQuery->count();
        $valorCancelado = $canceladasQuery->sum('total');

        $stats = [
            'total_vendas' => $totalVendas,
            'valor_total' => $valorTotal,
            'ticket_medio' => $ticketMedio,
            'total_itens' => $totalItens,
            'vendas_canceladas' => $vendasCanceladas,
            'valor_cancelado' => $valorCancelado,
        ];

        // Vendas por dia
        $vendasPorDia = Venda::select(
                DB::raw('DATE(data_venda) as data'),
                DB::raw('COUNT(*) as total_vendas'),
                DB::raw('SUM(total) as total_valor')
            )
            ->where('id_empresa', $idEmpresa)
            ->where('status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('data_venda', '<=', $filters['data_fim']);
            })
            ->when(!empty($filters['forma_pagamento']), function($q) use ($filters) {
                $q->where('id_forma_pagamento', $filters['forma_pagamento']);
            })
            ->groupBy(DB::raw('DATE(data_venda)'))
            ->orderBy('data')
            ->get();

        // Vendas por forma de pagamento
        $vendasPorFormaPagamento = DB::table('vendas')
            ->select(
                'forma_pagamento.nome as forma_pagamento',
                DB::raw('COUNT(vendas.id_venda) as total_vendas'),
                DB::raw('SUM(vendas.total) as total_valor')
            )
            ->join('forma_pagamento', 'vendas.id_forma_pagamento', '=', 'forma_pagamento.id_forma_pagamento')
            ->where('vendas.id_empresa', $idEmpresa)
            ->where('vendas.status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '<=', $filters['data_fim']);
            })
            ->groupBy('forma_pagamento.id_forma_pagamento', 'forma_pagamento.nome')
            ->orderByDesc('total_valor')
            ->get();

        // Top 10 produtos mais vendidos
        $topProdutos = DB::table('venda_itens')
            ->select(
                'venda_itens.nome_produto',
                DB::raw('SUM(venda_itens.quantidade) as total_quantidade'),
                DB::raw('SUM(venda_itens.subtotal) as total_valor')
            )
            ->join('vendas', 'venda_itens.id_venda', '=', 'vendas.id_venda')
            ->where('vendas.id_empresa', $idEmpresa)
            ->where('vendas.status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '<=', $filters['data_fim']);
            })
            ->when(!empty($filters['forma_pagamento']), function($q) use ($filters) {
                $q->where('vendas.id_forma_pagamento', $filters['forma_pagamento']);
            })
            ->groupBy('venda_itens.nome_produto')
            ->orderByDesc('total_quantidade')
            ->limit(10)
            ->get();

        // Vendas por operador
        $vendasPorOperador = DB::table('vendas')
            ->select(
                'usuarios.nome as nome_operador',
                DB::raw('COUNT(vendas.id_venda) as total_vendas'),
                DB::raw('SUM(vendas.total) as total_valor')
            )
            ->leftJoin('usuarios', 'vendas.id_usuario', '=', 'usuarios.id_usuario')
            ->where('vendas.id_empresa', $idEmpresa)
            ->where('vendas.status', 'finalizada')
            ->when(!empty($filters['data_inicio']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '>=', $filters['data_inicio']);
            })
            ->when(!empty($filters['data_fim']), function($q) use ($filters) {
                $q->whereDate('vendas.data_venda', '<=', $filters['data_fim']);
            })
            ->when(!empty($filters['forma_pagamento']), function($q) use ($filters) {
                $q->where('vendas.id_forma_pagamento', $filters['forma_pagamento']);
            })
            ->groupBy('vendas.id_usuario', 'usuarios.nome')
            ->orderByDesc('total_valor')
            ->get();

        // Buscar logo da empresa
        $empresa = Empresa::where('id_empresa', $idEmpresa)->first();
        $logoEmpresaDataUri = $this->resolverLogoEmpresaParaPdf($empresa);

        $pdf = Pdf::loadView('pdv.relatorio-vendas-pdf', [
            'stats' => $stats,
            'filters' => $filters,
            'vendasPorDia' => $vendasPorDia,
            'vendasPorFormaPagamento' => $vendasPorFormaPagamento,
            'topProdutos' => $topProdutos,
            'vendasPorOperador' => $vendasPorOperador,
            'logoEmpresaDataUri' => $logoEmpresaDataUri,
            'empresa' => $empresa,
            'geradoEm' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = 'relatorio-vendas-pdv-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Cancelar venda (estornar estoque e fluxo de caixa)
     */
    public function cancelarVenda($idVenda)
    {
        abort_unless(Perm::pode(Auth::user(), 'pdv.cancelar-venda'), 403);

        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $venda = Venda::with('itens')
                ->where('id_venda', $idVenda)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$venda) {
                throw new \Exception('Venda não encontrada.');
            }

            if ($venda->status === 'cancelada') {
                throw new \Exception('Esta venda já foi cancelada.');
            }

            // Estornar estoque
            foreach ($venda->itens as $item) {
                $produto = ProdutoVenda::where('id_empresa', $idEmpresa)->find($item->id_produto_venda); // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                if ($produto) {
                    $produto->aumentarEstoque($item->quantidade);
                }
            }

            // Remover do fluxo de caixa
            DB::table('fluxo_caixa')
                ->where('referencia_tipo', 'venda')
                ->where('referencia_id', $venda->id_venda)
                ->delete();

            // Atualizar status da venda
            $venda->status = 'cancelada';
            $venda->save();

            ActionLogger::log($venda->fresh(), 'cancelamento');

            DB::commit();

            Log::info('=== VENDA CANCELADA ===', [
                'id_venda' => $venda->id_venda,
                'numero_venda' => $venda->numero_venda
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venda cancelada com sucesso. Estoque restaurado.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== ERRO AO CANCELAR VENDA ===', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolver logo da empresa para PDF
     */
    private function resolverLogoEmpresaParaPdf($empresa): ?string
    {
        if (!$empresa) {
            return null;
        }

        $configuracoes = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logoUrl = trim((string) ($configuracoes['logo_url'] ?? $empresa->logo_url ?? ''));
        if ($logoUrl === '') {
            return null;
        }

        $logoUrl = str_replace(['https//', 'http//'], ['https://', 'http://'], $logoUrl);

        // Tenta resolver arquivo local no servidor
        $arquivoLocal = $this->resolverArquivoLocalParaPdf($logoUrl, ['assets/logos-empresa', 'storage/logos-empresa']);
        if ($arquivoLocal !== null) {
            return $arquivoLocal;
        }

        // Fallback: retorna URL (DomPDF tentará carregar remotamente)
        if (!str_starts_with($logoUrl, 'http://') && !str_starts_with($logoUrl, 'https://')) {
            $logoUrl = rtrim($this->getApiFilesBaseUrl(), '/') . '/' . ltrim($logoUrl, '/');
        }

        return $logoUrl;
    }

    /**
     * Resolver arquivo local para PDF
     */
    private function resolverArquivoLocalParaPdf(string $url, array $basePaths): ?string
    {
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if ($parsedPath === false || $parsedPath === null) {
            return null;
        }
        $parsedPath = ltrim($parsedPath, '/');

        foreach ($basePaths as $basePath) {
            // Caminho completo no public
            $publicPath = public_path($basePath . '/' . basename($parsedPath));
            if (file_exists($publicPath)) {
                return $this->fileToDataUri($publicPath);
            }

            // Caminho completo no storage
            $storagePath = storage_path('app/public/' . $basePath . '/' . basename($parsedPath));
            if (file_exists($storagePath)) {
                return $this->fileToDataUri($storagePath);
            }
        }

        // Tenta o path direto
        $directPath = public_path($parsedPath);
        if (file_exists($directPath)) {
            return $this->fileToDataUri($directPath);
        }

        $directStoragePath = storage_path('app/public/' . $parsedPath);
        if (file_exists($directStoragePath)) {
            return $this->fileToDataUri($directStoragePath);
        }

        return null;
    }

    /**
     * Converter arquivo para Data URI
     */
    private function fileToDataUri(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        $mime = mime_content_type($path);

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    /**
     * Base URL da API de arquivos
     */
    private function getApiFilesBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com')), '/');
        return str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);
    }
}
