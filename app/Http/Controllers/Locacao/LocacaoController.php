<?php

namespace App\Http\Controllers\Locacao;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Models\Usuario;
use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoProduto;
use App\Domain\Locacao\Models\LocacaoServico;
use App\Domain\Locacao\Models\LocacaoDespesa;
use App\Domain\Locacao\Models\LocacaoSala;
use App\Domain\Locacao\Models\LocacaoRetornoPatrimonio;
use App\Domain\Locacao\Models\LocacaoModeloContrato;
use App\Domain\Locacao\Models\LocacaoAssinaturaDigital;
use App\Domain\Locacao\Models\LocacaoChecklistFoto;
use App\Domain\Locacao\Models\LocacaoTrocaProduto;
use App\Domain\Locacao\Models\ProdutoTerceirosLocacao;
use App\Domain\Cliente\Models\Cliente;
use App\Domain\Produto\Models\Manutencao;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\TabelaPreco;
use App\Domain\Produto\Models\Patrimonio;
use App\Domain\Produto\Models\PatrimonioHistorico;
use App\Domain\Produto\Models\ProdutoHistorico;
use App\Domain\Produto\Models\ProdutoTerceiro;
use App\Models\CategoriaContas;
use App\Models\ContasAPagar;
use App\Models\ContasAReceber;
use App\Models\FaturamentoLocacao;
use App\Models\Fornecedor;
use App\Models\RegistroAtividade;
use App\Services\EstoqueService;
use App\Services\ContratoPdfService;
use App\Services\LocacaoRenovacaoService;
use App\Services\ManutencaoEstoqueService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocacaoController extends Controller
{
    protected $estoqueService;
    protected $contratoPdfService;
    protected $locacaoRenovacaoService;
    protected $manutencaoEstoqueService;

    public function __construct(
        EstoqueService $estoqueService,
        ContratoPdfService $contratoPdfService,
        LocacaoRenovacaoService $locacaoRenovacaoService,
        ManutencaoEstoqueService $manutencaoEstoqueService
    )
    {
        $this->estoqueService = $estoqueService;
        $this->contratoPdfService = $contratoPdfService;
        $this->locacaoRenovacaoService = $locacaoRenovacaoService;
        $this->manutencaoEstoqueService = $manutencaoEstoqueService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return redirect()->route('locacoes.contratos', $request->query());
    }

    public function pedidos(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $hoje = Carbon::today();
        $aba = Str::lower((string) $request->input('aba', 'iniciam_hoje'));
        if (!in_array($aba, ['iniciam_hoje', 'terminam_hoje'], true)) {
            $aba = 'iniciam_hoje';
        }

        $queryBase = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'cancelado', 'cancelada'])
            ->with([
                'cliente:id_clientes,nome',
                'produtos:id_produto_locacao,id_locacao,id_produto,quantidade,preco_unitario,preco_total',
                'produtos.produto:id_produto,nome',
            ])
            ->withCount([
                'produtos as itens_count',
            ]);

        $pedidosIniciamHoje = (clone $queryBase)
            ->whereDate('data_inicio', $hoje->toDateString())
            ->orderBy('hora_inicio')
            ->orderBy('numero_contrato')
            ->get();

        $pedidosTerminamHoje = (clone $queryBase)
            ->whereDate('data_fim', $hoje->toDateString())
            ->orderBy('hora_fim')
            ->orderBy('numero_contrato')
            ->get();

        $totais = [
            'iniciam_hoje' => $pedidosIniciamHoje->count(),
            'terminam_hoje' => $pedidosTerminamHoje->count(),
            'valor_iniciam_hoje' => (float) $pedidosIniciamHoje->sum(function (Locacao $locacao) {
                $this->aplicarTotaisListagemLocacao($locacao);
                return (float) ($locacao->valor_total_listagem ?? 0);
            }),
            'valor_terminam_hoje' => (float) $pedidosTerminamHoje->sum(function (Locacao $locacao) {
                $this->aplicarTotaisListagemLocacao($locacao);
                return (float) ($locacao->valor_total_listagem ?? 0);
            }),
        ];

        return view('locacoes.pedidos', [
            'pedidosIniciamHoje' => $pedidosIniciamHoje,
            'pedidosTerminamHoje' => $pedidosTerminamHoje,
            'totais' => $totais,
            'aba' => $aba,
            'hoje' => $hoje,
        ]);
    }

    public function orcamentos(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $agora = Carbon::now();

        $query = $this->aplicarFiltrosBaseListagemLocacao($filters, $idEmpresa)
            ->where('status', 'orcamento')
            ->orderByDesc('created_at');

        $orcamentosResumoColecao = (clone $query)->get();
        $quantidadeTotal = $orcamentosResumoColecao->count();
        $valorTotal = 0.0;
        $quantidadeMesAtual = 0;
        $proximosSeteDias = 0;
        $inicioAtrasado = 0;

        foreach ($orcamentosResumoColecao as $orcamentoResumo) {
            $valorTotal += $this->calcularValorTotalListagem($orcamentoResumo);

            if ($orcamentoResumo->created_at && $orcamentoResumo->created_at->isSameMonth($agora)) {
                $quantidadeMesAtual++;
            }

            $dataInicio = $orcamentoResumo->data_inicio instanceof \DateTime
                ? Carbon::instance($orcamentoResumo->data_inicio)
                : ($orcamentoResumo->data_inicio ? Carbon::parse($orcamentoResumo->data_inicio) : null);

            if ($dataInicio) {
                if ($dataInicio->between($agora->copy()->startOfDay(), $agora->copy()->addDays(7)->endOfDay())) {
                    $proximosSeteDias++;
                }

                if ($dataInicio->lt($agora->copy()->startOfDay())) {
                    $inicioAtrasado++;
                }
            }
        }

        $resumoOrcamentos = [
            'quantidade_total' => $quantidadeTotal,
            'valor_total' => max(0, $valorTotal),
            'ticket_medio' => $quantidadeTotal > 0 ? max(0, $valorTotal) / $quantidadeTotal : 0,
            'quantidade_mes_atual' => $quantidadeMesAtual,
            'proximos_sete_dias' => $proximosSeteDias,
            'inicio_atrasado' => $inicioAtrasado,
        ];

        $locacoes = $query->paginate(50)->withQueryString();
        $locacoes->getCollection()->transform(function (Locacao $locacao) {
            $this->aplicarTotaisListagemLocacao($locacao);
            return $locacao;
        });

        $clientes = Cliente::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get();

        $usuarios = \App\Models\User::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get(['id_usuario', 'nome']);

        return view('locacoes.orcamentos', compact('locacoes', 'filters', 'clientes', 'usuarios', 'resumoOrcamentos'));
    }

    public function contratos(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $agora = Carbon::now();
        $aba = $this->normalizarAbaContratos((string) $request->input('aba', 'ativos'));

        $query = $this->aplicarFiltrosBaseListagemLocacao($filters, $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'medicao', 'medicao_finalizada'])
            ->whereDoesntHave('faturamentos', function ($q) {
                $q->where('origem', 'faturamento_medicao');
            })
            ->with([
                'produtos.produto:id_produto,nome',
                'produtosTerceiros.produtoTerceiro:id_produto_terceiro,nome',
                'servicos:id_locacao_servico,id_locacao,descricao,tipo_item,quantidade,preco_unitario,valor_total',
                'despesas:id_locacao_despesa,id_locacao,descricao,tipo,valor',
                'assinaturaDigital:id_assinatura,id_locacao,id_modelo,token,status,assinado_em,solicitado_em',
                'assinaturasDigitais:id_assinatura,id_locacao,id_modelo,token,status,assinado_em,solicitado_em',
            ])
            ->withCount([
                'faturamentos as faturamentos_ativos_count',
            ]);

        $this->aplicarFiltroAbaContratos($query, $aba, $agora);

        $locacoes = $query->orderByDesc('created_at')->paginate(50)->withQueryString();
        $locacoes->getCollection()->transform(function (Locacao $locacao) {
            $this->aplicarTotaisListagemLocacao($locacao);
            return $locacao;
        });

        $filtersCards = $filters;
        unset($filtersCards['status'], $filtersCards['aba'], $filtersCards['page']);

        $abasContagem = [
            'ativos' => $this->contarContratosPorAba($idEmpresa, 'ativos', $agora, $filtersCards),
            'vencidos' => $this->contarContratosPorAba($idEmpresa, 'vencidos', $agora, $filtersCards),
            'futuros' => $this->contarContratosPorAba($idEmpresa, 'futuros', $agora, $filtersCards),
            'encerrados' => $this->contarContratosPorAba($idEmpresa, 'encerrados', $agora, $filtersCards),
            'todos' => $this->contarContratosPorAba($idEmpresa, 'todos', $agora, $filtersCards),
        ];

        $abasValores = [
            'ativos' => $this->somarValoresContratosPorAba($idEmpresa, 'ativos', $agora, $filtersCards),
            'vencidos' => $this->somarValoresContratosPorAba($idEmpresa, 'vencidos', $agora, $filtersCards),
            'futuros' => $this->somarValoresContratosPorAba($idEmpresa, 'futuros', $agora, $filtersCards),
            'encerrados' => $this->somarValoresContratosPorAba($idEmpresa, 'encerrados', $agora, $filtersCards),
            'todos' => $this->somarValoresContratosPorAba($idEmpresa, 'todos', $agora, $filtersCards),
        ];

        $clientes = Cliente::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get();

        $produtos = Produto::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get(['id_produto', 'nome']);

        $usuarios = \App\Models\User::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get(['id_usuario', 'nome']);

        $modelosContratoAtivos = $this->consultarModelosDocumento((int) $idEmpresa, 'contrato')
            ->orderBy('padrao', 'desc')
            ->orderBy('nome')
            ->get(['id_modelo', 'nome', 'padrao']);

        return view('locacoes.contratos', compact('locacoes', 'filters', 'clientes', 'usuarios', 'produtos', 'modelosContratoAtivos', 'aba', 'abasContagem', 'abasValores', 'agora'));
    }

    public function trocasProduto(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $idLocacaoFiltro = (int) $request->input('id_locacao', 0);
        $temCodigoPatrimonio = Schema::hasColumn('patrimonios', 'codigo_patrimonio');

        $locacoesElegiveis = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'encerrado', 'cancelado', 'cancelada'])
            ->whereDoesntHave('faturamentos')
            ->with([
                'cliente:id_clientes,nome',
            ])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id_locacao', 'numero_contrato', 'id_cliente', 'data_inicio', 'data_fim', 'status']);

        $produtosAtivos = Produto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->withCount([
                'patrimonios as patrimonios_ativos_count' => function ($query) use ($idEmpresa) {
                    $query->where('id_empresa', $idEmpresa)
                        ->where('status', 'Ativo');
                },
                'patrimonios as patrimonios_disponiveis_count' => function ($query) use ($idEmpresa) {
                    $query->where('id_empresa', $idEmpresa)
                        ->where('status', 'Ativo')
                        ->where(function ($sub) {
                            $sub->whereNull('status_locacao')
                                ->orWhere('status_locacao', 'Disponivel');
                        });
                },
            ])
            ->with([
                'patrimonios' => function ($query) use ($idEmpresa, $temCodigoPatrimonio) {
                    $query->where('id_empresa', $idEmpresa)
                        ->where('status', 'Ativo')
                        ->where(function ($sub) {
                            $sub->whereNull('status_locacao')
                                ->orWhere('status_locacao', 'Disponivel');
                        })
                        ->when($temCodigoPatrimonio, function ($subQuery) {
                            $subQuery->orderBy('codigo_patrimonio');
                        })
                        ->orderBy('numero_serie');
                },
            ])
            ->orderBy('nome')
            ->get(['id_produto', 'nome', 'codigo', 'quantidade']);

        $trocasQuery = LocacaoTrocaProduto::query()
            ->where('id_empresa', $idEmpresa)
            ->with([
                'locacao:id_locacao,numero_contrato,id_cliente',
                'locacao.cliente:id_clientes,nome',
                'produtoAnterior:id_produto,nome',
                'produtoNovo:id_produto,nome',
                'usuario:id_usuario,nome',
            ])
            ->orderByDesc('id_locacao_troca_produto');

        if ($idLocacaoFiltro > 0) {
            $trocasQuery->where('id_locacao', $idLocacaoFiltro);
        }

        $trocas = $trocasQuery->paginate(30)->withQueryString();

        return view('locacoes.trocas-produto', [
            'locacoesElegiveis' => $locacoesElegiveis,
            'produtosAtivos' => $produtosAtivos,
            'trocas' => $trocas,
            'idLocacaoFiltro' => $idLocacaoFiltro,
        ]);
    }

    public function itensTrocaProdutoContrato(Request $request, $locacaoId)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacaoId)
            ->with([
                'produtos' => function ($query) {
                    $query->with([
                        'produto:id_produto,nome,codigo',
                        'patrimonio',
                    ]);
                },
            ])
            ->first();

        if (!$locacao) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato não encontrado.',
            ], 404);
        }

        if ($this->locacaoTemFaturamento($locacao)) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato já faturado. Troca de produto não permitida.',
            ], 422);
        }

        $itens = ($locacao->produtos ?? collect())
            ->filter(function (LocacaoProduto $item) {
                $statusRetorno = (string) ($item->status_retorno ?? 'pendente');
                return (int) ($item->estoque_status ?? 0) !== 2
                    && in_array($statusRetorno, [null, '', 'pendente'], true);
            })
            ->map(function (LocacaoProduto $item) {
                $quantidadeItem = !empty($item->id_patrimonio)
                    ? 1
                    : max(1, (int) ($item->quantidade ?? 1));

                return [
                    'id_produto_locacao' => (int) $item->id_produto_locacao,
                    'id_produto' => (int) ($item->id_produto ?? 0),
                    'id_patrimonio' => !empty($item->id_patrimonio) ? (int) $item->id_patrimonio : null,
                    'patrimonio_codigo' => $item->patrimonio
                        ? ($item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? ('PAT-' . $item->patrimonio->id_patrimonio))
                        : null,
                    'usa_patrimonio' => !empty($item->id_patrimonio),
                    'produto' => $item->produto->nome ?? 'Produto',
                    'quantidade' => $quantidadeItem,
                    'status_estoque' => (int) ($item->estoque_status ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'itens' => $itens,
        ]);
    }

    public function disponibilidadeTrocaProdutoContrato(Request $request, $locacaoId)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $dados = $request->validate([
            'id_produto_locacao' => ['required', 'integer'],
            'id_produto_novo' => ['required', 'integer'],
        ]);

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacaoId)
            ->first();

        if (!$locacao) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato não encontrado.',
            ], 404);
        }

        if ($this->locacaoTemFaturamento($locacao)) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato já faturado. Troca de produto não permitida.',
            ], 422);
        }

        if (in_array((string) $locacao->status, ['encerrado', 'cancelado', 'cancelada'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato encerrado/cancelado não pode ter produto trocado.',
            ], 422);
        }

        $itemLocacao = LocacaoProduto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('id_produto_locacao', (int) $dados['id_produto_locacao'])
            ->with(['produto', 'patrimonio'])
            ->first();

        if (!$itemLocacao) {
            return response()->json([
                'success' => false,
                'message' => 'Item do contrato não encontrado.',
            ], 404);
        }

        $statusRetornoItem = (string) ($itemLocacao->status_retorno ?? 'pendente');
        $estoqueStatusItem = (int) ($itemLocacao->estoque_status ?? 0);
        if ($estoqueStatusItem === 2 || $statusRetornoItem !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível trocar produto de item já retornado.',
            ], 422);
        }

        $produtoNovo = Produto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_produto', (int) $dados['id_produto_novo'])
            ->where('status', 'ativo')
            ->first();

        if (!$produtoNovo) {
            return response()->json([
                'success' => false,
                'message' => 'Produto novo inválido para a troca.',
            ], 422);
        }

        $dadosDisponibilidade = $this->obterDadosDisponibilidadeTrocaProduto(
            $locacao,
            $itemLocacao,
            $produtoNovo,
            (int) $idEmpresa
        );

        return response()->json([
            'success' => true,
            'periodo' => $dadosDisponibilidade['periodo'],
            'disponibilidade' => $dadosDisponibilidade['disponibilidade'],
            'produto_usa_patrimonio' => (bool) $dadosDisponibilidade['produto_usa_patrimonio'],
        ]);
    }

    public function relatorioGerencialContratosPdf(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $agora = Carbon::now();
        $tipo = (string) $request->input('tipo', 'carteira');
        $tipo = in_array($tipo, ['carteira', 'agenda', 'lucratividade', 'filtros'], true) ? $tipo : 'carteira';
        $aba = $this->normalizarAbaContratos((string) $request->input('aba', 'todos'));

        $filters = $request->all();

        $query = $this->aplicarFiltrosBaseListagemLocacao($filters, $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'medicao', 'medicao_finalizada'])
            ->whereDoesntHave('faturamentos', function ($q) {
                $q->where('origem', 'faturamento_medicao');
            })
            ->with([
                'produtos.produto:id_produto,nome',
            ])
            ->withCount([
                'faturamentos as faturamentos_ativos_count',
            ]);

        if ($aba !== 'todos') {
            $this->aplicarFiltroAbaContratos($query, $aba, $agora);
        }

        $locacoes = $query->orderByDesc('created_at')->get();
        $locacoes->transform(function (Locacao $locacao) use ($agora) {
            $this->aplicarTotaisListagemLocacao($locacao);

            $receitaCalculada = (float) ($locacao->valor_total_listagem ?? 0);
            $receitaPersistida = max(
                (float) ($locacao->valor_final ?? 0),
                (float) ($locacao->valor_total ?? 0)
            );

            if ($receitaCalculada <= 0 && $receitaPersistida > 0) {
                $locacao->valor_total_listagem = $receitaPersistida;
                if ((float) ($locacao->valor_total_base_listagem ?? 0) <= 0) {
                    $locacao->valor_total_base_listagem = $receitaPersistida;
                }
            }

            if ((float) ($locacao->subtotal_despesas_listagem ?? 0) <= 0 && (float) ($locacao->valor_despesas_extras ?? 0) > 0) {
                $locacao->subtotal_despesas_listagem = (float) $locacao->valor_despesas_extras;
            }

            $porHora = $this->ehLocacaoPorHoraLocacao($locacao);
            $quantidadePeriodo = $this->calcularQuantidadePeriodoCobranca(
                $locacao->data_inicio,
                $locacao->hora_inicio,
                $locacao->data_fim,
                $locacao->hora_fim,
                $porHora,
                max(1, (int) ($locacao->quantidade_dias ?? 1))
            );

            $locacao->periodo_exibicao = $quantidadePeriodo . ' ' . ($porHora ? 'hora(s)' : 'dia(s)');

            $prazoStatus = 'Sem data fim';
            if (!empty($locacao->data_fim)) {
                if ($porHora) {
                    $fimRef = Carbon::parse(
                        Carbon::parse((string) $locacao->data_fim)->toDateString()
                        . ' '
                        . ((string) ($locacao->hora_fim ?: '23:59:59'))
                    );
                    $restante = $agora->diffInHours($fimRef, false);
                    $prazoStatus = $restante < 0 ? 'Vencido' : ($restante === 0 ? 'Vence agora' : 'A vencer');
                } else {
                    $restante = $agora->copy()->startOfDay()->diffInDays(Carbon::parse((string) $locacao->data_fim)->startOfDay(), false);
                    $prazoStatus = $restante < 0 ? 'Vencido' : ($restante === 0 ? 'Vence hoje' : 'A vencer');
                }
            }
            $locacao->prazo_status_agenda = $prazoStatus;

            $valorReceita = (float) ($locacao->valor_total_listagem ?? 0);
            $valorDespesas = (float) ($locacao->subtotal_despesas_listagem ?? 0);
            $lucro = $valorReceita - $valorDespesas;

            $locacao->valor_lucro_listagem = $lucro;
            $locacao->margem_lucro_listagem = $valorReceita > 0
                ? round(($lucro / $valorReceita) * 100, 2)
                : 0;

            return $locacao;
        });

        $locacoesAgenda = $locacoes
            ->filter(function (Locacao $locacao) {
                return (string) ($locacao->status ?? '') === 'aprovado';
            })
            ->sortBy(function (Locacao $locacao) {
                return optional($locacao->data_fim)->format('Y-m-d H:i:s') ?: '9999-12-31 23:59:59';
            })
            ->values();

        $totais = [
            'quantidade' => $locacoes->count(),
            'valor_total' => (float) $locacoes->sum(fn (Locacao $locacao) => (float) ($locacao->valor_total_listagem ?? 0)),
            'valor_despesas' => (float) $locacoes->sum(fn (Locacao $locacao) => (float) ($locacao->subtotal_despesas_listagem ?? 0)),
            'valor_lucro' => (float) $locacoes->sum(fn (Locacao $locacao) => (float) ($locacao->valor_lucro_listagem ?? 0)),
            'faturamentos_abertos' => (int) $locacoes->sum(fn (Locacao $locacao) => (int) ($locacao->faturamentos_ativos_count ?? 0)),
        ];
        $totais['margem_media'] = $totais['valor_total'] > 0
            ? round(($totais['valor_lucro'] / $totais['valor_total']) * 100, 2)
            : 0;

        $statusLabel = null;
        if (!empty($filters['status'])) {
            $statusLabel = Locacao::statusList()[$filters['status']] ?? (string) $filters['status'];
        }

        $clienteNome = null;
        if (!empty($filters['id_cliente'])) {
            $clienteNome = Cliente::query()
                ->where('id_clientes', $filters['id_cliente'])
                ->value('nome');
        }

        $produtoNome = null;
        if (!empty($filters['id_produto'])) {
            $produtoNome = Produto::query()
                ->where('id_produto', $filters['id_produto'])
                ->value('nome');
        }

        $periodoResumo = null;
        if (!empty($filters['data_inicio']) || !empty($filters['data_fim'])) {
            $inicioFiltro = !empty($filters['data_inicio'])
                ? Carbon::parse((string) $filters['data_inicio'])->format('d/m/Y')
                : '-';
            $fimFiltro = !empty($filters['data_fim'])
                ? Carbon::parse((string) $filters['data_fim'])->format('d/m/Y')
                : '-';
            $periodoResumo = $inicioFiltro . ' até ' . $fimFiltro;
        }

        $filtrosResumo = [
            'status' => $statusLabel,
            'cliente' => $clienteNome,
            'produto' => $produtoNome,
            'periodo' => $periodoResumo,
        ];

        $empresa = Empresa::where('id_empresa', $idEmpresa)->first();
        if ($empresa) {
            $this->normalizarLogoEmpresa($empresa);
            $empresa->refresh();
        }
        $logoEmpresaDataUri = $this->montarLogoDataUriEmpresa($empresa);

        $pdf = Pdf::loadView('locacoes.relatorios.contratos-gerenciais-pdf', [
            'locacoes' => $locacoes,
            'locacoesAgenda' => $locacoesAgenda,
            'totais' => $totais,
            'aba' => $aba,
            'tipo' => $tipo,
            'filtrosResumo' => $filtrosResumo,
            'geradoEm' => now(),
            'logoEmpresaDataUri' => $logoEmpresaDataUri,
        ])->setPaper('a4', 'landscape');

        $nomeTipo = [
            'carteira' => 'Carteira_Contratos',
            'agenda' => 'Agenda_Vencimentos',
            'lucratividade' => 'Lucratividade_Contratos',
            'filtros' => 'Locacoes_Por_Filtro',
        ][$tipo] ?? 'Relatorio_Contratos';

        return $pdf->stream($nomeTipo . '_' . now()->format('Ymd_His') . '.pdf');
    }

    public function medicoes(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $mesMovimento = (string) $request->input('mes_movimento', now()->format('Y-m'));
        $abaMedicao = (string) $request->input('aba', 'ativos');
        $abaMedicao = in_array($abaMedicao, ['ativos', 'encerrados', 'todos'], true) ? $abaMedicao : 'ativos';

        if (!preg_match('/^\d{4}-\d{2}$/', $mesMovimento)) {
            $mesMovimento = now()->format('Y-m');
        }

        $query = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('status', ['medicao', 'medicao_finalizada'])
            ->with(['cliente', 'assinaturaDigital'])
            ->withCount([
                'produtos as itens_ativos_count' => function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('status_retorno')->orWhere('status_retorno', 'pendente');
                    });
                },
            ])
            ->orderByDesc('created_at');

        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', (int) $request->input('id_cliente'));
        }

        if ($request->filled('busca')) {
            $query->buscar((string) $request->input('busca'));
        }

        if ($abaMedicao === 'ativos') {
            $query->where('status', 'medicao');
        }

        if ($abaMedicao === 'encerrados') {
            $query->where('status', 'medicao_finalizada');
        }

        $locacoesResumo = (clone $query)->get();

        $valorAbertoTotal = 0.0;
        $valorPrevistoTotal = 0.0;
        $itensAtivosTotal = 0;
        foreach ($locacoesResumo as $locacaoResumo) {
            $ultimoFaturamentoResumo = $this->obterUltimoFimPeriodoFaturadoMedicao(
                (int) $locacaoResumo->id_locacao,
                (int) $idEmpresa
            );

            $dataInicioResumo = optional($locacaoResumo->data_inicio)->format('Y-m-d');
            $inicioCorteResumo = $ultimoFaturamentoResumo
                ? $ultimoFaturamentoResumo->copy()->addDay()->startOfDay()
                : ($dataInicioResumo
                    ? Carbon::parse($dataInicioResumo)->startOfDay()
                    : now()->startOfDay());

            $inicioPrevistoResumo = $this->obterInicioLocacaoMedicao($locacaoResumo);

            $valorAbertoTotal += $this->calcularValorMedicaoPeriodoLocacao($locacaoResumo, $inicioCorteResumo, now()->endOfDay());
            $valorPrevistoTotal += $this->calcularValorPrevistoHojeLocacao($locacaoResumo, $inicioPrevistoResumo, now()->endOfDay());
            $itensAtivosTotal += (int) ($locacaoResumo->itens_ativos_count ?? 0);
        }

        $totalContratos = $locacoesResumo->count();

        $faturadosMes = FaturamentoLocacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereRaw("DATE_FORMAT(data_faturamento, '%Y-%m') = ?", [$mesMovimento])
            ->whereHas('locacao', function ($q) {
                $q->whereIn('status', ['medicao', 'medicao_finalizada']);
            })
            ->count();

        $resumoMedicoes = [
            'total_contratos' => $totalContratos,
            'itens_ativos_total' => $itensAtivosTotal,
            'valor_aberto_total' => round(max(0, $valorAbertoTotal), 2),
            'valor_previsto_total' => round(max(0, $valorPrevistoTotal), 2),
            'faturados_mes' => (int) $faturadosMes,
        ];

        $queryAbas = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('status', ['medicao', 'medicao_finalizada'])
            ->withCount([
                'produtos as itens_ativos_count' => function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('status_retorno')->orWhere('status_retorno', 'pendente');
                    });
                },
            ]);

        if ($request->filled('id_cliente')) {
            $queryAbas->where('id_cliente', (int) $request->input('id_cliente'));
        }

        if ($request->filled('busca')) {
            $queryAbas->buscar((string) $request->input('busca'));
        }

        $abasContagemMedicoes = [
            'ativos' => (int) (clone $queryAbas)->where('status', 'medicao')->count(),
            'encerrados' => (int) (clone $queryAbas)->where('status', 'medicao_finalizada')->count(),
            'todos' => (int) (clone $queryAbas)->count(),
        ];

        $abasValoresMedicoes = [
            'ativos' => round(max(0, (float) (clone $queryAbas)->where('status', 'medicao')->sum('valor_final')), 2),
            'encerrados' => round(max(0, (float) (clone $queryAbas)->where('status', 'medicao_finalizada')->sum('valor_final')), 2),
            'todos' => round(max(0, (float) (clone $queryAbas)->sum('valor_final')), 2),
        ];

        $locacoes = $query->paginate(30)->withQueryString();

        $locacoes->getCollection()->transform(function (Locacao $locacao) use ($idEmpresa) {
            $ultimoFaturamento = $this->obterUltimoFimPeriodoFaturadoMedicao(
                (int) $locacao->id_locacao,
                (int) $idEmpresa
            );

            $dataInicioLocacao = optional($locacao->data_inicio)->format('Y-m-d');
            $inicioCorte = $ultimoFaturamento
                ? $ultimoFaturamento->copy()->addDay()->startOfDay()
                : ($dataInicioLocacao
                    ? Carbon::parse($dataInicioLocacao)->startOfDay()
                    : now()->startOfDay());

            $inicioPrevisto = $this->obterInicioLocacaoMedicao($locacao);

            $fimCorte = now()->endOfDay();
            $valorFaturadoMedicao = $this->obterTotalFaturadoMedicaoLocacao((int) $locacao->id_locacao, (int) $idEmpresa);
            $valorAbertoBruto = $this->calcularValorMedicaoPeriodoBrutoLocacao($locacao, $inicioCorte, $fimCorte);
            $valorPrevistoHojeBruto = $this->calcularValorMedicaoPeriodoBrutoLocacao($locacao, $inicioPrevisto, $fimCorte);
            $saldoDisponivelEnvio = $this->obterSaldoDisponivelEnvioMedicaoLocacao(
                $locacao,
                $inicioCorte,
                $fimCorte,
                $valorFaturadoMedicao
            );

            $locacao->ultimo_faturamento = $ultimoFaturamento;
            $locacao->inicio_corte_faturamento = $inicioCorte;
            $locacao->valor_aberto_medicao = $valorAbertoBruto;
            $locacao->valor_previsto_hoje = $valorPrevistoHojeBruto;
            $locacao->inicio_previsto_medicao = $inicioPrevisto;
            $locacao->valor_limite_medicao = $this->obterValorLimiteMedicaoLocacao($locacao);
            $locacao->valor_faturado_medicao = $valorFaturadoMedicao;
            $locacao->valor_restante_limite_medicao = $saldoDisponivelEnvio;
            $locacao->limite_medicao_atingido = $locacao->valor_restante_limite_medicao !== null
                && (float) $locacao->valor_restante_limite_medicao <= 0.00001;
            $locacao->limite_medicao_ultrapassado = (float) $locacao->valor_limite_medicao > 0
                && ($valorPrevistoHojeBruto - (float) $locacao->valor_limite_medicao) > 0.00001;
            $locacao->valor_excedente_limite_medicao = $locacao->limite_medicao_ultrapassado
                ? round(max(0, $valorPrevistoHojeBruto - (float) $locacao->valor_limite_medicao), 2)
                : 0.0;

            return $locacao;
        });

        $modelosContratoMedicao = $this->consultarModelosDocumento((int) $idEmpresa, 'medicao')
            ->orderBy('padrao', 'desc')
            ->orderBy('nome')
            ->get(['id_modelo', 'nome', 'padrao']);

        $clientes = Cliente::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get(['id_clientes', 'nome']);

        return view('locacoes.medicoes', [
            'locacoes' => $locacoes,
            'clientes' => $clientes,
            'filters' => $request->all(),
            'mesMovimento' => $mesMovimento,
            'resumoMedicoes' => $resumoMedicoes,
            'abaMedicao' => $abaMedicao,
            'abasContagemMedicoes' => $abasContagemMedicoes,
            'abasValoresMedicoes' => $abasValoresMedicoes,
            'modelosContratoMedicao' => $modelosContratoMedicao,
        ]);
    }

    public function relatorioMovimentacoesMedicao(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->whereIn('status', ['medicao', 'medicao_finalizada'])
                ->where('id_locacao', (int) $id)
                ->with(['cliente', 'empresa'])
                ->first();

            if (!$locacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato de medição não encontrado.',
                ], 404);
            }

            $dadosRelatorio = $this->montarDadosRelatorioMovimentacoesMedicao($locacao, $idEmpresa);
            $periodosFaturados = $this->montarPeriodosFaturadosMedicao($locacao, (int) $idEmpresa);

            return response()->json([
                'success' => true,
                'locacao' => [
                    'id_locacao' => $locacao->id_locacao,
                    'codigo_display' => $locacao->codigo_display,
                    'cliente_nome' => $locacao->cliente->nome ?? 'N/A',
                ],
                'periodo' => [
                    'inicio' => $dadosRelatorio['periodo_inicio']->format('d/m/Y'),
                    'fim' => $dadosRelatorio['periodo_fim']->format('d/m/Y'),
                ],
                'resumo' => $dadosRelatorio['resumo'],
                'movimentacoes' => $dadosRelatorio['movimentacoes']->map(function ($mov) {
                    return [
                        'tipo' => $mov['tipo'],
                        'data_hora' => $mov['data_hora']->format('d/m/Y'),
                        'produto' => $mov['produto'],
                        'patrimonio' => $mov['patrimonio'] ?? '-',
                        'quantidade' => $mov['quantidade'],
                    ];
                })->values(),
                'produtos_resumo' => collect($dadosRelatorio['produtos_resumo'] ?? [])->map(function ($item) {
                    return [
                        'produto' => $item['produto'] ?? 'Produto',
                        'patrimonio' => $item['patrimonio'] ?? '-',
                        'quantidade' => (int) ($item['quantidade'] ?? 1),
                        'valor_unitario' => (float) ($item['valor_unitario'] ?? 0),
                        'dias_periodo' => (int) ($item['dias_periodo'] ?? 0),
                        'valor_periodo' => (float) ($item['valor_periodo'] ?? 0),
                        'inicio' => $item['inicio'] instanceof Carbon ? $item['inicio']->format('d/m/Y') : '-',
                        'fim' => $item['fim'] instanceof Carbon ? $item['fim']->format('d/m/Y') : '-',
                    ];
                })->values(),
                'periodos_faturados' => collect($periodosFaturados)->map(function ($periodo) {
                    return [
                        'inicio' => $periodo['inicio']->format('d/m/Y'),
                        'fim' => $periodo['fim']->format('d/m/Y'),
                        'valor' => (float) ($periodo['valor'] ?? 0),
                        'numero_fatura' => $periodo['numero_fatura'] ?? null,
                        'id_faturamento' => $periodo['id_faturamento_locacao'] ?? null,
                        'pdf_url' => $periodo['pdf_url'] ?? null,
                    ];
                })->values(),
                'valor_total_periodo' => (float) ($dadosRelatorio['valor_total_periodo'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar relatório mensal de movimentações.',
            ], 500);
        }
    }

    public function relatorioMovimentacoesMedicaoPdf(Request $request, $id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('status', ['medicao', 'medicao_finalizada'])
            ->where('id_locacao', (int) $id)
            ->with(['cliente', 'empresa'])
            ->firstOrFail();

        if ($locacao->empresa) {
            $this->normalizarLogoEmpresa($locacao->empresa);
        }

        $dadosRelatorio = $this->montarDadosRelatorioMovimentacoesMedicao($locacao, $idEmpresa);
        $periodosFaturados = $this->montarPeriodosFaturadosMedicao($locacao, (int) $idEmpresa);
        $logoEmpresaDataUri = $this->montarLogoDataUriEmpresa($locacao->empresa);

        $pdf = Pdf::loadView('locacoes.relatorios.medicao-mensal-pdf', [
            'locacao' => $locacao,
            'periodoInicio' => $dadosRelatorio['periodo_inicio'],
            'periodoFim' => $dadosRelatorio['periodo_fim'],
            'resumo' => $dadosRelatorio['resumo'],
            'movimentacoes' => $dadosRelatorio['movimentacoes'],
            'produtosResumo' => $dadosRelatorio['produtos_resumo'] ?? [],
            'periodosFaturados' => $periodosFaturados,
            'logoEmpresaDataUri' => $logoEmpresaDataUri,
            'valorTotalPeriodo' => (float) ($dadosRelatorio['valor_total_periodo'] ?? 0),
        ]);

        $nome = 'Relatorio_Medicao_' . ($locacao->codigo_display ?: $locacao->id_locacao)
            . '_' . $dadosRelatorio['periodo_inicio']->format('Ymd')
            . '_' . $dadosRelatorio['periodo_fim']->format('Ymd')
            . '.pdf';
        return $pdf->stream($nome);
    }

    public function listarItensMovimentacaoMedicao($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->whereIn('status', ['medicao', 'medicao_finalizada'])
                ->where('id_locacao', (int) $id)
                ->with(['produtos.produto', 'produtos.patrimonio'])
                ->first();

            if (!$locacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato de medição não encontrado.',
                ], 404);
            }

            $periodosFaturados = $this->montarPeriodosFaturadosMedicao($locacao, (int) $idEmpresa);
            $ultimoFimPeriodoFaturado = $this->obterUltimoFimPeriodoFaturadoMedicao((int) $locacao->id_locacao, (int) $idEmpresa);
            $dataEnvioMinima = $ultimoFimPeriodoFaturado
                ? $ultimoFimPeriodoFaturado->copy()->addDay()->startOfDay()
                : $this->obterInicioLocacaoMedicao($locacao);

            $itens = ($locacao->produtos ?? collect())
                ->sortByDesc('id_produto_locacao')
                ->values()
                ->map(function ($item) use ($periodosFaturados) {
                    $retornado = (int) ($item->estoque_status ?? 0) === 2
                        || !in_array($item->status_retorno, [null, '', 'pendente'], true);

                    $inicioItem = Carbon::parse((string) $item->data_inicio)->startOfDay();
                    $fimHoje = now()->endOfDay();
                    $fimReal = !empty($item->data_fim)
                        ? Carbon::parse((string) $item->data_fim)->endOfDay()
                        : null;

                    $quantidade = (int) ($item->quantidade ?? 1);
                    $valorUnitario = (float) ($item->preco_unitario ?? 0);
                    $diasPrevistos = $this->calcularDiasMedicaoPeriodo($inicioItem, $fimHoje);
                    $fimRealCalculo = $retornado && $fimReal ? $fimReal : $fimHoje;
                    $diasRealizados = $this->calcularDiasMedicaoPeriodo($inicioItem, $fimRealCalculo);

                    $valorPrevistoHoje = round($valorUnitario * max(1, $quantidade) * $diasPrevistos, 2);
                    $valorRealizado = round($valorUnitario * max(1, $quantidade) * $diasRealizados, 2);

                    $podeEditarDatas = $this->itemPodeEditarDatasMedicao($item, $periodosFaturados);

                    return [
                        'id_produto_locacao' => $item->id_produto_locacao,
                        'id_produto' => $item->id_produto,
                        'produto' => $item->produto->nome ?? 'Produto',
                        'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? '-',
                        'usa_patrimonio' => !empty($item->id_patrimonio),
                        'estoque_status' => (int) ($item->estoque_status ?? 0),
                        'quantidade' => $quantidade,
                        'preco_unitario' => $valorUnitario,
                        'data_inicio_iso' => $item->data_inicio ? Carbon::parse((string) $item->data_inicio)->format('Y-m-d') : null,
                        'data_inicio' => $item->data_inicio ? Carbon::parse((string) $item->data_inicio)->format('d/m/Y') : '-',
                        'hora_inicio' => '00:00',
                        'data_fim_iso' => $item->data_fim ? Carbon::parse((string) $item->data_fim)->format('Y-m-d') : null,
                        'data_fim' => $item->data_fim ? Carbon::parse((string) $item->data_fim)->format('d/m/Y') : '-',
                        'hora_fim' => '00:00',
                        'status_retorno' => $item->status_retorno ?: 'pendente',
                        'retornado' => $retornado,
                        'pode_editar_datas' => $podeEditarDatas,
                        'dias_previstos_hoje' => $diasPrevistos,
                        'dias_realizados' => $diasRealizados,
                        'valor_previsto_hoje' => $valorPrevistoHoje,
                        'valor_realizado' => $valorRealizado,
                        'periodo_previsto_hoje' => $inicioItem->format('d/m/Y') . ' até ' . $fimHoje->format('d/m/Y'),
                        'periodo_realizado' => $inicioItem->format('d/m/Y') . ' até ' . $fimRealCalculo->format('d/m/Y'),
                    ];
                });

            $valorFaturadoMedicao = $this->obterTotalFaturadoMedicaoLocacao((int) $locacao->id_locacao, (int) $idEmpresa);
            $valorRestanteLimite = $this->obterSaldoDisponivelEnvioMedicaoLocacao(
                $locacao,
                $dataEnvioMinima->copy()->startOfDay(),
                now()->endOfDay(),
                $valorFaturadoMedicao
            );

            return response()->json([
                'success' => true,
                'itens' => $itens,
                'limite_medicao' => [
                    'valor_limite' => $this->obterValorLimiteMedicaoLocacao($locacao),
                    'valor_faturado' => $valorFaturadoMedicao,
                    'valor_restante' => $valorRestanteLimite,
                ],
                'data_envio_minima' => $dataEnvioMinima->format('Y-m-d'),
                'data_envio_minima_local' => $dataEnvioMinima->format('Y-m-d'),
                'periodos_faturados' => collect($periodosFaturados)->map(function ($periodo) {
                    return [
                        'inicio' => $periodo['inicio']->format('d/m/Y'),
                        'fim' => $periodo['fim']->format('d/m/Y'),
                        'valor' => (float) ($periodo['valor'] ?? 0),
                        'numero_fatura' => $periodo['numero_fatura'] ?? null,
                        'id_faturamento' => $periodo['id_faturamento_locacao'] ?? null,
                        'pdf_url' => $periodo['pdf_url'] ?? null,
                    ];
                })->values(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar itens da medição.',
            ], 500);
        }
    }

    public function listarProdutosDisponiveisMedicao(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'medicao')
                ->where('id_locacao', (int) $id)
                ->first();

            if (!$locacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato de medição não encontrado.',
                ], 404);
            }

            $termo = trim((string) $request->input('q', ''));

            $produtos = Produto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'ativo')
                ->when($termo !== '', function ($query) use ($termo) {
                    $query->where(function ($q) use ($termo) {
                        $q->where('nome', 'like', "%{$termo}%")
                            ->orWhere('codigo', 'like', "%{$termo}%");
                    });
                })
                ->orderBy('nome')
                ->limit(80)
                ->with(['patrimonios' => function ($q) use ($idEmpresa) {
                    $q->where('id_empresa', $idEmpresa)
                        ->where('status', 'Ativo')
                        ->where(function ($qq) {
                            $qq->whereNull('status_locacao')
                                ->orWhere('status_locacao', 'Disponivel');
                        });
                }])
                ->get(['id_produto', 'nome', 'codigo', 'preco_locacao']);

            return response()->json([
                'success' => true,
                'produtos' => $produtos->map(function ($produto) {
                    $patrimonios = collect($produto->patrimonios ?? [])->map(function ($patrimonio) {
                        return [
                            'id_patrimonio' => (int) $patrimonio->id_patrimonio,
                            'codigo' => $patrimonio->codigo_patrimonio ?: $patrimonio->numero_serie ?: ('PAT-' . $patrimonio->id_patrimonio),
                        ];
                    })->values();

                    return [
                        'id_produto' => $produto->id_produto,
                        'nome' => $produto->nome,
                        'codigo' => $produto->codigo,
                        'preco_locacao' => (float) ($produto->preco_locacao ?? 0),
                        'patrimonios' => $patrimonios,
                        'usa_patrimonio' => $patrimonios->count() > 0,
                    ];
                })->values(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar produtos disponíveis.',
            ], 500);
        }
    }

    public function enviarProdutoMedicao(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'medicao')
                ->where('id_locacao', (int) $id)
                ->first();

            if (!$locacao) {
                throw new \Exception('Contrato de medição não encontrado.');
            }

            if (in_array((string) $locacao->status, ['encerrado', 'cancelado', 'cancelada'], true)) {
                throw new \Exception('Não é possível enviar produto para contrato encerrado/cancelado.');
            }

            $saldoLimiteMedicao = $this->obterSaldoLimiteMedicaoLocacao($locacao);
            if ($saldoLimiteMedicao !== null && $saldoLimiteMedicao <= 0) {
                throw new \Exception('O valor limite da medição foi atingido. Ajuste o limite para continuar enviando produtos.');
            }

            $itensPayload = $request->input('itens', []);
            if (empty($itensPayload) && $request->filled('id_produto')) {
                $itensPayload = [[
                    'id_produto' => $request->input('id_produto'),
                    'quantidade' => $request->input('quantidade'),
                    'preco_unitario' => $request->input('preco_unitario'),
                    'data_envio' => $request->input('data_envio'),
                    'data_hora_envio' => $request->input('data_hora_envio'),
                ]];
            }

            $request->merge(['itens' => $itensPayload]);
            $request->validate([
                'itens' => ['required', 'array', 'min:1'],
                'itens.*.id_produto' => [
                    'required',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'itens.*.quantidade' => ['required', 'integer', 'min:1'],
                'itens.*.preco_unitario' => ['nullable'],
                'itens.*.data_envio' => ['nullable', 'date'],
                'itens.*.data_hora_envio' => ['nullable', 'date'],
                'itens.*.patrimonios' => ['nullable', 'array'],
                'itens.*.patrimonios.*' => ['integer', 'distinct'],
            ]);

            $inicioContrato = optional($locacao->data_inicio)->format('Y-m-d')
                ? Carbon::parse(optional($locacao->data_inicio)->format('Y-m-d'))->startOfDay()
                : null;

            $ultimoFimPeriodoFaturado = $this->obterUltimoFimPeriodoFaturadoMedicao((int) $locacao->id_locacao, (int) $idEmpresa);
            $inicioPermitidoPosFaturamento = $ultimoFimPeriodoFaturado
                ? $ultimoFimPeriodoFaturado->copy()->addDay()->startOfDay()
                : null;

            $hojeFim = now()->endOfDay();
            $inicioCorteAtual = $inicioPermitidoPosFaturamento
                ? $inicioPermitidoPosFaturamento->copy()
                : ($inicioContrato ? $inicioContrato->copy() : now()->startOfDay());
            $saldoDisponivelLimiteEnvio = $this->obterSaldoDisponivelEnvioMedicaoLocacao($locacao, $inicioCorteAtual, $hojeFim);
            $valorProjetadoNovosItens = 0.0;

            if ($saldoDisponivelLimiteEnvio !== null && $saldoDisponivelLimiteEnvio <= 0) {
                throw new \Exception('Limite disponível da medição esgotado. Ajuste o limite para continuar enviando produtos.');
            }

            $itensCriados = [];

            foreach ($itensPayload as $itemPayload) {
                $idProduto = (int) ($itemPayload['id_produto'] ?? 0);
                $produto = Produto::query()
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_produto', $idProduto)
                    ->where('status', 'ativo')
                    ->first();

                if (!$produto) {
                    throw new \Exception('Produto não encontrado ou inativo.');
                }

                $quantidade = max(1, (int) ($itemPayload['quantidade'] ?? 1));
                $precoUnitario = !empty($itemPayload['preco_unitario'])
                    ? $this->parseDecimal($itemPayload['preco_unitario'])
                    : (float) ($produto->preco_locacao ?? $produto->preco ?? 0);

                $patrimoniosDisponiveis = Patrimonio::query()
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_produto', $produto->id_produto)
                    ->where('status', 'Ativo')
                    ->where(function ($q) {
                        $q->whereNull('status_locacao')
                            ->orWhere('status_locacao', 'Disponivel');
                    })
                    ->get(['id_patrimonio', 'numero_serie']);

                $produtoUsaPatrimonio = $patrimoniosDisponiveis->isNotEmpty();
                $patrimoniosSelecionados = collect($itemPayload['patrimonios'] ?? [])
                    ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
                    ->filter(fn ($idPatrimonio) => $idPatrimonio > 0)
                    ->unique()
                    ->values();

                if ($produtoUsaPatrimonio) {
                    if ($patrimoniosSelecionados->count() !== $quantidade) {
                        throw new \Exception("Selecione exatamente {$quantidade} patrimônio(s) para o produto {$produto->nome}.");
                    }

                    $idsDisponiveis = $patrimoniosDisponiveis->pluck('id_patrimonio')->map(fn ($v) => (int) $v)->all();
                    $invalidos = $patrimoniosSelecionados->reject(fn ($idPatrimonio) => in_array($idPatrimonio, $idsDisponiveis, true));
                    if ($invalidos->isNotEmpty()) {
                        throw new \Exception("Patrimônio inválido/indisponível selecionado para o produto {$produto->nome}.");
                    }
                }

                $dataEnvio = !empty($itemPayload['data_envio'])
                    ? Carbon::parse((string) $itemPayload['data_envio'])->startOfDay()
                    : (!empty($itemPayload['data_hora_envio'])
                        ? Carbon::parse((string) $itemPayload['data_hora_envio'])->startOfDay()
                        : now()->startOfDay());

                if ($inicioContrato && $dataEnvio->lt($inicioContrato)) {
                    throw new \Exception('A data de envio não pode ser anterior ao início do contrato.');
                }

                if ($inicioPermitidoPosFaturamento && $dataEnvio->lt($inicioPermitidoPosFaturamento)) {
                    throw new \Exception(sprintf(
                        'Não é permitido enviar produto em período já faturado. Próximo envio permitido: %s.',
                        $inicioPermitidoPosFaturamento->format('d/m/Y')
                    ));
                }

                if ($saldoDisponivelLimiteEnvio !== null && $dataEnvio->lte($hojeFim->copy()->startOfDay())) {
                    $diasProjetados = $this->calcularDiasMedicaoPeriodo($dataEnvio->copy()->startOfDay(), $hojeFim->copy());
                    $valorProjetadoItem = round($precoUnitario * $quantidade * $diasProjetados, 2);
                    $valorProjetadoNovosItens = round($valorProjetadoNovosItens + $valorProjetadoItem, 2);

                    if (($valorProjetadoNovosItens - $saldoDisponivelLimiteEnvio) > 0.00001) {
                        throw new \Exception(sprintf(
                            'Envio bloqueado: o lote ultrapassa o limite disponível da medição. Disponível: R$ %s | Projetado no lote: R$ %s.',
                            number_format($saldoDisponivelLimiteEnvio, 2, ',', '.'),
                            number_format($valorProjetadoNovosItens, 2, ',', '.')
                        ));
                    }
                }

                $baseDadosItem = [
                    'id_empresa' => $idEmpresa,
                    'id_locacao' => $locacao->id_locacao,
                    'id_produto' => $produto->id_produto,
                    'preco_unitario' => $precoUnitario,
                    'data_inicio' => $dataEnvio->toDateString(),
                    'hora_inicio' => '00:00:00',
                    'data_fim' => $dataEnvio->toDateString(),
                    'hora_fim' => '00:00:00',
                    'data_contrato' => optional($locacao->data_inicio)->format('Y-m-d') ?: $dataEnvio->toDateString(),
                    'data_contrato_fim' => optional($locacao->data_fim)->format('Y-m-d') ?: $dataEnvio->toDateString(),
                    'hora_contrato' => '00:00:00',
                    'hora_contrato_fim' => '00:00:00',
                    'tipo_cobranca' => 'diaria',
                    'tipo_movimentacao' => 'entrega',
                    'valor_fechado' => 0,
                    'status_retorno' => 'pendente',
                    'estoque_status' => 0,
                ];

                if ($produtoUsaPatrimonio) {
                    foreach ($patrimoniosSelecionados as $idPatrimonio) {
                        $itemCriado = LocacaoProduto::create(array_merge($baseDadosItem, [
                            'id_patrimonio' => $idPatrimonio,
                            'quantidade' => 1,
                            'preco_total' => $precoUnitario,
                        ]));

                        $this->estoqueService->registrarSaidaLocacao($itemCriado, Auth::user()->id_usuario ?? Auth::id());
                        $itemCriado->estoque_status = 1;
                        $itemCriado->save();

                        $itensCriados[] = [
                            'id_produto_locacao' => $itemCriado->id_produto_locacao,
                            'produto' => $produto->nome,
                            'quantidade' => 1,
                            'id_patrimonio' => $idPatrimonio,
                        ];
                    }
                } else {
                    $itemCriado = LocacaoProduto::create(array_merge($baseDadosItem, [
                        'id_patrimonio' => null,
                        'quantidade' => $quantidade,
                        'preco_total' => $precoUnitario * $quantidade,
                    ]));

                    $this->estoqueService->registrarSaidaLocacao($itemCriado, Auth::user()->id_usuario ?? Auth::id());
                    $itemCriado->estoque_status = 1;
                    $itemCriado->save();

                    $itensCriados[] = [
                        'id_produto_locacao' => $itemCriado->id_produto_locacao,
                        'produto' => $produto->nome,
                        'quantidade' => $quantidade,
                    ];
                }
            }

            $this->sincronizarTotaisLocacao($locacao);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($itensCriados) > 1
                    ? 'Produtos enviados para a medição com sucesso.'
                    : 'Produto enviado para a medição com sucesso.',
                'itens' => $itensCriados,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos para envio do produto.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function retornarItemMedicao(Request $request, $id, $idProdutoLocacao)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? Auth::id();

            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'medicao')
                ->where('id_locacao', (int) $id)
                ->first();

            if (!$locacao) {
                throw new \Exception('Contrato de medição não encontrado.');
            }

            $item = LocacaoProduto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->where('id_produto_locacao', (int) $idProdutoLocacao)
                ->with(['produto', 'locacao'])
                ->first();

            if (!$item) {
                throw new \Exception('Item não encontrado para retorno.');
            }

            $jaRetornado = (int) ($item->estoque_status ?? 0) === 2
                || !in_array($item->status_retorno, [null, '', 'pendente'], true);

            if ($jaRetornado) {
                throw new \Exception('Este item já foi retornado.');
            }

            if ((int) ($item->estoque_status ?? 0) !== 1) {
                throw new \Exception('Este item não está com saída de estoque ativa para retorno.');
            }

            $request->validate([
                'data_retorno' => ['nullable', 'date'],
                'data_hora_retorno' => ['nullable', 'date'],
                'data_envio' => ['nullable', 'date'],
                'quantidade_retorno' => ['nullable', 'integer', 'min:1'],
            ]);

            $dataRetorno = $request->filled('data_retorno')
                ? Carbon::parse((string) $request->input('data_retorno'))->startOfDay()
                : ($request->filled('data_hora_retorno')
                    ? Carbon::parse((string) $request->input('data_hora_retorno'))->startOfDay()
                    : null);

            if (!$dataRetorno) {
                throw new \Exception('Informe a data de retorno.');
            }

            $dataEnvioAjustada = $request->filled('data_envio')
                ? Carbon::parse((string) $request->input('data_envio'))->startOfDay()
                : null;

            $inicioItem = $this->combinarDataHoraSegura(
                $item->data_inicio ?: (optional($locacao->data_inicio)->format('Y-m-d') ?: now()->toDateString()),
                $item->hora_inicio ?: ($locacao->hora_inicio ?: '00:00:00'),
                '00:00:00'
            )->startOfDay();

            $inicioComparacao = $dataEnvioAjustada ?: $inicioItem;

            if ($dataRetorno->lt($inicioComparacao)) {
                throw new \Exception('A data de retorno não pode ser anterior ao envio.');
            }

            $periodosFaturados = $this->montarPeriodosFaturadosMedicao($locacao, (int) $idEmpresa);
            if ($dataEnvioAjustada) {
                if ($this->intervaloMedicaoSobrepoePeriodoFaturado($inicioComparacao, $dataRetorno->copy()->endOfDay(), $periodosFaturados)) {
                    throw new \Exception('Não é permitido editar datas em período já faturado.');
                }
            } else {
                $ultimoFimPeriodoFaturado = $this->obterUltimoFimPeriodoFaturadoMedicao((int) $locacao->id_locacao, (int) $idEmpresa);
                if ($ultimoFimPeriodoFaturado && $dataRetorno->lt($ultimoFimPeriodoFaturado->copy()->startOfDay())) {
                    throw new \Exception(sprintf(
                        'Não é permitido registrar retorno com data anterior ao último período faturado (%s).',
                        $ultimoFimPeriodoFaturado->format('d/m/Y')
                    ));
                }
            }

            $quantidadeItem = max(1, (int) ($item->quantidade ?? 1));
            $quantidadeRetorno = max(1, (int) $request->input('quantidade_retorno', $quantidadeItem));

            if ($item->id_patrimonio && $quantidadeRetorno !== 1) {
                throw new \Exception('Itens com patrimônio permitem retorno apenas unitário.');
            }

            if ($quantidadeRetorno > $quantidadeItem) {
                throw new \Exception('A quantidade de retorno não pode ser maior que a quantidade enviada.');
            }

            $precoUnitarioItem = (float) ($item->preco_unitario ?? 0);
            $diasPeriodo = $this->calcularDiasMedicaoPeriodo($inicioComparacao, $dataRetorno->copy()->endOfDay());
            $fatorCobranca = (bool) ($item->valor_fechado ?? false) ? 1 : $diasPeriodo;

            if (!$item->id_patrimonio && $quantidadeRetorno < $quantidadeItem) {
                $itemRetornado = $item->replicate();
                $itemRetornado->quantidade = $quantidadeRetorno;
                $itemRetornado->preco_total = round($precoUnitarioItem * $quantidadeRetorno * $fatorCobranca, 2);

                if ($dataEnvioAjustada) {
                    $itemRetornado->data_inicio = $dataEnvioAjustada->toDateString();
                    $itemRetornado->hora_inicio = '00:00:00';
                }

                $itemRetornado->data_fim = $dataRetorno->toDateString();
                $itemRetornado->hora_fim = '00:00:00';
                $itemRetornado->estoque_status = 2;
                $itemRetornado->status_retorno = 'devolvido';
                $itemRetornado->save();

                if ($dataEnvioAjustada) {
                    $item->data_inicio = $dataEnvioAjustada->toDateString();
                    $item->hora_inicio = '00:00:00';
                }

                $quantidadeRestante = max(1, $quantidadeItem - $quantidadeRetorno);
                $item->quantidade = $quantidadeRestante;
                $item->preco_total = round($precoUnitarioItem * $quantidadeRestante * $fatorCobranca, 2);
                $item->save();

                $this->registrarRetornoParcialQuantidadeProduto($item, $quantidadeRetorno, $idUsuario);
                $this->sincronizarTotaisLocacao($locacao);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Retorno parcial registrado com sucesso.',
                ]);
            }

            $item->preco_total = round($precoUnitarioItem * $quantidadeItem * $fatorCobranca, 2);

            if ($dataEnvioAjustada) {
                $item->data_inicio = $dataEnvioAjustada->toDateString();
                $item->hora_inicio = '00:00:00';
            }

            $item->data_fim = $dataRetorno->toDateString();
            $item->hora_fim = '00:00:00';

            $this->estoqueService->registrarRetornoLocacao(
                $item,
                'devolvido',
                'Retorno do item pela movimentação de medição',
                $idUsuario
            );

            $item->estoque_status = 2;
            $item->status_retorno = 'devolvido';
            $item->save();

            if ($item->id_patrimonio) {
                LocacaoRetornoPatrimonio::updateOrCreate(
                    [
                        'id_locacao' => $locacao->id_locacao,
                        'id_produto_locacao' => $item->id_produto_locacao,
                        'id_patrimonio' => $item->id_patrimonio,
                    ],
                    [
                        'id_empresa' => $idEmpresa,
                        'data_retorno' => $dataRetorno,
                        'status_retorno' => 'normal',
                        'observacoes_retorno' => 'Retorno via movimentação de medição',
                        'id_usuario' => $idUsuario,
                    ]
                );
            }

            $this->sincronizarTotaisLocacao($locacao);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item retornado com sucesso.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function atualizarDatasItemMedicao(Request $request, $id, $idProdutoLocacao)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? Auth::id();

            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'medicao')
                ->where('id_locacao', (int) $id)
                ->first();

            if (!$locacao) {
                throw new \Exception('Contrato de medição não encontrado.');
            }

            $item = LocacaoProduto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->where('id_produto_locacao', (int) $idProdutoLocacao)
                ->first();

            if (!$item) {
                throw new \Exception('Item não encontrado para edição de datas.');
            }

            $request->validate([
                'data_inicio' => ['required', 'date'],
                'data_retorno' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            ]);

            $dataInicio = Carbon::parse((string) $request->input('data_inicio'))->startOfDay();

            $retornado = (int) ($item->estoque_status ?? 0) === 2
                || !in_array($item->status_retorno, [null, '', 'pendente'], true);

            $dataRetorno = $request->filled('data_retorno')
                ? Carbon::parse((string) $request->input('data_retorno'))->endOfDay()
                : ($retornado && !empty($item->data_fim)
                    ? Carbon::parse((string) $item->data_fim)->endOfDay()
                    : $dataInicio->copy()->endOfDay());

            $periodosFaturados = $this->montarPeriodosFaturadosMedicao($locacao, (int) $idEmpresa);
            if ($this->intervaloMedicaoSobrepoePeriodoFaturado($dataInicio, $dataRetorno, $periodosFaturados)) {
                throw new \Exception('Não é permitido editar datas em período já faturado.');
            }

            $inicioContrato = $this->obterInicioLocacaoMedicao($locacao);
            if ($dataInicio->lt($inicioContrato)) {
                throw new \Exception('A data de envio não pode ser anterior ao início do contrato.');
            }

            $ultimoFimPeriodoFaturado = $this->obterUltimoFimPeriodoFaturadoMedicao((int) $locacao->id_locacao, (int) $idEmpresa);
            if ($ultimoFimPeriodoFaturado) {
                $inicioPermitidoPosFaturamento = $ultimoFimPeriodoFaturado->copy()->addDay()->startOfDay();
                if ($dataInicio->lt($inicioPermitidoPosFaturamento)) {
                    throw new \Exception(sprintf(
                        'Não é permitido enviar produto em período já faturado. Próximo envio permitido: %s.',
                        $inicioPermitidoPosFaturamento->format('d/m/Y')
                    ));
                }
            }

            $quantidadeItem = max(1, (int) ($item->quantidade ?? 1));
            $precoUnitarioItem = (float) ($item->preco_unitario ?? 0);
            $diasPeriodo = $this->calcularDiasMedicaoPeriodo($dataInicio, $dataRetorno);
            $fatorCobranca = (bool) ($item->valor_fechado ?? false) ? 1 : $diasPeriodo;

            $item->data_inicio = $dataInicio->toDateString();
            $item->hora_inicio = '00:00:00';
            $item->preco_total = round($precoUnitarioItem * $quantidadeItem * $fatorCobranca, 2);

            if ($retornado) {
                $item->data_fim = $dataRetorno->toDateString();
                $item->hora_fim = '00:00:00';
            } else {
                $item->data_fim = $dataInicio->toDateString();
                $item->hora_fim = '00:00:00';

                $agoraInicioDia = now()->startOfDay();
                $estoqueStatusAtual = (int) ($item->estoque_status ?? 0);

                if ($dataInicio->lte($agoraInicioDia) && $estoqueStatusAtual === 0) {
                    $this->estoqueService->registrarSaidaLocacao(
                        $item,
                        $idUsuario
                    );
                    $item->estoque_status = 1;
                }

                if ($dataInicio->gt($agoraInicioDia) && $estoqueStatusAtual === 1) {
                    $this->estoqueService->registrarRetornoLocacao(
                        $item,
                        'devolvido',
                        'Retorno automático ao editar item para início futuro',
                        $idUsuario
                    );

                    $item->estoque_status = 0;
                    $item->status_retorno = 'pendente';

                    if ($item->id_patrimonio) {
                        LocacaoRetornoPatrimonio::query()
                            ->where('id_locacao', $locacao->id_locacao)
                            ->where('id_produto_locacao', $item->id_produto_locacao)
                            ->where('id_patrimonio', $item->id_patrimonio)
                            ->delete();
                    }
                }
            }

            $item->save();

            $this->sincronizarTotaisLocacao($locacao);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Datas do item atualizadas com sucesso.',
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos para edição de datas.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function montarDadosRelatorioMovimentacoesMedicao(Locacao $locacao, int $idEmpresa): array
    {
        $inicioPeriodo = $this->obterInicioLocacaoMedicao($locacao);
        $fimPeriodo = now()->endOfDay();

        if (in_array((string) ($locacao->status ?? ''), ['encerrado', 'finalizada', 'cancelado', 'cancelada'], true)) {
            if (!empty($locacao->data_fim)) {
                $fimPeriodo = Carbon::parse((string) $locacao->data_fim)->endOfDay();
            }
        }

        $itens = LocacaoProduto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->with(['produto', 'patrimonio'])
            ->get();

        $movimentacoes = collect();
        $resumo = [
            'entradas_qtd' => 0,
            'retornos_qtd' => 0,
            'entradas_itens' => 0,
            'retornos_itens' => 0,
        ];
        $valorTotalPeriodo = 0.0;
        $produtosResumo = [];

        foreach ($itens as $item) {
            $dataInicio = $item->data_inicio
                ? Carbon::parse((string) $item->data_inicio)->startOfDay()
                : null;

            if (!$dataInicio) {
                continue;
            }

            $itemRetornado = (int) ($item->estoque_status ?? 0) === 2
                || !in_array($item->status_retorno, [null, '', 'pendente'], true);

            $dataFimReal = !empty($item->data_fim)
                ? Carbon::parse((string) $item->data_fim)->endOfDay()
                : null;

            if ($dataInicio->between($inicioPeriodo, $fimPeriodo)) {
                $movimentacoes->push([
                    'tipo' => 'Entrada',
                    'data_hora' => $dataInicio,
                    'produto' => $item->produto->nome ?? 'Produto',
                    'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                    'quantidade' => (int) max(1, $item->quantidade ?? 1),
                ]);
                $resumo['entradas_qtd']++;
                $resumo['entradas_itens'] += (int) max(1, $item->quantidade ?? 1);
            }

            if ($itemRetornado && $dataFimReal && $dataFimReal->between($inicioPeriodo, $fimPeriodo)) {
                $movimentacoes->push([
                    'tipo' => 'Retorno',
                    'data_hora' => $dataFimReal,
                    'produto' => $item->produto->nome ?? 'Produto',
                    'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                    'quantidade' => (int) max(1, $item->quantidade ?? 1),
                ]);
                $resumo['retornos_qtd']++;
                $resumo['retornos_itens'] += (int) max(1, $item->quantidade ?? 1);
            }

            $fimBaseCalculo = $itemRetornado
                ? ($dataFimReal ?: $fimPeriodo->copy()->endOfDay())
                : $fimPeriodo->copy()->endOfDay();

            $inicioEfetivo = $dataInicio->copy()->max($inicioPeriodo->copy()->startOfDay());
            $fimEfetivo = $fimBaseCalculo->copy()->min($fimPeriodo->copy()->endOfDay());

            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $precoUnitario = (float) ($item->preco_unitario ?? 0);
            $diasPeriodo = 0;
            $valorPeriodo = 0.0;

            if ($fimEfetivo->gte($inicioEfetivo)) {
                $diasPeriodo = $this->calcularDiasMedicaoPeriodo($inicioEfetivo, $fimEfetivo);
                $valorPeriodo = $precoUnitario * $quantidade * $diasPeriodo;
                $valorTotalPeriodo += $valorPeriodo;
            }

            $produtosResumo[] = [
                'produto' => $item->produto->nome ?? 'Produto',
                'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? '-',
                'quantidade' => $quantidade,
                'valor_unitario' => $precoUnitario,
                'dias_periodo' => $diasPeriodo,
                'valor_periodo' => round(max(0, $valorPeriodo), 2),
                'inicio' => $inicioEfetivo,
                'fim' => $fimEfetivo,
            ];
        }

        return [
            'periodo_inicio' => $inicioPeriodo,
            'periodo_fim' => $fimPeriodo,
            'resumo' => $resumo,
            'movimentacoes' => $movimentacoes->sortByDesc('data_hora')->values(),
            'produtos_resumo' => $produtosResumo,
            'valor_total_periodo' => round(max(0, $valorTotalPeriodo), 2),
        ];
    }

    private function obterInicioLocacaoMedicao(Locacao $locacao): Carbon
    {
        $dataInicioLocacao = optional($locacao->data_inicio)->format('Y-m-d');
        return $dataInicioLocacao
            ? Carbon::parse($dataInicioLocacao)->startOfDay()
            : now()->startOfDay();
    }

    private function intervaloMedicaoSobrepoePeriodoFaturado(Carbon $inicio, Carbon $fim, array $periodosFaturados): bool
    {
        if ($fim->lt($inicio) || empty($periodosFaturados)) {
            return false;
        }

        foreach ($periodosFaturados as $periodo) {
            $inicioPeriodo = ($periodo['inicio'] ?? null) instanceof Carbon
                ? $periodo['inicio']->copy()->startOfDay()
                : null;
            $fimPeriodo = ($periodo['fim'] ?? null) instanceof Carbon
                ? $periodo['fim']->copy()->endOfDay()
                : null;

            if (!$inicioPeriodo || !$fimPeriodo) {
                continue;
            }

            if ($inicio->lte($fimPeriodo) && $fim->gte($inicioPeriodo)) {
                return true;
            }
        }

        return false;
    }

    private function itemPodeEditarDatasMedicao(LocacaoProduto $item, array $periodosFaturados): bool
    {
        if (empty($periodosFaturados)) {
            return true;
        }

        if (empty($item->data_inicio)) {
            return false;
        }

        $inicio = Carbon::parse((string) $item->data_inicio)->startOfDay();

        $retornado = (int) ($item->estoque_status ?? 0) === 2
            || !in_array($item->status_retorno, [null, '', 'pendente'], true);

        $fim = ($retornado && !empty($item->data_fim))
            ? Carbon::parse((string) $item->data_fim)->endOfDay()
            : now()->endOfDay();

        return !$this->intervaloMedicaoSobrepoePeriodoFaturado($inicio, $fim, $periodosFaturados);
    }

    private function calcularDiasMedicaoPeriodo(Carbon $inicio, Carbon $fim): int
    {
        if ($fim->lt($inicio)) {
            return 0;
        }

        return max(1, $inicio->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay()) + 1);
    }

    private function montarPeriodosFaturadosMedicao(Locacao $locacao, int $idEmpresa): array
    {
        $faturas = FaturamentoLocacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->whereNull('deleted_at')
            ->orderBy('data_faturamento')
            ->orderBy('id_faturamento_locacao')
            ->get(['id_faturamento_locacao', 'numero_fatura', 'data_faturamento', 'descricao', 'valor_total']);

        $inicioAtual = $this->obterInicioLocacaoMedicao($locacao);
        $periodos = [];

        foreach ($faturas as $fatura) {
            $periodoDescricao = $this->extrairPeriodoDescricaoFaturamentoMedicao((string) ($fatura->descricao ?? ''));
            if ($periodoDescricao) {
                $inicioPeriodo = $periodoDescricao['inicio']->copy()->startOfDay();
                $fimPeriodo = $periodoDescricao['fim']->copy()->endOfDay();
            } else {
                if (!$fatura->data_faturamento) {
                    continue;
                }
                $inicioPeriodo = $inicioAtual->copy();
                $fimPeriodo = Carbon::parse((string) $fatura->data_faturamento)->endOfDay();
            }

            if ($fimPeriodo->lt($inicioAtual)) {
                continue;
            }

            $periodos[] = [
                'id_faturamento_locacao' => $fatura->id_faturamento_locacao,
                'numero_fatura' => $fatura->numero_fatura,
                'inicio' => $inicioPeriodo,
                'fim' => $fimPeriodo->copy(),
                'valor' => (float) ($fatura->valor_total ?? 0),
                'pdf_url' => $fatura->id_faturamento_locacao
                    ? route('financeiro.faturamento.pdf', ['idFaturamento' => $fatura->id_faturamento_locacao])
                    : null,
            ];

            $inicioAtual = $fimPeriodo->copy()->addDay()->startOfDay();
        }

        return $periodos;
    }

    private function obterUltimoFimPeriodoFaturadoMedicao(int $idLocacao, int $idEmpresa): ?Carbon
    {
        $faturas = FaturamentoLocacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->whereNull('deleted_at')
            ->orderBy('data_faturamento')
            ->orderBy('id_faturamento_locacao')
            ->get(['data_faturamento', 'descricao']);

        $ultimoFim = null;

        foreach ($faturas as $fatura) {
            $periodoDescricao = $this->extrairPeriodoDescricaoFaturamentoMedicao((string) ($fatura->descricao ?? ''));
            $fim = null;

            if ($periodoDescricao) {
                $fim = $periodoDescricao['fim']->copy()->endOfDay();
            } elseif (!empty($fatura->data_faturamento)) {
                $fim = Carbon::parse((string) $fatura->data_faturamento)->endOfDay();
            }

            if ($fim && (!$ultimoFim || $fim->gt($ultimoFim))) {
                $ultimoFim = $fim;
            }
        }

        return $ultimoFim;
    }

    private function extrairPeriodoDescricaoFaturamentoMedicao(string $descricao): ?array
    {
        if (!preg_match('/\((\d{2}\/\d{2}\/\d{4})\s+[aàá]\s+(\d{2}\/\d{2}\/\d{4})\)/iu', $descricao, $matches)) {
            return null;
        }

        try {
            return [
                'inicio' => Carbon::createFromFormat('d/m/Y', $matches[1])->startOfDay(),
                'fim' => Carbon::createFromFormat('d/m/Y', $matches[2])->endOfDay(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function montarLogoDataUriEmpresa(?Empresa $empresa): ?string
    {
        if (!$empresa) {
            return null;
        }

        $configuracoes = $empresa->configuracoes ?? null;
        if (is_string($configuracoes)) {
            $decoded = json_decode($configuracoes, true);
            $configuracoes = is_array($decoded) ? $decoded : [];
        }
        $configuracoes = is_array($configuracoes) ? $configuracoes : [];

        $logo = trim((string) ($configuracoes['logo_url'] ?? $empresa->logo_url ?? ''));
        if ($logo === '') {
            return null;
        }

        if (str_starts_with($logo, 'data:image/')) {
            return $logo;
        }

        $caminhos = [];
        $pathUrl = parse_url($logo, PHP_URL_PATH);

        if (!empty($pathUrl)) {
            $caminhos[] = public_path(ltrim((string) $pathUrl, '/'));
        }

        $caminhos[] = public_path(ltrim($logo, '/'));

        if (str_contains($logo, 'storage/')) {
            $aposStorage = substr($logo, strpos($logo, 'storage/') + 8);
            $caminhos[] = storage_path('app/public/' . ltrim((string) $aposStorage, '/'));
        }

        foreach (array_unique($caminhos) as $caminho) {
            if ($caminho && File::exists($caminho)) {
                $conteudo = @file_get_contents($caminho);
                if ($conteudo !== false) {
                    $mime = @mime_content_type($caminho) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
                }
            }
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            $conteudoRemoto = @file_get_contents($logo);
            if ($conteudoRemoto !== false) {
                $ext = strtolower((string) pathinfo((string) parse_url($logo, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };
                return 'data:' . $mime . ';base64,' . base64_encode($conteudoRemoto);
            }
        }

        return null;
    }

    private function combinarDataHoraSegura($data, $hora = null, string $horaPadrao = '00:00:00'): Carbon
    {
        $dataBase = $data instanceof \DateTimeInterface
            ? Carbon::instance($data)
            : Carbon::parse((string) $data);

        $horaFonte = $hora;
        if (empty($horaFonte)) {
            $horaFonte = $dataBase->format('H:i:s');
            if ($horaFonte === '00:00:00' && $horaPadrao !== '00:00:00') {
                $horaFonte = $horaPadrao;
            }
        }

        $horaNormalizada = Carbon::parse((string) $horaFonte)->format('H:i:s');
        return Carbon::createFromFormat('Y-m-d H:i:s', $dataBase->toDateString() . ' ' . $horaNormalizada);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $permiteNumeroManualLocacao = $this->empresaPermiteNumeroManualLocacao((int) $idEmpresa);

        $origem = (string) $request->query('origem', 'orcamentos');
        $statusInicial = (string) $request->query('status', '');

        if (!in_array($statusInicial, ['orcamento', 'aprovado', 'medicao'], true)) {
            $statusInicial = $origem === 'contratos' ? 'aprovado' : 'orcamento';
        }

        $isMedicao = $statusInicial === 'medicao';
        $rotuloStatusInicial = $isMedicao
            ? 'Medição'
            : ($statusInicial === 'aprovado' ? 'Aprovado' : 'Orçamento');
        $tituloAcaoCriacao = $isMedicao
            ? 'Criar Contrato de Medição'
            : ($statusInicial === 'aprovado' ? 'Criar Contrato' : 'Criar Orçamento');
        
        $clientes = Cliente::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        $fornecedores = Fornecedor::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        $produtosTerceiros = ProdutoTerceiro::where('id_empresa', $idEmpresa)->where('status', 'ativo')->with('fornecedor')->orderBy('nome')->get();
        $usuarios = \App\Models\User::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        
        $idCliente = $request->input('id_cliente');
        $cliente = $idCliente
            ? Cliente::where('id_empresa', $idEmpresa)->find($idCliente) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            : null;

        return view('locacoes.create', compact(
            'clientes',
            'produtos',
            'fornecedores',
            'produtosTerceiros',
            'cliente',
            'usuarios',
            'permiteNumeroManualLocacao',
            'statusInicial',
            'rotuloStatusInicial',
            'tituloAcaoCriacao',
            'isMedicao'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('=== RECEBENDO DADOS DA LOCAÇÃO ===', [
            'dados' => $request->all(),
            'has_cliente' => $request->has('id_cliente'),
            'id_cliente' => $request->input('id_cliente'),
            'data_inicio' => $request->input('data_inicio'),
            'data_fim' => $request->input('data_fim'),
        ]);

        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $validated = $request->validate([
                'id_cliente' => [
                    'required',
                    'integer',
                    Rule::exists('clientes', 'id_clientes')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'id_usuario' => ['nullable', 'integer'],
                'data_inicio' => ['required', 'date'],
                'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
                'hora_inicio' => ['nullable', 'date_format:H:i'],
                'hora_fim' => ['nullable', 'date_format:H:i'],
                'local_entrega' => ['nullable', 'string', 'max:500'],
                'contato_responsavel' => ['nullable', 'string', 'max:255'],
                'telefone_responsavel' => ['nullable', 'string', 'max:20'],
                'observacoes' => ['nullable', 'string'],
                'observacoes_orcamento' => ['nullable', 'string'],
                'observacoes_recibo' => ['nullable', 'string'],
                'observacoes_entrega' => ['nullable', 'string'],
                'observacoes_checklist' => ['nullable', 'string'],
                'produtos' => ['nullable', 'array'],
                'produtos.*.id_produto' => [
                    'nullable',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'produtos.*.quantidade' => ['nullable', 'integer', 'min:1'],
                'produtos.*.valor_unitario' => ['nullable'],
                'numero_manual' => ['nullable', 'integer', 'min:0'],
                'valor_limite_medicao' => ['nullable'],
            ]);
            $idUsuario = Auth::user()->id_usuario ?? Auth::id() ?? null;
            $idUsuarioResponsavel = (int) ($request->input('id_usuario') ?: 0);
            if ($idUsuarioResponsavel <= 0) {
                $idUsuarioResponsavel = (int) ($idUsuario ?: 0);
            }
            $locacaoPorHora = $request->boolean('locacao_por_hora');
            $permiteNumeroManualLocacao = $this->empresaPermiteNumeroManualLocacao((int) $idEmpresa);
            $numeroManual = $permiteNumeroManualLocacao
                ? $this->normalizarNumeroManual($request->input('numero_manual'))
                : null;

            $this->validarPeriodoDataHora(
                $request->data_inicio,
                $request->hora_inicio,
                $request->data_fim,
                $request->hora_fim,
                'Contrato'
            );

            $novoStatus = $request->input('status', 'orcamento');
            $numeroOrcamento = Locacao::gerarProximoNumeroOrcamento((int) $idEmpresa, false);
            $numeroContrato = null;
            $statusContrato = in_array((string) $novoStatus, ['aprovado', 'medicao'], true);

            if ($statusContrato) {
                $numeroContrato = Locacao::gerarProximoNumeroContrato((int) $idEmpresa, false);
            }

            $codigoLocacao = $statusContrato ? $numeroContrato : $numeroOrcamento;

            if ($numeroManual !== null) {
                if ($statusContrato) {
                    $numeroContrato = $numeroManual;
                    $codigoLocacao = $numeroManual;
                } else {
                    $numeroOrcamento = $numeroManual;
                    $codigoLocacao = $numeroManual;
                }
            }

            if ($novoStatus === 'aprovado') {
                $this->validarVinculacaoPatrimoniosParaAprovacaoPayload(
                    $request->produtos ?? [],
                    (int) $idEmpresa
                );

                $this->validarDisponibilidadeProdutos(
                    $request->produtos ?? [],
                    $idEmpresa,
                    null,
                    [
                        'data_inicio' => $request->data_inicio,
                        'hora_inicio' => $request->hora_inicio,
                        'data_fim' => $request->data_fim,
                        'hora_fim' => $request->hora_fim,
                    ]
                );
            }

            $quantidadePeriodoContrato = $this->calcularQuantidadePeriodoCobranca(
                $request->data_inicio,
                $request->hora_inicio,
                $request->data_fim,
                $request->hora_fim,
                $locacaoPorHora,
                1
            );

            $freteEntrega = max(0, $this->parseDecimal($request->input(
                'frete_entrega',
                $request->input('taxa_entrega', $request->input('valor_acrescimo', 0))
            )));
            $freteRetirada = max(0, $this->parseDecimal($request->input('frete_retirada', 0)));
            $freteTotal = $freteEntrega + $freteRetirada;

            $dadosLocacao = [
                'id_empresa' => $idEmpresa,
                'id_cliente' => $request->id_cliente,
                'id_usuario' => $idUsuarioResponsavel > 0 ? $idUsuarioResponsavel : null,
                'numero_contrato' => $statusContrato
                    ? str_pad((string) ($codigoLocacao ?? 0), 3, '0', STR_PAD_LEFT)
                    : $this->formatarNumeroInternoOrcamento((int) ($codigoLocacao ?? 0)),
                // Período (saída/retorno)
                'data_inicio' => $request->data_inicio,
                'hora_inicio' => $request->hora_inicio,
                'data_fim' => $request->data_fim,
                'hora_fim' => $request->hora_fim,
                'quantidade_dias' => $quantidadePeriodoContrato,
                // Transporte (opcional)
                'data_transporte_ida' => $request->data_transporte_ida ?: null,
                'hora_transporte_ida' => $request->hora_transporte_ida ?: null,
                'data_transporte_volta' => $request->data_transporte_volta ?: null,
                'hora_transporte_volta' => $request->hora_transporte_volta ?: null,
                // Endereço e contato
                'local_entrega' => $request->local_entrega,
                'local_retirada' => $request->local_retirada,
                'contato_responsavel' => $request->contato_responsavel,
                'telefone_responsavel' => $request->telefone_responsavel,
                'nome_obra' => $request->nome_obra,
                'contato_local' => $request->contato_local,
                'telefone_contato' => $request->telefone_contato,
                'local_evento' => $request->local_evento,
                'endereco_entrega' => $request->endereco_entrega,
                'cidade' => $request->cidade,
                'estado' => $request->estado,
                'cep' => $request->cep,
                // Valores
                'valor_frete' => $this->parseDecimal($request->valor_frete),
                'valor_despesas_extras' => $this->parseDecimal($request->valor_despesas_extras),
                'valor_desconto' => $this->parseDecimal($request->input('desconto', $request->input('valor_desconto', $request->input('desconto_geral', 0)))),
                'valor_acrescimo' => $freteTotal,
                'valor_imposto' => $this->parseDecimal($request->valor_imposto),
                // Status e observações
                'status' => $novoStatus,
                'observacoes' => $request->observacoes,
                'responsavel' => $request->responsavel,
            ];

            if ($this->hasColunaLocacao('valor_frete_entrega')) {
                $dadosLocacao['valor_frete_entrega'] = $freteEntrega;
            }

            if ($this->hasColunaLocacao('valor_frete_retirada')) {
                $dadosLocacao['valor_frete_retirada'] = $freteRetirada;
            }

            if ($this->hasColunaLocacao('observacoes_orcamento')) {
                $dadosLocacao['observacoes_orcamento'] = $request->input('observacoes_orcamento');
            }

            if ($this->hasColunaLocacao('observacoes_recibo')) {
                $dadosLocacao['observacoes_recibo'] = $request->input('observacoes_recibo');
            }

            if ($this->hasColunaLocacao('observacoes_entrega')) {
                $dadosLocacao['observacoes_entrega'] = $request->input('observacoes_entrega');
            }

            if ($this->hasColunaLocacao('observacoes_checklist')) {
                $dadosLocacao['observacoes_checklist'] = $request->input('observacoes_checklist');
            }

            if ($request->boolean('usar_endereco_cliente')) {
                $clienteEntrega = Cliente::query()
                    ->where('id_clientes', $request->id_cliente)
                    ->where('id_empresa', $idEmpresa)
                    ->first();

                $dadosLocacao['local_entrega'] = $this->montarEnderecoEntregaCliente($clienteEntrega)
                    ?: $dadosLocacao['local_entrega'];
            }

            if ($this->hasColunaLocacao('numero_orcamento')) {
                $dadosLocacao['numero_orcamento'] = $numeroOrcamento;
            }

            if ($this->hasColunaLocacao('renovacao_automatica')) {
                $dadosLocacao['renovacao_automatica'] = $request->boolean('renovacao_automatica');
            }

            if ($this->hasColunaLocacao('locacao_por_hora')) {
                $dadosLocacao['locacao_por_hora'] = $locacaoPorHora;
            }

            if ($this->hasColunaLocacao('valor_limite_medicao')) {
                $dadosLocacao['valor_limite_medicao'] = $this->parseDecimal($request->input('valor_limite_medicao', 0));
            }

            if ($this->hasColunaLocacao('numero_orcamento_origem')) {
                $dadosLocacao['numero_orcamento_origem'] = $numeroOrcamento;
            }

            if ($numeroManual !== null) {
                $this->validarNumeroLocacaoDisponivel((int) $codigoLocacao, (int) $idEmpresa);

                if ($novoStatus === 'orcamento' && $this->hasColunaLocacao('numero_orcamento')) {
                    $this->validarNumeroOrcamentoDisponivel((int) $numeroOrcamento, (int) $idEmpresa);
                }
            }

            // Criar locação
            $locacao = Locacao::create($dadosLocacao);

            if ($novoStatus === 'orcamento') {
                ActionLogger::log($locacao, 'orcamento_criado');
            }

            // Criar salas se enviadas
            $salasMap = [];
            if ($request->has('salas') && is_array($request->salas)) {
                foreach ($request->salas as $ordem => $salaData) {
                    $sala = LocacaoSala::create([
                        'id_empresa' => $idEmpresa,
                        'id_locacao' => $locacao->id_locacao,
                        'nome' => $salaData['nome'],
                        'descricao' => $salaData['descricao'] ?? null,
                        'ordem' => $ordem,
                    ]);
                    $salasMap[(string) ($salaData['temp_id'] ?? $ordem)] = $sala->id_sala;
                }
            }

            // Adicionar produtos próprios
            $valorTotal = 0;
            $produtos = $request->produtos ?? [];
            foreach ($produtos as $item) {
                $idProdutoItem = (int) ($item['id_produto'] ?? 0);
                $produtoDaEmpresa = Produto::where('id_empresa', $idEmpresa)
                    ->where('id_produto', $idProdutoItem)
                    ->first();

                if (!$produtoDaEmpresa) {
                    throw ValidationException::withMessages([
                        'produtos' => ['Existe produto inválido para a empresa atual.'],
                    ]); // Segurança: bloqueia vínculo de locação com produto de outra empresa (IDOR).
                }

                $valorUnitario = isset($item['valor_unitario']) ? $this->parseDecimal($item['valor_unitario']) : $this->parseDecimal($item['preco_unitario'] ?? 0);
                $quantidade = intval($item['quantidade']);
                $valorFechado = isset($item['valor_fechado']) && $item['valor_fechado'] == '1';
                $idTabelaPreco = !empty($item['id_tabela_preco']) ? $item['id_tabela_preco'] : null;
                $observacoesItem = $item['observacoes'] ?? null;
                $idSala = null;
                
                // Mapear sala se informada
                $idSalaRaw = $item['id_sala'] ?? null;
                if ($idSalaRaw !== null && $idSalaRaw !== '') {
                    $idSala = $salasMap[(string) $idSalaRaw] ?? $idSalaRaw;
                }
                
                // Usar as datas específicas do item se fornecidas, senão usar as do contrato
                $dataInicioItem = $item['data_inicio'] ?? $request->data_inicio;
                $dataFimItem = $item['data_fim'] ?? $request->data_fim;
                $horaInicioItem = $item['hora_inicio'] ?? $request->hora_inicio;
                $horaFimItem = $item['hora_fim'] ?? $request->hora_fim;

                $this->validarPeriodoDataHora($dataInicioItem, $horaInicioItem, $dataFimItem, $horaFimItem, 'Período do produto');
                $this->validarPeriodoProdutoDentroContrato(
                    $dataInicioItem,
                    $horaInicioItem,
                    $dataFimItem,
                    $horaFimItem,
                    $request->data_inicio,
                    $request->hora_inicio,
                    $request->data_fim,
                    $request->hora_fim,
                    'Produto'
                );
                
                $periodoItem = $this->calcularQuantidadePeriodoCobranca(
                    $dataInicioItem,
                    $horaInicioItem,
                    $dataFimItem,
                    $horaFimItem,
                    $locacaoPorHora,
                    max(1, (int) $quantidadePeriodoContrato)
                );
                
                // Calcular valor do produto baseado no tipo de cobrança
                $fatorFinanceiroItem = $this->obterFatorFinanceiroItem($locacaoPorHora, $valorFechado, $periodoItem);
                $valorProduto = $valorUnitario * $quantidade * $fatorFinanceiroItem;
                $valorTotal += $valorProduto;

                // Dados comuns do produto
                $dadosProduto = [
                    'id_empresa' => $idEmpresa,
                    'id_locacao' => $locacao->id_locacao,
                    'id_produto' => $idProdutoItem,
                    'id_sala' => $idSala,
                    'id_tabela_preco' => $idTabelaPreco,
                    'preco_unitario' => $valorUnitario,
                    'data_inicio' => $dataInicioItem,
                    'hora_inicio' => $horaInicioItem,
                    'data_fim' => $dataFimItem,
                    'hora_fim' => $horaFimItem,
                    'data_contrato' => $request->data_inicio,
                    'data_contrato_fim' => $request->data_fim,
                    'hora_contrato' => $request->hora_inicio,
                    'hora_contrato_fim' => $request->hora_fim,
                    'tipo_cobranca' => $valorFechado ? 'fechado' : 'diaria',
                    'tipo_movimentacao' => 'entrega',
                    'observacoes' => $observacoesItem,
                    'valor_fechado' => $valorFechado ? 1 : 0,
                    'status_retorno' => 'pendente',
                    'estoque_status' => 0,
                ];

                // Se tem patrimônios vinculados, criar um registro para cada
                if (!empty($item['patrimonios']) && is_array($item['patrimonios'])) {
                    foreach ($item['patrimonios'] as $idPatrimonio) {
                        $valorUnitarioPatrimonio = $valorUnitario * $this->obterFatorFinanceiroItem($locacaoPorHora, $valorFechado, $periodoItem);
                        
                        $produtoLocacao = LocacaoProduto::create(array_merge($dadosProduto, [
                            'id_patrimonio' => $idPatrimonio,
                            'quantidade' => 1,
                            'preco_total' => $valorUnitarioPatrimonio,
                        ]));

                        // Apenas registrar no histórico que foi vinculado à locação
                        $patrimonio = Patrimonio::where('id_patrimonio', $idPatrimonio)
                            ->where('id_empresa', $idEmpresa)
                            ->first();
                            
                        if ($patrimonio) {
                            PatrimonioHistorico::registrar([
                                'id_empresa' => $idEmpresa,
                                'id_patrimonio' => $idPatrimonio,
                                'id_produto' => $item['id_produto'],
                                'id_locacao' => $locacao->id_locacao,
                                'id_cliente' => $locacao->id_cliente,
                                'tipo_movimentacao' => 'saida_locacao',
                                'status_anterior' => $patrimonio->status_locacao ?? 'Disponivel',
                                'status_novo' => $patrimonio->status_locacao ?? 'Disponivel',
                                'local_destino' => $locacao->local_entrega,
                                'observacoes' => 'Vinculado à Locação #' . $locacao->numero_contrato . ' - Período: ' . $dataInicioItem . ' a ' . $dataFimItem,
                                'id_usuario' => $idUsuario,
                            ]);
                        }
                    }
                } else {
                    // Produto sem patrimônio - registro único com a quantidade
                    LocacaoProduto::create(array_merge($dadosProduto, [
                        'id_patrimonio' => null,
                        'quantidade' => $quantidade,
                        'preco_total' => $valorProduto,
                    ]));
                    
                    // Registrar no histórico do produto
                    if ($produtoDaEmpresa) {
                        ProdutoHistorico::registrar([
                            'id_empresa' => $idEmpresa,
                            'id_produto' => $idProdutoItem,
                            'id_locacao' => $locacao->id_locacao,
                            'id_cliente' => $locacao->id_cliente,
                            'tipo_movimentacao' => 'saida',
                            'quantidade' => $quantidade,
                            'estoque_anterior' => $produtoDaEmpresa->quantidade ?? 0,
                            'estoque_novo' => max(0, ($produtoDaEmpresa->quantidade ?? 0) - $quantidade),
                            'motivo' => 'Locação #' . $locacao->numero_contrato,
                            'id_usuario' => $idUsuario,
                        ]);
                    }
                }
            }

            // Adicionar produtos de terceiros se houver
            if ($request->has('produtos_terceiros') && is_array($request->produtos_terceiros)) {
                foreach ($request->produtos_terceiros as $itemTerceiro) {
                    $precoUnitario = $this->parseDecimal($itemTerceiro['preco_unitario'] ?? 0);
                    $custoFornecedor = $this->parseDecimal($itemTerceiro['custo_fornecedor'] ?? 0);
                    $quantidade = intval($itemTerceiro['quantidade'] ?? 1);
                    $valorFechado = !empty($itemTerceiro['valor_fechado']);
                    $diasCobrancaTerceiro = $valorFechado ? 1 : max(1, (int) ($quantidadePeriodoContrato ?? 1));
                    $valorTotalTerceiro = $precoUnitario * $quantidade * $diasCobrancaTerceiro;
                    $valorTotal += $valorTotalTerceiro;

                    // Mapear sala se necessário
                    $idSala = null;
                    $idSalaRaw = $itemTerceiro['id_sala'] ?? null;
                    if ($idSalaRaw !== null && $idSalaRaw !== '') {
                        if (isset($salasMap[(string) $idSalaRaw])) {
                            $idSala = $salasMap[(string) $idSalaRaw];
                        } else {
                            $idSala = $idSalaRaw;
                        }
                    }

                    $produtoTerceiro = ProdutoTerceirosLocacao::create($this->montarDadosProdutoTerceiroLocacao([
                        'id_empresa' => $idEmpresa,
                        'id_locacao' => $locacao->id_locacao,
                        'id_produto_terceiro' => $itemTerceiro['id_produto_terceiro'] ?? null,
                        'nome_produto_manual' => $itemTerceiro['nome_produto_manual'] ?? null,
                        'descricao_manual' => $itemTerceiro['descricao_manual'] ?? null,
                        'id_fornecedor' => $itemTerceiro['id_fornecedor'] ?? null,
                        'id_sala' => $idSala,
                        'quantidade' => $quantidade,
                        'preco_unitario' => $precoUnitario,
                        'valor_fechado' => $valorFechado,
                        'custo_fornecedor' => $custoFornecedor,
                        'valor_total' => $valorTotalTerceiro,
                        'tipo_movimentacao' => $itemTerceiro['tipo_movimentacao'] ?? 'entrega',
                        'observacoes' => $itemTerceiro['observacoes'] ?? null,
                        // Campos de conta a pagar
                        'gerar_conta_pagar' => !empty($itemTerceiro['gerar_conta_pagar']),
                        'conta_vencimento' => $itemTerceiro['conta_vencimento'] ?? null,
                        'conta_valor' => $this->parseDecimal($itemTerceiro['conta_valor'] ?? 0),
                        'conta_parcelas' => intval($itemTerceiro['conta_parcelas'] ?? 1),
                    ]));

                }
            }

            // Adicionar serviços se houver
            if ($request->has('servicos') && is_array($request->servicos)) {
                foreach ($request->servicos as $servico) {
                    $precoUnitario = $this->parseDecimal($servico['valor_unitario'] ?? $servico['valor'] ?? $servico['preco_unitario'] ?? 0);
                    $quantidade = intval($servico['quantidade'] ?? 1);
                    $valorTotalServico = $precoUnitario * $quantidade;
                    $valorTotal += $valorTotalServico;

                    $idSalaServicoRaw = $servico['id_sala'] ?? null;
                    $idSalaServico = null;
                    if ($idSalaServicoRaw !== null && $idSalaServicoRaw !== '') {
                        $idSalaServico = $salasMap[(string) $idSalaServicoRaw] ?? $idSalaServicoRaw;
                    }

                    $tipoItemServico = $servico['tipo_item'] ?? 'proprio';
                    $gerarContaPagarServico = !empty($servico['gerar_conta_pagar']);
                    $contaValorServico = $this->parseDecimal($servico['conta_valor'] ?? 0);
                    $contaParcelasServico = intval($servico['conta_parcelas'] ?? 1);

                    LocacaoServico::create($this->montarDadosLocacaoServico([
                        'id_locacao' => $locacao->id_locacao,
                        'descricao' => $servico['descricao'],
                        'quantidade' => $quantidade,
                        'preco_unitario' => $precoUnitario,
                        'valor_total' => $valorTotalServico,
                        'tipo_item' => $tipoItemServico,
                        'id_sala' => $idSalaServico,
                        'id_fornecedor' => $servico['id_fornecedor'] ?? null,
                        'fornecedor_nome' => $servico['fornecedor_nome'] ?? null,
                        'custo_fornecedor' => $this->parseDecimal($servico['custo_fornecedor'] ?? 0),
                        'gerar_conta_pagar' => $gerarContaPagarServico,
                        'conta_vencimento' => $servico['conta_vencimento'] ?? null,
                        'conta_valor' => $contaValorServico,
                        'conta_parcelas' => $contaParcelasServico,
                        'observacoes' => $this->anexarMetaObservacao($servico['observacoes'] ?? null, [
                            'tipo_item' => $tipoItemServico,
                            'id_sala' => $idSalaServico,
                            'id_fornecedor' => $servico['id_fornecedor'] ?? null,
                            'custo_fornecedor' => $this->parseDecimal($servico['custo_fornecedor'] ?? 0),
                            'gerar_conta_pagar' => $gerarContaPagarServico,
                            'conta_vencimento' => $servico['conta_vencimento'] ?? null,
                            'conta_valor' => $contaValorServico,
                            'conta_parcelas' => $contaParcelasServico,
                        ]),
                    ]));

                }
            }

            // Adicionar despesas se houver
            $despesasPayload = $request->input('despesas', []);
            foreach ((is_array($despesasPayload) ? $despesasPayload : []) as $despesa) {
                    $valorDespesa = $this->parseDecimal($despesa['valor'] ?? 0);

                    $despesaLocacao = LocacaoDespesa::create($this->montarDadosLocacaoDespesa([
                        'id_locacao' => $locacao->id_locacao,
                        'descricao' => $despesa['descricao'] ?? 'Despesa da locação',
                        'tipo' => $despesa['tipo'] ?? 'outros',
                        'valor' => $valorDespesa,
                        'data_despesa' => $despesa['data_despesa'] ?? null,
                        'conta_vencimento' => $despesa['conta_vencimento'] ?? null,
                        'conta_parcelas' => intval($despesa['conta_parcelas'] ?? 1),
                        'status' => 'pendente',
                        'observacoes' => $this->anexarMetaObservacao($despesa['observacoes'] ?? null, [
                            'conta_vencimento' => $despesa['conta_vencimento'] ?? null,
                            'conta_parcelas' => intval($despesa['conta_parcelas'] ?? 1),
                        ]),
                    ]));

            }

            // Atualizar valores totais (regra centralizada)
            $locacao->refresh();
            $this->sincronizarTotaisLocacao($locacao);
            $this->sincronizarContasPagarLocacaoPorStatus($locacao, (int) $idEmpresa);

            // ========== VERIFICAR SE DEVE PROCESSAR SAÍDA IMEDIATAMENTE ==========
            // Regras:
            // - Orçamento: nunca baixa estoque na criação
            // - Aprovado: baixa agora apenas se data/hora de início já passou
            // - Aprovado com início futuro: cron fará a baixa na data/hora
            if ($locacao->status === 'aprovado') {
                $itensProcessados = $this->processarSaidasProdutosElegiveis($locacao, $idUsuario);

                if ($itensProcessados > 0) {
                    Log::info('Baixa de estoque imediata na criação da locação aprovada (por item)', [
                        'id_locacao' => $locacao->id_locacao,
                        'numero_contrato' => $locacao->numero_contrato,
                        'itens_processados' => $itensProcessados,
                    ]);
                }
            }

            DB::commit();

            Log::info('=== LOCAÇÃO CRIADA ===', [
                'id_locacao' => $locacao->id_locacao,
                'numero_contrato' => $locacao->numero_contrato,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Locação criada com sucesso.',
                    'id_locacao' => $locacao->id_locacao,
                    'numero_contrato' => $locacao->numero_contrato
                ]);
            }

            if ($novoStatus === 'orcamento') {
                return redirect()->route('locacoes.orcamentos')->with('success', 'Locação criada com sucesso.');
            }

            $abaRetorno = $this->normalizarAbaContratos((string) $request->input('aba', $request->query('aba', 'ativos')));
            return redirect()->route('locacoes.contratos', ['aba' => $abaRetorno])->with('success', 'Locação criada com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== ERRO AO CRIAR LOCAÇÃO ===', ['erro' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            $mensagem = $e->getMessage() ?: 'Erro ao atualizar a locação.';
            return redirect()->back()->with('error', $mensagem)->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $locacao = Locacao::where('id_locacao', $id)
            ->where('id_empresa', $idEmpresa)
            ->with([
                'cliente',
                'usuario',
                'produtos.produto',
                'produtos.patrimonio',
                'produtos.sala',
                'produtos.fornecedor',
                'produtosTerceiros.fornecedor',
                'servicos',
                'despesas',
                'assinaturaDigital',
                'assinaturasDigitais',
            ])
            ->first();

        if (!$locacao) {
            abort(404);
        }

        $locacaoPorHora = $this->ehLocacaoPorHoraLocacao($locacao);

        $dataInicioLocacao = optional($locacao->data_inicio)->format('Y-m-d');
        $dataFimLocacao = optional($locacao->data_fim)->format('Y-m-d');

        $quantidadePeriodoLocacao = $this->calcularQuantidadePeriodoCobranca(
            $dataInicioLocacao,
            $locacao->hora_inicio,
            $dataFimLocacao,
            $locacao->hora_fim,
            $locacaoPorHora,
            max(1, (int) ($locacao->quantidade_dias ?? 1))
        );

        $locacao->periodo_qtd_exibicao = max(1, (int) ($locacao->quantidade_dias ?: $quantidadePeriodoLocacao));
        $locacao->periodo_unidade_exibicao = $locacaoPorHora ? 'hora(s)' : 'dia(s)';
        $locacao->locacao_por_hora_exibicao = $locacaoPorHora;

        foreach (($locacao->produtos ?? collect()) as $produtoLocacao) {
            $dataInicioItem = optional($produtoLocacao->data_inicio)->format('Y-m-d') ?: $dataInicioLocacao;
            $dataFimItem = optional($produtoLocacao->data_fim)->format('Y-m-d') ?: $dataFimLocacao;
            $horaInicioItem = $produtoLocacao->hora_inicio ?: $locacao->hora_inicio;
            $horaFimItem = $produtoLocacao->hora_fim ?: $locacao->hora_fim;

            $periodoItem = $this->calcularQuantidadePeriodoCobranca(
                $dataInicioItem,
                $horaInicioItem,
                $dataFimItem,
                $horaFimItem,
                $locacaoPorHora,
                max(1, (int) $locacao->periodo_qtd_exibicao)
            );

            $produtoLocacao->periodo_qtd_exibicao = $this->obterFatorFinanceiroItem(
                $locacaoPorHora,
                (bool) ($produtoLocacao->valor_fechado ?? false),
                $periodoItem
            );
        }

        foreach (($locacao->produtosTerceiros ?? collect()) as $produtoTerceiroLocacao) {
            $produtoTerceiroLocacao->periodo_qtd_exibicao = !empty($produtoTerceiroLocacao->valor_fechado)
                ? 1
                : max(1, (int) $locacao->periodo_qtd_exibicao);
        }

        foreach (($locacao->servicos ?? collect()) as $servicoLocacao) {
            $servicoLocacao->periodo_qtd_exibicao = max(1, (int) $locacao->periodo_qtd_exibicao);
        }

        $modelosContratoAtivos = $this->consultarModelosDocumento((int) $idEmpresa, 'contrato')
            ->orderBy('padrao', 'desc')
            ->orderBy('nome')
            ->get(['id_modelo', 'nome', 'padrao']);

        return view('locacoes.show', compact('locacao', 'modelosContratoAtivos'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $permiteNumeroManualLocacao = $this->empresaPermiteNumeroManualLocacao((int) $idEmpresa);
        
        $locacao = Locacao::where('id_locacao', $id)
            ->where('id_empresa', $idEmpresa)
            ->with(['cliente', 'produtos.produto', 'produtos.patrimonio', 'servicos', 'despesas', 'produtosTerceiros.fornecedor', 'produtosTerceiros.produtoTerceiro', 'salas'])
            ->first();

        if (!$locacao) {
            abort(404);
        }

        // Não permite editar locações finalizadas ou canceladas
        if (in_array($locacao->status, ['finalizada', 'cancelada', 'cancelado'])) {
            return redirect()->route('locacoes.show', $id)->with('error', 'Não é possível editar uma locação finalizada ou cancelada.');
        }

        $locacao->servicos->transform(function ($servico) {
            $meta = $this->extrairMetaObservacao($servico->observacoes);

            if ((empty($servico->tipo_item) || $servico->tipo_item === null) && isset($meta['tipo_item'])) {
                $servico->tipo_item = $meta['tipo_item'];
            }
            if (empty($servico->id_fornecedor) && isset($meta['id_fornecedor'])) {
                $servico->id_fornecedor = $meta['id_fornecedor'];
            }
            if ((float) ($servico->custo_fornecedor ?? 0) <= 0 && isset($meta['custo_fornecedor'])) {
                $servico->custo_fornecedor = $meta['custo_fornecedor'];
            }
            if ((int) ($servico->gerar_conta_pagar ?? 0) === 0 && isset($meta['gerar_conta_pagar'])) {
                $servico->gerar_conta_pagar = $meta['gerar_conta_pagar'] ? 1 : 0;
            }
            if (empty($servico->conta_vencimento) && isset($meta['conta_vencimento'])) {
                $servico->conta_vencimento = $meta['conta_vencimento'];
            }
            if ((float) ($servico->conta_valor ?? 0) <= 0 && isset($meta['conta_valor'])) {
                $servico->conta_valor = $meta['conta_valor'];
            }
            if ((int) ($servico->conta_parcelas ?? 1) <= 1 && isset($meta['conta_parcelas'])) {
                $servico->conta_parcelas = $meta['conta_parcelas'];
            }
            if (empty($servico->id_sala) && isset($meta['id_sala'])) {
                $servico->id_sala = $meta['id_sala'];
            }

            $servico->observacoes = $this->removerMetaObservacao($servico->observacoes);
            return $servico;
        });

        $locacao->despesas->transform(function ($despesa) {
            $meta = $this->extrairMetaObservacao($despesa->observacoes);

            if (empty($despesa->conta_vencimento) && isset($meta['conta_vencimento'])) {
                $despesa->conta_vencimento = $meta['conta_vencimento'];
            }
            if ((int) ($despesa->conta_parcelas ?? 1) <= 1 && isset($meta['conta_parcelas'])) {
                $despesa->conta_parcelas = $meta['conta_parcelas'];
            }

            $despesa->observacoes = $this->removerMetaObservacao($despesa->observacoes);
            return $despesa;
        });

        $clientes = Cliente::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        $usuarios = \App\Models\User::where('id_empresa', $idEmpresa)->where('status', 'ativo')->orderBy('nome')->get();
        $isMedicao = (string) ($locacao->status ?? '') === 'medicao';

        return view('locacoes.edit', compact('locacao', 'clientes', 'produtos', 'usuarios', 'permiteNumeroManualLocacao', 'isMedicao'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? Auth::id();

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            if (in_array($locacao->status, ['encerrado', 'cancelado'])) {
                throw new \Exception('Não é possível editar uma locação encerrada ou cancelada.');
            }

            $statusAnterior = $locacao->status;
            $novoStatus = $request->input('status', $locacao->status);
            $permiteNumeroManualLocacao = $this->empresaPermiteNumeroManualLocacao((int) $idEmpresa);
            $numeroManual = $permiteNumeroManualLocacao
                ? $this->normalizarNumeroManual($request->input('numero_manual'))
                : null;
            $sincronizarProdutos = $request->boolean('sync_produtos', false);
            $sincronizarProdutosTerceiros = $request->boolean('sync_produtos_terceiros', false);
            $sincronizarServicos = $request->boolean('sync_servicos', false);
            $sincronizarSalas = $request->boolean('sync_salas', false);
            $locacaoPorHora = $request->boolean('locacao_por_hora', $this->ehLocacaoPorHoraLocacao($locacao));
            $produtosPayloadRequest = $request->input('produtos', []);
            $produtosPayload = is_array($produtosPayloadRequest) ? $produtosPayloadRequest : [];

            $dataInicioValidacao = $request->input('data_inicio', optional($locacao->data_inicio)->format('Y-m-d'));
            $dataFimValidacao = $request->input('data_fim', optional($locacao->data_fim)->format('Y-m-d'));
            $horaInicioValidacao = $request->input('hora_inicio', $locacao->hora_inicio);
            $horaFimValidacao = $request->input('hora_fim', $locacao->hora_fim);

            $this->validarPeriodoDataHora(
                $dataInicioValidacao,
                $horaInicioValidacao,
                $dataFimValidacao,
                $horaFimValidacao,
                'Contrato'
            );

            // Calcular quantidade do período do contrato (dias ou horas)
            $dataInicio = $request->data_inicio ?? $locacao->data_inicio;
            $dataFim = $request->data_fim ?? $locacao->data_fim;
            $horaInicioContrato = $request->input('hora_inicio', $locacao->hora_inicio);
            $horaFimContrato = $request->input('hora_fim', $locacao->hora_fim);
            $quantidadeDias = $this->calcularQuantidadePeriodoCobranca(
                $dataInicio,
                $horaInicioContrato,
                $dataFim,
                $horaFimContrato,
                $locacaoPorHora,
                1
            );


            $desconto = $this->parseDecimal($request->input('desconto', $request->input('desconto_geral', $locacao->valor_desconto ?? 0)));
            $freteEntregaAtual = (float) ($locacao->valor_frete_entrega ?? $locacao->valor_acrescimo ?? 0);
            $freteRetiradaAtual = (float) ($locacao->valor_frete_retirada ?? 0);
            $freteEntrega = max(0, $this->parseDecimal($request->input(
                'frete_entrega',
                $request->input('taxa_entrega', $freteEntregaAtual)
            )));
            $freteRetirada = max(0, $this->parseDecimal($request->input('frete_retirada', $freteRetiradaAtual)));
            $freteTotal = $freteEntrega + $freteRetirada;

            $dadosUpdate = [
                'id_cliente' => $request->input('id_cliente', $locacao->id_cliente),
                'id_usuario' => $request->input('id_usuario') ?: ($locacao->id_usuario ?: $idUsuario),
                'status' => $novoStatus,
                'data_inicio' => $dataInicio,
                'hora_inicio' => $horaInicioContrato,
                'data_fim' => $dataFim,
                'hora_fim' => $horaFimContrato,
                'quantidade_dias' => $quantidadeDias,
                'data_transporte_ida' => $request->input('data_transporte_ida') ?: null,
                'hora_transporte_ida' => $request->input('hora_transporte_ida') ?: null,
                'data_transporte_volta' => $request->input('data_transporte_volta') ?: null,
                'hora_transporte_volta' => $request->input('hora_transporte_volta') ?: null,
                'local_entrega' => $request->input('local_entrega', $locacao->local_entrega),
                'local_retirada' => $request->input('local_retirada', $locacao->local_retirada),
                'contato_responsavel' => $request->input('contato_responsavel', $locacao->contato_responsavel),
                'telefone_responsavel' => $request->input('telefone_responsavel', $locacao->telefone_responsavel),
                'observacoes' => $request->input('observacoes', $locacao->observacoes),
                'valor_desconto' => $desconto,
                'valor_acrescimo' => $freteTotal,
            ];

            if ($this->hasColunaLocacao('valor_frete_entrega')) {
                $dadosUpdate['valor_frete_entrega'] = $freteEntrega;
            }

            if ($this->hasColunaLocacao('valor_frete_retirada')) {
                $dadosUpdate['valor_frete_retirada'] = $freteRetirada;
            }

            if ($this->hasColunaLocacao('observacoes_orcamento')) {
                $dadosUpdate['observacoes_orcamento'] = $request->input('observacoes_orcamento', $locacao->observacoes_orcamento);
            }

            if ($this->hasColunaLocacao('observacoes_recibo')) {
                $dadosUpdate['observacoes_recibo'] = $request->input('observacoes_recibo', $locacao->observacoes_recibo);
            }

            if ($this->hasColunaLocacao('observacoes_entrega')) {
                $dadosUpdate['observacoes_entrega'] = $request->input('observacoes_entrega', $locacao->observacoes_entrega);
            }

            if ($this->hasColunaLocacao('observacoes_checklist')) {
                $dadosUpdate['observacoes_checklist'] = $request->input('observacoes_checklist', $locacao->observacoes_checklist);
            }

            if ($this->hasColunaLocacao('valor_limite_medicao')) {
                $dadosUpdate['valor_limite_medicao'] = $this->parseDecimal(
                    $request->input('valor_limite_medicao', $locacao->valor_limite_medicao ?? 0)
                );
            }

            if ($this->hasColunaLocacao('locacao_por_hora')) {
                $dadosUpdate['locacao_por_hora'] = $locacaoPorHora;
            }

            if ($request->boolean('usar_endereco_cliente')) {
                $clienteEntrega = Cliente::query()
                    ->where('id_clientes', $dadosUpdate['id_cliente'])
                    ->where('id_empresa', $idEmpresa)
                    ->first();

                $dadosUpdate['local_entrega'] = $this->montarEnderecoEntregaCliente($clienteEntrega)
                    ?: $dadosUpdate['local_entrega'];
            }

            if ($this->hasColunaLocacao('renovacao_automatica')) {
                $dadosUpdate['renovacao_automatica'] = $request->boolean('renovacao_automatica');
            }

            if ($statusAnterior === 'orcamento' && $novoStatus === 'aprovado') {
                $numeroOrcamentoOrigem = $this->obterNumeroOrcamentoOrigemLocacao($locacao);
                $numeroContrato = Locacao::gerarProximoNumeroContrato((int) $idEmpresa, false);

                $dadosUpdate['numero_contrato'] = str_pad((string) $numeroContrato, 3, '0', STR_PAD_LEFT);

                if ($this->hasColunaLocacao('numero_orcamento') && empty($locacao->numero_orcamento)) {
                    $dadosUpdate['numero_orcamento'] = $numeroOrcamentoOrigem;
                }

                if ($this->hasColunaLocacao('numero_orcamento_origem')) {
                    $dadosUpdate['numero_orcamento_origem'] = $numeroOrcamentoOrigem;
                }
            }

            if ($numeroManual !== null) {
                if ($novoStatus === 'aprovado') {
                    $dadosUpdate['numero_contrato'] = str_pad((string) $numeroManual, 3, '0', STR_PAD_LEFT);
                } else {
                    $dadosUpdate['numero_contrato'] = $this->formatarNumeroInternoOrcamento($numeroManual);

                    if ($this->hasColunaLocacao('numero_orcamento')) {
                        $dadosUpdate['numero_orcamento'] = $numeroManual;
                    }

                    if ($this->hasColunaLocacao('numero_orcamento_origem')) {
                        $dadosUpdate['numero_orcamento_origem'] = $numeroManual;
                    }
                }

                $this->validarNumeroLocacaoDisponivel((int) $numeroManual, (int) $idEmpresa, (int) $locacao->id_locacao);

                if ($novoStatus === 'orcamento' && $this->hasColunaLocacao('numero_orcamento')) {
                    $this->validarNumeroOrcamentoDisponivel((int) $numeroManual, (int) $idEmpresa, (int) $locacao->id_locacao);
                }
            }

            $salasMap = [];
            if ($sincronizarSalas || ($request->has('salas') && is_array($request->salas))) {
                LocacaoSala::where('id_locacao', $id)->forceDelete();

                $salasPayload = $request->input('salas', []);
                if (is_array($salasPayload)) {
                    foreach ($salasPayload as $ordem => $salaData) {
                        $nomeSala = trim((string) ($salaData['nome'] ?? ''));
                        if ($nomeSala === '') {
                            continue;
                        }

                        $sala = LocacaoSala::create([
                            'id_empresa' => $idEmpresa,
                            'id_locacao' => $locacao->id_locacao,
                            'nome' => $nomeSala,
                            'ordem' => (int) $ordem,
                        ]);

                        $tempId = $salaData['temp_id'] ?? $ordem;
                        $salasMap[(string) $tempId] = $sala->id_sala;
                    }
                }
            }

            LocacaoProduto::where('id_locacao', $id)->update([
                'data_contrato' => $dataInicio,
                'data_contrato_fim' => $dataFim,
                'hora_contrato' => $horaInicioContrato,
                'hora_contrato_fim' => $horaFimContrato,
            ]);

            if ($statusAnterior === 'aprovado' && $novoStatus === 'orcamento') {
                $numeroOrcamentoDestino = Locacao::gerarProximoNumeroOrcamento((int) $idEmpresa, false);

                $dadosUpdate['numero_contrato'] = $this->formatarNumeroInternoOrcamento($numeroOrcamentoDestino);

                if ($this->hasColunaLocacao('numero_orcamento')) {
                    $dadosUpdate['numero_orcamento'] = $numeroOrcamentoDestino;
                }

                if ($this->hasColunaLocacao('numero_orcamento_origem')) {
                    $dadosUpdate['numero_orcamento_origem'] = $numeroOrcamentoDestino;
                }

                foreach (LocacaoProduto::where('id_locacao', $id)->with(['produto', 'patrimonio', 'locacao'])->get() as $produtoLocacao) {
                    if ((int) ($produtoLocacao->estoque_status ?? 0) !== 1) {
                        continue;
                    }

                    $this->estoqueService->registrarRetornoLocacao(
                        $produtoLocacao,
                        'devolvido',
                        'Retorno automático ao alterar contrato aprovado para orçamento',
                        $idUsuario
                    );

                    $produtoLocacao->estoque_status = 0;
                    $produtoLocacao->status_retorno = 'pendente';
                    $produtoLocacao->save();
                }
            }

            $locacao->update($dadosUpdate);
            $locacao->refresh();

            $recalcularTotais = false;
            $valorTotal = 0;

            // Atualizar produtos se enviados
            if ($sincronizarProdutos || ($request->has('produtos') && is_array($request->produtos))) {
                $recalcularTotais = true;
                $produtosAtualizacao = $sincronizarProdutos ? $produtosPayload : $request->produtos;
                // Liberar patrimônios antigos - apenas do histórico, não mudar status
                $produtosAntigos = LocacaoProduto::where('id_locacao', $id)
                    ->with(['produto', 'patrimonio', 'locacao'])
                    ->get();
                $produtosAntigosPorId = $produtosAntigos->keyBy('id_produto_locacao');
                $produtosAntigosPorChave = [];
                $idsProdutosMantidos = collect($produtosAtualizacao)
                    ->flatMap(function ($itemProduto) {
                        $ids = [];

                        $idPrincipal = (int) ($itemProduto['id_locacao_produto'] ?? $itemProduto['id_produto_locacao'] ?? 0);
                        if ($idPrincipal > 0) {
                            $ids[] = $idPrincipal;
                        }

                        if (!empty($itemProduto['ids_locacao_produtos']) && is_array($itemProduto['ids_locacao_produtos'])) {
                            foreach ($itemProduto['ids_locacao_produtos'] as $idExtra) {
                                $idExtra = (int) $idExtra;
                                if ($idExtra > 0) {
                                    $ids[] = $idExtra;
                                }
                            }
                        }

                        return $ids;
                    })
                    ->filter(function ($idProdutoLocacao) {
                        return $idProdutoLocacao > 0;
                    })
                    ->unique()
                    ->values()
                    ->all();

                $quantidadeNovaPorId = [];
                foreach ($produtosAtualizacao as $itemProduto) {
                    $idAntigo = (int) ($itemProduto['id_locacao_produto'] ?? $itemProduto['id_produto_locacao'] ?? 0);
                    if ($idAntigo <= 0) {
                        continue;
                    }

                    $temPatrimoniosVinculados = !empty($itemProduto['patrimonios']) && is_array($itemProduto['patrimonios']);
                    if ($temPatrimoniosVinculados) {
                        continue;
                    }

                    $quantidadeNovaPorId[$idAntigo] = (int) ($itemProduto['quantidade'] ?? 0);
                }

                foreach ($produtosAntigos as $produtoAntigo) {
                    $chave = $this->gerarChaveProdutoLocacao(
                        (int) $produtoAntigo->id_produto,
                        $produtoAntigo->id_patrimonio ? (int) $produtoAntigo->id_patrimonio : null,
                        $produtoAntigo->data_inicio,
                        $produtoAntigo->hora_inicio,
                        $produtoAntigo->data_fim,
                        $produtoAntigo->hora_fim
                    );

                    $produtosAntigosPorChave[$chave][] = [
                        'estoque_status' => (int) ($produtoAntigo->estoque_status ?? 0),
                        'status_retorno' => $produtoAntigo->status_retorno ?? 'pendente',
                    ];
                }

                $obterSnapshotEstoque = function (array $itemProduto, ?int $idPatrimonio = null) use ($produtosAntigosPorId, &$produtosAntigosPorChave) {
                    $chave = $this->gerarChaveProdutoLocacao(
                        (int) ($itemProduto['id_produto'] ?? 0),
                        $idPatrimonio,
                        $itemProduto['data_inicio'] ?? null,
                        $itemProduto['hora_inicio'] ?? null,
                        $itemProduto['data_fim'] ?? null,
                        $itemProduto['hora_fim'] ?? null
                    );

                    if (!empty($produtosAntigosPorChave[$chave])) {
                        return array_shift($produtosAntigosPorChave[$chave]);
                    }

                    $idAntigo = (int) ($itemProduto['id_locacao_produto'] ?? $itemProduto['id_produto_locacao'] ?? 0);
                    if ($idAntigo > 0 && isset($produtosAntigosPorId[$idAntigo])) {
                        $produtoAntigo = $produtosAntigosPorId[$idAntigo];
                        return [
                            'estoque_status' => (int) ($produtoAntigo->estoque_status ?? 0),
                            'status_retorno' => $produtoAntigo->status_retorno ?? 'pendente',
                        ];
                    }

                    if (!empty($itemProduto['ids_locacao_produtos']) && is_array($itemProduto['ids_locacao_produtos'])) {
                        foreach ($itemProduto['ids_locacao_produtos'] as $idAntigoExtra) {
                            $idAntigoExtra = (int) $idAntigoExtra;
                            if ($idAntigoExtra > 0 && isset($produtosAntigosPorId[$idAntigoExtra])) {
                                $produtoAntigo = $produtosAntigosPorId[$idAntigoExtra];
                                return [
                                    'estoque_status' => (int) ($produtoAntigo->estoque_status ?? 0),
                                    'status_retorno' => $produtoAntigo->status_retorno ?? 'pendente',
                                ];
                            }
                        }
                    }

                    return [
                        'estoque_status' => 0,
                        'status_retorno' => 'pendente',
                    ];
                };

                foreach ($produtosAntigos as $produtoAntigo) {
                    $foiRemovidoDaLocacao = !in_array((int) $produtoAntigo->id_produto_locacao, $idsProdutosMantidos, true);

                    if (
                        !$foiRemovidoDaLocacao
                        && !$produtoAntigo->id_patrimonio
                        && (int) ($produtoAntigo->estoque_status ?? 0) === 1
                        && $this->itemPendenteRetorno($produtoAntigo)
                    ) {
                        $idAntigo = (int) $produtoAntigo->id_produto_locacao;
                        $quantidadeNova = (int) ($quantidadeNovaPorId[$idAntigo] ?? $produtoAntigo->quantidade ?? 0);
                        $quantidadeAntiga = (int) ($produtoAntigo->quantidade ?? 0);

                        if ($quantidadeNova > 0 && $quantidadeNova < $quantidadeAntiga) {
                            $this->registrarRetornoParcialQuantidadeProduto(
                                $produtoAntigo,
                                $quantidadeAntiga - $quantidadeNova,
                                $idUsuario
                            );
                        }
                    }

                    if (
                        $foiRemovidoDaLocacao
                        && (int) ($produtoAntigo->estoque_status ?? 0) === 1
                        && $this->itemPendenteRetorno($produtoAntigo)
                    ) {
                        $this->estoqueService->registrarRetornoLocacao(
                            $produtoAntigo,
                            'devolvido',
                            'Retorno automático ao remover item da locação (edição)',
                            $idUsuario
                        );
                    }

                    if ($produtoAntigo->id_patrimonio && $foiRemovidoDaLocacao) {
                        // Registrar desvinculação no histórico
                        PatrimonioHistorico::registrar([
                            'id_empresa' => $idEmpresa,
                            'id_patrimonio' => $produtoAntigo->id_patrimonio,
                            'id_produto' => $produtoAntigo->id_produto,
                            'id_locacao' => $id,
                            'tipo_movimentacao' => 'retorno_locacao',
                            'observacoes' => 'Desvinculado da Locação #' . $locacao->numero_contrato . ' (edição)',
                            'id_usuario' => $idUsuario,
                        ]);
                    }
                }

                // Remover produtos antigos
                LocacaoProduto::where('id_locacao', $id)->forceDelete();

                // Adicionar produtos novos
                foreach ($produtosAtualizacao as $item) {
                    $valorUnitario = isset($item['valor_unitario']) ? $this->parseDecimal($item['valor_unitario']) : 0;
                    $quantidade = intval($item['quantidade']);
                    $valorFechado = isset($item['valor_fechado']) && $item['valor_fechado'] == '1';
                    $dataInicioItem = $item['data_inicio'] ?? $dataInicio;
                    $dataFimItem = $item['data_fim'] ?? $dataFim;
                    $horaInicioItem = $item['hora_inicio'] ?? $request->hora_inicio ?? $locacao->hora_inicio ?? '08:00';
                    $horaFimItem = $item['hora_fim'] ?? $request->hora_fim ?? $locacao->hora_fim ?? '18:00';
                    $observacoesItem = $item['observacoes'] ?? null;
                    $idSalaRaw = $item['id_sala'] ?? null;
                    $idSala = $idSalaRaw;
                    if ($idSalaRaw !== null && $idSalaRaw !== '' && isset($salasMap[(string) $idSalaRaw])) {
                        $idSala = $salasMap[(string) $idSalaRaw];
                    }
                    $idTabelaPreco = $item['id_tabela_preco'] ?? null;

                    $this->validarPeriodoDataHora($dataInicioItem, $horaInicioItem, $dataFimItem, $horaFimItem, 'Período do produto');
                    $this->validarPeriodoProdutoDentroContrato(
                        $dataInicioItem,
                        $horaInicioItem,
                        $dataFimItem,
                        $horaFimItem,
                        (string) $dataInicio,
                        (string) ($horaInicioContrato ?? '00:00'),
                        (string) $dataFim,
                        (string) ($horaFimContrato ?? '23:59'),
                        'Produto'
                    );

                    $periodoItem = $this->calcularQuantidadePeriodoCobranca(
                        $dataInicioItem,
                        $horaInicioItem,
                        $dataFimItem,
                        $horaFimItem,
                        $locacaoPorHora,
                        max(1, (int) $quantidadeDias)
                    );

                    $fatorFinanceiroItem = $this->obterFatorFinanceiroItem($locacaoPorHora, $valorFechado, $periodoItem);
                    $valorProduto = $valorUnitario * $quantidade * $fatorFinanceiroItem;
                    $valorTotal += $valorProduto;

                    // Dados comuns do produto
                    $dadosProduto = [
                        'id_empresa' => $idEmpresa,
                        'id_locacao' => $locacao->id_locacao,
                        'id_produto' => $item['id_produto'],
                        'id_sala' => $idSala,
                        'id_tabela_preco' => $idTabelaPreco,
                        'preco_unitario' => $valorUnitario,
                        'data_inicio' => $dataInicioItem,
                        'hora_inicio' => $horaInicioItem,
                        'data_fim' => $dataFimItem,
                        'hora_fim' => $horaFimItem,
                        'data_contrato' => $dataInicio,
                        'data_contrato_fim' => $dataFim,
                        'hora_contrato' => $horaInicioContrato,
                        'hora_contrato_fim' => $horaFimContrato,
                        'tipo_cobranca' => $valorFechado ? 'fechado' : 'diaria',
                        'tipo_movimentacao' => 'entrega',
                        'observacoes' => $observacoesItem,
                        'valor_fechado' => $valorFechado ? 1 : 0,
                        'status_retorno' => 'pendente',
                        'estoque_status' => 0,
                    ];

                    // Se tem patrimônios vinculados
                    if (!empty($item['patrimonios']) && is_array($item['patrimonios'])) {
                        foreach ($item['patrimonios'] as $idPatrimonio) {
                            $valorUnitarioPatrimonio = $valorUnitario * $this->obterFatorFinanceiroItem($locacaoPorHora, $valorFechado, $periodoItem);
                            $snapshotEstoque = $obterSnapshotEstoque($item, (int) $idPatrimonio);
                            
                            LocacaoProduto::create(array_merge($dadosProduto, [
                                'id_patrimonio' => $idPatrimonio,
                                'quantidade' => 1,
                                'preco_total' => $valorUnitarioPatrimonio,
                                'status_retorno' => $snapshotEstoque['status_retorno'] ?? 'pendente',
                                'estoque_status' => (int) ($snapshotEstoque['estoque_status'] ?? 0),
                            ]));

                            // Registrar vinculação no histórico
                            $patrimonio = Patrimonio::where('id_patrimonio', $idPatrimonio)
                                ->where('id_empresa', $idEmpresa)
                                ->first();
                                
                            if ($patrimonio) {
                                PatrimonioHistorico::registrar([
                                    'id_empresa' => $idEmpresa,
                                    'id_patrimonio' => $idPatrimonio,
                                    'id_produto' => $item['id_produto'],
                                    'id_locacao' => $locacao->id_locacao,
                                    'id_cliente' => $locacao->id_cliente,
                                    'tipo_movimentacao' => 'saida_locacao',
                                    'status_anterior' => $patrimonio->status_locacao ?? 'Disponivel',
                                    'status_novo' => $patrimonio->status_locacao ?? 'Disponivel',
                                    'local_destino' => $locacao->local_entrega,
                                    'observacoes' => 'Vinculado à Locação #' . $locacao->numero_contrato . ' (edição) - Período: ' . $dataInicioItem . ' a ' . $dataFimItem,
                                    'id_usuario' => $idUsuario,
                                ]);
                            }
                        }
                    } else {
                        $snapshotEstoque = $obterSnapshotEstoque($item, null);

                        LocacaoProduto::create(array_merge($dadosProduto, [
                            'id_patrimonio' => null,
                            'quantidade' => $quantidade,
                            'preco_total' => $valorProduto,
                            'status_retorno' => $snapshotEstoque['status_retorno'] ?? 'pendente',
                            'estoque_status' => (int) ($snapshotEstoque['estoque_status'] ?? 0),
                        ]));
                    }
                }

            }

            // Atualizar produtos de terceiros se enviados
            if ($sincronizarProdutosTerceiros || ($request->has('produtos_terceiros') && is_array($request->produtos_terceiros))) {
                $recalcularTotais = true;
                // Remover produtos de terceiros antigos
                ProdutoTerceirosLocacao::where('id_locacao', $id)->forceDelete();
                ContasAPagar::where('id_empresa', $idEmpresa)
                    ->where('id_locacao', $locacao->id_locacao)
                    ->where(function ($query) {
                        $query->where('origem', 'locacao_terceiro')
                            ->orWhereNull('origem');
                    })
                    ->delete();
                
                // Adicionar novos
                $produtosTerceirosPayload = $request->input('produtos_terceiros', []);
                foreach ((is_array($produtosTerceirosPayload) ? $produtosTerceirosPayload : []) as $itemTerceiro) {
                    $precoUnitario = $this->parseDecimal($itemTerceiro['preco_unitario'] ?? 0);
                    $custoFornecedor = $this->parseDecimal($itemTerceiro['custo_fornecedor'] ?? 0);
                    $quantidade = intval($itemTerceiro['quantidade'] ?? 1);
                    $valorFechado = !empty($itemTerceiro['valor_fechado']);
                    $diasCobrancaTerceiro = $valorFechado
                        ? 1
                        : max(1, (int) ($quantidadeDias ?? $locacao->quantidade_dias ?? 1));
                    $valorTotalTerceiro = $precoUnitario * $quantidade * $diasCobrancaTerceiro;
                    $valorTotal += $valorTotalTerceiro;

                    $idSalaRaw = $itemTerceiro['id_sala'] ?? null;
                    $idSala = $idSalaRaw;
                    if ($idSalaRaw !== null && $idSalaRaw !== '' && isset($salasMap[(string) $idSalaRaw])) {
                        $idSala = $salasMap[(string) $idSalaRaw];
                    }

                    $produtoTerceiro = ProdutoTerceirosLocacao::create($this->montarDadosProdutoTerceiroLocacao([
                        'id_empresa' => $idEmpresa,
                        'id_locacao' => $locacao->id_locacao,
                        'id_produto_terceiro' => $itemTerceiro['id_produto_terceiro'] ?? null,
                        'nome_produto_manual' => $itemTerceiro['nome_produto_manual'] ?? null,
                        'descricao_manual' => $itemTerceiro['descricao_manual'] ?? null,
                        'id_fornecedor' => $itemTerceiro['id_fornecedor'] ?? null,
                        'id_sala' => $idSala,
                        'quantidade' => $quantidade,
                        'preco_unitario' => $precoUnitario,
                        'valor_fechado' => $valorFechado,
                        'custo_fornecedor' => $custoFornecedor,
                        'valor_total' => $valorTotalTerceiro,
                        'tipo_movimentacao' => $itemTerceiro['tipo_movimentacao'] ?? 'entrega',
                        'observacoes' => $itemTerceiro['observacoes'] ?? null,
                        // Campos de conta a pagar
                        'gerar_conta_pagar' => !empty($itemTerceiro['gerar_conta_pagar']),
                        'conta_vencimento' => $itemTerceiro['conta_vencimento'] ?? null,
                        'conta_valor' => $this->parseDecimal($itemTerceiro['conta_valor'] ?? 0),
                        'conta_parcelas' => intval($itemTerceiro['conta_parcelas'] ?? 1),
                    ]));

                }
            }

            // Atualizar serviços se enviados
            if ($sincronizarServicos || ($request->has('servicos') && is_array($request->servicos))) {
                $recalcularTotais = true;
                LocacaoServico::where('id_locacao', $id)->forceDelete();
                ContasAPagar::where('id_empresa', $idEmpresa)
                    ->where('id_locacao', $locacao->id_locacao)
                    ->where(function ($query) {
                        $query->where('origem', 'servico')
                            ->orWhere('origem', 'locacao_servico_terceiro')
                            ->orWhereNull('origem');
                    })
                    ->delete();

                $servicosPayload = $request->input('servicos', []);
                foreach ((is_array($servicosPayload) ? $servicosPayload : []) as $servico) {
                    $precoUnitario = $this->parseDecimal($servico['valor_unitario'] ?? $servico['valor'] ?? $servico['preco_unitario'] ?? 0);
                    $quantidade = intval($servico['quantidade'] ?? 1);
                    $valorTotalServico = $precoUnitario * $quantidade;
                    $valorTotal += $valorTotalServico;

                    $idSalaRaw = $servico['id_sala'] ?? null;
                    $idSala = $idSalaRaw;
                    if ($idSalaRaw !== null && $idSalaRaw !== '' && isset($salasMap[(string) $idSalaRaw])) {
                        $idSala = $salasMap[(string) $idSalaRaw];
                    }

                    $tipoItemServico = $servico['tipo_item'] ?? 'proprio';
                    $gerarContaPagarServico = !empty($servico['gerar_conta_pagar']);
                    $contaValorServico = $this->parseDecimal($servico['conta_valor'] ?? 0);
                    $contaParcelasServico = intval($servico['conta_parcelas'] ?? 1);

                    LocacaoServico::create($this->montarDadosLocacaoServico([
                        'id_locacao' => $locacao->id_locacao,
                        'descricao' => $servico['descricao'],
                        'quantidade' => $quantidade,
                        'preco_unitario' => $precoUnitario,
                        'valor_total' => $valorTotalServico,
                        'tipo_item' => $tipoItemServico,
                        'id_sala' => $idSala,
                        'id_fornecedor' => $servico['id_fornecedor'] ?? null,
                        'fornecedor_nome' => $servico['fornecedor_nome'] ?? null,
                        'custo_fornecedor' => $this->parseDecimal($servico['custo_fornecedor'] ?? 0),
                        'gerar_conta_pagar' => $gerarContaPagarServico,
                        'conta_vencimento' => $servico['conta_vencimento'] ?? null,
                        'conta_valor' => $contaValorServico,
                        'conta_parcelas' => $contaParcelasServico,
                        'observacoes' => $this->anexarMetaObservacao($servico['observacoes'] ?? null, [
                            'tipo_item' => $tipoItemServico,
                            'id_sala' => $idSala,
                            'id_fornecedor' => $servico['id_fornecedor'] ?? null,
                            'custo_fornecedor' => $this->parseDecimal($servico['custo_fornecedor'] ?? 0),
                            'gerar_conta_pagar' => $gerarContaPagarServico,
                            'conta_vencimento' => $servico['conta_vencimento'] ?? null,
                            'conta_valor' => $contaValorServico,
                            'conta_parcelas' => $contaParcelasServico,
                        ]),
                    ]));

                }
            }

            // Atualizar despesas se enviadas
            if ($request->boolean('sync_despesas', false) || ($request->has('despesas') && is_array($request->despesas))) {
                LocacaoDespesa::where('id_locacao', $id)->forceDelete();
                ContasAPagar::where('id_empresa', $idEmpresa)
                    ->where('id_locacao', $locacao->id_locacao)
                    ->where(function ($query) {
                        $query->where('origem', 'compra')
                            ->orWhere('origem', 'locacao_despesa')
                            ->orWhereNull('origem');
                    })
                    ->delete();

                $despesasPayload = $request->input('despesas', []);
                if (is_array($despesasPayload)) {
                    foreach ($despesasPayload as $despesa) {
                        $valorDespesa = $this->parseDecimal($despesa['valor'] ?? 0);

                        $despesaLocacao = LocacaoDespesa::create($this->montarDadosLocacaoDespesa([
                            'id_locacao' => $locacao->id_locacao,
                            'descricao' => $despesa['descricao'] ?? 'Despesa da locação',
                            'tipo' => $despesa['tipo'] ?? 'outros',
                            'valor' => $valorDespesa,
                            'data_despesa' => $despesa['data_despesa'] ?? null,
                            'conta_vencimento' => $despesa['conta_vencimento'] ?? null,
                            'conta_parcelas' => intval($despesa['conta_parcelas'] ?? 1),
                            'status' => 'pendente',
                            'observacoes' => $this->anexarMetaObservacao($despesa['observacoes'] ?? null, [
                                'conta_vencimento' => $despesa['conta_vencimento'] ?? null,
                                'conta_parcelas' => intval($despesa['conta_parcelas'] ?? 1),
                            ]),
                        ]));

                    }
                }
            }

            $locacao->refresh();
            $this->sincronizarTotaisLocacao($locacao);
            $this->sincronizarContasPagarLocacaoPorStatus($locacao, (int) $idEmpresa);

            $statusComSincronizacaoPorData = in_array($novoStatus, ['aprovado', 'em_andamento', 'atrasada', 'retirada'], true);

            if ($statusComSincronizacaoPorData) {
                $this->reverterSaidasProdutosComInicioFuturo($locacao, $idUsuario);
            }

            if ($novoStatus === 'aprovado') {
                $locacao->refresh();
                $this->validarDisponibilidadeParaAprovacaoLocacao($locacao, $idEmpresa);
            }

            if ($statusComSincronizacaoPorData) {
                $this->processarSaidasProdutosElegiveis($locacao, $idUsuario);
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Locação atualizada com sucesso.'
                ]);
            }

            if ($novoStatus === 'orcamento') {
                return redirect()->route('locacoes.orcamentos')->with('success', 'Locação atualizada com sucesso.');
            }

            $abaRetorno = $this->normalizarAbaContratos((string) $request->input('aba', $request->query('aba', 'ativos')));
            return redirect()->route('locacoes.contratos', ['aba' => $abaRetorno])->with('success', 'Locação atualizada com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== ERRO AO ATUALIZAR LOCAÇÃO ===', [
                'id_locacao' => $id,
                'message' => $e->getMessage(),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with('produtos')
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            // Liberar patrimônios
            foreach ($locacao->produtos as $produto) {
                if ($produto->id_patrimonio) {
                    Patrimonio::where('id_patrimonio', $produto->id_patrimonio)
                        ->where('id_empresa', $idEmpresa)
                        ->update(['status_locacao' => 'Disponivel']);
                }
            }

            $locacao->delete();

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Locação excluída com sucesso.'
                ]);
            }

            return redirect()->route('locacoes.index')->with('success', 'Locação excluída com sucesso.');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function removerItemProduto(Request $request, $id, $idProdutoLocacao)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? Auth::id();

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            if (in_array($locacao->status, ['encerrado', 'cancelado'])) {
                throw new \Exception('Não é possível remover item de uma locação encerrada ou cancelada.');
            }

            $produtoLocacao = LocacaoProduto::where('id_locacao', $id)
                ->where('id_produto_locacao', $idProdutoLocacao)
                ->where('id_empresa', $idEmpresa)
                ->with(['produto', 'patrimonio', 'locacao'])
                ->first();

            if (!$produtoLocacao) {
                throw new \Exception('Item da locação não encontrado.');
            }

            if (
                (int) ($produtoLocacao->estoque_status ?? 0) === 1
                && $this->itemPendenteRetorno($produtoLocacao)
            ) {
                $this->estoqueService->registrarRetornoLocacao(
                    $produtoLocacao,
                    'devolvido',
                    'Retorno automático ao remover item da locação',
                    $idUsuario
                );
            }

            if ($produtoLocacao->id_patrimonio) {
                PatrimonioHistorico::registrar([
                    'id_empresa' => $idEmpresa,
                    'id_patrimonio' => $produtoLocacao->id_patrimonio,
                    'id_produto' => $produtoLocacao->id_produto,
                    'id_locacao' => $id,
                    'tipo_movimentacao' => 'retorno_locacao',
                    'observacoes' => 'Desvinculado da Locação #' . $locacao->numero_contrato . ' (remoção de item)',
                    'id_usuario' => $idUsuario,
                ]);
            }

            $produtoLocacao->forceDelete();

            $totalProdutos = (float) LocacaoProduto::where('id_locacao', $id)->sum('preco_total');
            $totalTerceiros = (float) ProdutoTerceirosLocacao::where('id_locacao', $id)->sum('valor_total');
            $totalServicos = (float) LocacaoServico::where('id_locacao', $id)->sum('valor_total');

            $locacao->valor_total = $totalProdutos + $totalTerceiros + $totalServicos;
            $locacao->valor_final = ($locacao->valor_total ?? 0) - ($locacao->valor_desconto ?? 0) + ($locacao->valor_acrescimo ?? 0);
            $locacao->save();

            if ($novoStatus === 'aprovado') {
                ActionLogger::log($locacao, 'aprovacao');
            }

            if (in_array($novoStatus, ['cancelado', 'cancelada'], true)) {
                ActionLogger::log($locacao, 'cancelamento');
            }

            if ($novoStatus === 'encerrado') {
                ActionLogger::log($locacao, 'encerramento');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item removido com sucesso.',
                'valor_total' => $locacao->valor_total,
                'valor_final' => $locacao->valor_final,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alterar status da locação
     */
    public function alterarStatus(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with('produtos.patrimonio')
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            $novoStatus = $request->status;
            $statusAnterior = $locacao->status;

            // Validar transições de status
            $transicoesPermitidas = [
                'orcamento' => ['aprovado', 'cancelado'],
                'aprovado' => ['orcamento', 'em_andamento', 'encerrado', 'cancelado'],
                'reserva' => ['aprovado', 'em_andamento', 'orcamento', 'cancelado'],
                'em_andamento' => ['encerrado', 'atrasada', 'orcamento', 'cancelado'],
                'atrasada' => ['encerrado', 'orcamento', 'cancelado'],
                'retirada' => ['encerrado', 'orcamento', 'cancelado'],
                'cancelado' => ['aprovado'],
                'cancelada' => ['aprovado'],
            ];

            if (!isset($transicoesPermitidas[$statusAnterior]) || !in_array($novoStatus, $transicoesPermitidas[$statusAnterior])) {
                throw new \Exception("Transição de status não permitida: {$statusAnterior} -> {$novoStatus}");
            }

            if ($novoStatus === 'aprovado') {
                $this->validarDisponibilidadeParaAprovacaoLocacao($locacao, $idEmpresa);
            }

            // Se está finalizando, verificar se tem patrimônios pendentes
            if ($novoStatus === 'finalizada' || $novoStatus === 'encerrado') {
                $temPatrimoniosPendentes = $locacao->temPatrimoniosPendentes();
                
                if ($temPatrimoniosPendentes && !$request->input('confirmar_retorno_patrimonios')) {
                    // Retornar indicando que precisa confirmar retorno dos patrimônios
                    return response()->json([
                        'success' => false,
                        'requires_patrimonio_return' => true,
                        'message' => 'Existem patrimônios pendentes de retorno. Por favor, registre o retorno antes de finalizar.',
                        'patrimonios_pendentes' => $locacao->getPatrimoniosPendentes()->map(function ($item) {
                            return [
                                'id_produto_locacao' => $item->id_produto_locacao,
                                'id_patrimonio' => $item->id_patrimonio,
                                'produto_nome' => $item->produto->nome ?? 'Produto',
                                'patrimonio_codigo' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? 'PAT-' . $item->id_patrimonio,
                            ];
                        }),
                    ], 422);
                }

                // Finalizar - registrar devolução para itens com e sem patrimônio
                foreach ($locacao->produtos as $produto) {
                    if ((int) ($produto->estoque_status ?? 0) !== 1) {
                        continue;
                    }

                    $this->estoqueService->registrarRetornoLocacao(
                        $produto,
                        'devolvido',
                        'Finalização automática da locação',
                        $idUsuario
                    );

                    $produto->estoque_status = 2;
                    $produto->save();
                }
                
                LocacaoProduto::where('id_locacao', $id)
                    ->where('status_retorno', 'pendente')
                    ->update([
                        'status_retorno' => 'devolvido',
                    ]);
            } elseif ($novoStatus === 'orcamento' && in_array($statusAnterior, ['aprovado', 'reserva', 'em_andamento', 'atrasada', 'retirada'], true)) {
                $numeroOrcamentoDestino = Locacao::gerarProximoNumeroOrcamento((int) $idEmpresa, false);
                $locacao->numero_contrato = $this->formatarNumeroInternoOrcamento($numeroOrcamentoDestino);

                if ($this->hasColunaLocacao('numero_orcamento')) {
                    $locacao->numero_orcamento = $numeroOrcamentoDestino;
                }

                if ($this->hasColunaLocacao('numero_orcamento_origem')) {
                    $locacao->numero_orcamento_origem = $numeroOrcamentoDestino;
                }

                foreach ($locacao->produtos as $produto) {
                    if ((int) ($produto->estoque_status ?? 0) !== 1) {
                        continue;
                    }

                    $this->estoqueService->registrarRetornoLocacao(
                        $produto,
                        'devolvido',
                        'Retorno automático ao alterar contrato aprovado para orçamento',
                        $idUsuario
                    );

                    $produto->estoque_status = 0;
                    $produto->status_retorno = 'pendente';
                    $produto->save();
                }
            } elseif ($novoStatus === 'cancelado') {
                foreach ($locacao->produtos as $produto) {
                    if ((int) ($produto->estoque_status ?? 0) !== 1) {
                        continue;
                    }

                    $this->estoqueService->registrarRetornoLocacao(
                        $produto,
                        'devolvido',
                        'Retorno automático ao cancelar contrato',
                        $idUsuario
                    );

                    $produto->estoque_status = 0;
                    $produto->status_retorno = 'pendente';
                    $produto->save();
                }
            }

            $locacao->status = $novoStatus;

            if ($statusAnterior === 'orcamento' && $novoStatus === 'aprovado') {
                $numeroOrcamentoOrigem = $this->obterNumeroOrcamentoOrigemLocacao($locacao);
                $numeroContrato = Locacao::gerarProximoNumeroContrato((int) $idEmpresa, false);

                $locacao->numero_contrato = str_pad((string) $numeroContrato, 3, '0', STR_PAD_LEFT);

                if ($this->hasColunaLocacao('numero_orcamento') && empty($locacao->numero_orcamento)) {
                    $locacao->numero_orcamento = $numeroOrcamentoOrigem;
                }

                if ($this->hasColunaLocacao('numero_orcamento_origem')) {
                    $locacao->numero_orcamento_origem = $numeroOrcamentoOrigem;
                }
            }

            // Ações baseadas no novo status
            if ($novoStatus === 'aprovado') {
                $this->processarSaidasProdutosElegiveis($locacao, $idUsuario);
            } elseif ($novoStatus === 'em_andamento') {
                // Registrar saída no histórico
                foreach ($locacao->produtos as $produto) {
                    if ((int) ($produto->estoque_status ?? 0) !== 0) {
                        continue;
                    }

                    $this->estoqueService->registrarSaidaLocacao($produto, $idUsuario);
                    $produto->estoque_status = 1;
                    $produto->save();
                }
            }

            $locacao->save();
            $this->sincronizarContasPagarLocacaoPorStatus($locacao, (int) $idEmpresa);

            $this->gerarFaturamentoLocacaoEncerrada($locacao, (int) $idEmpresa, $idUsuario, $statusAnterior);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status alterado com sucesso.',
                'novo_status' => $novoStatus
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retornar locação manualmente (fluxo dedicado da listagem)
     */
    public function retornarLocacao(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['produtos.produto', 'produtos.patrimonio', 'cliente'])
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            if (in_array($locacao->status, ['encerrado', 'cancelado'], true)) {
                throw new \Exception('Esta locação já está encerrada/cancelada.');
            }

            $dataHoraFinalizacao = $request->filled('data_hora_finalizacao')
                ? Carbon::parse((string) $request->input('data_hora_finalizacao'))
                : now();
            $locacaoPorHora = $this->ehLocacaoPorHoraLocacao($locacao);

            $recalcularAtraso = $request->boolean('recalcular_atraso');
            $confirmarSemRecalculoAtraso = $request->boolean('confirmar_sem_recalculo_atraso');

            $itensAtrasados = ($locacao->produtos ?? collect())
                ->filter(function ($item) use ($dataHoraFinalizacao) {
                    return $this->itemPendenteRetorno($item)
                        && $this->obterDataHoraFimItemLocacao($item, $item->locacao)->lt($dataHoraFinalizacao);
                })
                ->values();

            if ($itensAtrasados->isNotEmpty() && !$recalcularAtraso && !$confirmarSemRecalculoAtraso) {
                return response()->json([
                    'success' => false,
                    'requires_overdue_recalculation' => true,
                    'message' => 'Os itens abaixo estão sendo devolvidos em atraso, deseja recalcular os valores automaticamente?',
                    'data_hora_atual' => $dataHoraFinalizacao->format('Y-m-d H:i:s'),
                    'itens_atraso' => $itensAtrasados->map(function ($item) {
                        $fimItem = $this->obterDataHoraFimItemLocacao($item, $item->locacao);
                        return [
                            'id_produto_locacao' => $item->id_produto_locacao,
                            'produto_nome' => $item->produto->nome ?? 'Produto',
                            'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                            'data_fim_item' => $fimItem->format('d/m/Y H:i'),
                        ];
                    })->values(),
                ], 409);
            }

            if ($itensAtrasados->isNotEmpty() && $recalcularAtraso) {
                foreach ($itensAtrasados as $itemAtrasado) {
                    $fimOriginal = $this->obterDataHoraFimItemLocacao($itemAtrasado, $locacao);
                    $inicioItem = $this->obterDataHoraInicioItemLocacao($itemAtrasado, $locacao);

                    $periodoOriginal = $this->calcularQuantidadePeriodoCobranca(
                        $inicioItem->toDateString(),
                        $inicioItem->format('H:i:s'),
                        $fimOriginal->toDateString(),
                        $fimOriginal->format('H:i:s'),
                        $locacaoPorHora,
                        1
                    );
                    $periodoRecalculado = $this->calcularQuantidadePeriodoCobranca(
                        $inicioItem->toDateString(),
                        $inicioItem->format('H:i:s'),
                        $dataHoraFinalizacao->toDateString(),
                        $dataHoraFinalizacao->format('H:i:s'),
                        $locacaoPorHora,
                        $periodoOriginal
                    );

                    $itemAtrasado->data_fim = $dataHoraFinalizacao->toDateString();
                    $itemAtrasado->hora_fim = $dataHoraFinalizacao->format('H:i:s');

                    if ((bool) ($itemAtrasado->valor_fechado ?? false)) {
                        $quantidadeItem = max(1, (int) ($itemAtrasado->quantidade ?? 1));
                        $valorOriginalTotal = (float) ($itemAtrasado->preco_unitario ?? 0) * $quantidadeItem;
                        $valorDiaria = $periodoOriginal > 0 ? ($valorOriginalTotal / $periodoOriginal) : $valorOriginalTotal;
                        $novoValorTotal = round(max(0, $valorDiaria * $periodoRecalculado), 2);
                        $itemAtrasado->preco_unitario = round($novoValorTotal / $quantidadeItem, 2);
                    }

                    $itemAtrasado->save();
                }
            }

            $statusAnterior = (string) $locacao->status;

            $retornosInformados = collect($request->input('retornos', []))
                ->filter(fn ($item) => !empty($item['id_produto_locacao']))
                ->keyBy('id_produto_locacao');

            $pendentesComPatrimonio = $locacao->getPatrimoniosPendentes();

            if ($pendentesComPatrimonio->count() > 0 && $retornosInformados->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'requires_patrimonio_return' => true,
                    'message' => 'Existem patrimônios pendentes. Informe o retorno antes de concluir.',
                    'patrimonios_pendentes' => $pendentesComPatrimonio->map(function ($item) {
                        return [
                            'id_produto_locacao' => $item->id_produto_locacao,
                            'id_patrimonio' => $item->id_patrimonio,
                            'produto_nome' => $item->produto->nome ?? 'Produto',
                            'patrimonio_codigo' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? 'PAT-' . $item->id_patrimonio,
                        ];
                    })->values(),
                ], 422);
            }

            $itensDefeitoElegiveis = $this->obterItensDefeitoElegiveisRetorno($locacao);
            $decisoesAvariaInformadas = collect($request->input('decisoes_avaria', []))
                ->filter(fn ($item) => !empty($item['id_produto_locacao']))
                ->keyBy(fn ($item) => (int) $item['id_produto_locacao']);

            if ($itensDefeitoElegiveis->isNotEmpty()) {
                $idsSemDecisao = $itensDefeitoElegiveis
                    ->pluck('id_produto_locacao')
                    ->filter(fn ($idProdutoLocacao) => !$decisoesAvariaInformadas->has((int) $idProdutoLocacao))
                    ->values();

                if ($idsSemDecisao->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'requires_defeito_action' => true,
                        'message' => 'Existem itens marcados com defeito. Informe se deseja retornar ao estoque ou abrir manutenção.',
                        'itens_defeito' => $itensDefeitoElegiveis
                            ->whereIn('id_produto_locacao', $idsSemDecisao->all())
                            ->values(),
                    ], 409);
                }
            }

            foreach ($locacao->produtos as $produtoLocacao) {
                $statusRetorno = 'devolvido';
                $observacoesRetorno = null;
                $itemPendente = $this->itemPendenteRetorno($produtoLocacao);
                $decisaoAvaria = $decisoesAvariaInformadas->get((int) $produtoLocacao->id_produto_locacao, []);
                $acaoAvaria = Str::lower(trim((string) ($decisaoAvaria['acao'] ?? '')));
                $gerarManutencao = $acaoAvaria === 'gerar_manutencao';
                $statusRetornoPatrimonio = 'normal';
                $quantidadeRetornoItem = null;

                if ($gerarManutencao && $itemPendente && (int) ($produtoLocacao->estoque_status ?? 0) === 1) {
                    $inicioItem = $this->obterDataHoraInicioItemLocacao($produtoLocacao, $locacao);
                    $inicioItemAtingido = $inicioItem->lessThanOrEqualTo(now());
                    $estoqueJaSeparadoDaManutencao = !$produtoLocacao->id_patrimonio && $inicioItemAtingido;

                    $manutencaoCriada = $this->criarManutencaoRetornoDefeito(
                        produtoLocacao: $produtoLocacao,
                        locacao: $locacao,
                        decisaoAvaria: is_array($decisaoAvaria) ? $decisaoAvaria : [],
                        idEmpresa: (int) $idEmpresa,
                        idUsuario: (int) ($idUsuario ?? 0),
                        estoqueJaSeparadoDaManutencao: $estoqueJaSeparadoDaManutencao
                    );

                    if (!$produtoLocacao->id_patrimonio) {
                        $quantidadeContrato = max(1, (int) ($produtoLocacao->quantidade ?? 1));
                        $quantidadeManutencao = max(1, (int) ($manutencaoCriada->quantidade ?? 1));

                        if ($inicioItemAtingido) {
                            $quantidadeRetornoItem = max(0, $quantidadeContrato - $quantidadeManutencao);
                        } else {
                            $quantidadeRetornoItem = $quantidadeContrato;
                        }
                    }

                    $statusRetorno = 'avariado';
                    $statusRetornoPatrimonio = 'avariado';

                    $obsBase = trim((string) ($decisaoAvaria['observacoes'] ?? $decisaoAvaria['descricao'] ?? ''));
                    $observacoesRetorno = trim(
                        ($obsBase !== '' ? $obsBase . ' ' : '')
                        . 'Manutenção #' . $manutencaoCriada->id_manutencao . ' gerada automaticamente no retorno.'
                    );
                }

                if ($produtoLocacao->id_patrimonio && $itemPendente) {
                    $retornoPatrimonio = $retornosInformados->get($produtoLocacao->id_produto_locacao);

                    if (!$retornoPatrimonio) {
                        throw new \Exception('Existem patrimônios pendentes sem marcação de retorno.');
                    }

                    if (!$gerarManutencao) {
                        $statusRetorno = 'devolvido';
                        $statusRetornoPatrimonio = 'normal';
                    }

                    if (empty($observacoesRetorno)) {
                        $observacoesRetorno = $retornoPatrimonio['observacoes'] ?? null;
                    }

                    LocacaoRetornoPatrimonio::updateOrCreate(
                        [
                            'id_locacao' => $locacao->id_locacao,
                            'id_produto_locacao' => $produtoLocacao->id_produto_locacao,
                            'id_patrimonio' => $produtoLocacao->id_patrimonio,
                        ],
                        [
                            'id_empresa' => $idEmpresa,
                            'data_retorno' => now(),
                            'status_retorno' => $statusRetornoPatrimonio,
                            'observacoes_retorno' => $observacoesRetorno,
                            'id_usuario' => $idUsuario,
                        ]
                    );
                }

                if (!$itemPendente) {
                    continue;
                }

                if ((int) ($produtoLocacao->estoque_status ?? 0) !== 1) {
                    continue;
                }

                $this->estoqueService->registrarRetornoLocacao(
                    $produtoLocacao,
                    $statusRetorno,
                    $observacoesRetorno,
                    $idUsuario,
                    $quantidadeRetornoItem
                );

                $produtoLocacao->estoque_status = 2;
                $produtoLocacao->save();
            }

            $locacao->status = 'encerrado';
            $locacao->save();

            if ($itensAtrasados->isNotEmpty() && $recalcularAtraso) {
                $this->sincronizarTotaisLocacao($locacao);
            }

            $this->gerarFaturamentoLocacaoEncerrada($locacao, (int) $idEmpresa, $idUsuario, $statusAnterior);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Locação retornada com sucesso.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $statusCode = str_contains(mb_strtolower($e->getMessage()), 'pendente') ? 422 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function trocarProdutoContrato(Request $request, $locacaoId)
    {
        if (!$this->temTabelaTrocaProdutoLocacao()) {
            return response()->json([
                'success' => false,
                'message' => 'Tabela de histórico de trocas não encontrada. Execute o SQL create_locacao_troca_produto.sql.',
            ], 422);
        }

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $idUsuario = Auth::user()->id_usuario ?? null;

        $dados = $request->validate([
            'id_produto_locacao' => ['required', 'integer'],
            'id_produto_novo' => ['required', 'integer'],
            'quantidade_troca' => ['nullable', 'integer', 'min:1'],
            'patrimonios_novo' => ['nullable', 'array'],
            'patrimonios_novo.*' => ['integer', 'distinct'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $patrimoniosNovoSolicitados = collect($dados['patrimonios_novo'] ?? [])
            ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
            ->filter(fn ($idPatrimonio) => $idPatrimonio > 0)
            ->unique()
            ->values();

        DB::beginTransaction();

        try {
            $locacao = Locacao::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacaoId)
                ->lockForUpdate()
                ->first();

            if (!$locacao) {
                throw new \Exception('Contrato não encontrado.');
            }

            if ($this->locacaoTemFaturamento($locacao)) {
                throw new \Exception('Só é permitido trocar produto de contrato que ainda não foi faturado.');
            }

            if (in_array((string) $locacao->status, ['encerrado', 'cancelado', 'cancelada'], true)) {
                throw new \Exception('Contrato encerrado/cancelado não pode ter produto trocado.');
            }

            $itemLocacao = LocacaoProduto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->where('id_produto_locacao', (int) $dados['id_produto_locacao'])
                ->with(['produto', 'patrimonio'])
                ->lockForUpdate()
                ->first();

            if (!$itemLocacao) {
                throw new \Exception('Item do contrato não encontrado.');
            }

            $statusRetornoItem = (string) ($itemLocacao->status_retorno ?? 'pendente');
            $estoqueStatusItem = (int) ($itemLocacao->estoque_status ?? 0);

            if ($estoqueStatusItem === 2 || $statusRetornoItem !== 'pendente') {
                throw new \Exception('Não é possível trocar produto de item já retornado.');
            }

            $idProdutoAtual = (int) ($itemLocacao->id_produto ?? 0);
            $idProdutoNovo = (int) $dados['id_produto_novo'];

            if ($idProdutoAtual <= 0 || $idProdutoNovo <= 0) {
                throw new \Exception('Produto atual/novo inválido para a troca.');
            }

            $produtoAtual = Produto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_produto', $idProdutoAtual)
                ->lockForUpdate()
                ->first();

            $produtoNovo = Produto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_produto', $idProdutoNovo)
                ->where('status', 'ativo')
                ->lockForUpdate()
                ->first();

            if (!$produtoAtual || !$produtoNovo) {
                throw new \Exception('Produto atual/novo inválido para a troca.');
            }

            $quantidadeItem = !empty($itemLocacao->id_patrimonio)
                ? 1
                : max(1, (int) ($itemLocacao->quantidade ?? 1));

            $quantidadeTroca = max(1, (int) ($dados['quantidade_troca'] ?? $quantidadeItem));

            if (!empty($itemLocacao->id_patrimonio) && $quantidadeTroca !== 1) {
                throw new \Exception('Itens com patrimônio permitem troca unitária.');
            }

            if ($quantidadeTroca > $quantidadeItem) {
                throw new \Exception('A quantidade da troca não pode ser maior que a quantidade do item atual.');
            }

            $dadosDisponibilidadeTroca = $this->obterDadosDisponibilidadeTrocaProduto(
                $locacao,
                $itemLocacao,
                $produtoNovo,
                (int) $idEmpresa
            );

            $produtoNovoUsaPatrimonio = (bool) ($dadosDisponibilidadeTroca['produto_usa_patrimonio'] ?? false);
            $disponibilidadeTroca = $dadosDisponibilidadeTroca['disponibilidade'] ?? [];
            $patrimoniosDisponiveisTroca = collect($disponibilidadeTroca['patrimonios_disponiveis'] ?? []);

            $patrimoniosDisponiveisNovo = collect();
            if ($produtoNovoUsaPatrimonio) {
                if ($patrimoniosNovoSolicitados->count() !== $quantidadeTroca) {
                    throw new \Exception("Selecione exatamente {$quantidadeTroca} patrimônio(s) para o produto novo.");
                }

                if ($patrimoniosDisponiveisTroca->count() < $quantidadeTroca) {
                    throw new \Exception('Não há patrimônios disponíveis suficientes para o período do item selecionado.');
                }

                $idsDisponiveis = $patrimoniosDisponiveisTroca
                    ->pluck('id_patrimonio')
                    ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
                    ->all();

                $idsInvalidos = $patrimoniosNovoSolicitados
                    ->reject(fn ($idPatrimonio) => in_array($idPatrimonio, $idsDisponiveis, true));

                if ($idsInvalidos->isNotEmpty()) {
                    throw new \Exception('Foi selecionado patrimônio inválido, indisponível no período ou já vinculado a este contrato.');
                }

                $patrimoniosDisponiveisNovo = Patrimonio::query()
                    ->where('id_empresa', $idEmpresa)
                    ->whereIn('id_patrimonio', $patrimoniosNovoSolicitados->all())
                    ->lockForUpdate()
                    ->get();

                if ($patrimoniosDisponiveisNovo->count() !== $patrimoniosNovoSolicitados->count()) {
                    throw new \Exception('Um ou mais patrimônios selecionados não foram encontrados.');
                }
            } elseif ($patrimoniosNovoSolicitados->isNotEmpty()) {
                throw new \Exception('O produto novo não usa patrimônio. Remova os patrimônios selecionados.');
            } else {
                $disponivelPeriodo = (int) ($disponibilidadeTroca['disponivel'] ?? 0);
                if ($quantidadeTroca > $disponivelPeriodo) {
                    throw new \Exception("Estoque indisponível no período do item para o novo produto. Disponível: {$disponivelPeriodo}. Solicitado: {$quantidadeTroca}.");
                }
            }

            $trocaSomentePatrimonio = $idProdutoAtual === $idProdutoNovo;

            if ($trocaSomentePatrimonio) {
                if (!$produtoNovoUsaPatrimonio) {
                    throw new \Exception('Selecione um produto novo diferente do atual.');
                }

                if ($quantidadeTroca !== 1) {
                    throw new \Exception('Troca de patrimônio permite somente quantidade 1 por vez.');
                }
            }

            if (!empty($itemLocacao->id_patrimonio) && $produtoNovoUsaPatrimonio) {
                $idPatrimonioAtual = (int) $itemLocacao->id_patrimonio;
                if ($patrimoniosNovoSolicitados->contains($idPatrimonioAtual)) {
                    throw new \Exception('Selecione um patrimônio diferente do patrimônio atual para concluir a troca.');
                }
            }

            if ($estoqueStatusItem === 1 && !$produtoNovoUsaPatrimonio) {
                $estoqueNovoDisponivel = (int) ($produtoNovo->quantidade ?? 0);
                if ($idProdutoAtual === $idProdutoNovo) {
                    $estoqueNovoDisponivel += $quantidadeTroca;
                }

                if ($estoqueNovoDisponivel < $quantidadeTroca) {
                    throw new \Exception('Estoque insuficiente do novo produto para efetuar a troca.');
                }
            }

            $codigoPatrimonioAnterior = $itemLocacao->patrimonio
                ? ($itemLocacao->patrimonio->codigo_patrimonio ?? $itemLocacao->patrimonio->numero_serie ?? ('PAT-' . $itemLocacao->id_patrimonio))
                : null;

            $itemBaseTroca = $itemLocacao;
            if (empty($itemLocacao->id_patrimonio) && $quantidadeTroca < $quantidadeItem) {
                $itemLocacao->quantidade = max(1, $quantidadeItem - $quantidadeTroca);
                $itemLocacao->save();

                $itemBaseTroca = $itemLocacao->replicate();
                $itemBaseTroca->quantidade = $quantidadeTroca;
                $itemBaseTroca->id_patrimonio = null;
                $itemBaseTroca->status_retorno = 'pendente';
                $itemBaseTroca->estoque_status = $estoqueStatusItem;
                $itemBaseTroca->save();
            }

            $patrimoniosNovoMap = $patrimoniosDisponiveisNovo
                ->keyBy('id_patrimonio')
                ->map(function (Patrimonio $patrimonio) {
                    return $patrimonio->codigo_patrimonio
                        ?: $patrimonio->numero_serie
                        ?: ('PAT-' . $patrimonio->id_patrimonio);
                });

            $itensTrocados = collect();
            if ($produtoNovoUsaPatrimonio) {
                foreach ($patrimoniosNovoSolicitados as $indice => $idPatrimonioNovo) {
                    $itemTroca = $indice === 0
                        ? $itemBaseTroca
                        : $itemBaseTroca->replicate();

                    $itemTroca->id_produto = $produtoNovo->id_produto;
                    $itemTroca->id_patrimonio = $idPatrimonioNovo;
                    $itemTroca->quantidade = 1;
                    $itemTroca->status_retorno = 'pendente';
                    $itemTroca->estoque_status = $estoqueStatusItem;
                    $itemTroca->save();

                    $itensTrocados->push($itemTroca);
                }
            } else {
                $itemBaseTroca->id_produto = $produtoNovo->id_produto;
                $itemBaseTroca->id_patrimonio = null;
                $itemBaseTroca->quantidade = $quantidadeTroca;
                $itemBaseTroca->status_retorno = 'pendente';
                $itemBaseTroca->estoque_status = $estoqueStatusItem;
                $itemBaseTroca->save();

                $itensTrocados->push($itemBaseTroca);
            }

            $estoqueMovimentado = false;
            if ($estoqueStatusItem === 1) {
                $estoqueAnteriorAtual = (int) ($produtoAtual->quantidade ?? 0);
                $produtoAtual->quantidade = $estoqueAnteriorAtual + $quantidadeTroca;
                $produtoAtual->save();

                ProdutoHistorico::registrar([
                    'id_empresa' => $idEmpresa,
                    'id_produto' => $produtoAtual->id_produto,
                    'id_locacao' => $locacao->id_locacao,
                    'id_cliente' => $locacao->id_cliente,
                    'tipo_movimentacao' => 'retorno',
                    'quantidade' => $quantidadeTroca,
                    'estoque_anterior' => $estoqueAnteriorAtual,
                    'estoque_novo' => $produtoAtual->quantidade,
                    'motivo' => 'Troca de produto - retorno do item original (Contrato #' . $locacao->numero_contrato . ')',
                    'observacoes' => $dados['observacoes'] ?? null,
                    'id_usuario' => $idUsuario,
                ]);

                if ($produtoAtual->id_produto === $produtoNovo->id_produto) {
                    $produtoNovo->refresh();
                }

                $estoqueAnteriorNovo = (int) ($produtoNovo->quantidade ?? 0);
                if ($estoqueAnteriorNovo < $quantidadeTroca) {
                    throw new \Exception('Estoque insuficiente do novo produto para efetuar a troca.');
                }

                $produtoNovo->quantidade = max(0, $estoqueAnteriorNovo - $quantidadeTroca);
                $produtoNovo->save();

                ProdutoHistorico::registrar([
                    'id_empresa' => $idEmpresa,
                    'id_produto' => $produtoNovo->id_produto,
                    'id_locacao' => $locacao->id_locacao,
                    'id_cliente' => $locacao->id_cliente,
                    'tipo_movimentacao' => 'saida',
                    'quantidade' => $quantidadeTroca,
                    'estoque_anterior' => $estoqueAnteriorNovo,
                    'estoque_novo' => $produtoNovo->quantidade,
                    'motivo' => 'Troca de produto - saída do novo item (Contrato #' . $locacao->numero_contrato . ')',
                    'observacoes' => $dados['observacoes'] ?? null,
                    'id_usuario' => $idUsuario,
                ]);

                if (!empty($itemLocacao->id_patrimonio)) {
                    $patrimonioAnterior = Patrimonio::query()
                        ->where('id_empresa', $idEmpresa)
                        ->where('id_patrimonio', (int) $itemLocacao->id_patrimonio)
                        ->lockForUpdate()
                        ->first();

                    if ($patrimonioAnterior) {
                        $statusAnteriorPatrimonio = $patrimonioAnterior->status_locacao ?? 'Disponivel';

                        PatrimonioHistorico::registrar([
                            'id_empresa' => $idEmpresa,
                            'id_patrimonio' => $patrimonioAnterior->id_patrimonio,
                            'id_produto' => $patrimonioAnterior->id_produto,
                            'id_locacao' => $locacao->id_locacao,
                            'id_cliente' => $locacao->id_cliente,
                            'tipo_movimentacao' => 'retorno_locacao',
                            'status_anterior' => $statusAnteriorPatrimonio,
                            'status_novo' => 'Disponivel',
                            'local_origem' => $locacao->local_entrega,
                            'local_destino' => 'Estoque',
                            'observacoes' => 'Troca de produto/patrimônio no contrato #' . $locacao->numero_contrato,
                            'id_usuario' => $idUsuario,
                        ]);

                        $patrimonioAnterior->status_locacao = 'Disponivel';
                        $patrimonioAnterior->id_locacao_atual = null;
                        $patrimonioAnterior->localizacao_atual = 'Estoque';
                        $patrimonioAnterior->data_ultima_movimentacao = now();
                        $patrimonioAnterior->save();
                    }
                }

                if ($produtoNovoUsaPatrimonio) {
                    foreach ($patrimoniosNovoSolicitados as $idPatrimonioNovo) {
                        /** @var Patrimonio|null $patrimonioNovo */
                        $patrimonioNovo = $patrimoniosDisponiveisNovo
                            ->first(fn (Patrimonio $item) => (int) $item->id_patrimonio === (int) $idPatrimonioNovo);

                        if (!$patrimonioNovo) {
                            throw new \Exception('Patrimônio selecionado não encontrado para concluir a troca.');
                        }

                        $statusAnteriorPatrimonio = $patrimonioNovo->status_locacao ?? 'Disponivel';
                        if (!in_array($statusAnteriorPatrimonio, [null, '', 'Disponivel'], true)) {
                            throw new \Exception('Um dos patrimônios selecionados não está mais disponível para locação.');
                        }

                        PatrimonioHistorico::registrar([
                            'id_empresa' => $idEmpresa,
                            'id_patrimonio' => $patrimonioNovo->id_patrimonio,
                            'id_produto' => $patrimonioNovo->id_produto,
                            'id_locacao' => $locacao->id_locacao,
                            'id_cliente' => $locacao->id_cliente,
                            'tipo_movimentacao' => 'saida_locacao',
                            'status_anterior' => $statusAnteriorPatrimonio ?: 'Disponivel',
                            'status_novo' => 'Locado',
                            'local_destino' => $locacao->local_entrega,
                            'observacoes' => 'Troca de produto/patrimônio no contrato #' . $locacao->numero_contrato,
                            'id_usuario' => $idUsuario,
                        ]);

                        $patrimonioNovo->status_locacao = 'Locado';
                        $patrimonioNovo->id_locacao_atual = $locacao->id_locacao;
                        $patrimonioNovo->localizacao_atual = $locacao->local_entrega ?: 'Locação';
                        $patrimonioNovo->data_ultima_movimentacao = now();
                        $patrimonioNovo->save();
                    }
                }

                $estoqueMovimentado = true;
            }

            $motivoTroca = trim((string) ($dados['motivo'] ?? '')) ?: null;
            $observacoesBase = trim((string) ($dados['observacoes'] ?? ''));

            $trocasCriadas = collect();
            if ($produtoNovoUsaPatrimonio) {
                foreach ($itensTrocados as $itemTroca) {
                    $observacoesTroca = $observacoesBase;

                    if (!empty($codigoPatrimonioAnterior)) {
                        $observacoesTroca = trim($observacoesTroca . ($observacoesTroca !== '' ? ' ' : '') . 'Patrimônio anterior: ' . $codigoPatrimonioAnterior . '.');
                    }

                    $codigoPatrimonioNovo = $patrimoniosNovoMap->get((int) ($itemTroca->id_patrimonio ?? 0));
                    if (!empty($codigoPatrimonioNovo)) {
                        $observacoesTroca = trim($observacoesTroca . ($observacoesTroca !== '' ? ' ' : '') . 'Patrimônio novo: ' . $codigoPatrimonioNovo . '.');
                    }

                    $trocasCriadas->push(LocacaoTrocaProduto::create([
                        'id_empresa' => $idEmpresa,
                        'id_locacao' => $locacao->id_locacao,
                        'id_produto_locacao' => $itemTroca->id_produto_locacao,
                        'id_produto_anterior' => $produtoAtual->id_produto,
                        'id_produto_novo' => $produtoNovo->id_produto,
                        'quantidade' => 1,
                        'motivo' => $motivoTroca,
                        'observacoes' => $observacoesTroca !== '' ? $observacoesTroca : null,
                        'estoque_movimentado' => $estoqueMovimentado,
                        'id_usuario' => $idUsuario,
                    ]));
                }
            } else {
                $observacoesTroca = $observacoesBase;
                if (!empty($codigoPatrimonioAnterior)) {
                    $observacoesTroca = trim($observacoesTroca . ($observacoesTroca !== '' ? ' ' : '') . 'Patrimônio anterior: ' . $codigoPatrimonioAnterior . '.');
                }

                $trocasCriadas->push(LocacaoTrocaProduto::create([
                    'id_empresa' => $idEmpresa,
                    'id_locacao' => $locacao->id_locacao,
                    'id_produto_locacao' => $itemBaseTroca->id_produto_locacao,
                    'id_produto_anterior' => $produtoAtual->id_produto,
                    'id_produto_novo' => $produtoNovo->id_produto,
                    'quantidade' => $quantidadeTroca,
                    'motivo' => $motivoTroca,
                    'observacoes' => $observacoesTroca !== '' ? $observacoesTroca : null,
                    'estoque_movimentado' => $estoqueMovimentado,
                    'id_usuario' => $idUsuario,
                ]));
            }

            $trocaPrincipal = $trocasCriadas->first();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $trocasCriadas->count() > 1
                    ? 'Trocas registradas com sucesso.'
                    : 'Produto trocado com sucesso.',
                'trocas_registradas' => $trocasCriadas->count(),
                'troca' => $trocaPrincipal
                    ? [
                        'id_locacao_troca_produto' => $trocaPrincipal->id_locacao_troca_produto,
                        'pdf_url' => route('locacoes.trocas.pdf', ['troca' => $trocaPrincipal->id_locacao_troca_produto]),
                    ]
                    : null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function listarTrocasContrato(Request $request, $locacaoId)
    {
        if (!$this->temTabelaTrocaProdutoLocacao()) {
            return response()->json([
                'success' => true,
                'trocas' => [],
            ]);
        }

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $locacao = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacaoId)
            ->first();

        if (!$locacao) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato não encontrado.',
            ], 404);
        }

        $trocas = LocacaoTrocaProduto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->with([
                'produtoAnterior:id_produto,nome',
                'produtoNovo:id_produto,nome',
                'usuario:id_usuario,nome',
            ])
            ->orderByDesc('id_locacao_troca_produto')
            ->get()
            ->map(function (LocacaoTrocaProduto $troca) {
                return [
                    'id_locacao_troca_produto' => (int) $troca->id_locacao_troca_produto,
                    'produto_anterior' => $troca->produtoAnterior->nome ?? 'Produto removido',
                    'produto_novo' => $troca->produtoNovo->nome ?? 'Produto novo',
                    'patrimonio_anterior' => $troca->patrimonio_anterior_troca,
                    'patrimonio_novo' => $troca->patrimonio_novo_troca,
                    'quantidade' => (int) ($troca->quantidade ?? 1),
                    'motivo' => $troca->motivo,
                    'observacoes' => $troca->observacoes,
                    'estoque_movimentado' => (bool) $troca->estoque_movimentado,
                    'usuario' => $troca->usuario->nome ?? '-',
                    'data_troca' => optional($troca->created_at)->format('d/m/Y H:i'),
                    'pdf_url' => route('locacoes.trocas.pdf', ['troca' => $troca->id_locacao_troca_produto]),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'trocas' => $trocas,
        ]);
    }

    public function comprovanteTrocaProdutoPdf(Request $request, $trocaId)
    {
        if (!$this->temTabelaTrocaProdutoLocacao()) {
            abort(404);
        }

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $troca = LocacaoTrocaProduto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao_troca_produto', $trocaId)
            ->with([
                'locacao.cliente',
                'produtoAnterior:id_produto,nome,codigo',
                'produtoNovo:id_produto,nome,codigo',
                'usuario:id_usuario,nome',
            ])
            ->firstOrFail();

        $empresa = Empresa::where('id_empresa', $idEmpresa)->first();
        if ($empresa) {
            $this->normalizarLogoEmpresa($empresa);
            $empresa->refresh();
        }

        $pdf = Pdf::loadView('locacoes.documentos.troca-produto-pdf', [
            'troca' => $troca,
            'empresa' => $empresa,
            'logoEmpresaDataUri' => $this->montarLogoDataUriEmpresa($empresa),
            'geradoEm' => now(),
        ])->setPaper('a4');

        return $pdf->stream('comprovante_troca_' . $troca->id_locacao_troca_produto . '.pdf');
    }

    private function itemPendenteRetorno(LocacaoProduto $item): bool
    {
        $statusRetorno = $item->status_retorno;
        return (int) ($item->estoque_status ?? 0) !== 2
            && in_array($statusRetorno, [null, '', 'pendente'], true);
    }

    private function obterItensDefeitoElegiveisRetorno(Locacao $locacao)
    {
        $idEmpresa = (int) ($locacao->id_empresa ?? 0);
        $usaCamposProdutoLocacao = $this->temColunasDefeitoProdutoLocacao();

        $fotosDefeito = LocacaoChecklistFoto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('tipo', 'entrada')
            ->where('voltou_com_defeito', true)
            ->orderByDesc('capturado_em')
            ->orderByDesc('id_locacao_checklist_foto')
            ->get()
            ->groupBy('id_produto_locacao')
            ->map(fn ($fotos) => $fotos->first());

        return ($locacao->produtos ?? collect())
            ->filter(function ($item) use ($fotosDefeito, $usaCamposProdutoLocacao) {
                if (!$this->itemPendenteRetorno($item) || (int) ($item->estoque_status ?? 0) !== 1) {
                    return false;
                }

                $temDefeitoProduto = $usaCamposProdutoLocacao && (bool) ($item->voltou_com_defeito ?? false);
                $temDefeitoFoto = $fotosDefeito->has((int) $item->id_produto_locacao);

                return $temDefeitoProduto || $temDefeitoFoto;
            })
            ->map(function ($item) use ($fotosDefeito, $usaCamposProdutoLocacao) {
                $fotoDefeito = $fotosDefeito->get((int) $item->id_produto_locacao);
                $quantidadeItem = max(1, (int) ($item->quantidade ?? 1));

                $quantidadeComDefeito = $usaCamposProdutoLocacao
                    ? (int) ($item->quantidade_com_defeito ?? 0)
                    : 1;

                if (!empty($item->id_patrimonio)) {
                    $quantidadeComDefeito = 1;
                } else {
                    $quantidadeComDefeito = max(1, min($quantidadeItem, $quantidadeComDefeito > 0 ? $quantidadeComDefeito : 1));
                }

                $observacaoDefeito = $usaCamposProdutoLocacao
                    ? trim((string) ($item->observacao_defeito ?? ''))
                    : trim((string) ($fotoDefeito->observacao ?? ''));

                return [
                    'id_locacao' => (int) $item->id_locacao,
                    'id_produto_locacao' => (int) $item->id_produto_locacao,
                    'id_produto' => (int) ($item->id_produto ?? 0),
                    'id_patrimonio' => (int) ($item->id_patrimonio ?? 0),
                    'nome_produto' => $item->produto->nome ?? 'Produto',
                    'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                    'quantidade' => $quantidadeItem,
                    'quantidade_com_defeito' => $quantidadeComDefeito,
                    'observacao_defeito' => $observacaoDefeito,
                    'capturado_em' => optional(optional($fotoDefeito)->capturado_em)->format('d/m/Y H:i'),
                ];
            })
            ->values();
    }

    private function criarManutencaoRetornoDefeito(
        LocacaoProduto $produtoLocacao,
        Locacao $locacao,
        array $decisaoAvaria,
        int $idEmpresa,
        int $idUsuario,
        bool $estoqueJaSeparadoDaManutencao = false
    ): Manutencao
    {
        $idProduto = (int) ($produtoLocacao->id_produto ?? 0);
        if ($idProduto <= 0) {
            throw new \Exception('Item com defeito sem produto vinculado para gerar manutenção automática.');
        }

        $tipoManutencao = Str::lower(trim((string) ($decisaoAvaria['tipo'] ?? 'corretiva')));
        if (!in_array($tipoManutencao, ['preventiva', 'corretiva', 'preditiva', 'emergencial'], true)) {
            $tipoManutencao = 'corretiva';
        }

        $dataManutencao = now()->toDateString();
        if (!empty($decisaoAvaria['data_manutencao'])) {
            $dataManutencao = Carbon::parse((string) $decisaoAvaria['data_manutencao'])->toDateString();
        }

        $dataPrevisao = null;
        if (!empty($decisaoAvaria['data_previsao'])) {
            $dataPrevisao = Carbon::parse((string) $decisaoAvaria['data_previsao'])->toDateString();
        }

        $descricaoInformada = trim((string) ($decisaoAvaria['descricao'] ?? ''));
        $descricao = $descricaoInformada !== ''
            ? $descricaoInformada
            : 'Manutenção gerada automaticamente no retorno da locação #' . ($locacao->numero_contrato ?: $locacao->id_locacao);

        $observacoes = trim((string) ($decisaoAvaria['observacoes'] ?? ''));
        $responsavel = trim((string) ($decisaoAvaria['responsavel'] ?? ''));
        if ($responsavel === '') {
            $responsavel = trim((string) (Auth::user()->nome ?? ''));
        }

        $payloadManutencao = [
            'id_empresa' => $idEmpresa,
            'id_produto' => $idProduto,
            'id_patrimonio' => !empty($produtoLocacao->id_patrimonio) ? (int) $produtoLocacao->id_patrimonio : null,
            'quantidade' => !empty($produtoLocacao->id_patrimonio)
                ? 1
                : max(1, min(
                    (int) ($produtoLocacao->quantidade ?? 1),
                    (int) ($decisaoAvaria['quantidade_defeito'] ?? $produtoLocacao->quantidade_com_defeito ?? 1)
                )),
            'data_manutencao' => $dataManutencao,
            'tipo' => $tipoManutencao,
            'descricao' => $descricao,
            'status' => 'em_andamento',
            'responsavel' => $responsavel !== '' ? $responsavel : null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'estoque_status' => $estoqueJaSeparadoDaManutencao ? 1 : 0,
        ];

        if ($dataPrevisao !== null && $this->temColunaDataPrevisaoManutencao()) {
            $payloadManutencao['data_previsao'] = $dataPrevisao;
        }

        $manutencao = Manutencao::create($payloadManutencao);

        $this->manutencaoEstoqueService->sincronizarAoSalvar($manutencao, null);

        return $manutencao;
    }

    private function temColunasDefeitoProdutoLocacao(): bool
    {
        return Schema::hasTable('produto_locacao')
            && Schema::hasColumn('produto_locacao', 'voltou_com_defeito')
            && Schema::hasColumn('produto_locacao', 'quantidade_com_defeito')
            && Schema::hasColumn('produto_locacao', 'observacao_defeito');
    }

    private function temColunaDataPrevisaoManutencao(): bool
    {
        return Schema::hasTable('manutencoes')
            && Schema::hasColumn('manutencoes', 'data_previsao');
    }

    private function temTabelaTrocaProdutoLocacao(): bool
    {
        return Schema::hasTable('locacao_troca_produto');
    }

    private function locacaoTemFaturamento(Locacao $locacao): bool
    {
        if (array_key_exists('faturamentos_ativos_count', $locacao->getAttributes())) {
            return (int) ($locacao->faturamentos_ativos_count ?? 0) > 0;
        }

        return $locacao->faturamentos()->exists();
    }

    private function obterDataHoraInicioItemLocacao(LocacaoProduto $item, ?Locacao $locacao = null): Carbon
    {
        $locacaoRef = $locacao ?: $item->locacao;
        $dataInicio = $item->data_inicio ?: ($locacaoRef->data_inicio ?? now()->toDateString());
        $horaInicio = $item->hora_inicio ?: ($locacaoRef->hora_inicio ?? '00:00:00');

        $dataInicioNormalizada = $dataInicio instanceof \DateTimeInterface
            ? $dataInicio->format('Y-m-d')
            : Carbon::parse((string) $dataInicio)->toDateString();

        $horaInicioNormalizada = Carbon::parse((string) $horaInicio)->format('H:i:s');

        return Carbon::createFromFormat('Y-m-d H:i:s', $dataInicioNormalizada . ' ' . $horaInicioNormalizada);
    }

    private function obterDataHoraFimItemLocacao(LocacaoProduto $item, ?Locacao $locacao = null): Carbon
    {
        $locacaoRef = $locacao ?: $item->locacao;
        $dataFim = $item->data_fim ?: ($locacaoRef->data_fim ?? now()->toDateString());
        $horaFim = $item->hora_fim ?: ($locacaoRef->hora_fim ?? '23:59:59');

        $dataFimNormalizada = $dataFim instanceof \DateTimeInterface
            ? $dataFim->format('Y-m-d')
            : Carbon::parse((string) $dataFim)->toDateString();

        $horaFimNormalizada = Carbon::parse((string) $horaFim)->format('H:i:s');

        return Carbon::createFromFormat('Y-m-d H:i:s', $dataFimNormalizada . ' ' . $horaFimNormalizada);
    }

    /**
     * Retorno parcial de itens da locação
     */
    public function retornoParcial(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['produtos.produto', 'produtos.patrimonio'])
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            if (in_array($locacao->status, ['encerrado', 'cancelado', 'cancelada'], true)) {
                throw new \Exception('Não é possível realizar retorno parcial para esta locação.');
            }

            $locacaoPorHora = $this->ehLocacaoPorHoraLocacao($locacao);

            if ($this->locacaoPossuiFaturamento((int) $idEmpresa, (int) $locacao->id_locacao)) {
                throw new \Exception('Contrato já faturado. Cancele o Faturamento existente.');
            }

            $naoRecalcularValor = $request->boolean('nao_recalcular_valor');

            $itensSelecionados = collect($request->input('itens', []))
                ->map(function ($item) {
                    if (is_array($item)) {
                        return [
                            'id_produto_locacao' => (int) ($item['id_produto_locacao'] ?? 0),
                            'quantidade_retorno' => max(1, (int) ($item['quantidade_retorno'] ?? 1)),
                        ];
                    }

                    return [
                        'id_produto_locacao' => (int) $item,
                        'quantidade_retorno' => 1,
                    ];
                })
                ->filter(fn ($item) => (int) ($item['id_produto_locacao'] ?? 0) > 0)
                ->unique('id_produto_locacao')
                ->values();

            if ($itensSelecionados->isEmpty()) {
                throw new \Exception('Selecione ao menos um produto para retorno parcial.');
            }

            $dataHoraRetornoInformada = (string) $request->input('data_hora_retorno', '');
            if ($dataHoraRetornoInformada === '') {
                throw new \Exception('Informe a data e hora do retorno.');
            }

            try {
                $dataHoraRetorno = Carbon::parse($dataHoraRetornoInformada);
            } catch (\Throwable $e) {
                throw new \Exception('Data/hora de retorno inválida.');
            }

            $produtosPorId = $locacao->produtos->keyBy('id_produto_locacao');

            $quantidadeProcessada = 0;
            foreach ($itensSelecionados as $itemSelecionado) {
                $idProdutoLocacao = (int) ($itemSelecionado['id_produto_locacao'] ?? 0);
                $quantidadeRetornoSolicitada = max(1, (int) ($itemSelecionado['quantidade_retorno'] ?? 1));

                $produtoLocacao = $produtosPorId->get($idProdutoLocacao);

                if (!$produtoLocacao) {
                    continue;
                }

                $jaRetornado = (int) ($produtoLocacao->estoque_status ?? 0) === 2
                    || !in_array($produtoLocacao->status_retorno, [null, '', 'pendente'], true);

                if ($jaRetornado) {
                    continue;
                }

                $quantidadeAtual = max(1, (int) ($produtoLocacao->quantidade ?? 1));
                $temPatrimonio = !empty($produtoLocacao->id_patrimonio);

                if ($temPatrimonio) {
                    $quantidadeRetorno = 1;
                } else {
                    $quantidadeRetorno = min($quantidadeAtual, $quantidadeRetornoSolicitada);
                }

                if ($quantidadeRetorno <= 0) {
                    continue;
                }

                $dataInicioLocacao = $locacao->data_inicio instanceof \DateTime
                    ? $locacao->data_inicio->format('Y-m-d')
                    : ($locacao->data_inicio ?: now()->toDateString());

                $dataInicioProduto = $produtoLocacao->data_inicio;
                if ($dataInicioProduto instanceof \DateTimeInterface) {
                    $dataInicioProduto = $dataInicioProduto->format('Y-m-d');
                } elseif (!empty($dataInicioProduto)) {
                    $dataInicioProduto = Carbon::parse((string) $dataInicioProduto)->toDateString();
                } else {
                    $dataInicioProduto = $dataInicioLocacao;
                }

                $horaInicioProduto = $produtoLocacao->hora_inicio ?: ($locacao->hora_inicio ?: '00:00:00');
                $horaInicioProduto = Carbon::parse((string) $horaInicioProduto)->format('H:i:s');

                $inicioProduto = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $dataInicioProduto . ' ' . $horaInicioProduto
                );

                if ($dataHoraRetorno->lt($inicioProduto)) {
                    throw new \Exception('A data/hora de retorno não pode ser anterior ao início do produto.');
                }

                $periodoUtilizado = $this->calcularQuantidadePeriodoCobranca(
                    $inicioProduto->toDateString(),
                    $inicioProduto->format('H:i:s'),
                    $dataHoraRetorno->toDateString(),
                    $dataHoraRetorno->format('H:i:s'),
                    $locacaoPorHora,
                    max(1, (int) ($locacao->quantidade_dias ?? 1))
                );

                $periodoOriginal = $this->calcularQuantidadePeriodoCobranca(
                    $produtoLocacao->data_inicio ?? $locacao->data_inicio,
                    $produtoLocacao->hora_inicio ?? $locacao->hora_inicio,
                    $produtoLocacao->data_fim ?? $locacao->data_fim,
                    $produtoLocacao->hora_fim ?? $locacao->hora_fim,
                    $locacaoPorHora,
                    max(1, (int) ($locacao->quantidade_dias ?? 1))
                );

                $itemRetorno = $produtoLocacao;
                if (!$temPatrimonio && $quantidadeRetorno < $quantidadeAtual) {
                    $itemRetorno = $produtoLocacao->replicate();
                    $itemRetorno->quantidade = $quantidadeRetorno;
                    $itemRetorno->status_retorno = 'pendente';
                    $itemRetorno->estoque_status = $produtoLocacao->estoque_status;
                    $itemRetorno->data_fim = $dataHoraRetorno->toDateString();
                    $itemRetorno->hora_fim = $dataHoraRetorno->format('H:i:s');

                    $this->aplicarRegraValorRetornoParcialItem(
                        $itemRetorno,
                        $periodoUtilizado,
                        $periodoOriginal,
                        $naoRecalcularValor
                    );
                    $itemRetorno->save();

                    $produtoLocacao->quantidade = $quantidadeAtual - $quantidadeRetorno;
                    $produtoLocacao->save();
                } else {
                    $itemRetorno->data_fim = $dataHoraRetorno->toDateString();
                    $itemRetorno->hora_fim = $dataHoraRetorno->format('H:i:s');

                    $this->aplicarRegraValorRetornoParcialItem(
                        $itemRetorno,
                        $periodoUtilizado,
                        $periodoOriginal,
                        $naoRecalcularValor
                    );
                    $itemRetorno->save();
                }

                if ((int) ($itemRetorno->estoque_status ?? 0) === 1) {
                    $this->estoqueService->registrarRetornoLocacao(
                        $itemRetorno,
                        'devolvido',
                        'Retorno parcial de locação',
                        $idUsuario
                    );

                    $itemRetorno->estoque_status = 2;
                }

                $itemRetorno->status_retorno = 'devolvido';
                $itemRetorno->save();

                if ($itemRetorno->id_patrimonio) {
                    LocacaoRetornoPatrimonio::updateOrCreate(
                        [
                            'id_locacao' => $locacao->id_locacao,
                            'id_produto_locacao' => $itemRetorno->id_produto_locacao,
                            'id_patrimonio' => $itemRetorno->id_patrimonio,
                        ],
                        [
                            'id_empresa' => $idEmpresa,
                            'data_retorno' => $dataHoraRetorno,
                            'status_retorno' => 'normal',
                            'observacoes_retorno' => 'Retorno parcial de locação',
                            'id_usuario' => $idUsuario,
                        ]
                    );
                }

                $quantidadeProcessada++;
            }

            if ($quantidadeProcessada === 0) {
                throw new \Exception('Nenhum item elegível para retorno parcial foi selecionado.');
            }

            $this->sincronizarTotaisLocacao($locacao);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retorno parcial realizado com sucesso.',
                'itens_processados' => $quantidadeProcessada,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Listar itens elegíveis para o modal de retorno parcial
     */
    public function logsAtividades($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $escopo = (string) request()->query('escopo', 'todos');

            $escoposPermitidos = ['todos', 'locacao', 'produtos', 'servicos', 'despesas', 'terceiros', 'retornos', 'trocas'];
            if (!in_array($escopo, $escoposPermitidos, true)) {
                $escopo = 'todos';
            }

            $locacao = Locacao::query()
                ->where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with('cliente:id_clientes,nome')
                ->first();

            if (!$locacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Locação não encontrada.',
                ], 404);
            }

            $idsProdutos = LocacaoProduto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->pluck('id_produto_locacao')
                ->all();

            $idsServicos = LocacaoServico::query()
                ->where('id_locacao', $locacao->id_locacao)
                ->pluck('id_locacao_servico')
                ->all();

            $idsDespesas = LocacaoDespesa::query()
                ->where('id_locacao', $locacao->id_locacao)
                ->pluck('id_locacao_despesa')
                ->all();

            $idsTerceiros = ProdutoTerceirosLocacao::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->pluck('id_produto_terceiros_locacao')
                ->all();

            $idsRetornos = LocacaoRetornoPatrimonio::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->pluck('id_retorno')
                ->all();

            $idsTrocas = LocacaoTrocaProduto::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_locacao', $locacao->id_locacao)
                ->pluck('id_locacao_troca_produto')
                ->all();

            $tiposLocacao = ['locacao', 'Locacao'];
            $tiposProdutos = ['locacaoproduto', 'locacao_produto', 'LocacaoProduto'];
            $tiposServicos = ['locacaoservico', 'locacao_servico', 'LocacaoServico'];
            $tiposDespesas = ['locacaodespesa', 'locacao_despesa', 'LocacaoDespesa'];
            $tiposTerceiros = ['produtoterceiroslocacao', 'produto_terceiros_locacao', 'ProdutoTerceirosLocacao'];
            $tiposRetornos = ['locacaoretornopatrimonio', 'locacao_retorno_patrimonio', 'LocacaoRetornoPatrimonio'];
            $tiposTrocas = ['locacaotrocaproduto', 'locacao_troca_produto', 'LocacaoTrocaProduto'];

            $contagens = [
                'locacao' => RegistroAtividade::query()
                    ->where('id_empresa', $idEmpresa)
                    ->whereIn('entidade_tipo', $tiposLocacao)
                    ->where('entidade_id', $locacao->id_locacao)
                    ->count(),
                'produtos' => empty($idsProdutos)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposProdutos)
                        ->whereIn('entidade_id', $idsProdutos)
                        ->count(),
                'servicos' => empty($idsServicos)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposServicos)
                        ->whereIn('entidade_id', $idsServicos)
                        ->count(),
                'despesas' => empty($idsDespesas)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposDespesas)
                        ->whereIn('entidade_id', $idsDespesas)
                        ->count(),
                'terceiros' => empty($idsTerceiros)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposTerceiros)
                        ->whereIn('entidade_id', $idsTerceiros)
                        ->count(),
                'retornos' => empty($idsRetornos)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposRetornos)
                        ->whereIn('entidade_id', $idsRetornos)
                        ->count(),
                'trocas' => empty($idsTrocas)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposTrocas)
                        ->whereIn('entidade_id', $idsTrocas)
                        ->count(),
            ];

            $contagens['todos'] = $contagens['locacao']
                + $contagens['produtos']
                + $contagens['servicos']
                + $contagens['despesas']
                + $contagens['terceiros']
                + $contagens['retornos']
                + $contagens['trocas'];

            $escopoSemRegistros = [
                'produtos' => empty($idsProdutos),
                'servicos' => empty($idsServicos),
                'despesas' => empty($idsDespesas),
                'terceiros' => empty($idsTerceiros),
                'retornos' => empty($idsRetornos),
                'trocas' => empty($idsTrocas),
            ];

            if (($escopoSemRegistros[$escopo] ?? false) === true) {
                return response()->json([
                    'success' => true,
                    'locacao' => [
                        'id_locacao' => (int) $locacao->id_locacao,
                        'numero_contrato' => $locacao->numero_contrato,
                        'cliente' => $locacao->cliente->nome ?? null,
                    ],
                    'escopo' => $escopo,
                    'contagens' => $contagens,
                    'logs' => [],
                ]);
            }

            $logs = RegistroAtividade::query()
                ->where('id_empresa', $idEmpresa)
                ->where(function ($query) use (
                    $escopo,
                    $tiposLocacao,
                    $tiposProdutos,
                    $tiposServicos,
                    $tiposDespesas,
                    $tiposTerceiros,
                    $tiposRetornos,
                    $tiposTrocas,
                    $locacao,
                    $idsProdutos,
                    $idsServicos,
                    $idsDespesas,
                    $idsTerceiros,
                    $idsRetornos,
                    $idsTrocas
                ) {
                    if ($escopo === 'locacao' || $escopo === 'todos') {
                        $query->orWhere(function ($sub) use ($tiposLocacao, $locacao) {
                            $sub->whereIn('entidade_tipo', $tiposLocacao)
                                ->where('entidade_id', $locacao->id_locacao);
                        });
                    }

                    if (($escopo === 'produtos' || $escopo === 'todos') && !empty($idsProdutos)) {
                        $query->orWhere(function ($sub) use ($tiposProdutos, $idsProdutos) {
                            $sub->whereIn('entidade_tipo', $tiposProdutos)
                                ->whereIn('entidade_id', $idsProdutos);
                        });
                    }

                    if (($escopo === 'servicos' || $escopo === 'todos') && !empty($idsServicos)) {
                        $query->orWhere(function ($sub) use ($tiposServicos, $idsServicos) {
                            $sub->whereIn('entidade_tipo', $tiposServicos)
                                ->whereIn('entidade_id', $idsServicos);
                        });
                    }

                    if (($escopo === 'despesas' || $escopo === 'todos') && !empty($idsDespesas)) {
                        $query->orWhere(function ($sub) use ($tiposDespesas, $idsDespesas) {
                            $sub->whereIn('entidade_tipo', $tiposDespesas)
                                ->whereIn('entidade_id', $idsDespesas);
                        });
                    }

                    if (($escopo === 'terceiros' || $escopo === 'todos') && !empty($idsTerceiros)) {
                        $query->orWhere(function ($sub) use ($tiposTerceiros, $idsTerceiros) {
                            $sub->whereIn('entidade_tipo', $tiposTerceiros)
                                ->whereIn('entidade_id', $idsTerceiros);
                        });
                    }

                    if (($escopo === 'retornos' || $escopo === 'todos') && !empty($idsRetornos)) {
                        $query->orWhere(function ($sub) use ($tiposRetornos, $idsRetornos) {
                            $sub->whereIn('entidade_tipo', $tiposRetornos)
                                ->whereIn('entidade_id', $idsRetornos);
                        });
                    }

                    if (($escopo === 'trocas' || $escopo === 'todos') && !empty($idsTrocas)) {
                        $query->orWhere(function ($sub) use ($tiposTrocas, $idsTrocas) {
                            $sub->whereIn('entidade_tipo', $tiposTrocas)
                                ->whereIn('entidade_id', $idsTrocas);
                        });
                    }
                })
                ->orderByDesc('ocorrido_em')
                ->limit(80)
                ->get([
                    'id_registro',
                    'acao',
                    'descricao',
                    'entidade_tipo',
                    'entidade_id',
                    'entidade_label',
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

            $referencias = $this->montarReferenciasLogLocacao(
                $logs,
                (int) $idEmpresa,
                (int) $locacao->id_locacao
            );

            return response()->json([
                'success' => true,
                'locacao' => [
                    'id_locacao' => (int) $locacao->id_locacao,
                    'numero_contrato' => $locacao->numero_contrato,
                    'cliente' => $locacao->cliente->nome ?? null,
                ],
                'escopo' => $escopo,
                'contagens' => $contagens,
                'referencias' => $referencias,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar log de atividades da locação', [
                'id_locacao' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar log de atividades: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar itens elegíveis para o modal de retorno parcial
     */
    public function itensRetornoParcial($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['produtos.produto', 'produtos.patrimonio'])
                ->first();

            if (!$locacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Locação não encontrada.',
                ], 404);
            }

            if ($this->locacaoPossuiFaturamento((int) $idEmpresa, (int) $locacao->id_locacao)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato já faturado. Cancele o Faturamento existente.',
                ], 422);
            }

            $itens = ($locacao->produtos ?? collect())->map(function ($item) {
                return [
                    'id_produto_locacao' => $item->id_produto_locacao,
                    'nome' => $item->produto->nome ?? 'Produto',
                    'id_patrimonio' => $item->id_patrimonio,
                    'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                    'data_inicio' => optional($item->data_inicio)->format('Y-m-d'),
                    'hora_inicio' => $item->hora_inicio,
                    'data_fim' => optional($item->data_fim)->format('Y-m-d'),
                    'hora_fim' => $item->hora_fim,
                    'valor_fechado' => (bool) ($item->valor_fechado ?? false),
                    'preco_unitario' => (float) ($item->preco_unitario ?? 0),
                    'quantidade' => (int) ($item->quantidade ?? 1),
                    'status_retorno' => $item->status_retorno,
                    'estoque_status' => (int) ($item->estoque_status ?? 0),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'itens' => $itens,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function montarReferenciasLogLocacao($logs, int $idEmpresa, int $idLocacao): array
    {
        $idsEmpresas = [$idEmpresa];
        $idsClientes = [];
        $idsUsuarios = [];
        $idsLocacoes = [$idLocacao];
        $idsProdutos = [];
        $idsSalas = [];
        $idsTabelasPreco = [];

        $coletarIds = function (array $dados) use (&$idsEmpresas, &$idsClientes, &$idsUsuarios, &$idsLocacoes, &$idsProdutos, &$idsSalas, &$idsTabelasPreco): void {
            $mapaCampos = [
                'id_empresa' => 'empresa',
                'id_cliente' => 'cliente',
                'id_clientes' => 'cliente',
                'id_usuario' => 'usuario',
                'id_locacao' => 'locacao',
                'id_produto' => 'produto',
                'id_sala' => 'sala',
                'id_tabela_preco' => 'tabela_preco',
            ];

            foreach ($mapaCampos as $campo => $tipo) {
                if (!array_key_exists($campo, $dados)) {
                    continue;
                }

                $valor = $dados[$campo];
                if ($valor === null || $valor === '') {
                    continue;
                }

                $id = (int) $valor;
                if ($id <= 0) {
                    continue;
                }

                if ($tipo === 'empresa') {
                    $idsEmpresas[] = $id;
                } elseif ($tipo === 'cliente') {
                    $idsClientes[] = $id;
                } elseif ($tipo === 'usuario') {
                    $idsUsuarios[] = $id;
                } elseif ($tipo === 'locacao') {
                    $idsLocacoes[] = $id;
                } elseif ($tipo === 'produto') {
                    $idsProdutos[] = $id;
                } elseif ($tipo === 'sala') {
                    $idsSalas[] = $id;
                } elseif ($tipo === 'tabela_preco') {
                    $idsTabelasPreco[] = $id;
                }
            }
        };

        foreach ($logs as $log) {
            $coletarIds((array) ($log->antes ?? []));
            $coletarIds((array) ($log->depois ?? []));
        }

        $locacao = Locacao::query()
            ->select(['id_locacao', 'id_cliente'])
            ->where('id_locacao', $idLocacao)
            ->first();

        if ($locacao && (int) ($locacao->id_cliente ?? 0) > 0) {
            $idsClientes[] = (int) $locacao->id_cliente;
        }

        $idsEmpresas = array_values(array_unique(array_filter($idsEmpresas)));
        $idsClientes = array_values(array_unique(array_filter($idsClientes)));
        $idsUsuarios = array_values(array_unique(array_filter($idsUsuarios)));
        $idsLocacoes = array_values(array_unique(array_filter($idsLocacoes)));
        $idsProdutos = array_values(array_unique(array_filter($idsProdutos)));
        $idsSalas = array_values(array_unique(array_filter($idsSalas)));
        $idsTabelasPreco = array_values(array_unique(array_filter($idsTabelasPreco)));

        $empresas = empty($idsEmpresas)
            ? []
            : Empresa::query()
                ->whereIn('id_empresa', $idsEmpresas)
                ->pluck('nome_empresa', 'id_empresa')
                ->map(function ($nome) {
                    return trim((string) $nome);
                })
                ->toArray();

        $clientes = empty($idsClientes)
            ? []
            : Cliente::query()
                ->whereIn('id_clientes', $idsClientes)
                ->get(['id_clientes', 'nome', 'razao_social'])
                ->mapWithKeys(function (Cliente $cliente) {
                    $nome = trim((string) ($cliente->nome ?? ''));
                    if ($nome === '') {
                        $nome = trim((string) ($cliente->razao_social ?? ''));
                    }

                    return [(string) $cliente->id_clientes => $nome !== '' ? $nome : 'Cliente nao identificado'];
                })
                ->toArray();

        $usuarios = empty($idsUsuarios)
            ? []
            : Usuario::query()
                ->whereIn('id_usuario', $idsUsuarios)
                ->get(['id_usuario', 'nome', 'login'])
                ->mapWithKeys(function (Usuario $usuario) {
                    $nome = trim((string) ($usuario->nome ?? ''));
                    if ($nome === '') {
                        $nome = trim((string) ($usuario->login ?? ''));
                    }

                    return [(string) $usuario->id_usuario => $nome !== '' ? $nome : 'Usuario nao identificado'];
                })
                ->toArray();

        $locacoes = empty($idsLocacoes)
            ? []
            : Locacao::query()
                ->whereIn('id_locacao', $idsLocacoes)
                ->get(['id_locacao', 'numero_contrato'])
                ->mapWithKeys(function (Locacao $locacaoItem) {
                    $numero = trim((string) ($locacaoItem->numero_contrato ?? ''));
                    if ($numero === '') {
                        $numero = (string) $locacaoItem->id_locacao;
                    }

                    return [(string) $locacaoItem->id_locacao => 'Contrato #' . $numero];
                })
                ->toArray();

        $produtos = empty($idsProdutos)
            ? []
            : Produto::query()
                ->whereIn('id_produto', $idsProdutos)
                ->get(['id_produto', 'nome'])
                ->mapWithKeys(function (Produto $produto) {
                    $nome = trim((string) ($produto->nome ?? ''));
                    return [(string) $produto->id_produto => $nome !== '' ? $nome : 'Produto nao identificado'];
                })
                ->toArray();

        $salas = empty($idsSalas)
            ? []
            : LocacaoSala::query()
                ->whereIn('id_sala', $idsSalas)
                ->get(['id_sala', 'nome'])
                ->mapWithKeys(function (LocacaoSala $sala) {
                    $nome = trim((string) ($sala->nome ?? ''));
                    return [(string) $sala->id_sala => $nome !== '' ? $nome : 'Sala nao identificada'];
                })
                ->toArray();

        $tabelasPreco = empty($idsTabelasPreco)
            ? []
            : TabelaPreco::query()
                ->whereIn('id_tabela', $idsTabelasPreco)
                ->get(['id_tabela', 'nome'])
                ->mapWithKeys(function (TabelaPreco $tabela) {
                    $nome = trim((string) ($tabela->nome ?? ''));
                    return [(string) $tabela->id_tabela => $nome !== '' ? $nome : 'Tabela de preco nao identificada'];
                })
                ->toArray();

        return [
            'empresas' => $empresas,
            'clientes' => $clientes,
            'usuarios' => $usuarios,
            'locacoes' => $locacoes,
            'produtos' => $produtos,
            'salas' => $salas,
            'tabelas_preco' => $tabelasPreco,
        ];
    }

    public function finalizarContratoMedicao(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::query()
                ->where('id_locacao', (int) $id)
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'medicao')
                ->with(['produtos'])
                ->first();

            if (!$locacao) {
                throw new \Exception('Contrato de medição não encontrado.');
            }

            $itensEmUso = collect($locacao->produtos ?? [])->filter(function ($item) {
                $retornado = (int) ($item->estoque_status ?? 0) === 2
                    || !in_array($item->status_retorno, [null, '', 'pendente'], true);
                return !$retornado;
            })->count();

            if ($itensEmUso > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Existem itens em uso. Retorne todos os produtos antes de finalizar o contrato.',
                ], 422);
            }

            $ultimoFimPeriodo = $this->obterUltimoFimPeriodoFaturadoMedicao((int) $locacao->id_locacao, (int) $idEmpresa);
            $inicioLocacao = $this->obterInicioLocacaoMedicao($locacao);
            $inicioPendente = $ultimoFimPeriodo
                ? $ultimoFimPeriodo->copy()->addDay()->startOfDay()
                : $inicioLocacao->copy();

            $fimReferencia = now()->endOfDay();
            $valorPendente = $this->calcularValorMedicaoPeriodoLocacao($locacao, $inicioPendente, $fimReferencia);

            if ($valorPendente > 0) {
                return response()->json([
                    'success' => false,
                    'requires_faturamento' => true,
                    'message' => sprintf(
                        'Existe período pendente de faturamento (%s até %s). Fature antes de finalizar o contrato.',
                        $inicioPendente->format('d/m/Y'),
                        $fimReferencia->format('d/m/Y')
                    ),
                    'pendencia' => [
                        'inicio' => $inicioPendente->format('Y-m-d'),
                        'fim' => $fimReferencia->format('Y-m-d'),
                        'valor' => (float) $valorPendente,
                    ],
                ], 422);
            }

            $locacao->status = 'medicao_finalizada';
            $locacao->save();
            ActionLogger::log($locacao, 'medicao_finalizada');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrato de medição finalizado com sucesso.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function itensRenovacao($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::query()
                ->where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['produtos.produto', 'produtos.patrimonio'])
                ->first();

            if (!$locacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Locação não encontrada.',
                ], 404);
            }

            if (!in_array((string) $locacao->status, ['aprovado', 'atrasada', 'retirada', 'em_andamento'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Somente contratos ativos/vencidos podem ser renovados.',
                ], 422);
            }

            $porHora = $this->ehLocacaoPorHoraLocacao($locacao);
            $dataInicioAtual = optional($locacao->data_inicio)->format('Y-m-d') ?: now()->toDateString();
            $dataFimAtual = optional($locacao->data_fim)->format('Y-m-d') ?: now()->toDateString();
            $inicioAtual = Carbon::parse($dataInicioAtual . ' ' . ($locacao->hora_inicio ?: '00:00:00'));
            $fimAtual = Carbon::parse($dataFimAtual . ' ' . ($locacao->hora_fim ?: '23:59:59'));

            $inicioPadrao = $porHora
                ? $fimAtual->copy()
                : $fimAtual->copy()->addDay()->setTimeFromTimeString((string) ($locacao->hora_inicio ?: '00:00:00'));

            if ($porHora) {
                $inicioAtualContrato = Carbon::parse(
                    (optional($locacao->data_inicio)->format('Y-m-d') ?: $dataFimAtual)
                    . ' '
                    . ((string) ($locacao->hora_inicio ?: '00:00:00'))
                );
                $duracao = max(1, (int) ceil($inicioAtualContrato->diffInMinutes($fimAtual) / 60));
            } else {
                $duracao = max(1, (int) ($locacao->quantidade_dias ?? 1));
            }

            $fimPadrao = $porHora
                ? $inicioPadrao->copy()->addHours($duracao)
                : $inicioPadrao->copy()->addDays($duracao - 1)->setTimeFromTimeString((string) ($locacao->hora_fim ?: '23:59:59'));

            $itens = ($locacao->produtos ?? collect())
                ->map(function ($item) use ($inicioPadrao, $fimPadrao, $inicioAtual, $locacao, $porHora, $fimAtual) {
                    $retornado = (int) ($item->estoque_status ?? 0) === 2
                        || !in_array($item->status_retorno, [null, '', 'pendente'], true);

                    $dataInicioItemAtual = optional($item->data_inicio)->format('Y-m-d')
                        ?: optional($locacao->data_inicio)->format('Y-m-d')
                        ?: $inicioAtual->toDateString();
                    $horaInicioItemAtual = (string) ($item->hora_inicio ?: $locacao->hora_inicio ?: '00:00:00');
                    $dataFimItemAtual = optional($item->data_fim)->format('Y-m-d')
                        ?: optional($locacao->data_fim)->format('Y-m-d')
                        ?: $inicioAtual->toDateString();
                    $horaFimItemAtual = (string) ($item->hora_fim ?: $locacao->hora_fim ?: '23:59:59');

                    $inicioItemAtual = Carbon::parse($dataInicioItemAtual . ' ' . $horaInicioItemAtual);
                    $fimItemAtual = Carbon::parse($dataFimItemAtual . ' ' . $horaFimItemAtual);

                    if ($fimItemAtual->lt($inicioItemAtual)) {
                        $fimItemAtual = $inicioItemAtual->copy();
                    }

                    $offsetMinutos = max(0, $inicioAtual->diffInMinutes($inicioItemAtual, false));
                    $duracaoMinutos = $porHora
                        ? max(1, $inicioAtual->diffInMinutes($fimAtual))
                        : max(1, $inicioItemAtual->diffInMinutes($fimItemAtual));

                    $inicioItemNovo = $inicioPadrao->copy()->addMinutes($offsetMinutos);
                    $fimItemNovo = $inicioItemNovo->copy()->addMinutes($duracaoMinutos);

                    if ($fimItemNovo->gt($fimPadrao)) {
                        $fimItemNovo = $fimPadrao->copy();
                        if ($inicioItemNovo->gt($fimItemNovo)) {
                            $inicioItemNovo = $fimPadrao->copy()->subMinutes(max(1, $duracaoMinutos));
                        }
                        if ($inicioItemNovo->lt($inicioPadrao)) {
                            $inicioItemNovo = $inicioPadrao->copy();
                        }
                    }

                    return [
                        'id_produto_locacao' => (int) $item->id_produto_locacao,
                        'nome' => $item->produto->nome ?? 'Produto',
                        'patrimonio' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                        'id_patrimonio' => $item->id_patrimonio,
                        'quantidade' => (int) max(1, $item->quantidade ?? 1),
                        'data_inicio' => $inicioItemNovo->toDateString(),
                        'hora_inicio' => $inicioItemNovo->format('H:i:s'),
                        'data_fim' => $fimItemNovo->toDateString(),
                        'hora_fim' => $fimItemNovo->format('H:i:s'),
                        'retornado' => $retornado,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'locacao' => [
                    'id_locacao' => (int) $locacao->id_locacao,
                    'numero_contrato' => $locacao->numero_contrato,
                    'aditivo' => (int) ($locacao->aditivo ?? 1),
                    'renovacao_automatica' => (bool) ($locacao->renovacao_automatica ?? false),
                ],
                'periodo_padrao' => [
                    'data_inicio' => $inicioPadrao->toDateString(),
                    'hora_inicio' => $inicioPadrao->format('H:i:s'),
                    'data_fim' => $fimPadrao->toDateString(),
                    'hora_fim' => $fimPadrao->format('H:i:s'),
                ],
                'itens' => $itens,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function renovarAditivo(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? Auth::id();

            $locacao = Locacao::query()
                ->where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['produtos.produto', 'produtos.patrimonio'])
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            $itens = collect($request->input('itens', []))
                ->map(function ($item) {
                    return [
                        'id_produto_locacao' => (int) ($item['id_produto_locacao'] ?? 0),
                        'quantidade' => (int) max(1, $item['quantidade'] ?? 1),
                        'data_inicio' => $item['data_inicio'] ?? null,
                        'hora_inicio' => $item['hora_inicio'] ?? null,
                        'data_fim' => $item['data_fim'] ?? null,
                        'hora_fim' => $item['hora_fim'] ?? null,
                    ];
                })
                ->filter(fn ($item) => (int) $item['id_produto_locacao'] > 0)
                ->values();

            $novaLocacao = $this->locacaoRenovacaoService->renovarManual(
                $locacao,
                [
                    'data_inicio' => $request->input('data_inicio'),
                    'hora_inicio' => $request->input('hora_inicio', '00:00:00'),
                    'data_fim' => $request->input('data_fim'),
                    'hora_fim' => $request->input('hora_fim', '23:59:59'),
                    'renovacao_automatica' => $request->boolean('renovacao_automatica'),
                    'itens' => $itens,
                ],
                $idUsuario
            );

            ActionLogger::log($novaLocacao, 'renovacao');
            ActionLogger::log($novaLocacao, 'aditivo_gerado');

            return response()->json([
                'success' => true,
                'message' => 'Renovação criada com sucesso.',
                'locacao' => [
                    'id_locacao' => (int) $novaLocacao->id_locacao,
                    'numero_contrato' => $novaLocacao->numero_contrato,
                    'aditivo' => (int) ($novaLocacao->aditivo ?? 1),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function locacaoPossuiFaturamento(int $idEmpresa, int $idLocacao): bool
    {
        if (!Schema::hasTable('faturamento_locacoes')) {
            return false;
        }

        return FaturamentoLocacao::where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->exists();
    }

    private function aplicarRegraValorRetornoParcialItem(
        LocacaoProduto $item,
        int $periodoUtilizado,
        int $periodoOriginal,
        bool $naoRecalcularValor
    ): void {
        $periodoOriginal = max(1, $periodoOriginal);

        if ($naoRecalcularValor) {
            if (!(bool) ($item->valor_fechado ?? false)) {
                $item->preco_unitario = round((float) ($item->preco_unitario ?? 0) * $periodoOriginal, 2);
                $item->valor_fechado = 1;
                if (!empty($item->tipo_cobranca)) {
                    $item->tipo_cobranca = 'fechado';
                }
            }
            return;
        }

        if (!(bool) ($item->valor_fechado ?? false)) {
            return;
        }

        $quantidade = max(1, (int) ($item->quantidade ?? 1));
        $valorOriginalTotal = (float) ($item->preco_unitario ?? 0) * $quantidade;
        $valorDiaria = $periodoOriginal > 0 ? ($valorOriginalTotal / $periodoOriginal) : $valorOriginalTotal;
        $novoValorTotal = round(max(0, $valorDiaria * max(1, $periodoUtilizado)), 2);

        $item->preco_unitario = round($novoValorTotal / $quantidade, 2);
    }

    /**
     * Buscar produtos disponíveis para locação
     */
    public function produtosDisponiveis(Request $request)
    {
        try {
            \Log::info('[PRODUTOS-DISPONIVEIS] Iniciando requisição');
            
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $termo = $request->input('q', '');
            
            \Log::info('[PRODUTOS-DISPONIVEIS] ID Empresa:', ['id_empresa' => $idEmpresa]);

            // Busca TODOS os produtos ativos (independente de estoque)
            $query = Produto::where('id_empresa', $idEmpresa)
                ->where('status', 'ativo');
            
            // Se houver termo de busca, aplica filtro
            if (!empty($termo)) {
                $query->where(function($q) use ($termo) {
                    $q->where('nome', 'like', "%{$termo}%")
                      ->orWhere('codigo', 'like', "%{$termo}%");
                });
            }

            $produtos = $query->with(['tabelasPreco', 'patrimonios' => function($q) {
                    // Somente patrimônios disponíveis
                    $q->where('status', 'Ativo')
                      ->where('status_locacao', 'Disponivel');
                }])
                ->orderBy('nome')
                ->get();

            \Log::info('[PRODUTOS-DISPONIVEIS] Total de produtos encontrados:', ['count' => $produtos->count()]);

            $result = [];
            foreach ($produtos as $produto) {
                // Quantidade de patrimônios disponíveis
                $qtdPatrimoniosDisponiveis = $produto->patrimonios ? $produto->patrimonios->count() : 0;
                
                // Se tiver patrimônios, usa a contagem deles. Senão, usa quantidade do produto
                $quantidadeDisponivel = $qtdPatrimoniosDisponiveis > 0 ? $qtdPatrimoniosDisponiveis : ($produto->quantidade ?? 0);
                
                // Pegar preço da primeira tabela ativa se houver
                $precoDiaria = 0;
                if ($produto->tabelasPreco && $produto->tabelasPreco->count() > 0) {
                    $tabela = $produto->tabelasPreco->first();
                    $precoDiaria = $tabela->d1 ?? 0;
                } else {
                    $precoDiaria = $produto->preco_locacao ?? $produto->preco_venda ?? 0;
                }
                
                // Montar patrimônios disponíveis para o select
                $patrimoniosDisponiveis = [];
                if ($produto->patrimonios) {
                    foreach ($produto->patrimonios as $pat) {
                        $patrimoniosDisponiveis[] = [
                            'id_patrimonio' => $pat->id_patrimonio,
                            'numero_serie' => $pat->codigo_patrimonio ?: $pat->numero_serie ?: ('PAT-' . $pat->id_patrimonio)
                        ];
                    }
                }
                
                // Montar tabelas de preços com todos os valores disponíveis
                $tabelasPreco = [];
                if ($produto->tabelasPreco) {
                    foreach ($produto->tabelasPreco as $tabela) {
                        $tabelasPreco[] = [
                            'id_tabela' => $tabela->id_tabela,
                            'nome' => $tabela->nome,
                            'd1' => floatval($tabela->d1 ?? 0),
                            'd2' => floatval($tabela->d2 ?? 0),
                            'd3' => floatval($tabela->d3 ?? 0),
                            'd4' => floatval($tabela->d4 ?? 0),
                            'd5' => floatval($tabela->d5 ?? 0),
                            'd6' => floatval($tabela->d6 ?? 0),
                            'd7' => floatval($tabela->d7 ?? 0),
                            'd15' => floatval($tabela->d15 ?? 0),
                            'd30' => floatval($tabela->d30 ?? 0),
                            'd60' => floatval($tabela->d60 ?? 0),
                            'd90' => floatval($tabela->d90 ?? 0),
                            'd120' => floatval($tabela->d120 ?? 0),
                            'd360' => floatval($tabela->d360 ?? 0),
                        ];
                    }
                }
                
                $result[] = [
                    'id_produto' => $produto->id_produto,
                    'nome' => $produto->nome,
                    'codigo' => $produto->codigo,
                    'foto_url' => $produto->foto_url,
                    'quantidade' => $produto->quantidade ?? 0,
                    'quantidade_disponivel' => $quantidadeDisponivel,
                    'preco_diaria' => floatval($precoDiaria),
                    'preco_venda' => floatval($produto->preco_venda ?? 0),
                    'preco_locacao' => floatval($produto->preco_locacao ?? 0),
                    'patrimonios' => $patrimoniosDisponiveis,
                    'tabelas_preco' => $tabelasPreco
                ];
            }

            \Log::info('[PRODUTOS-DISPONIVEIS] Retornando produtos:', ['total' => count($result), 'primeiros' => array_slice($result, 0, 2)]);
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('[PRODUTOS-DISPONIVEIS] ERRO:', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar clientes
     */
    public function buscarClientes(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $termo = $request->input('q', '');

        $query = Cliente::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo');
        
        // Se houver termo de busca, aplica filtro
        if (!empty($termo)) {
            $query->where(function($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('razao_social', 'like', "%{$termo}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$termo}%");
            });
        }

        $clientes = $query->orderBy('nome')
            ->limit(50)
            ->get();

        return response()->json($clientes);
    }

    /**
     * Parse decimal value
     */
    private function parseDecimal($value)
    {
        if (empty($value)) return 0;
        if (is_numeric($value)) return floatval($value);
        
        $value = str_replace(['R$', ' ', '.'], '', $value);
        $value = str_replace(',', '.', $value);
        return floatval($value);
    }

    private function montarEnderecoEntregaCliente(?Cliente $cliente): string
    {
        if (!$cliente) {
            return '';
        }

        $partesEntrega = [];
        if (!empty($cliente->endereco_entrega)) $partesEntrega[] = (string) $cliente->endereco_entrega;
        if (!empty($cliente->numero_entrega)) $partesEntrega[] = (string) $cliente->numero_entrega;
        if (!empty($cliente->complemento_entrega)) $partesEntrega[] = (string) $cliente->complemento_entrega;
        if (!empty($cliente->cep_entrega)) $partesEntrega[] = 'CEP ' . (string) $cliente->cep_entrega;

        if (!empty($partesEntrega)) {
            return implode(', ', $partesEntrega);
        }

        $partesPrincipal = [];
        if (!empty($cliente->endereco)) $partesPrincipal[] = (string) $cliente->endereco;
        if (!empty($cliente->numero)) $partesPrincipal[] = (string) $cliente->numero;
        if (!empty($cliente->complemento)) $partesPrincipal[] = (string) $cliente->complemento;
        if (!empty($cliente->bairro)) $partesPrincipal[] = (string) $cliente->bairro;
        if (!empty($cliente->cep)) $partesPrincipal[] = 'CEP ' . (string) $cliente->cep;

        return implode(', ', $partesPrincipal);
    }

    private function gerarFaturamentoLocacaoEncerrada(
        Locacao $locacao,
        int $idEmpresa,
        ?int $idUsuario = null,
        ?string $statusOrigem = null
    ): void
    {
        if ($locacao->status !== 'encerrado') {
            return;
        }

        $statusOrigemNormalizado = $statusOrigem !== null ? strtolower(trim($statusOrigem)) : null;
        if ($statusOrigemNormalizado !== null && !in_array($statusOrigemNormalizado, ['aprovado', 'encerrado'], true)) {
            return;
        }

        if (!Schema::hasTable('faturamento_locacoes')) {
            Log::warning('Tabela faturamento_locacoes não encontrada. Execute as migrations para habilitar faturamento de locações.');
            return;
        }

        $faturamentoExistente = FaturamentoLocacao::withTrashed()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->first();

        if ($faturamentoExistente) {
            return;
        }

        $locacao->loadMissing([
            'produtos:id_produto_locacao,id_locacao,quantidade,preco_unitario,valor_fechado,data_inicio,data_fim',
            'produtosTerceiros:id_produto_terceiros_locacao,id_locacao,quantidade,preco_unitario,valor_fechado',
            'servicos:id_locacao_servico,id_locacao,quantidade,preco_unitario,valor_total',
            'despesas:id_locacao_despesa,id_locacao,valor',
        ]);

        $valorFaturado = (float) ($locacao->valor_final ?? 0);
        if ($valorFaturado <= 0) {
            $valorFaturado = $this->calcularValorTotalListagem($locacao);
        }

        if ($valorFaturado <= 0) {
            return;
        }

        $descricao = sprintf(
            'Faturamento Locação #%s',
            $locacao->numero_contrato ?: $locacao->id_locacao
        );

        $vencimento = $locacao->vencimento ?: now()->toDateString();

        $categoriaReceita = CategoriaContas::where('id_empresa', $idEmpresa)
            ->where('tipo', 'receita')
            ->whereRaw('LOWER(nome) in (?, ?)', ['faturamento de locações', 'faturamento de locacoes'])
            ->first();

        if (!$categoriaReceita) {
            $categoriaReceita = CategoriaContas::create([
                'id_empresa' => $idEmpresa,
                'nome' => 'Faturamento de Locações',
                'tipo' => 'receita',
                'descricao' => 'Categoria automática para faturamento de contratos encerrados de locação',
            ]);
        }

        $contaReceber = ContasAReceber::where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('descricao', $descricao)
            ->first();

        if (!$contaReceber) {
            $idUsuarioEfetivo = $idUsuario
                ?? (Auth::user()->id_usuario ?? null)
                ?? (Auth::id() ? (int) Auth::id() : null)
                ?? ($locacao->id_usuario ? (int) $locacao->id_usuario : null);

            $dadosContaReceber = [
                'id_empresa' => $idEmpresa,
                'id_clientes' => $locacao->id_cliente,
                'id_locacao' => $locacao->id_locacao,
                'id_categoria_contas' => $categoriaReceita->id_categoria_contas,
                'descricao' => $descricao,
                'valor_total' => $valorFaturado,
                'valor_pago' => 0,
                'juros' => 0,
                'multa' => 0,
                'desconto' => 0,
                'data_emissao' => now()->toDateString(),
                'data_vencimento' => $vencimento,
                'status' => 'pendente',
                'observacoes' => 'Gerado automaticamente no encerramento da locação.',
            ];

            if (Schema::hasColumn('contas_a_receber', 'id_usuario')) {
                $dadosContaReceber['id_usuario'] = $idUsuarioEfetivo;
            }

            $contaReceber = ContasAReceber::create($dadosContaReceber);
        }

        $idUsuarioEfetivoFaturamento = $idUsuario
            ?? (Auth::user()->id_usuario ?? null)
            ?? (Auth::id() ? (int) Auth::id() : null)
            ?? ($locacao->id_usuario ? (int) $locacao->id_usuario : null)
            ?? ($contaReceber->id_usuario ?? null);

        // Gerar próximo número de fatura para a empresa
        $numeroFatura = FaturamentoLocacao::gerarProximoNumeroFatura($idEmpresa);

        $dadosFaturamento = [
            'id_empresa' => $idEmpresa,
            'id_locacao' => $locacao->id_locacao,
            'id_cliente' => $locacao->id_cliente,
            'numero_fatura' => $numeroFatura,
            'id_conta_receber' => $contaReceber->id_contas,
            'descricao' => $descricao,
            'valor_total' => $valorFaturado,
            'data_faturamento' => now()->toDateString(),
            'data_vencimento' => $vencimento,
            'status' => 'faturado',
            'origem' => 'encerramento_locacao',
            'observacoes' => 'Registro de faturamento gerado automaticamente para integração com o Financeiro.',
        ];

        if (Schema::hasColumn('faturamento_locacoes', 'id_usuario')) {
            if (empty($idUsuarioEfetivoFaturamento)) {
                throw new \Exception('Não foi possível identificar o usuário responsável para gerar o faturamento da locação.');
            }

            $dadosFaturamento['id_usuario'] = (int) $idUsuarioEfetivoFaturamento;
        }

        try {
            FaturamentoLocacao::create($dadosFaturamento);
        } catch (QueryException $e) {
            $mensagem = mb_strtolower((string) $e->getMessage());
            $duplicado = ((string) $e->getCode() === '23000')
                && (str_contains($mensagem, 'duplicate entry') || str_contains($mensagem, 'uq_faturamento_locacao_empresa_locacao'));

            if ($duplicado) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Criar conta a pagar para produto de terceiro
     */
    private function criarContaPagarTerceiro($idEmpresa, $locacao, $item, $custoFornecedor, $quantidade, $dias)
    {
        $valorTotal = $custoFornecedor * $quantidade;
        if (!($item['custo_valor_fechado'] ?? false)) {
            $valorTotal *= $dias;
        }

        $dataVencimento = $item['data_vencimento_fornecedor'] ?? $locacao->data_inicio;
        $totalParcelas = intval($item['parcelas_fornecedor'] ?? 1);
        $idParcelamento = null;

        // Se for parcelado, criar grupo de parcelamento
        if ($totalParcelas > 1) {
            $idParcelamento = uniqid('PARC-');
        }

        $contasCriadas = [];
        $valorParcela = $valorTotal / $totalParcelas;

        for ($i = 1; $i <= $totalParcelas; $i++) {
            $dataVenc = new \DateTime($dataVencimento);
            $dataVenc->modify('+' . (($i - 1) * 30) . ' days');

            $produto = Produto::where('id_empresa', $idEmpresa)->find($item['id_produto']); // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            $nomeProduto = $produto ? $produto->nome : 'Produto de terceiro';

            $conta = ContasAPagar::create($this->montarDadosContaPagar([
                'id_empresa' => $idEmpresa,
                'id_fornecedores' => $item['id_fornecedor'],
                'id_locacao' => $locacao->id_locacao,
                'descricao' => "Locação #{$locacao->numero_contrato} - {$nomeProduto}",
                'valor_total' => $valorParcela,
                'data_emissao' => now(),
                'data_vencimento' => $dataVenc->format('Y-m-d'),
                'status' => 'pendente',
                'numero_parcela' => $i,
                'total_parcelas' => $totalParcelas,
                'id_parcelamento' => $idParcelamento,
                'origem' => 'locacao_terceiro',
                'observacoes' => "Gerado automaticamente pela locação #{$locacao->numero_contrato}",
            ]));

            $contasCriadas[] = $conta;
        }

        return $contasCriadas[0] ?? null;
    }

    /**
     * Verificar disponibilidade de estoque em tempo real
     */
    public function verificarDisponibilidade(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $preferenciaEstoque = $this->validarPreferenciaEstoque($request->input('preferencia_estoque'));
            
            $disponibilidade = $this->estoqueService->calcularDisponibilidade(
                $request->id_produto,
                $idEmpresa,
                $request->data_inicio,
                $request->data_fim,
                $request->hora_inicio,
                $request->hora_fim,
                $request->excluir_locacao,
                $preferenciaEstoque
            );

            return response()->json($disponibilidade);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar produtos disponíveis com estoque calculado para o período
     */
    public function produtosDisponiveisPeriodo(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $dataInicio = $request->input('data_inicio', date('Y-m-d'));
            $dataFim = $request->input('data_fim', date('Y-m-d'));
            $horaInicio = $request->input('hora_inicio');
            $horaFim = $request->input('hora_fim');
            $excluirLocacao = $request->input('excluir_locacao');
            $preferenciaEstoque = $this->validarPreferenciaEstoque($request->input('preferencia_estoque'));

            $produtos = $this->estoqueService->getProdutosDisponiveis(
                $idEmpresa,
                $dataInicio,
                $dataFim,
                $horaInicio,
                $horaFim,
                $excluirLocacao,
                $preferenciaEstoque
            );

            return response()->json($produtos);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar produtos disponíveis: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar retorno de patrimônios (modal de finalização)
     */
    public function registrarRetornoPatrimonios(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::user()->id_usuario ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$locacao) {
                throw new \Exception('Locação não encontrada.');
            }

            $retornos = $request->input('retornos', []);

            foreach ($retornos as $retorno) {
                $produtoLocacao = LocacaoProduto::where('id_empresa', $idEmpresa)->find($retorno['id_produto_locacao']); // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                
                if (!$produtoLocacao || !$produtoLocacao->id_patrimonio) {
                    continue;
                }

                $statusRetornoInformado = $retorno['status'] ?? 'devolvido';
                if (!in_array($statusRetornoInformado, ['devolvido', 'normal', 'avariado', 'extraviado'], true)) {
                    $statusRetornoInformado = 'normal';
                }

                $statusRetornoPatrimonio = $statusRetornoInformado === 'devolvido'
                    ? 'normal'
                    : $statusRetornoInformado;

                // Registrar retorno
                LocacaoRetornoPatrimonio::create([
                    'id_empresa' => $idEmpresa,
                    'id_locacao' => $id,
                    'id_produto_locacao' => $retorno['id_produto_locacao'],
                    'id_patrimonio' => $produtoLocacao->id_patrimonio,
                    'data_retorno' => now(),
                    'status_retorno' => $statusRetornoPatrimonio,
                    'observacoes_retorno' => $retorno['observacoes'] ?? null,
                    'id_usuario' => $idUsuario,
                ]);

                // Usar o serviço de estoque para registrar retorno
                $this->estoqueService->registrarRetornoLocacao(
                    $produtoLocacao,
                    $statusRetornoInformado,
                    $retorno['observacoes'] ?? null,
                    $idUsuario
                );

                if ((int) ($produtoLocacao->estoque_status ?? 0) === 1) {
                    $produtoLocacao->estoque_status = 2;
                    $produtoLocacao->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retorno de patrimônios registrado com sucesso.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter patrimônios pendentes de retorno
     */
    public function patrimoniosPendentes($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$locacao) {
                return response()->json(['error' => 'Locação não encontrada.'], 404);
            }

            $pendentes = $locacao->getPatrimoniosPendentes();

            return response()->json([
                'success' => true,
                'total' => $pendentes->count(),
                'patrimonios' => $pendentes->map(function ($item) {
                    return [
                        'id_produto_locacao' => $item->id_produto_locacao,
                        'id_patrimonio' => $item->id_patrimonio,
                        'id_produto' => $item->id_produto,
                        'produto_nome' => $item->produto->nome ?? 'Produto',
                        'numero_serie' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? null,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Gerar PDF do contrato
     */
    public function gerarContratoPdf($id, Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $locacao = Locacao::where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with([
                    'cliente',
                    'usuario',
                    'produtos.produto',
                    'produtos.patrimonio',
                    'produtos.sala',
                    'produtos.fornecedor',
                    'produtosTerceiros.fornecedor',
                    'produtosTerceiros.produtoTerceiro',
                    'produtosTerceiros.sala',
                    'servicos',
                    'despesas',
                    'salas',
                    'empresa',
                    'assinaturaDigital'
                ])
                ->first();

            if (!$locacao) {
                abort(404);
            }

            $tipo = strtolower((string) $request->input('tipo', 'contrato'));
            $tiposPermitidos = ['contrato', 'orcamento', 'checklist', 'romaneio', 'entrega', 'recibo', 'medicao'];
            if (!in_array($tipo, $tiposPermitidos, true)) {
                $tipo = 'contrato';
            }

            $idModelo = $request->input('id_modelo');
            $formato = $request->input('formato', 'pdf');
            $imprimirComFoto = $request->boolean('com_foto', false);
            $empresa = $locacao->empresa;
            $corPrimariaDocumento = $this->obterCorPrimariaModeloPadrao((int) $idEmpresa);
            $this->normalizarLogoEmpresa($empresa);
            $logoEmpresaPdfSrc = $this->resolverLogoEmpresaParaPdf($empresa);
            $nomeArquivo = $tipo . '-' . $locacao->numero_contrato . '.pdf';
            $assinaturaClientePdfSrc = $this->resolverAssinaturaClienteParaPdf(
                $locacao->assinaturaDigital->assinatura_cliente_url ?? null
            );
            $assinaturaLocadoraPdfSrc = $this->resolverAssinaturaLocadoraParaPdf(
                (int) $idEmpresa,
                !empty($idModelo) ? (int) $idModelo : null
            );
            $responsavelContrato = trim((string) (
                $locacao->responsavel
                ?? $locacao->usuario->name
                ?? $locacao->usuario->nome
                ?? '-'
            ));

            $viewsPorTipo = [
                'contrato' => 'locacoes.documentos.contrato',
                'orcamento' => 'locacoes.documentos.orcamento',
                'checklist' => 'locacoes.documentos.checklist',
                'romaneio' => 'locacoes.documentos.romaneio',
                'entrega' => 'locacoes.documentos.entrega',
                'recibo' => 'locacoes.documentos.recibo',
                'medicao' => 'locacoes.documentos.medicao',
            ];

            $viewDocumento = $viewsPorTipo[$tipo] ?? $viewsPorTipo['contrato'];
            $modeloContratoMedicao = null;
            $modeloDocumentoOrcamento = null;
            $assinaturaClientePdfSrc = $assinaturaClientePdfSrc;

            if ($tipo === 'medicao') {
                $modeloContratoMedicao = $this->consultarModelosDocumento((int) $idEmpresa, 'medicao')
                    ->when(!empty($idModelo), function ($query) use ($idModelo) {
                        $query->where('id_modelo', (int) $idModelo);
                    })
                    ->orderBy('padrao', 'desc')
                    ->orderBy('nome')
                    ->first();

                $assinaturaClientePdfSrc = $this->resolverAssinaturaClienteParaPdf(
                    $locacao->assinaturaDigital->assinatura_cliente_url ?? null
                );
            }

            if ($tipo === 'orcamento') {
                if (!empty($idModelo)) {
                    $modeloDocumentoOrcamento = LocacaoModeloContrato::query()
                        ->where('id_empresa', (int) $idEmpresa)
                        ->where('ativo', true)
                        ->where('id_modelo', (int) $idModelo)
                        ->first();
                }

                if (!$modeloDocumentoOrcamento) {
                    $modeloDocumentoOrcamento = $this->consultarModelosDocumento((int) $idEmpresa, 'orcamento')
                        ->orderBy('padrao', 'desc')
                        ->orderBy('nome')
                        ->first();
                }

                $assinaturaLocadoraPdfSrc = $this->resolverAssinaturaLocadoraParaPdf(
                    (int) $idEmpresa,
                    $modeloDocumentoOrcamento?->id_modelo ? (int) $modeloDocumentoOrcamento->id_modelo : null
                );
            }

            // Preview HTML explícito (somente quando solicitado)
            if ($formato === 'html') {
                if ($tipo === 'contrato') {
                    $idModeloContrato = !empty($idModelo) ? (int) $idModelo : null;
                    $htmlContrato = $this->contratoPdfService->gerarHtml($locacao, $idModeloContrato);
                    return response($htmlContrato)->header('Content-Type', 'text/html; charset=UTF-8');
                }

                return view($viewDocumento, compact('locacao', 'empresa', 'tipo', 'corPrimariaDocumento', 'modeloContratoMedicao', 'modeloDocumentoOrcamento', 'assinaturaClientePdfSrc', 'assinaturaLocadoraPdfSrc', 'responsavelContrato', 'imprimirComFoto', 'logoEmpresaPdfSrc'));
            }

            // Sempre gerar PDF real (sem interface HTML de impressão)
            if ($tipo === 'contrato') {
                $idModeloContrato = !empty($idModelo) ? (int) $idModelo : null;
                $htmlContrato = $this->contratoPdfService->gerarHtml($locacao, $idModeloContrato);
                $pdf = Pdf::loadHTML($htmlContrato)->setPaper('a4', 'portrait');
            } else {
                $orientacao = 'portrait';
                $pdf = Pdf::loadView($viewDocumento, compact('locacao', 'empresa', 'tipo', 'corPrimariaDocumento', 'modeloContratoMedicao', 'modeloDocumentoOrcamento', 'assinaturaClientePdfSrc', 'assinaturaLocadoraPdfSrc', 'responsavelContrato', 'imprimirComFoto', 'logoEmpresaPdfSrc'))
                    ->setOptions([
                        'isRemoteEnabled' => true,
                        'isHtml5ParserEnabled' => true,
                    ])
                    ->setPaper('a4', $orientacao);
            }

            if ($request->input('download')) {
                return $pdf->download($nomeArquivo);
            }
            
            return $pdf->stream($nomeArquivo);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar PDF do contrato: ' . $e->getMessage());
            
            // Em caso de erro, tenta retornar a view simples
            try {
                $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
                $locacao = Locacao::where('id_locacao', $id)
                    ->where('id_empresa', $idEmpresa)
                    ->with(['cliente', 'usuario', 'produtos.produto', 'produtos.patrimonio', 'produtos.sala', 'servicos', 'empresa', 'assinaturaDigital'])
                    ->first();
                
                if ($locacao) {
                    $empresa = $locacao->empresa;
                    $this->normalizarLogoEmpresa($empresa);
                    $logoEmpresaPdfSrc = $this->resolverLogoEmpresaParaPdf($empresa);
                    $tipo = strtolower((string) $request->input('tipo', 'contrato'));
                    $nomeArquivo = $tipo . '-' . $locacao->numero_contrato . '.pdf';
                    $imprimirComFoto = $request->boolean('com_foto', false);
                    $viewsPorTipo = [
                        'contrato' => 'locacoes.documentos.contrato',
                        'orcamento' => 'locacoes.documentos.orcamento',
                        'checklist' => 'locacoes.documentos.checklist',
                        'romaneio' => 'locacoes.documentos.romaneio',
                        'entrega' => 'locacoes.documentos.entrega',
                        'recibo' => 'locacoes.documentos.recibo',
                        'medicao' => 'locacoes.documentos.medicao',
                    ];
                    $viewDocumento = $viewsPorTipo[$tipo] ?? $viewsPorTipo['contrato'];
                    $modeloContratoMedicao = null;
                    $modeloDocumentoOrcamento = null;
                    $assinaturaClientePdfSrc = $this->resolverAssinaturaClienteParaPdf(
                        $locacao->assinaturaDigital->assinatura_cliente_url ?? null
                    );
                    $assinaturaLocadoraPdfSrc = $this->resolverAssinaturaLocadoraParaPdf(
                        (int) $idEmpresa,
                        $request->filled('id_modelo') ? (int) $request->input('id_modelo') : null
                    );
                    $responsavelContrato = trim((string) (
                        $locacao->responsavel
                        ?? $locacao->usuario->name
                        ?? $locacao->usuario->nome
                        ?? '-'
                    ));
                    if ($tipo === 'medicao') {
                        $modeloContratoMedicao = $this->consultarModelosDocumento((int) $idEmpresa, 'medicao')
                            ->when($request->filled('id_modelo'), function ($query) use ($request) {
                                $query->where('id_modelo', (int) $request->input('id_modelo'));
                            })
                            ->orderBy('padrao', 'desc')
                            ->orderBy('nome')
                            ->first();

                        $assinaturaClientePdfSrc = $this->resolverAssinaturaClienteParaPdf(
                            $locacao->assinaturaDigital->assinatura_cliente_url ?? null
                        );
                    }

                    if ($tipo === 'orcamento') {
                        $idModeloOrcamento = $request->filled('id_modelo') ? (int) $request->input('id_modelo') : null;

                        if ($idModeloOrcamento) {
                            $modeloDocumentoOrcamento = LocacaoModeloContrato::query()
                                ->where('id_empresa', (int) $idEmpresa)
                                ->where('ativo', true)
                                ->where('id_modelo', $idModeloOrcamento)
                                ->first();
                        }

                        if (!$modeloDocumentoOrcamento) {
                            $modeloDocumentoOrcamento = $this->consultarModelosDocumento((int) $idEmpresa, 'orcamento')
                                ->orderBy('padrao', 'desc')
                                ->orderBy('nome')
                                ->first();
                        }

                        $assinaturaLocadoraPdfSrc = $this->resolverAssinaturaLocadoraParaPdf(
                            (int) $idEmpresa,
                            $modeloDocumentoOrcamento?->id_modelo ? (int) $modeloDocumentoOrcamento->id_modelo : null
                        );
                    }
                    if ($tipo === 'contrato') {
                        $idModeloContrato = $request->filled('id_modelo') ? (int) $request->input('id_modelo') : null;
                        $htmlContrato = $this->contratoPdfService->gerarHtml($locacao, $idModeloContrato);
                        $pdf = Pdf::loadHTML($htmlContrato)->setPaper('a4', 'portrait');
                    } else {
                        $orientacao = 'portrait';
                        $corPrimariaDocumento = $this->obterCorPrimariaModeloPadrao((int) $idEmpresa);
                        $pdf = Pdf::loadView($viewDocumento, compact('locacao', 'empresa', 'tipo', 'corPrimariaDocumento', 'modeloContratoMedicao', 'modeloDocumentoOrcamento', 'assinaturaClientePdfSrc', 'assinaturaLocadoraPdfSrc', 'responsavelContrato', 'imprimirComFoto', 'logoEmpresaPdfSrc'))
                            ->setOptions([
                                'isRemoteEnabled' => true,
                                'isHtml5ParserEnabled' => true,
                            ])
                            ->setPaper('a4', $orientacao);
                    }

                    if ($request->boolean('download')) {
                        return $pdf->download($nomeArquivo);
                    }

                    return $pdf->stream($nomeArquivo);
                }
            } catch (\Exception $e2) {
                // ignore
            }
            
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enviarAssinaturaDigital($id, Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $tipoDocumento = strtolower((string) $request->input('tipo', ''));
            if (!in_array($tipoDocumento, ['contrato', 'medicao'], true)) {
                $tipoDocumento = 'contrato';
            }

            $locacao = Locacao::query()
                ->where('id_locacao', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['cliente', 'empresa'])
                ->first();

            if (!$locacao) {
                return redirect()->back()->with('error', 'Locação não encontrada.');
            }

            if ($tipoDocumento === 'contrato' && in_array((string) ($locacao->status ?? ''), ['medicao', 'medicao_finalizada'], true)) {
                $tipoDocumento = 'medicao';
            }

            $emailCliente = trim((string) ($locacao->cliente->email ?? ''));
            if ($emailCliente === '') {
                return redirect()->back()->with('error', 'Cliente sem e-mail cadastrado para assinatura digital.');
            }

            $token = Str::lower(Str::random(72));
            $idModelo = $request->filled('id_modelo') ? (int) $request->input('id_modelo') : null;

            $queryAssinaturaModelo = LocacaoAssinaturaDigital::query()
                ->where('id_empresa', (int) $idEmpresa)
                ->where('id_locacao', (int) $locacao->id_locacao)
                ->when($idModelo !== null, function ($query) use ($idModelo) {
                    $query->where('id_modelo', $idModelo);
                }, function ($query) {
                    $query->whereNull('id_modelo');
                });

            $assinaturaAssinada = (clone $queryAssinaturaModelo)
                ->where('status', 'assinado')
                ->whereNotNull('assinatura_cliente_url')
                ->latest('id_assinatura')
                ->first();

            if ($assinaturaAssinada) {
                return redirect()->back()->with('info', 'Este modelo de contrato já foi assinado digitalmente.');
            }

            $assinaturaAtual = (clone $queryAssinaturaModelo)
                ->latest('id_assinatura')
                ->first();

            if ($assinaturaAtual) {
                $assinaturaAtual->update([
                    'id_modelo' => $idModelo,
                    'email_destinatario' => $emailCliente,
                    'token' => $token,
                    'status' => 'pendente',
                    'solicitado_em' => now(),
                    'assinado_em' => null,
                    'assinatura_tipo' => null,
                    'assinatura_cliente_url' => null,
                    'ip_assinatura' => null,
                    'user_agent' => null,
                    'hash_documento' => null,
                    'corpo_contrato_assinado' => null,
                    'assinado_por_nome' => null,
                    'assinado_por_documento' => null,
                ]);
            } else {
                $assinaturaAtual = LocacaoAssinaturaDigital::create([
                    'id_empresa' => (int) $idEmpresa,
                    'id_locacao' => (int) $locacao->id_locacao,
                    'id_cliente' => $locacao->id_cliente,
                    'id_modelo' => $idModelo,
                    'email_destinatario' => $emailCliente,
                    'token' => $token,
                    'status' => 'pendente',
                    'solicitado_em' => now(),
                ]);
            }

            $urlAssinatura = route('locacoes.assinatura-digital.form', [
                'token' => $assinaturaAtual->token,
                'tipo' => $tipoDocumento,
                'id_modelo' => $idModelo,
            ]);
            $urlContrato = route('locacoes.assinatura-digital.contrato', [
                'token' => $assinaturaAtual->token,
                'tipo' => $tipoDocumento,
                'id_modelo' => $idModelo,
            ]);

            Mail::send('emails.locacao-assinatura-digital', [
                'locacao' => $locacao,
                'cliente' => $locacao->cliente,
                'empresaNome' => $this->resolverNomeEmpresaDocumento($locacao->empresa),
                'empresaLogoUrl' => $this->resolverLogoEmpresaEmail($locacao->empresa),
                'urlAssinatura' => $urlAssinatura,
                'urlContrato' => $urlContrato,
            ], function ($message) use ($emailCliente, $locacao) {
                $message->to($emailCliente)
                    ->subject('Assinatura digital do contrato #' . ($locacao->numero_contrato ?? $locacao->id_locacao));
            });

            return redirect()->back()->with('success', 'Solicitação de assinatura digital enviada para o cliente.');
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar assinatura digital.', [
                'id_locacao' => $id,
                'erro' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Não foi possível enviar a assinatura digital.');
        }
    }

    public function formularioAssinaturaDigital(string $token, Request $request)
    {
        $assinatura = LocacaoAssinaturaDigital::query()
            ->where('token', $token)
            ->with(['locacao.cliente'])
            ->first();

        if (!$assinatura) {
            abort(404);
        }

        $tipoDocumento = strtolower((string) $request->query('tipo', ''));
        if (!in_array($tipoDocumento, ['contrato', 'medicao'], true)) {
            $tipoDocumento = in_array((string) ($assinatura->locacao?->status ?? ''), ['medicao', 'medicao_finalizada'], true)
                ? 'medicao'
                : 'contrato';
        }

        $idModeloDocumento = $request->filled('id_modelo')
            ? (int) $request->query('id_modelo')
            : ($assinatura->id_modelo ? (int) $assinatura->id_modelo : null);

        return view('locacoes.assinatura-digital.form', [
            'assinatura' => $assinatura,
            'locacao' => $assinatura->locacao,
            'cliente' => $assinatura->locacao?->cliente,
            'jaAssinado' => $assinatura->status === 'assinado' && !empty($assinatura->assinatura_cliente_url),
            'tipoDocumento' => $tipoDocumento,
            'idModeloDocumento' => $idModeloDocumento,
        ]);
    }

    public function salvarAssinaturaDigital(Request $request, string $token)
    {
        $assinatura = LocacaoAssinaturaDigital::query()
            ->where('token', $token)
            ->with(['locacao.cliente'])
            ->first();

        if (!$assinatura) {
            abort(404);
        }

        if ($assinatura->status === 'assinado' && !empty($assinatura->assinatura_cliente_url)) {
            return redirect()->route('locacoes.assinatura-digital.form', ['token' => $token])
                ->with('info', 'Contrato já assinado digitalmente.');
        }

        $request->validate([
            'assinatura_tipo' => ['required', 'in:desenho,upload,digitada'],
            'assinatura_upload' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'assinatura_desenho' => ['nullable', 'string'],
        ], [
            'assinatura_tipo.required' => 'Selecione o tipo de assinatura.',
        ]);

        $conteudo = null;
        $nomeArquivo = 'assinatura-cliente-locacao-' . $assinatura->id_locacao . '-' . now()->format('YmdHis') . '.png';
        $mimeType = 'image/png';

        if ($request->hasFile('assinatura_upload')) {
            $arquivo = $request->file('assinatura_upload');
            $conteudo = file_get_contents($arquivo->getRealPath());
            $nomeArquivo = 'assinatura-cliente-locacao-' . $assinatura->id_locacao . '-' . now()->format('YmdHis') . '.' . $arquivo->getClientOriginalExtension();
            $mimeType = (string) ($arquivo->getMimeType() ?: 'image/png');
        } else {
            $desenhoBase64 = (string) $request->input('assinatura_desenho', '');
            $conteudo = $this->decodeDataUrl($desenhoBase64);
        }

        if (!$conteudo) {
            return redirect()->back()->withInput()->with('error', 'Informe uma assinatura válida para continuar.');
        }

        $urlAssinatura = $this->enviarAssinaturaClienteParaApi($conteudo, $nomeArquivo, (int) $assinatura->id_empresa, $mimeType);

        if (!$urlAssinatura) {
            return redirect()->back()->withInput()->with('error', 'Não foi possível salvar a assinatura no servidor de arquivos.');
        }

        // Gerar hash jurídico para validade do documento
        $locacao = $assinatura->locacao;
        $cliente = $locacao?->cliente;
        $dataAceite = now();
        
        // Resolver id do modelo de contrato usado na assinatura
        $idModeloContrato = $request->filled('id_modelo')
            ? (int) $request->query('id_modelo')
            : ($assinatura->id_modelo ? (int) $assinatura->id_modelo : null);

        $assinadoPorNome = $cliente?->nome ?? $cliente?->razao_social;
        $assinadoPorDocumento = $cliente?->cpf_cnpj;

        // Primeiro persiste o estado assinado; assim o snapshot já nasce com assinatura no HTML.
        $assinatura->update([
            'status' => 'assinado',
            'assinatura_tipo' => (string) $request->input('assinatura_tipo'),
            'assinatura_cliente_url' => $urlAssinatura,
            'assinado_em' => $dataAceite,
            'ip_assinatura' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1900),
            'assinado_por_nome' => $assinadoPorNome,
            'assinado_por_documento' => $assinadoPorDocumento,
        ]);

        $assinatura->refresh();
        $assinatura->loadMissing(['locacao.cliente']);
        $locacao = $assinatura->locacao;
        $cliente = $locacao?->cliente;

        // Reutilizar snapshot existente quando válido; senão gerar snapshot atualizado.
        $corpoContratoAssinado = trim((string) ($assinatura->corpo_contrato_assinado ?? ''));
        if ($corpoContratoAssinado === '' || $this->snapshotAssinadoSemAssinaturaCliente($assinatura, $corpoContratoAssinado)) {
            $corpoContratoAssinado = $this->gerarCorpoContratoParaHash($assinatura, $locacao, $idModeloContrato);
        }
        
        // Gerar hash SHA-256 com dados do aceite
        $dadosParaHash = json_encode([
            'id_assinatura' => $assinatura->id_assinatura,
            'id_locacao' => $assinatura->id_locacao,
            'id_cliente' => $assinatura->id_cliente,
            'numero_contrato' => $locacao?->numero_contrato,
            'cliente_nome' => $cliente?->nome ?? $cliente?->razao_social,
            'cliente_documento' => $cliente?->cpf_cnpj,
            'valor_total' => $locacao?->valor_total,
            'data_aceite' => $dataAceite->toIso8601String(),
            'ip_aceite' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'token' => $token,
            'assinatura_cliente_url' => $urlAssinatura,
            'corpo_contrato_assinado' => $corpoContratoAssinado,
        ], JSON_UNESCAPED_UNICODE);
        
        $hashDocumento = hash('sha256', $dadosParaHash);

        $assinatura->update([
            'hash_documento' => $hashDocumento,
            'corpo_contrato_assinado' => $corpoContratoAssinado,
        ]);

        $tipoDocumento = strtolower((string) $request->query('tipo', ''));
        if (!in_array($tipoDocumento, ['contrato', 'medicao'], true)) {
            $tipoDocumento = in_array((string) ($assinatura->locacao?->status ?? ''), ['medicao', 'medicao_finalizada'], true)
                ? 'medicao'
                : 'contrato';
        }

        return redirect()->route('locacoes.assinatura-digital.form', [
            'token' => $token,
            'tipo' => $tipoDocumento,
            'id_modelo' => $idModeloContrato,
        ])
            ->with('success', 'Assinatura digital registrada com sucesso.');
    }

    public function contratoPdfAssinaturaDigital(string $token, Request $request)
    {
        try {
            $assinatura = LocacaoAssinaturaDigital::query()
                ->where('token', $token)
                ->with(['locacao' => function ($query) {
                    $query->with([
                        'cliente',
                        'produtos.produto',
                        'produtos.patrimonio',
                        'produtos.sala',
                        'produtos.fornecedor',
                        'produtosTerceiros.fornecedor',
                        'produtosTerceiros.produtoTerceiro',
                        'produtosTerceiros.sala',
                        'servicos',
                        'despesas',
                        'salas',
                        'empresa',
                        'assinaturaDigital'
                    ]);
                }])
                ->first();

            if (!$assinatura || !$assinatura->locacao) {
                abort(404);
            }

            $locacao = $assinatura->locacao;
            $locacao->setRelation('assinaturaDigital', $assinatura);
            $idModelo = $request->filled('id_modelo')
                ? (int) $request->query('id_modelo')
                : ($assinatura->id_modelo ? (int) $assinatura->id_modelo : null);
            $tipoDocumento = strtolower((string) $request->query('tipo', ''));
            if (!in_array($tipoDocumento, ['contrato', 'medicao'], true)) {
                $tipoDocumento = in_array((string) ($locacao->status ?? ''), ['medicao', 'medicao_finalizada'], true)
                    ? 'medicao'
                    : 'contrato';
            }
            $nomeArquivo = 'contrato-' . ($locacao->numero_contrato ?? $locacao->id_locacao) . '.pdf';

            if ($tipoDocumento === 'medicao') {
                $idEmpresa = (int) ($locacao->id_empresa ?? 0);
                $empresa = $locacao->empresa;
                $corPrimariaDocumento = $this->obterCorPrimariaModeloPadrao($idEmpresa);
                $this->normalizarLogoEmpresa($empresa);

                $modeloContratoMedicao = $this->consultarModelosDocumento($idEmpresa, 'medicao')
                    ->when(!empty($idModelo), function ($query) use ($idModelo) {
                        $query->where('id_modelo', (int) $idModelo);
                    })
                    ->orderBy('padrao', 'desc')
                    ->orderBy('nome')
                    ->first();

                $pdf = Pdf::loadView('locacoes.documentos.medicao', [
                    'locacao' => $locacao,
                    'empresa' => $empresa,
                    'tipo' => 'medicao',
                    'corPrimariaDocumento' => $corPrimariaDocumento,
                    'modeloContratoMedicao' => $modeloContratoMedicao,
                    'assinaturaClientePdfSrc' => $this->resolverAssinaturaClienteParaPdf(
                        $locacao->assinaturaDigital->assinatura_cliente_url ?? null
                    ),
                ])->setPaper('a4', 'portrait');
            } else {
                $htmlContrato = $this->obterHtmlContratoPorToken($assinatura, $locacao, $idModelo ?: null);
                $htmlContrato = $this->anexarBlocoValidadeAssinaturaNoHtml($htmlContrato, $assinatura);
                $pdf = Pdf::loadHTML($htmlContrato)->setPaper('a4', 'portrait');
            }

            return $pdf->stream($nomeArquivo);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar contrato PDF por token de assinatura.', [
                'token' => $token,
                'erro' => $e->getMessage(),
            ]);

            abort(500, 'Erro ao gerar contrato.');
        }
    }

    /**
     * Visualizar contrato assinado com informações de validade jurídica
     */
    public function visualizarContratoAssinado(string $token)
    {
        $assinatura = LocacaoAssinaturaDigital::query()
            ->where('token', $token)
            ->with(['locacao' => function ($query) {
                $query->with([
                    'cliente',
                    'produtos.produto',
                    'servicos',
                    'empresa',
                ]);
            }])
            ->first();

        if (!$assinatura || !$assinatura->locacao) {
            abort(404, 'Contrato não encontrado.');
        }

        if ($assinatura->status !== 'assinado') {
            return redirect()->route('locacoes.assinatura-digital.form', ['token' => $token])
                ->with('info', 'Este contrato ainda não foi assinado.');
        }

        $locacao = $assinatura->locacao;
        $cliente = $locacao->cliente;
        $empresa = $locacao->empresa;

        // Resolver logo da empresa
        $logoSrc = null;
        $empresaConfig = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        if (!empty($empresaConfig['logo_url'])) {
            $logoSrc = $empresaConfig['logo_url'];
        }

        return view('locacoes.contrato-assinado', [
            'assinatura' => $assinatura,
            'locacao' => $locacao,
            'cliente' => $cliente,
            'empresa' => $empresa,
            'logoSrc' => $logoSrc,
        ]);
    }

    /**
     * Gera HTML completo do contrato para preservar estado exato no momento da assinatura.
     * Este HTML é usado como "espelho" imutável do contrato assinado.
     */
    private function gerarCorpoContratoParaHash($assinatura, $locacao, ?int $idModelo = null): string
    {
        if (!$locacao) {
            return '<div class="erro">Locação não encontrada</div>';
        }

        try {
            // Usar o serviço de PDF para gerar o HTML completo do contrato
            // Isso garante que o espelho seja idêntico ao que foi enviado por email
            $htmlContrato = $this->contratoPdfService->gerarHtml($locacao, $idModelo, $assinatura);
            return $htmlContrato;
        } catch (\Throwable $e) {
            Log::warning('Erro ao gerar HTML do contrato para espelho assinado', [
                'id_locacao' => $locacao->id_locacao,
                'erro' => $e->getMessage(),
            ]);
            
            // Fallback: gerar HTML básico com dados essenciais
            return $this->gerarHtmlFallbackContrato($locacao);
        }
    }

    private function obterHtmlContratoPorToken(LocacaoAssinaturaDigital $assinatura, $locacao, ?int $idModelo = null): string
    {
        $htmlSalvo = trim((string) ($assinatura->corpo_contrato_assinado ?? ''));
        if ($htmlSalvo !== '' && !$this->snapshotAssinadoSemAssinaturaCliente($assinatura, $htmlSalvo)) {
            return $htmlSalvo;
        }

        $htmlContrato = $this->gerarCorpoContratoParaHash($assinatura, $locacao, $idModelo);

        if ($htmlContrato !== '') {
            try {
                $assinatura->forceFill(['corpo_contrato_assinado' => $htmlContrato])->save();
            } catch (\Throwable $e) {
                Log::warning('Não foi possível salvar snapshot HTML do contrato por token.', [
                    'id_assinatura' => $assinatura->id_assinatura,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $htmlContrato;
    }

    private function snapshotAssinadoSemAssinaturaCliente(LocacaoAssinaturaDigital $assinatura, string $htmlContrato): bool
    {
        if ((string) ($assinatura->status ?? '') !== 'assinado') {
            return false;
        }

        $assinaturaClienteUrl = trim((string) ($assinatura->assinatura_cliente_url ?? ''));
        if ($assinaturaClienteUrl === '') {
            return false;
        }

        $htmlContrato = trim($htmlContrato);
        if ($htmlContrato === '') {
            return true;
        }

        if (stripos($htmlContrato, $assinaturaClienteUrl) !== false) {
            return false;
        }

        $temBlocoAssinaturas = stripos($htmlContrato, 'assinaturas-table') !== false
            || stripos($htmlContrato, 'assinatura-cell') !== false
            || stripos($htmlContrato, 'assinatura-linha') !== false;

        if (!$temBlocoAssinaturas) {
            return false;
        }

        if (stripos($htmlContrato, 'assinatura-placeholder') !== false) {
            return true;
        }

        if (
            stripos($htmlContrato, 'alt="Assinatura Cliente"') !== false
            || stripos($htmlContrato, "alt='Assinatura Cliente'") !== false
            || stripos($htmlContrato, 'Assinatura Cliente') !== false
        ) {
            return false;
        }

        return true;
    }
    
    /**
     * Gera HTML básico do contrato como fallback
     */
    private function gerarHtmlFallbackContrato($locacao): string
    {
        $locacao->load(['cliente', 'produtos.produto', 'servicos', 'empresa']);
        
        $cliente = $locacao->cliente;
        $empresa = $locacao->empresa;
        
        $produtosHtml = '';
        foreach ($locacao->produtos as $item) {
            $produtosHtml .= '<tr>';
            $produtosHtml .= '<td>' . e($item->produto?->nome ?? 'Item') . '</td>';
            $produtosHtml .= '<td>' . e($item->quantidade) . '</td>';
            $produtosHtml .= '<td>R$ ' . number_format($item->preco_unitario ?? 0, 2, ',', '.') . '</td>';
            $produtosHtml .= '<td>R$ ' . number_format($item->preco_total ?? 0, 2, ',', '.') . '</td>';
            $produtosHtml .= '</tr>';
        }
        
        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Locação #' . e($locacao->numero_contrato) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        .section { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Contrato de Locação #' . e($locacao->numero_contrato) . '</h1>
    
    <div class="section">
        <h3>Contratante</h3>
        <p><strong>Nome:</strong> ' . e($cliente?->nome ?? $cliente?->razao_social ?? '') . '</p>
        <p><strong>CPF/CNPJ:</strong> ' . e($cliente?->cpf_cnpj ?? '') . '</p>
        <p><strong>Endereço:</strong> ' . e($cliente?->endereco ?? '') . '</p>
    </div>
    
    <div class="section">
        <h3>Contratado</h3>
        <p><strong>Nome:</strong> ' . e($empresa?->razao_social ?? $empresa?->nome ?? '') . '</p>
        <p><strong>CNPJ:</strong> ' . e($empresa?->cnpj ?? '') . '</p>
    </div>
    
    <div class="section">
        <h3>Período</h3>
        <p><strong>Data Início:</strong> ' . optional($locacao->data_inicio)->format('d/m/Y') . '</p>
        <p><strong>Data Fim:</strong> ' . optional($locacao->data_fim)->format('d/m/Y') . '</p>
        <p><strong>Dias:</strong> ' . e($locacao->quantidade_dias) . '</p>
    </div>
    
    <div class="section">
        <h3>Itens Locados</h3>
        <table>
            <thead>
                <tr><th>Produto</th><th>Qtd</th><th>Valor Unit.</th><th>Subtotal</th></tr>
            </thead>
            <tbody>' . $produtosHtml . '</tbody>
        </table>
    </div>
    
    <div class="section">
        <h3>Valor Total</h3>
        <p><strong>R$ ' . number_format($locacao->valor_total ?? 0, 2, ',', '.') . '</strong></p>
    </div>
</body>
</html>';
    }

    private function anexarBlocoValidadeAssinaturaNoHtml(string $htmlContrato, LocacaoAssinaturaDigital $assinatura): string
    {
        if (stripos($htmlContrato, 'gn-hash-assinatura') !== false) {
            return $htmlContrato;
        }

        $statusAssinado = (string) ($assinatura->status ?? '') === 'assinado';
        $hashDocumento = trim((string) ($assinatura->hash_documento ?? ''));

        if ($statusAssinado && $hashDocumento === '') {
            $hashDocumento = $this->gerarHashFallbackAssinatura($assinatura);

            if ($hashDocumento !== '') {
                try {
                    $assinatura->forceFill(['hash_documento' => $hashDocumento])->save();
                } catch (\Throwable $e) {
                    Log::warning('Não foi possível persistir hash fallback da assinatura.', [
                        'id_assinatura' => $assinatura->id_assinatura,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!$statusAssinado && $hashDocumento === '') {
            return $htmlContrato;
        }

        $statusLabel = $statusAssinado
            ? 'ASSINADO DIGITALMENTE'
            : strtoupper((string) ($assinatura->status ?? 'PENDENTE'));

        $dataAssinatura = optional($assinatura->assinado_em)->format('d/m/Y H:i:s') ?? '-';
        $nomeAssinante = (string) ($assinatura->assinado_por_nome ?? '');
        $documentoAssinante = (string) ($assinatura->assinado_por_documento ?? '');
        $ipAssinatura = (string) ($assinatura->ip_assinatura ?? '-');
        $hashExibicao = $hashDocumento !== '' ? $hashDocumento : 'Hash não disponível';

        $blocoValidade = '
<style>
    .gn-hash-assinatura {
        margin-top: 18px;
        border: 1px solid #cbd5e1;
        border-left: 4px solid #16a34a;
        border-radius: 6px;
        background: #f8fafc;
        padding: 10px;
        font-size: 10px;
        line-height: 1.45;
        page-break-inside: avoid;
    }
    .gn-hash-assinatura h4 {
        margin: 0 0 6px 0;
        font-size: 11px;
        color: #065f46;
        text-transform: uppercase;
    }
    .gn-hash-assinatura .hash {
        margin-top: 6px;
        padding: 6px;
        border: 1px dashed #94a3b8;
        border-radius: 4px;
        background: #ffffff;
        font-family: DejaVu Sans Mono, Courier, monospace;
        font-size: 9px;
        word-break: break-all;
    }
</style>
<div class="gn-hash-assinatura">
    <h4>Validade Jurídica da Assinatura</h4>
    <div><strong>Status:</strong> ' . e($statusLabel) . '</div>
    <div><strong>Data/Hora:</strong> ' . e($dataAssinatura) . '</div>
    <div><strong>Assinado por:</strong> ' . e($nomeAssinante !== '' ? $nomeAssinante : '-') . '</div>
    <div><strong>Documento:</strong> ' . e($documentoAssinante !== '' ? $documentoAssinante : '-') . '</div>
    <div><strong>IP de origem:</strong> ' . e($ipAssinatura) . '</div>
    <div class="hash"><strong>Hash SHA-256:</strong><br>' . e($hashExibicao) . '</div>
</div>';

        $posicaoBody = stripos($htmlContrato, '</body>');
        if ($posicaoBody !== false) {
            return substr_replace($htmlContrato, $blocoValidade, $posicaoBody, 0);
        }

        return $htmlContrato . $blocoValidade;
    }

    private function gerarHashFallbackAssinatura(LocacaoAssinaturaDigital $assinatura): string
    {
        $dados = json_encode([
            'id_assinatura' => (int) ($assinatura->id_assinatura ?? 0),
            'token' => (string) ($assinatura->token ?? ''),
            'id_locacao' => (int) ($assinatura->id_locacao ?? 0),
            'numero_contrato' => (string) ($assinatura->locacao->numero_contrato ?? ''),
            'status' => (string) ($assinatura->status ?? ''),
            'assinado_em' => optional($assinatura->assinado_em)->toIso8601String(),
            'assinado_por_nome' => (string) ($assinatura->assinado_por_nome ?? ''),
            'assinado_por_documento' => (string) ($assinatura->assinado_por_documento ?? ''),
            'ip_assinatura' => (string) ($assinatura->ip_assinatura ?? ''),
            'user_agent' => (string) ($assinatura->user_agent ?? ''),
            'corpo_contrato_assinado' => (string) ($assinatura->corpo_contrato_assinado ?? ''),
        ], JSON_UNESCAPED_UNICODE);

        if (!is_string($dados) || $dados === '') {
            return '';
        }

        return hash('sha256', $dados);
    }

    private function decodeDataUrl(?string $dataUrl): ?string
    {
        $dataUrl = trim((string) $dataUrl);
        if ($dataUrl === '') {
            return null;
        }

        if (!preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $dataUrl)) {
            return null;
        }

        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $decoded = base64_decode($base64, true);

        return $decoded !== false ? $decoded : null;
    }

    private function enviarAssinaturaClienteParaApi(string $conteudo, string $nomeArquivo, int $idEmpresa, string $mimeType): ?string
    {
        $baseUrl = $this->getApiFilesBaseUrl();
        if ($baseUrl === '') {
            return null;
        }

        $endpoints = [
            rtrim($baseUrl, '/') . '/api/assinaturas',
            rtrim($baseUrl, '/') . '/uploads/assinaturas',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(25)
                    ->attach('file', $conteudo, $nomeArquivo, ['Content-Type' => $mimeType])
                    ->post($endpoint, [
                        'idEmpresa' => $idEmpresa,
                        'nomeImagemAssinatura' => pathinfo($nomeArquivo, PATHINFO_FILENAME),
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $url = data_get($data, 'data.file.url')
                        ?? data_get($data, 'data.url')
                        ?? data_get($data, 'url');

                    if (is_string($url) && $url !== '') {
                        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                            return $url;
                        }

                        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                    }

                    return rtrim($baseUrl, '/') . '/uploads/assinaturas/' . $idEmpresa . '/' . $nomeArquivo;
                }

                Log::warning('Upload de assinatura do cliente retornou falha.', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Erro ao enviar assinatura de cliente para API.', [
                    'endpoint' => $endpoint,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        Log::warning('Upload de assinatura bloqueado no fallback local para evitar exposição pública insegura.', [
            'id_empresa' => $idEmpresa,
            'arquivo' => $nomeArquivo,
        ]);

        return null; // Segurança: evita salvar assinatura privada em diretório público.
    }

    private function resolverNomeEmpresaDocumento($empresa): string
    {
        return trim((string) (
            $empresa->razao_social
            ?? $empresa->nome_fantasia
            ?? $empresa->nome_empresa
            ?? $empresa->nome
            ?? 'Empresa'
        ));
    }

    private function resolverLogoEmpresaEmail($empresa): ?string
    {
        if (!$empresa) {
            return null;
        }

        $configuracoes = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logoUrl = (string) ($configuracoes['logo_url'] ?? $empresa->logo_url ?? '');
        if ($logoUrl === '') {
            return null;
        }

        $logoUrl = str_replace(['https//', 'http//'], ['https://', 'http://'], $logoUrl);

        if (str_starts_with($logoUrl, '/')) {
            return rtrim($this->getApiFilesBaseUrl(), '/') . $logoUrl;
        }

        if (!str_starts_with($logoUrl, 'http://') && !str_starts_with($logoUrl, 'https://')) {
            return rtrim($this->getApiFilesBaseUrl(), '/') . '/' . ltrim($logoUrl, '/');
        }

        return $logoUrl;
    }

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

    private function getApiFilesBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com')), '/');
        return str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);
    }

    private function resolverAssinaturaLocadoraParaPdf(int $idEmpresa, ?int $idModelo = null): ?string
    {
        if (!$this->hasColunaModeloContrato('assinatura_locadora_url')) {
            return null;
        }

        $modelo = null;
        if (!empty($idModelo)) {
            $modelo = LocacaoModeloContrato::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_modelo', (int) $idModelo)
                ->where('ativo', true)
                ->first();
        }

        if (!$modelo) {
            $modelo = LocacaoModeloContrato::query()
                ->where('id_empresa', $idEmpresa)
                ->where('ativo', true)
                ->orderByDesc('padrao')
                ->orderBy('nome')
                ->first();
        }

        if (!$modelo) {
            return null;
        }

        return $this->resolverAssinaturaClienteParaPdf($modelo->assinatura_locadora_url ?? null);
    }

    private function resolverAssinaturaClienteParaPdf(?string $assinaturaUrl): ?string
    {
        $assinaturaUrl = trim((string) $assinaturaUrl);
        if ($assinaturaUrl === '') {
            return null;
        }

        $assinaturaUrl = str_replace(['https//', 'http//'], ['https://', 'http://'], $assinaturaUrl);

        // Tenta resolver arquivo local no servidor (assinaturas e storage)
        $arquivoLocal = $this->resolverArquivoLocalParaPdf($assinaturaUrl, [
            'assets/assinaturas-contrato',
            'storage/assinaturas',
            'assets/assinaturas',
        ]);
        if ($arquivoLocal !== null) {
            return $arquivoLocal;
        }

        // Fallback: tenta resolver via URL remota convertendo para base64
        if (str_starts_with($assinaturaUrl, 'http://') || str_starts_with($assinaturaUrl, 'https://')) {
            try {
                $response = Http::timeout(20)->get($assinaturaUrl);
                if ($response->successful()) {
                    $mime = (string) ($response->header('Content-Type') ?: 'image/png');
                    return 'data:' . $mime . ';base64,' . base64_encode((string) $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao resolver assinatura do cliente para PDF.', [
                    'url' => $assinaturaUrl,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $assinaturaUrl;
    }

    /**
     * Resolve um arquivo local para uso em PDF (converte para base64 data URI).
     * Tenta múltiplos caminhos conhecidos onde o arquivo pode estar no servidor.
     *
     * @param string $url URL ou caminho do arquivo
     * @param array $pathsConhecidos Subdiretórios conhecidos onde procurar (ex: ['assets/logos-empresa'])
     * @return string|null Data URI base64 ou null se não encontrar
     */
    private function resolverArquivoLocalParaPdf(string $url, array $pathsConhecidos = []): ?string
    {
        $caminhosTentar = [];

        // Se for URL, extrai o path
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            // Tenta o path direto
            $caminhosTentar[] = public_path(ltrim($path, '/'));
            
            // Verifica se o path contém algum dos caminhos conhecidos
            foreach ($pathsConhecidos as $pathConhecido) {
                if (strpos($path, $pathConhecido) !== false) {
                    // Extrai o nome do arquivo e monta path completo
                    $partes = explode($pathConhecido, $path);
                    if (count($partes) > 1) {
                        $nomeArquivo = ltrim(end($partes), '/');
                        $caminhosTentar[] = public_path($pathConhecido . '/' . $nomeArquivo);
                    }
                }
            }
        }

        // Se não for URL completa, tenta como path direto
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $caminhosTentar[] = public_path(ltrim($url, '/'));
            foreach ($pathsConhecidos as $pathConhecido) {
                $nomeArquivo = basename($url);
                $caminhosTentar[] = public_path($pathConhecido . '/' . $nomeArquivo);
            }
        }

        // Remove duplicados e tenta cada caminho
        $caminhosTentar = array_unique($caminhosTentar);
        foreach ($caminhosTentar as $caminho) {
            if (File::exists($caminho)) {
                try {
                    $conteudo = File::get($caminho);
                    $mime = $this->detectarMimeType($caminho);
                    return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
                } catch (\Throwable $e) {
                    Log::warning('Falha ao ler arquivo local para PDF.', [
                        'caminho' => $caminho,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * Detecta o MIME type de um arquivo de imagem.
     */
    private function detectarMimeType(string $caminho): string
    {
        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
        ];
        return $mimeTypes[$extensao] ?? 'image/png';
    }

    /**
     * Listar modelos de contrato disponíveis
     */
    public function modelosContrato()
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $tipoDocumento = request()->query('tipo');
            if ($tipoDocumento === null && request()->boolean('medicao')) {
                $tipoDocumento = 'medicao';
            }
            $tipoDocumentoNormalizado = strtolower((string) ($tipoDocumento ?? ''));

            $modelos = $this->consultarModelosDocumento(
                (int) $idEmpresa,
                $tipoDocumento ? (string) $tipoDocumento : 'contrato'
            )
                ->orderBy('padrao', 'desc')
                ->orderBy('nome')
                ->get(['id_modelo', 'nome', 'descricao', 'padrao']);

            if ($tipoDocumentoNormalizado === 'orcamento' && $modelos->isEmpty()) {
                $modelos = $this->consultarModelosDocumento((int) $idEmpresa, 'contrato')
                    ->orderBy('padrao', 'desc')
                    ->orderBy('nome')
                    ->get(['id_modelo', 'nome', 'descricao', 'padrao']);
            }

            return response()->json($modelos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar fornecedores
     */
    public function buscarFornecedores(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $termo = $request->input('q', '');

        $query = Fornecedor::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo');
        
        if (!empty($termo)) {
            $query->where(function($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('razao_social', 'like', "%{$termo}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$termo}%");
            });
        }

        $fornecedores = $query->orderBy('nome')->limit(50)->get();

        return response()->json($fornecedores);
    }

    /**
     * Buscar produtos de terceiros
     */
    public function buscarProdutosTerceiros(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $termo = $request->input('q', '');
        $idFornecedor = $request->input('id_fornecedor');

        $query = ProdutoTerceiro::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->with('fornecedor');
        
        if ($idFornecedor) {
            $query->where('id_fornecedor', $idFornecedor);
        }
        
        if (!empty($termo)) {
            $query->where(function($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('codigo', 'like', "%{$termo}%");
            });
        }

        $produtos = $query->orderBy('nome')->get();

        return response()->json($produtos->map(function ($p) {
            return [
                'id_produto_terceiro' => $p->id_produto_terceiro,
                'nome' => $p->nome,
                'codigo' => $p->codigo,
                'custo_diaria' => $p->custo_diaria,
                'preco_locacao' => $p->preco_locacao,
                'fornecedor' => $p->fornecedor ? [
                    'id_fornecedores' => $p->fornecedor->id_fornecedores,
                    'nome' => $p->fornecedor->nome,
                ] : null,
            ];
        }));
    }

    /**
     * Histórico de um patrimônio específico
     */
    public function historicoPatrimonio($idPatrimonio)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            Log::info('=== BUSCANDO HISTÓRICO PATRIMÔNIO ===', [
                'id_patrimonio' => $idPatrimonio,
                'id_empresa' => $idEmpresa,
            ]);

            $patrimonio = Patrimonio::where('id_patrimonio', $idPatrimonio)
                ->where('id_empresa', $idEmpresa)
                ->with(['produto', 'locacaoAtual.cliente'])
                ->first();

            // Buscar histórico na tabela patrimonio_historico
            $historico = PatrimonioHistorico::where('id_patrimonio', $idPatrimonio)
                ->where('id_empresa', $idEmpresa)
                ->with(['locacao.cliente', 'usuario'])
                ->orderBy('data_movimentacao', 'desc')
                ->paginate(50);

            // Se não tem histórico na tabela, buscar das locações vinculadas
            if ($historico->isEmpty() && $patrimonio) {
                // Buscar locações onde esse patrimônio foi usado
                $locacoesProdutos = \App\Domain\Locacao\Models\LocacaoProduto::where('id_patrimonio', $idPatrimonio)
                    ->where('id_empresa', $idEmpresa)
                    ->with(['locacao.cliente'])
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Criar histórico fake para exibição
                $historicoFake = [];
                foreach ($locacoesProdutos as $lp) {
                    if ($lp->locacao) {
                        $historicoFake[] = (object)[
                            'id_historico' => null,
                            'tipo_movimentacao' => 'saida_locacao',
                            'data_movimentacao' => $lp->created_at,
                            'locacao' => $lp->locacao,
                            'observacoes' => 'Locação #' . ($lp->locacao->numero_contrato ?? $lp->locacao->id_locacao),
                            'status_anterior' => 'Disponivel',
                            'status_novo' => 'Locado',
                            'usuario' => null,
                        ];
                    }
                }

                Log::info('Histórico gerado das locações:', ['count' => count($historicoFake)]);
            }

            Log::info('Histórico encontrado:', [
                'patrimonio_encontrado' => $patrimonio ? true : false,
                'total_historico' => $historico->count(),
            ]);

            // Se for requisição AJAX, retorna JSON
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'patrimonio' => $patrimonio,
                    'historico' => $historico
                ]);
            }

            // Se não tem histórico oficial, usar o fake
            if ($historico->isEmpty() && !empty($historicoFake)) {
                $historico = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect($historicoFake),
                    count($historicoFake),
                    50,
                    1
                );
            }

            // Se for requisição normal, retorna view
            return view('locacoes.historico-patrimonio', compact('patrimonio', 'historico'));
        } catch (\Exception $e) {
            Log::error('Erro ao buscar histórico do patrimônio:', ['erro' => $e->getMessage()]);
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Erro ao carregar histórico: ' . $e->getMessage());
        }
    }

    /**
     * Valida e retorna um valor válido para preferencia_estoque
    * SIMPLIFICADO: Apenas 'data_item', 'data_contrato' ou 'data_transporte'
     * 
     * @param string|null $preferencia
     * @return string
     */
    private function validarPreferenciaEstoque(?string $preferencia): string
    {
        $valoresValidos = ['data_item', 'data_contrato', 'data_transporte'];
        
        if ($preferencia && in_array($preferencia, $valoresValidos)) {
            return $preferencia;
        }
        
        // Valor padrão: data_item
        return 'data_item';
    }

    private function sincronizarContasPagarLocacaoPorStatus(Locacao $locacao, int $idEmpresa): void
    {
        $locacaoAtual = Locacao::where('id_locacao', $locacao->id_locacao)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$locacaoAtual) {
            return;
        }

        $statusPermiteGeracao = in_array((string) $locacaoAtual->status, [
            'aprovado',
            'em_andamento',
            'atrasada',
            'retirada',
            'encerrado',
            'finalizada',
        ], true);

        $this->limparContasPagarGeradasAutomaticamenteLocacao($locacaoAtual, $idEmpresa);

        if (!$statusPermiteGeracao) {
            return;
        }

        $produtosTerceiros = ProdutoTerceirosLocacao::where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacaoAtual->id_locacao)
            ->with('produtoTerceiro')
            ->get();

        foreach ($produtosTerceiros as $produtoTerceiro) {
            if (empty($produtoTerceiro->gerar_conta_pagar) || empty($produtoTerceiro->conta_vencimento)) {
                continue;
            }

            $this->gerarContaPagarProdutoTerceiro($produtoTerceiro, $locacaoAtual, $idEmpresa);
        }

        $servicos = LocacaoServico::where('id_locacao', $locacaoAtual->id_locacao)->get();

        foreach ($servicos as $servico) {
            $meta = $this->extrairMetaObservacao($servico->observacoes);

            $tipoItem = $servico->tipo_item ?? ($meta['tipo_item'] ?? 'proprio');
            if ($tipoItem !== 'terceiro') {
                continue;
            }

            $gerarContaPagar = $servico->gerar_conta_pagar;
            if ($gerarContaPagar === null) {
                $gerarContaPagar = $meta['gerar_conta_pagar'] ?? false;
            }

            if (empty($gerarContaPagar)) {
                continue;
            }

            $contaVencimento = $servico->conta_vencimento ?? ($meta['conta_vencimento'] ?? null);
            if (empty($contaVencimento)) {
                continue;
            }

            $this->gerarContaPagarServicoTerceiro([
                'descricao' => $servico->descricao,
                'quantidade' => $servico->quantidade,
                'tipo_item' => $tipoItem,
                'id_fornecedor' => $servico->id_fornecedor ?? ($meta['id_fornecedor'] ?? null),
                'custo_fornecedor' => $servico->custo_fornecedor ?? ($meta['custo_fornecedor'] ?? 0),
                'gerar_conta_pagar' => $gerarContaPagar,
                'conta_vencimento' => $contaVencimento,
                'conta_valor' => $servico->conta_valor ?? ($meta['conta_valor'] ?? 0),
                'conta_parcelas' => $servico->conta_parcelas ?? ($meta['conta_parcelas'] ?? 1),
            ], $locacaoAtual, $idEmpresa);
        }

        $despesas = LocacaoDespesa::where('id_locacao', $locacaoAtual->id_locacao)->get();

        foreach ($despesas as $despesa) {
            $meta = $this->extrairMetaObservacao($despesa->observacoes);

            $valorDespesa = $this->parseDecimal($despesa->valor ?? 0);
            if ($valorDespesa <= 0) {
                continue;
            }

            $this->gerarContaPagarDespesaLocacao($despesa, $locacaoAtual, $idEmpresa, [
                'valor' => $valorDespesa,
                'conta_vencimento' => $despesa->conta_vencimento ?? ($meta['conta_vencimento'] ?? null),
                'conta_parcelas' => $despesa->conta_parcelas ?? ($meta['conta_parcelas'] ?? 1),
            ]);
        }
    }

    private function limparContasPagarGeradasAutomaticamenteLocacao(Locacao $locacao, int $idEmpresa): void
    {
        $origens = array_values(array_unique(array_filter([
            $this->normalizarOrigemContaPagar('locacao_terceiro'),
            $this->normalizarOrigemContaPagar('locacao_servico_terceiro'),
            $this->normalizarOrigemContaPagar('servico'),
            $this->normalizarOrigemContaPagar('locacao_despesa'),
            $this->normalizarOrigemContaPagar('compra'),
            'locacao_terceiro',
            'locacao_servico_terceiro',
            'servico',
            'locacao_despesa',
            'compra',
        ])));

        $temDescricao = Schema::hasColumn('contas_a_pagar', 'descricao');
        $temStatus = Schema::hasColumn('contas_a_pagar', 'status');

        $query = ContasAPagar::where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where(function ($query) use ($origens, $temDescricao) {
                $query->whereIn('origem', $origens)
                    ->orWhere(function ($legacyQuery) use ($temDescricao) {
                        $legacyQuery->whereNull('origem');

                        if ($temDescricao) {
                            $legacyQuery->where('descricao', 'like', 'Locação #%');
                        }
                    });
            });

        if ($temStatus) {
            $query->where(function ($statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhereNotIn('status', ['pago', 'paga', 'quitado', 'liquidado']);
            });
        }

        $query->delete();
    }

    /**
     * Gera conta(s) a pagar para produto de terceiro
     * 
     * @param ProdutoTerceirosLocacao $produtoTerceiro
     * @param Locacao $locacao
     * @param int $idEmpresa
     */
    private function gerarContaPagarProdutoTerceiro(ProdutoTerceirosLocacao $produtoTerceiro, Locacao $locacao, $idEmpresa)
    {
        $valorTotal = $produtoTerceiro->conta_valor ?: ($produtoTerceiro->custo_fornecedor * $produtoTerceiro->quantidade);
        $parcelas = max(1, $produtoTerceiro->conta_parcelas ?? 1);
        $valorParcela = round($valorTotal / $parcelas, 2);
        $idParcelamento = $parcelas > 1 ? (string) \Illuminate\Support\Str::uuid() : null;
        
        // Nome do produto para descrição
        $nomeProduto = $produtoTerceiro->nome_produto_manual;
        if (!$nomeProduto && $produtoTerceiro->produtoTerceiro) {
            $nomeProduto = $produtoTerceiro->produtoTerceiro->nome;
        }
        $nomeProduto = $nomeProduto ?: 'Produto de Terceiro';

        // Data de vencimento base
        $dataVencimentoBase = $produtoTerceiro->conta_vencimento 
            ? \Carbon\Carbon::parse($produtoTerceiro->conta_vencimento) 
            : \Carbon\Carbon::now();

        for ($i = 0; $i < $parcelas; $i++) {
            $dataVencimento = $dataVencimentoBase->copy()->addMonths($i);
            
            // Ajustar a última parcela para compensar diferenças de arredondamento
            $valorEfetivo = $valorParcela;
            if ($i === $parcelas - 1) {
                $valorEfetivo = $valorTotal - ($valorParcela * ($parcelas - 1));
            }

            ContasAPagar::create($this->montarDadosContaPagar([
                'id_empresa' => $idEmpresa,
                'id_fornecedores' => $produtoTerceiro->id_fornecedor 
                    ?? ($produtoTerceiro->produtoTerceiro ? $produtoTerceiro->produtoTerceiro->id_fornecedor : null),
                'descricao' => "Locação #{$locacao->numero_contrato} - {$nomeProduto}" . ($parcelas > 1 ? " (Parcela " . ($i + 1) . "/{$parcelas})" : ""),
                'valor_total' => $valorEfetivo,
                'data_emissao' => now(),
                'data_vencimento' => $dataVencimento->format('Y-m-d'),
                'status' => 'pendente',
                'id_locacao' => $locacao->id_locacao,
                'id_parcelamento' => $idParcelamento,
                'origem' => 'locacao_terceiro',
                'observacoes' => "Gerado automaticamente para produto de terceiro na locação",
                'numero_parcela' => $i + 1,
                'total_parcelas' => $parcelas,
            ]));
        }
    }

    private function montarDadosLocacaoServico(array $dados): array
    {
        static $colunasDisponiveis = null;

        if ($colunasDisponiveis === null) {
            $colunasDisponiveis = array_flip(Schema::getColumnListing('locacao_servicos'));
        }

        $payload = [
            'id_locacao' => $dados['id_locacao'],
            'descricao' => $dados['descricao'] ?? 'Serviço',
            'quantidade' => (int) ($dados['quantidade'] ?? 1),
            'preco_unitario' => $dados['preco_unitario'] ?? 0,
            'valor_total' => $dados['valor_total'] ?? 0,
            'observacoes' => $dados['observacoes'] ?? null,
        ];

        $extras = [
            'tipo_item' => $dados['tipo_item'] ?? 'proprio',
            'id_sala' => $dados['id_sala'] ?? null,
            'id_fornecedor' => $dados['id_fornecedor'] ?? null,
            'fornecedor_nome' => $dados['fornecedor_nome'] ?? null,
            'custo_fornecedor' => $dados['custo_fornecedor'] ?? 0,
            'gerar_conta_pagar' => !empty($dados['gerar_conta_pagar']),
            'conta_vencimento' => $dados['conta_vencimento'] ?? null,
            'conta_valor' => $dados['conta_valor'] ?? 0,
            'conta_parcelas' => max(1, (int) ($dados['conta_parcelas'] ?? 1)),
        ];

        foreach ($extras as $coluna => $valor) {
            if (isset($colunasDisponiveis[$coluna])) {
                $payload[$coluna] = $valor;
            }
        }

        return $payload;
    }

    private function montarDadosLocacaoDespesa(array $dados): array
    {
        static $colunasDisponiveis = null;

        if ($colunasDisponiveis === null) {
            $colunasDisponiveis = array_flip(Schema::getColumnListing('locacao_despesas'));
        }

        $payload = [
            'id_locacao' => $dados['id_locacao'],
            'descricao' => $dados['descricao'] ?? 'Despesa da locação',
            'valor' => $dados['valor'] ?? 0,
        ];

        $extras = [
            'tipo' => $dados['tipo'] ?? 'outros',
            'data_despesa' => $dados['data_despesa'] ?? null,
            'conta_vencimento' => $dados['conta_vencimento'] ?? null,
            'conta_parcelas' => max(1, (int) ($dados['conta_parcelas'] ?? 1)),
            'status' => $dados['status'] ?? 'pendente',
            'observacoes' => $dados['observacoes'] ?? null,
        ];

        foreach ($extras as $coluna => $valor) {
            if (isset($colunasDisponiveis[$coluna])) {
                $payload[$coluna] = $valor;
            }
        }

        return $payload;
    }

    private function anexarMetaObservacao(?string $observacoes, array $meta): ?string
    {
        $metaNormalizada = [];
        foreach ($meta as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }
            $metaNormalizada[$chave] = $valor;
        }

        $base = $this->removerMetaObservacao($observacoes);
        if (empty($metaNormalizada)) {
            return $base;
        }

        $payloadMeta = '[GN_META]' . json_encode($metaNormalizada, JSON_UNESCAPED_UNICODE);
        return trim((string) $base) !== '' ? trim((string) $base) . PHP_EOL . $payloadMeta : $payloadMeta;
    }

    private function extrairMetaObservacao(?string $observacoes): array
    {
        $texto = (string) ($observacoes ?? '');
        if (!preg_match('/\[GN_META\](\{.*\})/s', $texto, $matches)) {
            return [];
        }

        $dados = json_decode($matches[1], true);
        return is_array($dados) ? $dados : [];
    }

    private function removerMetaObservacao(?string $observacoes): ?string
    {
        $texto = (string) ($observacoes ?? '');
        $limpo = preg_replace('/\s*\[GN_META\]\{.*\}\s*/s', '', $texto);
        $limpo = trim((string) $limpo);
        return $limpo !== '' ? $limpo : null;
    }

    private function montarDadosProdutoTerceiroLocacao(array $dados): array
    {
        static $colunasDisponiveis = null;

        if ($colunasDisponiveis === null) {
            $colunasDisponiveis = array_flip(Schema::getColumnListing('produto_terceiros_locacao'));
        }

        $payload = [
            'id_empresa' => $dados['id_empresa'],
            'id_locacao' => $dados['id_locacao'],
            'quantidade' => (int) ($dados['quantidade'] ?? 1),
            'preco_unitario' => $dados['preco_unitario'] ?? 0,
            'custo_fornecedor' => $dados['custo_fornecedor'] ?? 0,
            'valor_total' => $dados['valor_total'] ?? 0,
        ];

        $extras = [
            'id_produto_terceiro' => $dados['id_produto_terceiro'] ?? null,
            'nome_produto_manual' => $dados['nome_produto_manual'] ?? null,
            'descricao_manual' => $dados['descricao_manual'] ?? null,
            'id_fornecedor' => $dados['id_fornecedor'] ?? null,
            'id_sala' => $dados['id_sala'] ?? null,
            'valor_fechado' => !empty($dados['valor_fechado']),
            'tipo_movimentacao' => $dados['tipo_movimentacao'] ?? 'entrega',
            'observacoes' => $dados['observacoes'] ?? null,
            'gerar_conta_pagar' => !empty($dados['gerar_conta_pagar']),
            'conta_vencimento' => $dados['conta_vencimento'] ?? null,
            'conta_valor' => $dados['conta_valor'] ?? 0,
            'conta_parcelas' => max(1, (int) ($dados['conta_parcelas'] ?? 1)),
        ];

        foreach ($extras as $coluna => $valor) {
            if (isset($colunasDisponiveis[$coluna])) {
                $payload[$coluna] = $valor;
            }
        }

        return $payload;
    }

    private function gerarContaPagarServicoTerceiro(array $servico, Locacao $locacao, $idEmpresa): void
    {
        $valorContaInformado = $this->parseDecimal($servico['conta_valor'] ?? 0);
        $custoFornecedor = $this->parseDecimal($servico['custo_fornecedor'] ?? 0);
        $quantidade = max(1, (int) ($servico['quantidade'] ?? 1));
        $parcelas = max(1, (int) ($servico['conta_parcelas'] ?? 1));
        $valorTotal = $valorContaInformado > 0 ? $valorContaInformado : ($custoFornecedor * $quantidade);
        $valorParcela = round($valorTotal / $parcelas, 2);
        $idParcelamento = $parcelas > 1 ? (string) \Illuminate\Support\Str::uuid() : null;

        $descricaoServico = trim((string) ($servico['descricao'] ?? 'Serviço de Terceiro'));
        $idFornecedor = $servico['id_fornecedor'] ?? null;

        $dataVencimentoBase = !empty($servico['conta_vencimento'])
            ? Carbon::parse($servico['conta_vencimento'])
            : Carbon::now();

        for ($i = 0; $i < $parcelas; $i++) {
            $dataVencimento = $dataVencimentoBase->copy()->addMonths($i);

            $valorEfetivo = $valorParcela;
            if ($i === $parcelas - 1) {
                $valorEfetivo = $valorTotal - ($valorParcela * ($parcelas - 1));
            }

            ContasAPagar::create($this->montarDadosContaPagar([
                'id_empresa' => $idEmpresa,
                'id_fornecedores' => $idFornecedor,
                'descricao' => "Locação #{$locacao->numero_contrato} - {$descricaoServico}" . ($parcelas > 1 ? " (Parcela " . ($i + 1) . "/{$parcelas})" : ''),
                'valor_total' => $valorEfetivo,
                'data_emissao' => now(),
                'data_vencimento' => $dataVencimento->format('Y-m-d'),
                'status' => 'pendente',
                'id_locacao' => $locacao->id_locacao,
                'id_origem' => $locacao->id_locacao,
                'id_parcelamento' => $idParcelamento,
                'origem' => 'servico',
                'observacoes' => 'Gerado automaticamente para serviço de terceiro na locação',
                'numero_parcela' => $i + 1,
                'total_parcelas' => $parcelas,
            ]));
        }
    }

    private function gerarContaPagarDespesaLocacao(LocacaoDespesa $despesaLocacao, Locacao $locacao, $idEmpresa, array $dadosEntrada = []): void
    {
        $valorTotal = $this->parseDecimal($dadosEntrada['valor'] ?? $despesaLocacao->valor ?? 0);
        if ($valorTotal <= 0) {
            return;
        }

        $parcelas = max(1, (int) ($dadosEntrada['conta_parcelas'] ?? 1));
        $valorParcela = round($valorTotal / $parcelas, 2);
        $idParcelamento = $parcelas > 1 ? (string) \Illuminate\Support\Str::uuid() : null;

        $dataVencimentoBase = !empty($dadosEntrada['conta_vencimento'])
            ? Carbon::parse($dadosEntrada['conta_vencimento'])
            : (!empty($despesaLocacao->data_despesa)
                ? Carbon::parse($despesaLocacao->data_despesa)
                : Carbon::now());

        $descricaoDespesa = trim((string) ($despesaLocacao->descricao ?? 'Despesa da Locação'));

        for ($i = 0; $i < $parcelas; $i++) {
            $dataVencimento = $dataVencimentoBase->copy()->addMonths($i);

            $valorEfetivo = $valorParcela;
            if ($i === $parcelas - 1) {
                $valorEfetivo = $valorTotal - ($valorParcela * ($parcelas - 1));
            }

            ContasAPagar::create($this->montarDadosContaPagar([
                'id_empresa' => $idEmpresa,
                'descricao' => "Locação #{$locacao->numero_contrato} - {$descricaoDespesa}" . ($parcelas > 1 ? " (Parcela " . ($i + 1) . "/{$parcelas})" : ''),
                'valor_total' => $valorEfetivo,
                'data_emissao' => now(),
                'data_vencimento' => $dataVencimento->format('Y-m-d'),
                'status' => 'pendente',
                'id_locacao' => $locacao->id_locacao,
                'id_origem' => $locacao->id_locacao,
                'id_parcelamento' => $idParcelamento,
                'origem' => 'compra',
                'observacoes' => 'Gerado automaticamente para despesa da locação',
                'numero_parcela' => $i + 1,
                'total_parcelas' => $parcelas,
            ]));
        }
    }

    private function montarDadosContaPagar(array $dados): array
    {
        static $colunasDisponiveis = null;

        if ($colunasDisponiveis === null) {
            $colunasDisponiveis = array_flip(Schema::getColumnListing('contas_a_pagar'));
        }

        $payload = [];
        foreach ($dados as $coluna => $valor) {
            if ($coluna === 'origem') {
                $valor = $this->normalizarOrigemContaPagar($valor);
            }

            if (isset($colunasDisponiveis[$coluna])) {
                $payload[$coluna] = $valor;
            }
        }

        return $payload;
    }

    private function normalizarOrigemContaPagar(?string $origem): ?string
    {
        if ($origem === null || $origem === '') {
            return $origem;
        }

        static $metaOrigem = null;

        if ($metaOrigem === null) {
            $metaOrigem = [
                'enum' => null,
                'max' => null,
            ];

            try {
                $coluna = DB::selectOne("SHOW COLUMNS FROM contas_a_pagar LIKE 'origem'");

                if ($coluna && isset($coluna->Type)) {
                    $tipo = (string) $coluna->Type;

                    if (preg_match('/^enum\((.*)\)$/i', $tipo, $matches)) {
                        $itens = str_getcsv($matches[1], ',', "'", "\\");
                        $metaOrigem['enum'] = array_values(array_filter(array_map('trim', $itens)));
                    } elseif (preg_match('/^(?:var)?char\((\d+)\)/i', $tipo, $matches)) {
                        $metaOrigem['max'] = (int) $matches[1];
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (is_array($metaOrigem['enum']) && count($metaOrigem['enum']) > 0) {
            if (in_array($origem, $metaOrigem['enum'], true)) {
                return $origem;
            }

            $mapeamentoPreferencial = [
                'locacao_servico_terceiro' => 'servico',
                'locacao_despesa' => 'compra',
                'locacao_terceiro' => 'locacao_terceiro',
            ];

            if (isset($mapeamentoPreferencial[$origem]) && in_array($mapeamentoPreferencial[$origem], $metaOrigem['enum'], true)) {
                return $mapeamentoPreferencial[$origem];
            }

            foreach (['locacao_terceiro', 'servico', 'compra', 'manual'] as $fallback) {
                if (in_array($fallback, $metaOrigem['enum'], true)) {
                    return $fallback;
                }
            }

            foreach ($metaOrigem['enum'] as $opcao) {
                if (stripos($opcao, 'locacao') === 0) {
                    return $opcao;
                }
            }

            return $metaOrigem['enum'][0];
        }

        if (!empty($metaOrigem['max']) && mb_strlen($origem) > $metaOrigem['max']) {
            return mb_substr($origem, 0, $metaOrigem['max']);
        }

        return $origem;
    }

    private function aplicarFiltrosBaseListagemLocacao(array $filters, ?int $idEmpresa)
    {
        $query = Locacao::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['cliente'])
            ->with([
                'produtos:id_produto_locacao,id_locacao,quantidade,preco_unitario,preco_total,valor_fechado,data_inicio,data_fim',
                'produtosTerceiros:id_produto_terceiros_locacao,id_locacao,quantidade,preco_unitario,valor_total,valor_fechado',
                'servicos:id_locacao_servico,id_locacao,quantidade,preco_unitario,valor_total',
                'despesas:id_locacao_despesa,id_locacao,valor',
            ])
            ->withSum('produtos as total_produtos_proprios', 'preco_total')
            ->withSum('produtosTerceiros as total_produtos_terceiros', 'valor_total')
            ->withSum('servicos as total_servicos', 'valor_total')
            ->withSum('despesas as total_despesas', 'valor');

        if (!empty($filters['id_cliente'])) {
            $query->where('id_cliente', $filters['id_cliente']);
        }

        if (!empty($filters['id_usuario'])) {
            $query->where('id_usuario', $filters['id_usuario']);
        }

        if (!empty($filters['id_produto'])) {
            $query->whereHas('produtos', function ($q) use ($filters) {
                $q->where('id_produto', $filters['id_produto']);
            });
        }

        if (!empty($filters['codigo'])) {
            $codigo = trim((string) $filters['codigo']);
            $query->where('numero_contrato', 'like', "%{$codigo}%");
        }

        if (!empty($filters['data_inicio'])) {
            $query->whereDate('data_inicio', '>=', $filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $query->whereDate('data_fim', '<=', $filters['data_fim']);
        }

        if (!empty($filters['busca'])) {
            $query->buscar($filters['busca']);
        }

        return $query;
    }

    private function contarContratosPorAba(int $idEmpresa, string $aba, Carbon $agora, array $filters = []): int
    {
        $query = $this->aplicarFiltrosBaseListagemLocacao($filters, $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'medicao'])
            ->whereDoesntHave('faturamentos', function ($q) {
                $q->where('origem', 'faturamento_medicao');
            });

        $aba = $this->normalizarAbaContratos($aba);

        if ($aba === 'todos') {
            return (int) $query->count();
        }

        $this->aplicarFiltroAbaContratos($query, $aba, $agora);

        return (int) $query->count();
    }

    private function somarValoresContratosPorAba(int $idEmpresa, string $aba, Carbon $agora, array $filters = []): float
    {
        $query = $this->aplicarFiltrosBaseListagemLocacao($filters, $idEmpresa)
            ->whereNotIn('status', ['orcamento', 'medicao'])
            ->whereDoesntHave('faturamentos', function ($q) {
                $q->where('origem', 'faturamento_medicao');
            });

        $aba = $this->normalizarAbaContratos($aba);

        if ($aba !== 'todos') {
            $this->aplicarFiltroAbaContratos($query, $aba, $agora);
        }

        $locacoes = $query->get();
        $total = 0.0;

        foreach ($locacoes as $locacao) {
            $total += $this->calcularValorTotalListagem($locacao);
        }

        return max(0, $total);
    }

    private function normalizarAbaContratos(string $aba): string
    {
        return in_array($aba, ['ativos', 'vencidos', 'futuros', 'encerrados', 'todos'], true)
            ? $aba
            : 'ativos';
    }

    private function aplicarFiltroAbaContratos($query, string $aba, Carbon $agora): void
    {
        $aba = $this->normalizarAbaContratos($aba);

        if ($aba === 'todos') {
            return;
        }

        if ($aba === 'encerrados') {
            $query->whereIn('status', ['encerrado', 'cancelado', 'cancelada']);
            return;
        }

        if ($aba === 'futuros') {
            $query->where('status', 'aprovado')
                ->where(function ($q) use ($agora) {
                    $q->whereDate('data_inicio', '>', $agora->toDateString())
                        ->orWhere(function ($q2) use ($agora) {
                            $q2->whereDate('data_inicio', '=', $agora->toDateString())
                                ->whereRaw("COALESCE(hora_inicio, '00:00:00') > ?", [$agora->format('H:i:s')]);
                        });
                });
            return;
        }

        if ($aba === 'vencidos') {
            $query->where('status', 'aprovado')
                ->where(function ($q) use ($agora) {
                    $q->whereDate('data_fim', '<', $agora->toDateString())
                        ->orWhere(function ($q2) use ($agora) {
                            $q2->whereDate('data_fim', '=', $agora->toDateString())
                                ->whereRaw("COALESCE(hora_fim, '23:59:59') < ?", [$agora->format('H:i:s')]);
                        });
                });
            return;
        }

        $query->where('status', 'aprovado')
            ->where(function ($q) use ($agora) {
                $q->whereDate('data_inicio', '<', $agora->toDateString())
                    ->orWhere(function ($q2) use ($agora) {
                        $q2->whereDate('data_inicio', '=', $agora->toDateString())
                            ->whereRaw("COALESCE(hora_inicio, '00:00:00') <= ?", [$agora->format('H:i:s')]);
                    });
            })
            ->where(function ($q) use ($agora) {
                $q->whereDate('data_fim', '>', $agora->toDateString())
                    ->orWhere(function ($q2) use ($agora) {
                        $q2->whereDate('data_fim', '=', $agora->toDateString())
                            ->whereRaw("COALESCE(hora_fim, '23:59:59') >= ?", [$agora->format('H:i:s')]);
                    });
                });
    }

    private function calcularValorTotalListagem(Locacao $locacao): float
    {
        $totalBase = $this->calcularValorBaseLocacao($locacao);
        return $this->calcularValorFinalComAjustes($totalBase, $locacao);
    }

    private function aplicarTotaisListagemLocacao(Locacao $locacao): void
    {
        $subtotalProdutos = $this->calcularSubtotalProdutosLocacao($locacao);
        $subtotalServicos = $this->calcularSubtotalServicosLocacao($locacao);
        $subtotalDespesas = $this->calcularSubtotalDespesasLocacao($locacao);
        $totalBase = $subtotalProdutos + $subtotalServicos;

        $locacao->subtotal_produtos_listagem = $subtotalProdutos;
        $locacao->subtotal_servicos_listagem = $subtotalServicos;
        $locacao->subtotal_despesas_listagem = $subtotalDespesas;
        $locacao->valor_total_base_listagem = $totalBase;
        $locacao->valor_total_listagem = $this->calcularValorFinalComAjustes($totalBase, $locacao);
    }

    private function sincronizarTotaisLocacao(Locacao $locacao): void
    {
        $locacao->loadMissing([
            'produtos:id_produto_locacao,id_locacao,quantidade,preco_unitario,preco_total,valor_fechado,data_inicio,data_fim',
            'produtosTerceiros:id_produto_terceiros_locacao,id_locacao,quantidade,preco_unitario,valor_total,valor_fechado',
            'servicos:id_locacao_servico,id_locacao,quantidade,preco_unitario,valor_total',
            'despesas:id_locacao_despesa,id_locacao,valor',
        ]);

        $totalBase = $this->calcularValorBaseLocacao($locacao);
        $locacao->valor_total = max(0, $totalBase);
        $locacao->valor_final = $this->calcularValorFinalComAjustes($totalBase, $locacao);
        $locacao->save();
    }

    private function calcularValorBaseLocacao(Locacao $locacao): float
    {
        return $this->calcularSubtotalProdutosLocacao($locacao)
            + $this->calcularSubtotalServicosLocacao($locacao);
    }

    private function calcularValorFinalComAjustes(float $valorBase, Locacao $locacao): float
    {
        $total = $valorBase
            + (float) ($locacao->valor_frete ?? 0)
            + (float) ($locacao->valor_acrescimo ?? 0)
            + (float) ($locacao->valor_imposto ?? 0)
            + (float) ($locacao->valor_despesas_extras ?? 0)
            - (float) ($locacao->valor_desconto ?? 0);

        return max(0, $total);
    }

    private function calcularSubtotalProdutosLocacao(Locacao $locacao): float
    {
        $subtotal = 0.0;
        $locacaoPorHora = $this->ehLocacaoPorHoraLocacao($locacao);

        foreach (($locacao->produtos ?? collect()) as $produto) {
            $quantidade = max(1, (int) ($produto->quantidade ?? 1));
            $precoUnitario = (float) ($produto->preco_unitario ?? 0);
            $valorFechado = (bool) ($produto->valor_fechado ?? false);
            $fatorPeriodoCalculado = $this->calcularQuantidadePeriodoCobranca(
                $produto->data_inicio ?? $locacao->data_inicio,
                $produto->hora_inicio ?? $locacao->hora_inicio,
                $produto->data_fim ?? $locacao->data_fim,
                $produto->hora_fim ?? $locacao->hora_fim,
                $locacaoPorHora,
                (int) ($locacao->quantidade_dias ?? 1)
            );
            $fatorPeriodo = $this->obterFatorFinanceiroItem($locacaoPorHora, $valorFechado, $fatorPeriodoCalculado);

            $subtotal += $precoUnitario * $quantidade * $fatorPeriodo;
        }

        foreach (($locacao->produtosTerceiros ?? collect()) as $produtoTerceiro) {
            $quantidade = max(1, (int) ($produtoTerceiro->quantidade ?? 1));
            $precoUnitario = (float) ($produtoTerceiro->preco_unitario ?? 0);
            $valorFechado = (bool) ($produtoTerceiro->valor_fechado ?? false);
            $fatorPeriodoCalculado = $this->calcularQuantidadePeriodoCobranca(
                $locacao->data_inicio,
                $locacao->hora_inicio,
                $locacao->data_fim,
                $locacao->hora_fim,
                $locacaoPorHora,
                max(1, (int) ($locacao->quantidade_dias ?? 1))
            );
            $fatorPeriodo = $this->obterFatorFinanceiroItem($locacaoPorHora, $valorFechado, $fatorPeriodoCalculado);

            $subtotal += $precoUnitario * $quantidade * $fatorPeriodo;
        }

        return max(0, $subtotal);
    }

    private function calcularSubtotalServicosLocacao(Locacao $locacao): float
    {
        $subtotal = 0.0;

        foreach (($locacao->servicos ?? collect()) as $servico) {
            $quantidade = max(1, (int) ($servico->quantidade ?? 1));
            $precoUnitario = (float) ($servico->preco_unitario ?? 0);
            $subtotal += $precoUnitario * $quantidade;
        }

        return max(0, $subtotal);
    }

    private function obterFatorFinanceiroItem(bool $locacaoPorHora, bool $valorFechado, int $fatorPeriodoCalculado): int
    {
        if ($valorFechado) {
            return 1;
        }

        return max(1, $fatorPeriodoCalculado);
    }

    private function calcularSubtotalDespesasLocacao(Locacao $locacao): float
    {
        return max(0, (float) (($locacao->despesas ?? collect())->sum('valor') ?? 0));
    }

    private function calcularValorMedicaoPeriodoBrutoLocacao(Locacao $locacao, Carbon $inicioCorte, Carbon $fimCorte): float
    {
        $locacao->loadMissing(['produtos']);

        if ($fimCorte->lt($inicioCorte)) {
            return 0.0;
        }

        $total = 0.0;

        foreach (($locacao->produtos ?? collect()) as $item) {
            $inicioItem = $item->data_inicio
                ? Carbon::parse((string) $item->data_inicio)->startOfDay()
                : null;

            if (!$inicioItem) {
                continue;
            }

            $itemRetornado = (int) ($item->estoque_status ?? 0) === 2
                || !in_array($item->status_retorno, [null, '', 'pendente'], true);

            $fimItem = $itemRetornado
                ? (!empty($item->data_fim)
                    ? Carbon::parse((string) $item->data_fim)->endOfDay()
                    : $fimCorte->copy())
                : $fimCorte->copy();

            $inicioEfetivo = $inicioItem->copy()->max($inicioCorte);
            $fimEfetivo = $fimItem->copy()->min($fimCorte);

            if ($fimEfetivo->lt($inicioEfetivo)) {
                continue;
            }

            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $precoUnitario = (float) ($item->preco_unitario ?? 0);

            $diasPeriodo = $this->calcularDiasMedicaoPeriodo($inicioEfetivo, $fimEfetivo);
            $total += $precoUnitario * $quantidade * $diasPeriodo;
        }

        return round(max(0, $total), 2);
    }

    private function calcularValorMedicaoPeriodoLocacao(Locacao $locacao, Carbon $inicioCorte, Carbon $fimCorte): float
    {
        $valorBruto = $this->calcularValorMedicaoPeriodoBrutoLocacao($locacao, $inicioCorte, $fimCorte);
        return $this->aplicarLimiteValorMedicaoLocacao($locacao, $valorBruto);
    }

    private function calcularValorPrevistoHojeLocacao(Locacao $locacao, Carbon $inicioCorte, Carbon $fimCorte): float
    {
        $locacao->loadMissing(['produtos']);

        if ($fimCorte->lt($inicioCorte)) {
            return 0.0;
        }

        $total = 0.0;

        foreach (($locacao->produtos ?? collect()) as $item) {
            $inicioItem = $item->data_inicio
                ? Carbon::parse((string) $item->data_inicio)->startOfDay()
                : null;

            if (!$inicioItem) {
                continue;
            }

            $itemRetornado = (int) ($item->estoque_status ?? 0) === 2
                || !in_array($item->status_retorno, [null, '', 'pendente'], true);

            $fimItem = $itemRetornado
                ? (!empty($item->data_fim)
                    ? Carbon::parse((string) $item->data_fim)->endOfDay()
                    : $fimCorte->copy())
                : $fimCorte->copy();

            $inicioEfetivo = $inicioItem->copy()->max($inicioCorte);
            $fimEfetivo = $fimItem->copy()->min($fimCorte);

            if ($fimEfetivo->lt($inicioEfetivo)) {
                continue;
            }

            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $precoUnitario = (float) ($item->preco_unitario ?? 0);
            $diasPeriodo = $this->calcularDiasMedicaoPeriodo($inicioEfetivo, $fimEfetivo);
            $total += $precoUnitario * $quantidade * $diasPeriodo;
        }

        return $this->aplicarLimiteValorMedicaoLocacao($locacao, $total);
    }

    private function obterValorLimiteMedicaoLocacao(Locacao $locacao): float
    {
        if (!$this->hasColunaLocacao('valor_limite_medicao')) {
            return 0.0;
        }

        return round(max(0, (float) ($locacao->valor_limite_medicao ?? 0)), 2);
    }

    private function obterTotalFaturadoMedicaoLocacao(int $idLocacao, int $idEmpresa): float
    {
        return round(max(0, (float) FaturamentoLocacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $idLocacao)
            ->where('origem', 'faturamento_medicao')
            ->sum('valor_total')), 2);
    }

    private function obterSaldoLimiteMedicaoLocacao(Locacao $locacao, ?float $valorFaturado = null): ?float
    {
        $valorLimite = $this->obterValorLimiteMedicaoLocacao($locacao);
        if ($valorLimite <= 0) {
            return null;
        }

        if ($valorFaturado === null) {
            $valorFaturado = $this->obterTotalFaturadoMedicaoLocacao((int) $locacao->id_locacao, (int) $locacao->id_empresa);
        }

        return round(max(0, $valorLimite - max(0, (float) $valorFaturado)), 2);
    }

    private function obterSaldoDisponivelEnvioMedicaoLocacao(
        Locacao $locacao,
        Carbon $inicioCorte,
        Carbon $fimCorte,
        ?float $valorFaturado = null
    ): ?float {
        $saldoLimite = $this->obterSaldoLimiteMedicaoLocacao($locacao, $valorFaturado);

        if ($saldoLimite === null) {
            return null;
        }

        $valorAbertoBruto = $this->calcularValorMedicaoPeriodoBrutoLocacao($locacao, $inicioCorte, $fimCorte);
        return round(max(0, $saldoLimite - $valorAbertoBruto), 2);
    }

    private function aplicarLimiteValorMedicaoLocacao(Locacao $locacao, float $valorCalculado): float
    {
        $valorCalculado = round(max(0, $valorCalculado), 2);
        $saldoLimite = $this->obterSaldoLimiteMedicaoLocacao($locacao);

        if ($saldoLimite === null) {
            return $valorCalculado;
        }

        return round(min($valorCalculado, $saldoLimite), 2);
    }

    private function calcularDiasCobranca($dataInicio, $dataFim, int $fallback = 1): int
    {
        if (empty($dataInicio) || empty($dataFim)) {
            return max(1, $fallback);
        }

        try {
            $inicio = Carbon::parse($dataInicio);
            $fim = Carbon::parse($dataFim);
            return max(1, $inicio->diffInDays($fim) + 1);
        } catch (\Throwable $e) {
            return max(1, $fallback);
        }
    }

    private function ehLocacaoPorHoraLocacao(Locacao $locacao): bool
    {
        if ($this->hasColunaLocacao('locacao_por_hora')) {
            return (bool) ($locacao->locacao_por_hora ?? false);
        }

        if (empty($locacao->data_inicio) || empty($locacao->data_fim)) {
            return false;
        }

        $dataInicio = $locacao->data_inicio instanceof \DateTimeInterface
            ? $locacao->data_inicio->format('Y-m-d')
            : Carbon::parse((string) $locacao->data_inicio)->toDateString();

        $dataFim = $locacao->data_fim instanceof \DateTimeInterface
            ? $locacao->data_fim->format('Y-m-d')
            : Carbon::parse((string) $locacao->data_fim)->toDateString();

        $diasInclusivos = max(1, Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1);
        $quantidadePeriodo = max(1, (int) ($locacao->quantidade_dias ?? 1));

        return $quantidadePeriodo > $diasInclusivos;
    }

    private function calcularQuantidadePeriodoCobranca(
        $dataInicio,
        ?string $horaInicio,
        $dataFim,
        ?string $horaFim,
        bool $porHora,
        int $fallback = 1
    ): int {
        if (empty($dataInicio) || empty($dataFim)) {
            return max(1, $fallback);
        }

        try {
            $dataInicioNormalizada = $dataInicio instanceof \DateTimeInterface
                ? $dataInicio->format('Y-m-d')
                : Carbon::parse((string) $dataInicio)->toDateString();

            $dataFimNormalizada = $dataFim instanceof \DateTimeInterface
                ? $dataFim->format('Y-m-d')
                : Carbon::parse((string) $dataFim)->toDateString();

            if ($porHora) {
                $horaInicioNormalizada = Carbon::parse((string) ($horaInicio ?: '00:00:00'))->format('H:i:s');
                $horaFimNormalizada = Carbon::parse((string) ($horaFim ?: '23:59:59'))->format('H:i:s');

                $inicio = Carbon::createFromFormat('Y-m-d H:i:s', $dataInicioNormalizada . ' ' . $horaInicioNormalizada);
                $fim = Carbon::createFromFormat('Y-m-d H:i:s', $dataFimNormalizada . ' ' . $horaFimNormalizada);

                if ($fim->lt($inicio)) {
                    return max(1, $fallback);
                }

                return max(1, (int) ceil($inicio->diffInMinutes($fim) / 60));
            }

            $inicio = Carbon::parse($dataInicioNormalizada);
            $fim = Carbon::parse($dataFimNormalizada);
            return max(1, $inicio->diffInDays($fim) + 1);
        } catch (\Throwable $e) {
            return max(1, $fallback);
        }
    }

    private function obterNumeroOrcamentoOrigemLocacao(Locacao $locacao): int
    {
        if ($this->hasColunaLocacao('numero_orcamento_origem') && !empty($locacao->numero_orcamento_origem)) {
            return (int) $locacao->numero_orcamento_origem;
        }

        if ($this->hasColunaLocacao('numero_orcamento') && !empty($locacao->numero_orcamento)) {
            return (int) $locacao->numero_orcamento;
        }

        $codigo = trim((string) ($locacao->numero_contrato ?? ''));
        if (is_numeric($codigo)) {
            return (int) $codigo;
        }

        if (preg_match('/(\d{1,})/', $codigo, $matches)) {
            $numero = (int) ltrim($matches[1], '0');
            return $numero > 0 ? $numero : (int) $matches[1];
        }

        return Locacao::gerarProximoNumeroOrcamento((int) $locacao->id_empresa, Locacao::usaNumeracaoUnificada((int) $locacao->id_empresa));
    }

    private function hasColunaLocacao(string $coluna): bool
    {
        static $colunas = null;

        if ($colunas === null) {
            $colunas = Schema::hasTable('locacao')
                ? Schema::getColumnListing('locacao')
                : [];
        }

        return in_array($coluna, $colunas, true);
    }

    private function hasColunaModeloContrato(string $coluna): bool
    {
        static $colunas = null;

        if ($colunas === null) {
            $colunas = Schema::hasTable('locacao_modelos_contrato')
                ? Schema::getColumnListing('locacao_modelos_contrato')
                : [];
        }

        return in_array($coluna, $colunas, true);
    }

    private function normalizarTipoModeloDocumento(?string $tipoDocumento): string
    {
        $tipoNormalizado = strtolower(trim((string) $tipoDocumento));

        return in_array($tipoNormalizado, ['contrato', 'orcamento', 'medicao'], true)
            ? $tipoNormalizado
            : 'contrato';
    }

    private function consultarModelosDocumento(int $idEmpresa, string $tipoDocumento = 'contrato')
    {
        $query = LocacaoModeloContrato::query()
            ->where('id_empresa', $idEmpresa)
            ->where('ativo', true);

        return $this->aplicarFiltroTipoModeloContrato($query, $tipoDocumento);
    }

    private function aplicarFiltroTipoModeloContrato($query, string $tipoDocumento)
    {
        $tipo = $this->normalizarTipoModeloDocumento($tipoDocumento);

        if ($this->hasColunaModeloContrato('tipo_modelo')) {
            if ($tipo === 'medicao') {
                if ($this->hasColunaModeloContrato('usa_medicao')) {
                    return $query->where(function ($sub) {
                        $sub->where('tipo_modelo', 'medicao')
                            ->orWhere(function ($legacy) {
                                $legacy->whereNull('tipo_modelo')
                                    ->where('usa_medicao', true);
                            });
                    });
                }

                return $query->where('tipo_modelo', 'medicao');
            }

            if ($tipo === 'orcamento') {
                return $query->where('tipo_modelo', 'orcamento');
            }

            return $query->where(function ($sub) {
                $sub->whereNull('tipo_modelo')
                    ->orWhere('tipo_modelo', '')
                    ->orWhere('tipo_modelo', 'contrato');

                if ($this->hasColunaModeloContrato('usa_medicao')) {
                    $sub->where(function ($legacy) {
                        $legacy->whereNull('usa_medicao')
                            ->orWhere('usa_medicao', false);
                    });
                }
            });
        }

        if ($this->hasColunaModeloContrato('usa_medicao')) {
            if ($tipo === 'medicao') {
                return $query->where('usa_medicao', true);
            }

            return $query->where(function ($sub) {
                $sub->whereNull('usa_medicao')
                    ->orWhere('usa_medicao', false);
            });
        }

        return $query;
    }

    private function validarPeriodoDataHora(
        ?string $dataInicio,
        ?string $horaInicio,
        ?string $dataFim,
        ?string $horaFim,
        string $contexto = 'Período'
    ): void {
        if (!$dataInicio || !$dataFim) {
            return;
        }

        $inicio = Carbon::parse($dataInicio . ' ' . ($horaInicio ?: '00:00'));
        $fim = Carbon::parse($dataFim . ' ' . ($horaFim ?: '23:59'));

        if ($fim->lt($inicio)) {
            throw ValidationException::withMessages([
                'data_fim' => ["{$contexto}: a data/hora de fim não pode ser anterior à data/hora de início."],
            ]);
        }
    }

    private function validarPeriodoProdutoDentroContrato(
        ?string $dataInicioProduto,
        ?string $horaInicioProduto,
        ?string $dataFimProduto,
        ?string $horaFimProduto,
        ?string $dataInicioContrato,
        ?string $horaInicioContrato,
        ?string $dataFimContrato,
        ?string $horaFimContrato,
        string $contexto = 'Produto'
    ): void {
        if (!$dataInicioProduto || !$dataFimProduto || !$dataInicioContrato || !$dataFimContrato) {
            return;
        }

        $inicioProduto = Carbon::parse($dataInicioProduto . ' ' . ($horaInicioProduto ?: '00:00'));
        $fimProduto = Carbon::parse($dataFimProduto . ' ' . ($horaFimProduto ?: '23:59'));
        $inicioContrato = Carbon::parse($dataInicioContrato . ' ' . ($horaInicioContrato ?: '00:00'));
        $fimContrato = Carbon::parse($dataFimContrato . ' ' . ($horaFimContrato ?: '23:59'));

        if ($inicioProduto->lt($inicioContrato)) {
            throw ValidationException::withMessages([
                'produtos' => ["{$contexto}: data/hora de início do item não pode ser menor que a data/hora de início do contrato."],
            ]);
        }

        if ($fimProduto->gt($fimContrato)) {
            throw ValidationException::withMessages([
                'produtos' => ["{$contexto}: data/hora de fim do item não pode ser maior que a data/hora de fim do contrato."],
            ]);
        }
    }

    private function obterDataHoraInicioEstoqueItem(Locacao $locacao, LocacaoProduto $item): Carbon
    {
        $normalizarData = static function ($data): ?string {
            if ($data instanceof \DateTimeInterface) {
                return $data->format('Y-m-d');
            }

            $valor = trim((string) ($data ?? ''));
            return $valor !== '' ? $valor : null;
        };

        $dataInicio = $normalizarData($item->data_inicio)
            ?? $normalizarData($item->data_contrato);
        $horaInicio = trim((string) ($item->hora_inicio ?? $item->hora_contrato ?? ''));

        if (!$dataInicio) {
            $datasEfetivasLocacao = $locacao->getDatasEfetivasEstoque();
            $dataInicio = $normalizarData($datasEfetivasLocacao['data_inicio'] ?? null)
                ?? now()->format('Y-m-d');
            $horaInicio = $horaInicio !== ''
                ? $horaInicio
                : trim((string) ($datasEfetivasLocacao['hora_inicio'] ?? '00:00'));
        }

        if ($horaInicio === '') {
            $horaInicio = '00:00';
        }

        return Carbon::parse($dataInicio . ' ' . $horaInicio);
    }

    private function processarSaidasProdutosElegiveis(Locacao $locacao, ?int $idUsuario = null): int
    {
        if (!in_array((string) $locacao->status, ['aprovado', 'em_andamento', 'atrasada', 'retirada'], true)) {
            return 0;
        }

        $agora = now();
        $itensProcessados = 0;

        foreach ($locacao->produtos()->with(['produto', 'patrimonio', 'locacao'])->get() as $produtoLocacao) {
            if ((int) ($produtoLocacao->estoque_status ?? 0) !== 0) {
                continue;
            }

            $dataHoraInicioItem = $this->obterDataHoraInicioEstoqueItem($locacao, $produtoLocacao);
            if ($dataHoraInicioItem->gt($agora)) {
                continue;
            }

            $this->estoqueService->registrarSaidaLocacao($produtoLocacao, $idUsuario);
            $produtoLocacao->estoque_status = 1;
            $produtoLocacao->save();
            $itensProcessados++;
        }

        return $itensProcessados;
    }

    private function reverterSaidasProdutosComInicioFuturo(Locacao $locacao, int $idUsuario): int
    {
        $agora = now();
        $itensRevertidos = 0;

        foreach ($locacao->produtos()->with(['produto', 'patrimonio', 'locacao'])->get() as $produtoLocacao) {
            if ((int) ($produtoLocacao->estoque_status ?? 0) !== 1) {
                continue;
            }

            $dataHoraInicioItem = $this->obterDataHoraInicioEstoqueItem($locacao, $produtoLocacao);
            if ($dataHoraInicioItem->lte($agora)) {
                continue;
            }

            $this->estoqueService->registrarRetornoLocacao(
                $produtoLocacao,
                'devolvido',
                'Retorno automático ao editar item com início futuro',
                $idUsuario
            );

            $produtoLocacao->estoque_status = 0;
            $produtoLocacao->status_retorno = 'pendente';
            $produtoLocacao->save();
            $itensRevertidos++;
        }

        return $itensRevertidos;
    }

    private function obterDadosDisponibilidadeTrocaProduto(
        Locacao $locacao,
        LocacaoProduto $itemLocacao,
        Produto $produtoNovo,
        int $idEmpresa
    ): array {
        $inicioItem = $this->obterDataHoraInicioItemLocacao($itemLocacao, $locacao);
        $fimItem = $this->obterDataHoraFimItemLocacao($itemLocacao, $locacao);

        if ($fimItem->lt($inicioItem)) {
            $fimItem = $inicioItem->copy();
        }

        $produtoNovoUsaPatrimonio = Patrimonio::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_produto', $produtoNovo->id_produto)
            ->where('status', 'Ativo')
            ->exists();

        $totalPatrimoniosAtivosProduto = 0;
        if ($produtoNovoUsaPatrimonio) {
            $totalPatrimoniosAtivosProduto = (int) Patrimonio::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_produto', $produtoNovo->id_produto)
                ->where('status', 'Ativo')
                ->count();
        }

        $disponibilidade = $this->estoqueService->calcularDisponibilidade(
            (int) $produtoNovo->id_produto,
            $idEmpresa,
            $inicioItem->toDateString(),
            $fimItem->toDateString(),
            $inicioItem->format('H:i:s'),
            $fimItem->format('H:i:s'),
            (int) $locacao->id_locacao,
            'data_item'
        );

        $patrimoniosEmUsoNoContrato = LocacaoProduto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->whereNotNull('id_patrimonio')
            ->where('id_produto_locacao', '!=', $itemLocacao->id_produto_locacao)
            ->where(function ($query) {
                $query->whereNull('status_retorno')
                    ->orWhere('status_retorno', '')
                    ->orWhere('status_retorno', 'pendente');
            })
            ->where(function ($query) {
                $query->whereNull('estoque_status')
                    ->orWhere('estoque_status', '!=', 2);
            })
            ->pluck('id_patrimonio')
            ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
            ->filter(fn ($idPatrimonio) => $idPatrimonio > 0)
            ->unique()
            ->values();

        $patrimoniosDisponiveisFiltrados = collect($disponibilidade['patrimonios_disponiveis'] ?? [])
            ->map(function ($patrimonio) {
                $idPatrimonio = (int) ($patrimonio['id_patrimonio'] ?? 0);
                $codigo = (string) ($patrimonio['numero_serie'] ?? $patrimonio['codigo'] ?? ('PAT-' . $idPatrimonio));

                return [
                    'id_patrimonio' => $idPatrimonio,
                    'codigo' => trim($codigo) !== '' ? $codigo : ('PAT-' . $idPatrimonio),
                    'status' => (string) ($patrimonio['status'] ?? 'Disponivel'),
                ];
            })
            ->filter(fn ($patrimonio) => (int) ($patrimonio['id_patrimonio'] ?? 0) > 0)
            ->reject(function ($patrimonio) use ($patrimoniosEmUsoNoContrato) {
                return $patrimoniosEmUsoNoContrato->contains((int) ($patrimonio['id_patrimonio'] ?? 0));
            })
            ->values();

        $disponibilidade['patrimonios_disponiveis'] = $patrimoniosDisponiveisFiltrados
            ->map(function ($patrimonio) {
                return [
                    'id_patrimonio' => (int) $patrimonio['id_patrimonio'],
                    'numero_serie' => $patrimonio['codigo'],
                    'status' => $patrimonio['status'],
                ];
            })
            ->values()
            ->all();

        $disponibilidade['patrimonios_em_uso_no_contrato'] = $patrimoniosEmUsoNoContrato->all();

        if ($produtoNovoUsaPatrimonio) {
            $disponibilidade['patrimonios_total'] = $totalPatrimoniosAtivosProduto;
            $disponibilidade['estoque_total'] = $totalPatrimoniosAtivosProduto;
            $disponibilidade['disponivel'] = $patrimoniosDisponiveisFiltrados->count();
        }

        return [
            'periodo' => [
                'data_inicio' => $inicioItem->toDateString(),
                'hora_inicio' => $inicioItem->format('H:i:s'),
                'data_fim' => $fimItem->toDateString(),
                'hora_fim' => $fimItem->format('H:i:s'),
                'por_hora' => $this->ehLocacaoPorHoraLocacao($locacao),
            ],
            'produto_usa_patrimonio' => $produtoNovoUsaPatrimonio,
            'disponibilidade' => $disponibilidade,
        ];
    }

    private function validarDisponibilidadeProdutos(
        array $produtos,
        int $idEmpresa,
        ?int $excluirLocacao = null,
        array $periodoPadrao = []
    ): void {
        foreach ($produtos as $item) {
            $idProduto = isset($item['id_produto']) ? (int)$item['id_produto'] : 0;
            $quantidade = isset($item['quantidade']) ? (int)$item['quantidade'] : 0;

            if ($idProduto <= 0 || $quantidade <= 0) {
                continue;
            }

            $dataInicio = $item['data_inicio'] ?? ($periodoPadrao['data_inicio'] ?? null);
            $horaInicio = $item['hora_inicio'] ?? ($periodoPadrao['hora_inicio'] ?? null);
            $dataFim = $item['data_fim'] ?? ($periodoPadrao['data_fim'] ?? null);
            $horaFim = $item['hora_fim'] ?? ($periodoPadrao['hora_fim'] ?? null);

            $this->validarPeriodoDataHora($dataInicio, $horaInicio, $dataFim, $horaFim, 'Período do produto');
            $this->validarPeriodoProdutoDentroContrato(
                $dataInicio,
                $horaInicio,
                $dataFim,
                $horaFim,
                $periodoPadrao['data_inicio'] ?? null,
                $periodoPadrao['hora_inicio'] ?? null,
                $periodoPadrao['data_fim'] ?? null,
                $periodoPadrao['hora_fim'] ?? null,
                'Produto'
            );

            if (!$dataInicio || !$dataFim) {
                continue;
            }

            $disponibilidade = $this->estoqueService->calcularDisponibilidade(
                $idProduto,
                $idEmpresa,
                $dataInicio,
                $dataFim,
                $horaInicio,
                $horaFim,
                $excluirLocacao,
                'data_item'
            );

            $disponivel = (int)($disponibilidade['disponivel'] ?? 0);
            if ($quantidade > $disponivel) {
                $produto = Produto::where('id_produto', $idProduto)
                    ->where('id_empresa', $idEmpresa)
                    ->first();
                $nomeProduto = $produto->nome ?? "Produto #{$idProduto}";

                throw ValidationException::withMessages([
                    'produtos' => ["Estoque insuficiente para {$nomeProduto} no período informado. Disponível: {$disponivel}. Solicitado: {$quantidade}."],
                ]);
            }
        }
    }

    private function validarVinculacaoPatrimoniosParaAprovacaoPayload(array $produtos, int $idEmpresa): void
    {
        if (empty($produtos)) {
            return;
        }

        $idsProdutos = collect($produtos)
            ->map(fn ($item) => (int) ($item['id_produto'] ?? 0))
            ->filter(fn ($idProduto) => $idProduto > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($idsProdutos)) {
            return;
        }

        $produtosPatrimoniais = Patrimonio::query()
            ->where('id_empresa', $idEmpresa)
            ->where('status', 'Ativo')
            ->whereIn('id_produto', $idsProdutos)
            ->pluck('id_produto')
            ->map(fn ($idProduto) => (int) $idProduto)
            ->unique()
            ->all();

        if (empty($produtosPatrimoniais)) {
            return;
        }

        $mapProdutosPatrimoniais = array_fill_keys($produtosPatrimoniais, true);
        $nomesProdutos = Produto::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('id_produto', $idsProdutos)
            ->pluck('nome', 'id_produto')
            ->all();

        foreach ($produtos as $item) {
            $idProduto = (int) ($item['id_produto'] ?? 0);
            $quantidade = max(0, (int) ($item['quantidade'] ?? 0));

            if ($idProduto <= 0 || $quantidade <= 0 || empty($mapProdutosPatrimoniais[$idProduto])) {
                continue;
            }

            $patrimoniosSelecionados = collect($item['patrimonios'] ?? [])
                ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
                ->filter(fn ($idPatrimonio) => $idPatrimonio > 0)
                ->unique()
                ->values();

            $nomeProduto = (string) ($nomesProdutos[$idProduto] ?? "Produto #{$idProduto}");

            if ($patrimoniosSelecionados->count() !== $quantidade) {
                throw new \Exception("Vincule {$quantidade} patrimônio(s) para {$nomeProduto} antes de aprovar.");
            }

            $idsPatrimoniosValidos = Patrimonio::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_produto', $idProduto)
                ->where('status', 'Ativo')
                ->whereIn('id_patrimonio', $patrimoniosSelecionados->all())
                ->pluck('id_patrimonio')
                ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
                ->all();

            if (count($idsPatrimoniosValidos) !== $patrimoniosSelecionados->count()) {
                throw new \Exception("Existe patrimônio inválido ou inativo vinculado em {$nomeProduto}. Revise os vínculos para aprovar.");
            }
        }
    }

    private function validarDisponibilidadeParaAprovacaoLocacao(Locacao $locacao, int $idEmpresa): void
    {
        $normalizarData = static function ($data): ?string {
            if ($data instanceof \DateTimeInterface) {
                return $data->format('Y-m-d');
            }

            $data = (string) ($data ?? '');
            return $data !== '' ? $data : null;
        };

        $datasEfetivas = $locacao->getDatasEfetivasEstoque();

        $itensLocacao = LocacaoProduto::query()
            ->where('id_locacao', $locacao->id_locacao)
            ->get([
                'id_produto',
                'id_patrimonio',
                'data_inicio',
                'hora_inicio',
                'data_fim',
                'hora_fim',
                'quantidade',
            ]);

        if ($itensLocacao->isEmpty()) {
            return;
        }

        $gruposItens = $itensLocacao->groupBy(function ($item) use ($normalizarData, $datasEfetivas) {
            return implode('|', [
                (int) ($item->id_produto ?? 0),
                $normalizarData($item->data_inicio) ?? ($datasEfetivas['data_inicio'] ?? ''),
                (string) ($item->hora_inicio ?? ($datasEfetivas['hora_inicio'] ?? '00:00')),
                $normalizarData($item->data_fim) ?? ($datasEfetivas['data_fim'] ?? ''),
                (string) ($item->hora_fim ?? ($datasEfetivas['hora_fim'] ?? '23:59')),
            ]);
        });

        $idsProdutos = $gruposItens->map(function ($grupo) {
            return (int) ($grupo->first()->id_produto ?? 0);
        })
            ->filter(fn ($idProduto) => $idProduto > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($idsProdutos)) {
            return;
        }

        $nomesProdutos = Produto::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('id_produto', $idsProdutos)
            ->pluck('nome', 'id_produto')
            ->all();

        $produtosPatrimoniais = Patrimonio::query()
            ->where('id_empresa', $idEmpresa)
            ->where('status', 'Ativo')
            ->whereIn('id_produto', $idsProdutos)
            ->pluck('id_produto')
            ->map(fn ($idProduto) => (int) $idProduto)
            ->unique()
            ->all();
        $mapProdutosPatrimoniais = array_fill_keys($produtosPatrimoniais, true);

        $produtosParaValidar = [];

        foreach ($gruposItens as $grupo) {
            $itemBase = $grupo->first();
            $idProduto = (int) ($itemBase->id_produto ?? 0);
            $quantidade = (int) $grupo->sum(function ($item) {
                return max(0, (int) ($item->quantidade ?? 0));
            });

            $dataInicio = $normalizarData($itemBase->data_inicio) ?? ($datasEfetivas['data_inicio'] ?? null);
            $horaInicio = (string) ($itemBase->hora_inicio ?? ($datasEfetivas['hora_inicio'] ?? '00:00'));
            $dataFim = $normalizarData($itemBase->data_fim) ?? ($datasEfetivas['data_fim'] ?? null);
            $horaFim = (string) ($itemBase->hora_fim ?? ($datasEfetivas['hora_fim'] ?? '23:59'));

            if ($idProduto <= 0 || $quantidade <= 0) {
                continue;
            }

            if (!empty($mapProdutosPatrimoniais[$idProduto])) {
                $nomeProduto = (string) ($nomesProdutos[$idProduto] ?? "Produto #{$idProduto}");

                $itensComPatrimonio = $grupo->filter(function ($item) {
                    return !empty($item->id_patrimonio);
                });

                $quantidadeComPatrimonio = (int) $itensComPatrimonio->sum(function ($item) {
                    return max(0, (int) ($item->quantidade ?? 0));
                });

                if ($quantidadeComPatrimonio !== $quantidade) {
                    throw new \Exception(
                        "Vincule todos os patrimônios de {$nomeProduto} no período {$dataInicio} a {$dataFim} antes de aprovar."
                    );
                }

                $idsPatrimonios = $itensComPatrimonio
                    ->pluck('id_patrimonio')
                    ->map(fn ($idPatrimonio) => (int) $idPatrimonio)
                    ->filter(fn ($idPatrimonio) => $idPatrimonio > 0)
                    ->values();

                if ($idsPatrimonios->count() !== $idsPatrimonios->unique()->count()) {
                    throw new \Exception("Existem patrimônios duplicados para {$nomeProduto}. Revise os vínculos antes de aprovar.");
                }

                if ($idsPatrimonios->isNotEmpty()) {
                    $quantidadePatrimoniosValidos = Patrimonio::query()
                        ->where('id_empresa', $idEmpresa)
                        ->where('id_produto', $idProduto)
                        ->where('status', 'Ativo')
                        ->whereIn('id_patrimonio', $idsPatrimonios->all())
                        ->count();

                    if ($quantidadePatrimoniosValidos !== $idsPatrimonios->count()) {
                        throw new \Exception("Há patrimônio inválido ou inativo em {$nomeProduto}. Revise os vínculos antes de aprovar.");
                    }
                }
            }

            $produtosParaValidar[] = [
                'id_produto' => $idProduto,
                'quantidade' => $quantidade,
                'data_inicio' => $dataInicio,
                'hora_inicio' => $horaInicio,
                'data_fim' => $dataFim,
                'hora_fim' => $horaFim,
            ];
        }

        if (empty($produtosParaValidar)) {
            return;
        }

        $this->validarDisponibilidadeProdutos(
            $produtosParaValidar,
            $idEmpresa,
            (int) $locacao->id_locacao,
            [
                'data_inicio' => $datasEfetivas['data_inicio'] ?? null,
                'hora_inicio' => $datasEfetivas['hora_inicio'] ?? '00:00',
                'data_fim' => $datasEfetivas['data_fim'] ?? null,
                'hora_fim' => $datasEfetivas['hora_fim'] ?? '23:59',
            ]
        );
    }

    private function registrarRetornoParcialQuantidadeProduto(LocacaoProduto $produtoLocacao, int $quantidadeRetorno, int $idUsuario): void
    {
        if ($quantidadeRetorno <= 0 || $produtoLocacao->id_patrimonio) {
            return;
        }

        if ((int) ($produtoLocacao->estoque_status ?? 0) !== 1) {
            return;
        }

        if (!$this->itemPendenteRetorno($produtoLocacao)) {
            return;
        }

        $produto = $produtoLocacao->produto;
        $locacao = $produtoLocacao->locacao;

        if (!$produto || !$locacao) {
            return;
        }

        ProdutoHistorico::registrar([
            'id_empresa' => $produtoLocacao->id_empresa,
            'id_produto' => $produtoLocacao->id_produto,
            'id_locacao' => $produtoLocacao->id_locacao,
            'id_cliente' => $locacao->id_cliente,
            'tipo_movimentacao' => 'retorno',
            'quantidade' => $quantidadeRetorno,
            'estoque_anterior' => $produto->quantidade ?? 0,
            'estoque_novo' => ($produto->quantidade ?? 0) + $quantidadeRetorno,
            'motivo' => 'Retorno parcial automático ao reduzir quantidade na edição da locação #' . $locacao->numero_contrato,
            'id_usuario' => $idUsuario,
        ]);

        $estoqueAtual = (int) ($produto->quantidade ?? 0);
        $produto->quantidade = $estoqueAtual + $quantidadeRetorno;
        $produto->save();
    }

    private function gerarChaveProdutoLocacao(
        int $idProduto,
        ?int $idPatrimonio,
        $dataInicio,
        ?string $horaInicio,
        $dataFim,
        ?string $horaFim
    ): string {
        $dataInicioNormalizada = $dataInicio instanceof \DateTime
            ? $dataInicio->format('Y-m-d')
            : (string) ($dataInicio ?? '');

        $dataFimNormalizada = $dataFim instanceof \DateTime
            ? $dataFim->format('Y-m-d')
            : (string) ($dataFim ?? '');

        return implode('|', [
            $idProduto,
            $idPatrimonio ?? 0,
            $dataInicioNormalizada,
            (string) ($horaInicio ?? ''),
            $dataFimNormalizada,
            (string) ($horaFim ?? ''),
        ]);
    }

    private function obterCorPrimariaModeloPadrao(int $idEmpresa): string
    {
        $corPadrao = '#1f97ea';

        $modeloPadrao = LocacaoModeloContrato::query()
            ->where('id_empresa', $idEmpresa)
            ->where('ativo', true)
            ->where('padrao', true);

        $this->aplicarFiltroTipoModeloContrato($modeloPadrao, 'contrato');

        $modeloPadrao = $modeloPadrao->first(['cor_borda']);

        $corModelo = trim((string) ($modeloPadrao->cor_borda ?? ''));

        if ($corModelo !== '' && preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $corModelo)) {
            return $corModelo;
        }

        return $corPadrao;
    }

    private function normalizarLogoEmpresa($empresa): void
    {
        if (!$empresa) {
            return;
        }

        $configuracoes = $empresa->configuracoes ?? null;
        if (is_string($configuracoes)) {
            $decoded = json_decode($configuracoes, true);
            $configuracoes = is_array($decoded) ? $decoded : [];
        }
        $configuracoes = is_array($configuracoes) ? $configuracoes : [];

        $logoAtual = $configuracoes['logo_url'] ?? ($empresa->logo_url ?? null);
        $logoNormalizada = $this->resolverLogoLegada($logoAtual);

        if ($logoNormalizada) {
            $configuracoes['logo_url'] = $logoNormalizada;
            $empresa->configuracoes = $configuracoes;
            $empresa->logo_url = $logoNormalizada;
        }
    }

    private function resolverLogoLegada(?string $logoUrl): ?string
    {
        if (empty($logoUrl)) {
            return null;
        }

        $isUrlExterna = str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://');
        $logoPath = $isUrlExterna ? parse_url($logoUrl, PHP_URL_PATH) : $logoUrl;
        $nomeArquivo = basename((string) $logoPath);

        if (empty($nomeArquivo) || $nomeArquivo === '.' || $nomeArquivo === '..') {
            return $logoUrl;
        }

        $diretorioPublico = public_path('assets/logos-empresa');
        $logoPublica = $diretorioPublico . DIRECTORY_SEPARATOR . $nomeArquivo;
        $logoPublicaUrl = asset('assets/logos-empresa/' . $nomeArquivo);

        if (File::exists($logoPublica)) {
            return $logoPublicaUrl;
        }

        $origens = array_filter([
            $logoPath ? public_path(ltrim($logoPath, '/')) : null,
            storage_path('app/public/logos-empresa/' . $nomeArquivo),
        ]);

        foreach ($origens as $origem) {
            if (!File::exists($origem) || !File::isFile($origem)) {
                continue;
            }

            if (!File::exists($diretorioPublico)) {
                File::makeDirectory($diretorioPublico, 0755, true);
            }

            File::copy($origem, $logoPublica);
            return $logoPublicaUrl;
        }

        return $logoUrl;
    }

    private function empresaPermiteNumeroManualLocacao(int $idEmpresa): bool
    {
        if ($idEmpresa <= 0) {
            return false;
        }

        $empresa = Empresa::query()
            ->select(['id_empresa', 'locacao_numero_manual'])
            ->where('id_empresa', $idEmpresa)
            ->first();

        return (int) ($empresa->locacao_numero_manual ?? 0) === 1;
    }

    private function normalizarNumeroManual($valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        $numero = (int) preg_replace('/\D/', '', (string) $valor);

        return $numero > 0 ? $numero : null;
    }

    private function formatarNumeroInternoOrcamento(int $numeroOrcamento): string
    {
        return 'O' . str_pad((string) max(1, $numeroOrcamento), 3, '0', STR_PAD_LEFT);
    }

    private function validarNumeroLocacaoDisponivel(int $numero, int $idEmpresa, ?int $ignorarIdLocacao = null): void
    {
        if ($numero <= 0 || $idEmpresa <= 0) {
            return;
        }

        $codigoPadded = str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
        $codigoSemPadding = (string) $numero;

        $query = Locacao::withTrashed()
            ->where(function ($q) use ($codigoPadded, $codigoSemPadding) {
                $q->where('numero_contrato', $codigoPadded)
                    ->orWhere('numero_contrato', $codigoSemPadding);
            });

        if ($ignorarIdLocacao) {
            $query->where('id_locacao', '!=', $ignorarIdLocacao);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'numero_manual' => 'O número informado já está em uso em outra locação.',
            ]);
        }
    }

    private function validarNumeroOrcamentoDisponivel(int $numero, int $idEmpresa, ?int $ignorarIdLocacao = null): void
    {
        if ($numero <= 0 || $idEmpresa <= 0 || !$this->hasColunaLocacao('numero_orcamento')) {
            return;
        }

        $query = Locacao::withTrashed()
            ->where('id_empresa', $idEmpresa)
            ->where('numero_orcamento', $numero);

        if ($ignorarIdLocacao) {
            $query->where('id_locacao', '!=', $ignorarIdLocacao);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'numero_manual' => 'O número de orçamento informado já está em uso.',
            ]);
        }
    }
}
