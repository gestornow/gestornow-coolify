<?php

namespace App\Http\Controllers\Financeiro;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Models\FaturamentoLocacao;
use App\Models\ContasAReceber;
use App\Domain\Locacao\Models\Locacao;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class FaturamentoController extends Controller
{
    /**
     * Lista os faturamentos já realizados
     */
    public function index(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        if (!Schema::hasTable('faturamento_locacoes')) {
            return view('financeiro.faturamento.index', [
                'faturamentos' => collect(),
                'stats' => [
                    'total_registros' => 0,
                    'valor_total' => 0,
                    'valor_recebido' => 0,
                    'valor_aberto' => 0,
                ],
                'mesFiltro' => $request->filled('mes_filtro') ? $request->mes_filtro : now()->format('Y-m'),
                'tabelaDisponivel' => false,
            ]);
        }

        $mesFiltro = $request->filled('mes_filtro') ? $request->mes_filtro : now()->format('Y-m');

        $baseQuery = FaturamentoLocacao::query()
            ->with(['cliente', 'locacao', 'contaReceber'])
            ->where('id_empresa', $idEmpresa)
            ->where(function ($query) {
                $query->whereNull('origem')
                    ->orWhere('origem', '!=', 'faturamento_medicao');
            })
            ->whereHas('locacao', function ($query) {
                $query->whereIn('status', ['aprovado', 'encerrado']);
            });

        if ($mesFiltro && $mesFiltro !== 'todos') {
            $baseQuery->whereRaw("DATE_FORMAT(data_faturamento, '%Y-%m') = ?", [$mesFiltro]);
        }

        if ($request->filled('status')) {
            $statusFinanceiro = $request->input('status');

            if ($statusFinanceiro === 'recebido') {
                $baseQuery->whereHas('contaReceber', function ($query) {
                    $query->where('status', 'pago');
                });
            }

            if ($statusFinanceiro === 'pendente') {
                $baseQuery->where(function ($query) {
                    $query->whereDoesntHave('contaReceber')
                        ->orWhereHas('contaReceber', function ($subQuery) {
                            $subQuery->where('status', '!=', 'pago');
                        });
                });
            }
        }

        if ($request->filled('busca')) {
            $busca = trim((string) $request->input('busca'));

            $baseQuery->where(function ($query) use ($busca) {
                $query->where('descricao', 'like', "%{$busca}%")
                    ->orWhereHas('locacao', function ($subQuery) use ($busca) {
                        $subQuery->where('numero_contrato', 'like', "%{$busca}%");
                    })
                    ->orWhereHas('cliente', function ($subQuery) use ($busca) {
                        $subQuery->where('nome', 'like', "%{$busca}%")
                            ->orWhere('razao_social', 'like', "%{$busca}%");
                    });
            });
        }

        $statsCollection = (clone $baseQuery)->get();

        $valorTotal = (float) $statsCollection->sum('valor_total');
        $valorRecebido = (float) $statsCollection
            ->filter(function ($faturamento) {
                return $faturamento->contaReceber && $faturamento->contaReceber->status === 'pago';
            })
            ->sum('valor_total');

        $valorAberto = max(0, $valorTotal - $valorRecebido);

        $perPage = $request->filled('per_page') ? (int) $request->per_page : 20;
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $faturamentos = (clone $baseQuery)
            ->orderByDesc('data_faturamento')
            ->orderByDesc('id_faturamento_locacao')
            ->paginate($perPage)
            ->appends($request->except('page'));

        // Agrupar faturamentos em lote
        $faturamentosAgrupados = [];
        $gruposProcessados = [];

        foreach ($faturamentos as $faturamento) {
            if ($faturamento->id_grupo_faturamento) {
                // Se é um faturamento em lote
                if (!isset($gruposProcessados[$faturamento->id_grupo_faturamento])) {
                    // Buscar todos os faturamentos do grupo
                    $faturamentosDoGrupo = FaturamentoLocacao::with(['cliente', 'locacao', 'contaReceber'])
                        ->where('id_grupo_faturamento', $faturamento->id_grupo_faturamento)
                        ->where('id_empresa', $idEmpresa)
                        ->get();

                    $faturamentosAgrupados[] = [
                        'tipo' => 'lote',
                        'id_grupo' => $faturamento->id_grupo_faturamento,
                        'data_faturamento' => $faturamento->data_faturamento,
                        'data_vencimento' => $faturamento->data_vencimento,
                        'valor_total' => $faturamentosDoGrupo->sum('valor_total'),
                        'quantidade' => $faturamentosDoGrupo->count(),
                        'conta_receber' => $faturamento->contaReceber,
                        'faturamentos' => $faturamentosDoGrupo,
                        'status' => $faturamento->contaReceber ? $faturamento->contaReceber->status : null,
                    ];

                    $gruposProcessados[$faturamento->id_grupo_faturamento] = true;
                }
            } else {
                // Faturamento individual
                $faturamentosAgrupados[] = [
                    'tipo' => 'individual',
                    'faturamento' => $faturamento,
                ];
            }
        }

        return view('financeiro.faturamento.index', [
            'faturamentos' => $faturamentos,
            'faturamentosAgrupados' => $faturamentosAgrupados,
            'stats' => [
                'total_registros' => $statsCollection->count(),
                'valor_total' => $valorTotal,
                'valor_recebido' => $valorRecebido,
                'valor_aberto' => $valorAberto,
            ],
            'mesFiltro' => $mesFiltro,
            'tabelaDisponivel' => true,
        ]);
    }

    /**
     * Lista locações pendentes de faturamento
     * Status: 'aprovado' ou 'encerrado' e que ainda não foram faturadas
     */
    public function listarPendentes(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        if (!Schema::hasTable('faturamento_locacoes')) {
            return view('financeiro.faturamento.pendentes', [
                'locacoes' => collect(),
                'stats' => [
                    'total_locacoes' => 0,
                    'valor_total' => 0,
                ],
                'tabelaDisponivel' => false,
            ]);
        }

        // Buscar locações aprovadas ou encerradas que ainda não foram faturadas
        $query = Locacao::with(['cliente', 'usuario', 'produtos', 'servicos'])
            ->where('id_empresa', $idEmpresa)
            ->whereIn('status', ['aprovado', 'encerrado'])
            ->whereDoesntHave('faturamentos', function ($q) {
                $q->where('origem', 'faturamento_medicao');
            })
            ->whereDoesntHave('faturamentos'); // Somente locações sem faturamento

        // Filtros opcionais
        if ($request->filled('busca')) {
            $busca = trim((string) $request->input('busca'));
            $query->where(function ($q) use ($busca) {
                $q->where('numero_contrato', 'like', "%{$busca}%")
                    ->orWhereHas('cliente', function ($subQuery) use ($busca) {
                        $subQuery->where('nome', 'like', "%{$busca}%")
                            ->orWhere('razao_social', 'like', "%{$busca}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Paginação
        $perPage = $request->filled('per_page') ? (int) $request->per_page : 20;
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $locacoes = $query
            ->orderByDesc('id_locacao')
            ->paginate($perPage)
            ->appends($request->except('page'));

        // Estatísticas
        $statsQuery = Locacao::where('id_empresa', $idEmpresa)
            ->whereIn('status', ['aprovado', 'encerrado'])
            ->whereDoesntHave('faturamentos', function ($q) {
                $q->where('origem', 'faturamento_medicao');
            })
            ->whereDoesntHave('faturamentos');

        $totalLocacoes = $statsQuery->count();
        $valorTotal = $statsQuery->sum('valor_final');

        return view('financeiro.faturamento.pendentes', [
            'locacoes' => $locacoes,
            'stats' => [
                'total_locacoes' => $totalLocacoes,
                'valor_total' => $valorTotal,
            ],
            'tabelaDisponivel' => true,
        ]);
    }

    /**
     * Realiza o faturamento de uma locação
     * Cria registro em faturamento_locacoes e gera conta a receber
     */
    public function previewMedicao(Request $request, $idLocacao)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $request->validate([
                'periodo_inicio' => 'nullable|date',
                'periodo_fim' => 'nullable|date',
                'parcelar' => 'nullable|boolean',
                'quantidade_parcelas' => 'nullable|integer|min:2|max:24',
            ]);

            $locacao = Locacao::query()
                ->where('id_locacao', $idLocacao)
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'medicao')
                ->firstOrFail();

            [$inicioCortePadrao, $fimCortePadrao] = $this->obterCorteFaturamentoMedicao($locacao, (int) $idEmpresa);

            $inicioCorte = $request->filled('periodo_inicio')
                ? Carbon::parse((string) $request->input('periodo_inicio'))->startOfDay()
                : $inicioCortePadrao->copy()->startOfDay();
            $fimCorte = $request->filled('periodo_fim')
                ? Carbon::parse((string) $request->input('periodo_fim'))->endOfDay()
                : $fimCortePadrao->copy();

            if (!$inicioCorte->isSameDay($inicioCortePadrao)) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'A data inicial do faturamento é fixa e deve ser %s.',
                        $inicioCortePadrao->format('d/m/Y')
                    ),
                ], 422);
            }

            if ($fimCorte->lt($inicioCorte)) {
                return response()->json([
                    'success' => false,
                    'message' => 'A data final do período deve ser maior ou igual à data inicial.',
                ], 422);
            }

            if ($fimCorte->gt(now()->endOfDay())) {
                return response()->json([
                    'success' => false,
                    'message' => 'A data final do período de medição não pode ser futura.',
                ], 422);
            }

            $valorTotal = $this->calcularValorMedicaoPeriodo($locacao, $inicioCorte, $fimCorte);
            $diasPeriodo = max(1, $inicioCorte->copy()->startOfDay()->diffInDays($fimCorte->copy()->startOfDay()) + 1);

            $parcelar = $request->boolean('parcelar');
            $quantidadeParcelas = $parcelar ? max(2, (int) $request->input('quantidade_parcelas', 2)) : 1;
            $valoresParcelas = $this->ratearValorParcelas((float) $valorTotal, $quantidadeParcelas);

            return response()->json([
                'success' => true,
                'periodo' => [
                    'inicio' => $inicioCorte->format('Y-m-d'),
                    'fim' => $fimCorte->format('Y-m-d'),
                    'dias' => $diasPeriodo,
                ],
                'valor_total' => (float) $valorTotal,
                'quantidade_parcelas' => $quantidadeParcelas,
                'valores_parcelas' => $valoresParcelas,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível calcular a prévia do faturamento.',
            ], 500);
        }
    }

    public function faturar(Request $request, $idLocacao)
    {
        try {
            // Garante que os jobs de log executem imediatamente neste fluxo.
            config(['queue.default' => 'sync']);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::id();

            // Validação
            $request->validate([
                'data_vencimento' => 'required|date',
                'observacoes' => 'nullable|string|max:1000',
                'periodo_inicio' => 'nullable|date',
                'periodo_fim' => 'nullable|date',
                'quantidade_parcelas' => 'nullable|integer|min:1|max:24',
                'parcelas' => 'nullable|array',
                'parcelas.*.descricao' => 'nullable|string',
                'parcelas.*.data_vencimento' => 'nullable|date',
                'parcelas.*.valor' => 'nullable|string',
            ]);

            // Buscar locação
            $locacao = Locacao::with('cliente')
                ->where('id_locacao', $idLocacao)
                ->where('id_empresa', $idEmpresa)
                ->whereIn('status', ['aprovado', 'encerrado', 'medicao'])
                ->firstOrFail();

            $isMedicao = (string) ($locacao->status ?? '') === 'medicao';

            if (!$isMedicao) {
                // Regra padrão: locações não-medição têm apenas 1 faturamento ativo
                $jaFaturado = FaturamentoLocacao::where('id_empresa', $idEmpresa)
                    ->where('id_locacao', $idLocacao)
                    ->exists();

                if ($jaFaturado) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta locação já possui um faturamento ativo.',
                    ], 422);
                }
            }

            $valorFaturamento = (float) ($locacao->valor_final ?? 0);
            $descricaoFaturamento = "Faturamento Locação #{$locacao->numero_contrato}";
            $origemFaturamento = 'encerramento_locacao';
            $periodoInicioFaturamento = null;
            $periodoFimFaturamento = null;

            $parcelar = $request->boolean('parcelar');
            $dataVencimentoPadrao = $request->filled('data_vencimento')
                ? Carbon::parse((string) $request->input('data_vencimento'))->toDateString()
                : now()->addDays(15)->toDateString();
            $datasVencimentoParcelas = [$dataVencimentoPadrao];
            $quantidadeParcelas = 1;

            if ($isMedicao) {
                [$inicioCortePadrao, $fimCortePadrao] = $this->obterCorteFaturamentoMedicao($locacao, $idEmpresa);

                $inicioCorte = $request->filled('periodo_inicio')
                    ? Carbon::parse((string) $request->input('periodo_inicio'))->startOfDay()
                    : $inicioCortePadrao->copy()->startOfDay();
                $fimCorte = $request->filled('periodo_fim')
                    ? Carbon::parse((string) $request->input('periodo_fim'))->endOfDay()
                    : $fimCortePadrao->copy();

                if (!$inicioCorte->isSameDay($inicioCortePadrao)) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'A data inicial do faturamento é fixa e deve ser %s.',
                            $inicioCortePadrao->format('d/m/Y')
                        ),
                    ], 422);
                }

                if ($fimCorte->lt($inicioCorte)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A data final do período deve ser maior ou igual à data inicial.',
                    ], 422);
                }

                if ($fimCorte->gt(now()->endOfDay())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A data final do período de medição não pode ser futura.',
                    ], 422);
                }

                $periodoInicioFaturamento = $inicioCorte->copy();
                $periodoFimFaturamento = $fimCorte->copy();
                $valorFaturamento = $this->calcularValorMedicaoPeriodo($locacao, $inicioCorte, $fimCorte);

                if ($valorFaturamento <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não há valor em aberto para faturar neste contrato de medição.',
                    ], 422);
                }

                $descricaoFaturamento = sprintf(
                    'Faturamento Medição %s (%s a %s)',
                    $locacao->numero_contrato ?: ('#' . $locacao->id_locacao),
                    $inicioCorte->format('d/m/Y'),
                    $fimCorte->format('d/m/Y')
                );
                $origemFaturamento = 'faturamento_medicao';
            }

            // Processar parcelas customizadas
            $parcelasCustomizadas = [];
            $quantidadeParcelas = (int)$request->input('quantidade_parcelas', 1);
            
            if ($quantidadeParcelas > 1 && $request->has('parcelas')) {
                $parcelasRequest = $request->input('parcelas', []);
                
                if (count($parcelasRequest) < $quantidadeParcelas) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Informe todas as parcelas.',
                    ], 422);
                }
                
                $totalValorParcelas = 0;
                foreach ($parcelasRequest as $index => $parcelaData) {
                    if ($index >= $quantidadeParcelas) break;
                    
                    $valorStr = $parcelaData['valor'] ?? '0';
                    // Remover formatação de moeda
                    $valorStr = str_replace(['R$', ' ', '.'], '', $valorStr);
                    $valorStr = str_replace(',', '.', $valorStr);
                    $valor = (float)$valorStr;
                    
                    $parcelasCustomizadas[] = [
                        'descricao' => $parcelaData['descricao'] ?? "Parcela " . ($index + 1) . "/" . $quantidadeParcelas,
                        'data_vencimento' => Carbon::parse($parcelaData['data_vencimento'])->toDateString(),
                        'valor' => $valor,
                    ];
                    
                    $totalValorParcelas += $valor;
                }
                
                // Validar se a soma das parcelas bate com o total
                if (abs($totalValorParcelas - $valorFaturamento) > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'A soma das parcelas (R$ %.2f) não corresponde ao valor total (R$ %.2f).',
                            $totalValorParcelas,
                            $valorFaturamento
                        ),
                    ], 422);
                }
                
                $parcelar = true;
                $datasVencimentoParcelas = array_column($parcelasCustomizadas, 'data_vencimento');
            } else {
                if (!$request->filled('data_vencimento')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Informe a data de vencimento para faturar.',
                    ], 422);
                }

                $datasVencimentoParcelas = [$dataVencimentoPadrao];
                $quantidadeParcelas = 1;
            }

            if ($isMedicao) {
                $this->ajustarIndiceLegadoFaturamentoLocacao();
            }

            DB::beginTransaction();

            if (!$isMedicao) {
                // Manter comportamento legado de refaturamento para não-medição
                FaturamentoLocacao::onlyTrashed()
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_locacao', $idLocacao)
                    ->forceDelete();
            }

            // Gerar próximo número de fatura para a empresa
            $numeroFatura = FaturamentoLocacao::gerarProximoNumeroFatura($idEmpresa);

            // 1. Criar registro de faturamento
            $dadosFaturamento = [
                'id_empresa' => $idEmpresa,
                'id_locacao' => $locacao->id_locacao,
                'id_usuario' => $idUsuario,
                'numero_fatura' => $numeroFatura,
                'id_cliente' => $locacao->id_cliente,
                'descricao' => $descricaoFaturamento,
                'valor_total' => $valorFaturamento,
                'data_faturamento' => now(),
                'data_vencimento' => $datasVencimentoParcelas[0],
                'status' => 'faturado',
                'origem' => $origemFaturamento,
                'observacoes' => $request->observacoes,
            ];

            try {
                $faturamento = FaturamentoLocacao::create($dadosFaturamento);
            } catch (QueryException $e) {
                if (!$isMedicao || !$this->isDuplicidadeFaturamentoPorLocacao($e)) {
                    throw $e;
                }

                $this->ajustarIndiceLegadoFaturamentoLocacao();
                $faturamento = FaturamentoLocacao::create($dadosFaturamento);
            }

            // 2. Criar conta(s) a receber
            $contaReceber = null;

            if ($quantidadeParcelas <= 1) {
                $contaReceber = ContasAReceber::create([
                    'id_empresa' => $idEmpresa,
                    'id_clientes' => $locacao->id_cliente,
                    'id_locacao' => $locacao->id_locacao,
                    'descricao' => $descricaoFaturamento,
                    'documento' => $locacao->numero_contrato ?? '',
                    'valor_total' => $valorFaturamento,
                    'valor_pago' => 0,
                    'data_emissao' => now(),
                    'data_vencimento' => $datasVencimentoParcelas[0],
                    'status' => 'pendente',
                    'observacoes' => $request->observacoes,
                ]);
            } else {
                $idParcelamento = 'PARC-' . now()->format('YmdHis') . '-' . $locacao->id_locacao;

                foreach ($parcelasCustomizadas as $indice => $parcelaData) {
                    $descricaoParcela = $parcelaData['descricao'] ?? ($descricaoFaturamento . " - Parcela " . ($indice + 1) . "/" . $quantidadeParcelas);
                    
                    $parcela = ContasAReceber::create([
                        'id_empresa' => $idEmpresa,
                        'id_clientes' => $locacao->id_cliente,
                        'id_locacao' => $locacao->id_locacao,
                        'descricao' => $descricaoParcela,
                        'documento' => $locacao->numero_contrato ?? '',
                        'valor_total' => $parcelaData['valor'],
                        'valor_pago' => 0,
                        'data_emissao' => now(),
                        'data_vencimento' => $parcelaData['data_vencimento'],
                        'status' => 'pendente',
                        'observacoes' => $request->observacoes,
                        'numero_parcela' => $indice + 1,
                        'total_parcelas' => $quantidadeParcelas,
                        'id_parcelamento' => $idParcelamento,
                    ]);

                    if (!$contaReceber) {
                        $contaReceber = $parcela;
                    }
                }
            }

            // 3. Atualizar faturamento com ID da conta
            $faturamento->update([
                'id_conta_receber' => $contaReceber->id_contas,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Faturamento realizado com sucesso!',
                'faturamento_id' => $faturamento->id_faturamento_locacao,
                'conta_id' => $contaReceber->id_contas,
                'parcelas' => $quantidadeParcelas,
                'periodo' => $periodoInicioFaturamento && $periodoFimFaturamento ? [
                    'inicio' => $periodoInicioFaturamento->format('Y-m-d'),
                    'fim' => $periodoFimFaturamento->format('Y-m-d'),
                ] : null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao faturar locação: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao realizar faturamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function obterCorteFaturamentoMedicao(Locacao $locacao, int $idEmpresa): array
    {
        $ultimaFatura = FaturamentoLocacao::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->whereNull('deleted_at')
            ->orderByDesc('data_faturamento')
            ->orderByDesc('id_faturamento_locacao')
            ->first(['data_faturamento', 'descricao']);

        $fimUltimoPeriodo = $this->obterFimPeriodoFaturaMedicao($ultimaFatura);

        $inicioLocacao = $locacao->data_inicio
            ? Carbon::parse((string) $locacao->data_inicio)->startOfDay()
            : now()->startOfDay();

        $inicioCorte = $fimUltimoPeriodo
            ? $fimUltimoPeriodo->copy()->addDay()->startOfDay()
            : $inicioLocacao->copy();

        $fimCorte = now()->endOfDay();

        return [$inicioCorte, $fimCorte];
    }

    private function obterFimPeriodoFaturaMedicao(?FaturamentoLocacao $fatura): ?Carbon
    {
        if (!$fatura) {
            return null;
        }

        $descricao = (string) ($fatura->descricao ?? '');
        if (preg_match('/\((\d{2}\/\d{2}\/\d{4})\s+[aàá]\s+(\d{2}\/\d{2}\/\d{4})\)/iu', $descricao, $matches)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $matches[2])->endOfDay();
            } catch (\Throwable $e) {
            }
        }

        if (!empty($fatura->data_faturamento)) {
            try {
                return Carbon::parse((string) $fatura->data_faturamento)->endOfDay();
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    private function calcularValorMedicaoPeriodo(Locacao $locacao, Carbon $inicioCorte, Carbon $fimCorte): float
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
            $diasPeriodo = max(1, $inicioEfetivo->copy()->startOfDay()->diffInDays($fimEfetivo->copy()->startOfDay()) + 1);

            $total += $precoUnitario * $quantidade * $diasPeriodo;
        }

        return $this->aplicarLimiteValorMedicao($locacao, $total);
    }

    private function obterValorLimiteMedicao(Locacao $locacao): float
    {
        if (!$this->hasColunaLocacao('valor_limite_medicao')) {
            return 0.0;
        }

        return round(max(0, (float) ($locacao->valor_limite_medicao ?? 0)), 2);
    }

    private function obterTotalFaturadoMedicao(Locacao $locacao): float
    {
        return round(max(0, (float) FaturamentoLocacao::query()
            ->where('id_empresa', $locacao->id_empresa)
            ->where('id_locacao', $locacao->id_locacao)
            ->where('origem', 'faturamento_medicao')
            ->sum('valor_total')), 2);
    }

    private function aplicarLimiteValorMedicao(Locacao $locacao, float $valorCalculado): float
    {
        $valorCalculado = round(max(0, $valorCalculado), 2);
        $valorLimite = $this->obterValorLimiteMedicao($locacao);

        if ($valorLimite <= 0) {
            return $valorCalculado;
        }

        $faturado = $this->obterTotalFaturadoMedicao($locacao);
        $saldo = round(max(0, $valorLimite - $faturado), 2);

        return round(min($valorCalculado, $saldo), 2);
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

    private function ratearValorParcelas(float $valorTotal, int $quantidadeParcelas): array
    {
        $quantidadeParcelas = max(1, $quantidadeParcelas);
        $valorTotal = round(max(0, $valorTotal), 2);

        if ($quantidadeParcelas === 1) {
            return [$valorTotal];
        }

        $base = floor(($valorTotal / $quantidadeParcelas) * 100) / 100;
        $parcelas = array_fill(0, $quantidadeParcelas, $base);
        $restanteCentavos = (int) round(($valorTotal - ($base * $quantidadeParcelas)) * 100);

        for ($i = 0; $i < $restanteCentavos; $i++) {
            $parcelas[$i] = round($parcelas[$i] + 0.01, 2);
        }

        return $parcelas;
    }

    private function isDuplicidadeFaturamentoPorLocacao(QueryException $e): bool
    {
        $mensagem = mb_strtolower((string) $e->getMessage());
        $codigo = (string) $e->getCode();

        return in_array($codigo, ['23000', '1062'], true)
            && (
                str_contains($mensagem, 'uq_faturamento_locacao_empresa_locacao')
                || str_contains($mensagem, 'duplicate entry')
                || str_contains($mensagem, 'for key')
            );
    }

    private function ajustarIndiceLegadoFaturamentoLocacao(): void
    {
        if (!Schema::hasTable('faturamento_locacoes')) {
            return;
        }

        $indices = DB::select("SHOW INDEX FROM faturamento_locacoes");
        $mapaIndices = [];
        foreach ($indices as $indice) {
            $nome = (string) ($indice->Key_name ?? '');
            if ($nome === '') {
                continue;
            }

            if (!isset($mapaIndices[$nome])) {
                $mapaIndices[$nome] = [
                    'non_unique' => (int) ($indice->Non_unique ?? 1),
                    'cols' => [],
                ];
            }

            $seq = (int) ($indice->Seq_in_index ?? (count($mapaIndices[$nome]['cols']) + 1));
            $mapaIndices[$nome]['cols'][$seq] = (string) ($indice->Column_name ?? '');
        }

        $indicesParaRemover = [];
        foreach ($mapaIndices as $nome => $meta) {
            if ($nome === 'PRIMARY' || (int) ($meta['non_unique'] ?? 1) !== 0) {
                continue;
            }

            ksort($meta['cols']);
            $colunas = array_values(array_filter($meta['cols']));
            if ($colunas === ['id_empresa', 'id_locacao']) {
                $indicesParaRemover[] = $nome;
            }
        }

        foreach ($indicesParaRemover as $nomeIndice) {
            try {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $nomeIndice)) {
                    continue; // Segurança: rejeita nomes de índice fora do padrão seguro para evitar injeção SQL.
                }

                DB::statement("ALTER TABLE faturamento_locacoes DROP INDEX `{$nomeIndice}`");
            } catch (\Throwable $e) {
                throw new \Exception('Não foi possível ajustar o índice de faturamento da locação automaticamente. Remova o índice único por (id_empresa, id_locacao) em faturamento_locacoes e tente novamente.');
            }
        }

        $indicesAposDrop = DB::select("SHOW INDEX FROM faturamento_locacoes");
        $jaTemIndiceComum = collect($indicesAposDrop)->contains(function ($indice) {
            return ($indice->Key_name ?? null) === 'idx_faturamento_locacao_empresa_locacao'
                && (int) ($indice->Non_unique ?? 1) === 1;
        });

        if (!$jaTemIndiceComum) {
            DB::statement('ALTER TABLE faturamento_locacoes ADD INDEX idx_faturamento_locacao_empresa_locacao (id_empresa, id_locacao)');
        }
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
     * Realiza o faturamento de múltiplas locações em lote
     * Cria múltiplos faturamentos com mesmo id_grupo e uma única conta a receber consolidada
     */
    public function faturarLote(Request $request)
    {
        try {
            // Garante que os jobs de log executem imediatamente neste fluxo.
            config(['queue.default' => 'sync']);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idUsuario = Auth::id();

            // Validação
            $request->validate([
                'locacoes' => 'required|array|min:1',
                'locacoes.*' => [
                    'required',
                    'integer',
                    Rule::exists('locacao', 'id_locacao')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'data_vencimento' => 'required|date',
                'observacoes' => 'nullable|string|max:1000',
                'quantidade_parcelas' => 'nullable|integer|min:1|max:24',
                'parcelas' => 'nullable|array',
                'parcelas.*.descricao' => 'nullable|string',
                'parcelas.*.data_vencimento' => 'nullable|date',
                'parcelas.*.valor' => 'nullable|string',
            ]);

            $idsLocacoes = $request->locacoes;

            // Remover duplicatas (caso o usuário selecione a mesma locação duas vezes)
            $idsLocacoes = array_unique($idsLocacoes);

            // Buscar locações
            $locacoes = Locacao::with('cliente')
                ->whereIn('id_locacao', $idsLocacoes)
                ->where('id_empresa', $idEmpresa)
                ->whereIn('status', ['aprovado', 'encerrado'])
                ->get();

            // Verificar se encontrou todas
            if ($locacoes->count() !== count($idsLocacoes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Algumas locações não foram encontradas ou não estão disponíveis para faturamento.',
                ], 422);
            }

            // Verificar se todas as locações são do mesmo cliente
            $clientesUnicos = $locacoes->pluck('id_cliente')->unique();
            if ($clientesUnicos->count() > 1) {
                $nomesClientes = $locacoes->map(function($loc) {
                    return $loc->cliente->nome ?? $loc->cliente->razao_social ?? "Cliente #{$loc->id_cliente}";
                })->unique()->take(3)->implode(', ');
                
                return response()->json([
                    'success' => false,
                    'message' => "Não é possível faturar em lote locações de clientes diferentes. Clientes: {$nomesClientes}" . ($clientesUnicos->count() > 3 ? '...' : ''),
                ], 422);
            }

            // Verificar se alguma já foi faturada (apenas faturas ativas)
            $jaFaturados = FaturamentoLocacao::where('id_empresa', $idEmpresa)
                ->whereIn('id_locacao', $idsLocacoes)
                ->pluck('id_locacao')
                ->toArray();
            
            if (count($jaFaturados) > 0) {
                $locacoesJaFaturadas = $locacoes->whereIn('id_locacao', $jaFaturados)
                    ->pluck('numero_contrato')
                    ->implode(', ');
                
                return response()->json([
                    'success' => false,
                    'message' => "Locações com faturamento ativo: #{$locacoesJaFaturadas}",
                ], 422);
            }

            // Calcular valor total das locações selecionadas
            $valorTotalLote = $locacoes->sum('valor_final');
            
            // Processar parcelas customizadas
            $parcelasCustomizadas = [];
            $quantidadeParcelas = (int)$request->input('quantidade_parcelas', 1);
            
            if ($quantidadeParcelas > 1 && $request->has('parcelas')) {
                $parcelasRequest = $request->input('parcelas', []);
                
                if (count($parcelasRequest) < $quantidadeParcelas) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Informe todas as parcelas.',
                    ], 422);
                }
                
                $totalValorParcelas = 0;
                foreach ($parcelasRequest as $index => $parcelaData) {
                    if ($index >= $quantidadeParcelas) break;
                    
                    $valorStr = $parcelaData['valor'] ?? '0';
                    // Remover formatação de moeda
                    $valorStr = str_replace(['R$', ' ', '.'], '', $valorStr);
                    $valorStr = str_replace(',', '.', $valorStr);
                    $valor = (float)$valorStr;
                    
                    $parcelasCustomizadas[] = [
                        'descricao' => $parcelaData['descricao'] ?? "Parcela " . ($index + 1) . "/" . $quantidadeParcelas,
                        'data_vencimento' => Carbon::parse($parcelaData['data_vencimento'])->toDateString(),
                        'valor' => $valor,
                    ];
                    
                    $totalValorParcelas += $valor;
                }
                
                // Validar se a soma das parcelas bate com o total
                if (abs($totalValorParcelas - $valorTotalLote) > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'A soma das parcelas (R$ %.2f) não corresponde ao valor total (R$ %.2f).',
                            $totalValorParcelas,
                            $valorTotalLote
                        ),
                    ], 422);
                }
                
                $datasVencimentoParcelas = array_column($parcelasCustomizadas, 'data_vencimento');
            } else {
                if (!$request->filled('data_vencimento')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Informe a data de vencimento para faturar.',
                    ], 422);
                }

                $datasVencimentoParcelas = [$request->data_vencimento];
                $quantidadeParcelas = 1;
            }

            DB::beginTransaction();

            // Limpar faturas canceladas (soft deleted) das locações para permitir refaturamento
            FaturamentoLocacao::onlyTrashed()
                ->where('id_empresa', $idEmpresa)
                ->whereIn('id_locacao', $idsLocacoes)
                ->forceDelete();

            // Gerar ID único para o grupo
            $idGrupoFaturamento = 'LOTE-' . now()->format('YmdHis') . '-' . $idEmpresa;

            $valorTotalGeral = 0;
            $faturamentos = [];
            $idCliente = $locacoes->first()->id_cliente; // Já validado que todos são do mesmo cliente

            // Gerar UM único número de fatura para todo o lote
            $numeroFatura = FaturamentoLocacao::gerarProximoNumeroFatura($idEmpresa);

            // Criar faturamentos individuais (todas com o mesmo numero_fatura)
            foreach ($locacoes as $locacao) {
                $valorTotalGeral += $locacao->valor_final ?? 0;

                $faturamento = FaturamentoLocacao::create([
                    'id_empresa' => $idEmpresa,
                    'id_locacao' => $locacao->id_locacao,
                    'id_usuario' => $idUsuario,
                    'numero_fatura' => $numeroFatura,
                    'id_cliente' => $locacao->id_cliente,
                    'id_grupo_faturamento' => $idGrupoFaturamento,
                    'descricao' => "Faturamento Locação #{$locacao->numero_contrato}",
                    'valor_total' => $locacao->valor_final ?? 0,
                    'data_faturamento' => now(),
                    'data_vencimento' => $datasVencimentoParcelas[0],
                    'status' => 'faturado',
                    'origem' => 'faturamento_lote',
                    'observacoes' => $request->observacoes,
                ]);

                $faturamentos[] = $faturamento;
            }

            // Criar conta(s) a receber - com ou sem parcelamento
            $contaReceber = null;

            if ($quantidadeParcelas <= 1) {
                // Criar UMA conta a receber consolidada
                $contaReceber = ContasAReceber::create([
                    'id_empresa' => $idEmpresa,
                    'id_clientes' => $idCliente,
                    'descricao' => "Faturamento Lote - {$locacoes->count()} Locações",
                    'documento' => $idGrupoFaturamento,
                    'valor_total' => $valorTotalGeral,
                    'valor_pago' => 0,
                    'data_emissao' => now(),
                    'data_vencimento' => $datasVencimentoParcelas[0],
                    'status' => 'pendente',
                    'observacoes' => $request->observacoes,
                ]);
            } else {
                // Criar múltiplas contas a receber (parcelamento)
                $idParcelamento = 'PARC-' . now()->format('YmdHis') . '-LOTE-' . $idEmpresa;

                foreach ($parcelasCustomizadas as $indice => $parcelaData) {
                    $descricaoParcela = $parcelaData['descricao'] ?? "Faturamento Lote - {$locacoes->count()} Locações - Parcela " . ($indice + 1) . "/" . $quantidadeParcelas;
                    
                    $parcela = ContasAReceber::create([
                        'id_empresa' => $idEmpresa,
                        'id_clientes' => $idCliente,
                        'descricao' => $descricaoParcela,
                        'documento' => $idGrupoFaturamento,
                        'valor_total' => $parcelaData['valor'],
                        'valor_pago' => 0,
                        'data_emissao' => now(),
                        'data_vencimento' => $parcelaData['data_vencimento'],
                        'status' => 'pendente',
                        'observacoes' => $request->observacoes,
                        'numero_parcela' => $indice + 1,
                        'total_parcelas' => $quantidadeParcelas,
                        'id_parcelamento' => $idParcelamento,
                    ]);

                    if (!$contaReceber) {
                        $contaReceber = $parcela;
                    }
                }
            }

            // Atualizar todos os faturamentos com ID da conta
            foreach ($faturamentos as $faturamento) {
                $faturamento->update([
                    'id_conta_receber' => $contaReceber->id_contas,
                ]);
            }

            if (!empty($faturamentos)) {
                ActionLogger::log($faturamentos[0], 'faturamento_lote');
            }

            DB::commit();

            $mensagem = $quantidadeParcelas > 1
                ? "{$locacoes->count()} locações faturadas com sucesso em {$quantidadeParcelas} parcelas!"
                : "{$locacoes->count()} locações faturadas com sucesso!";

            return response()->json([
                'success' => true,
                'message' => $mensagem,
                'grupo_id' => $idGrupoFaturamento,
                'conta_id' => $contaReceber->id_contas,
                'parcelas' => $quantidadeParcelas,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao faturar lote: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao realizar faturamento em lote: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela um faturamento específico
     * Se for parte de um lote, recalcula a conta a receber
     * Se for o último do lote, exclui a conta a receber
     */
    public function cancelar($idFaturamento)
    {
        try {
            // Garante que os jobs de log executem imediatamente neste fluxo.
            config(['queue.default' => 'sync']);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $faturamento = FaturamentoLocacao::with('contaReceber')
                ->where('id_faturamento_locacao', $idFaturamento)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();

            // Verificar se a conta já foi paga
            if ($faturamento->contaReceber && $faturamento->contaReceber->status === 'pago') {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível cancelar um faturamento já pago.',
                ], 422);
            }

            DB::beginTransaction();

            $idGrupo = $faturamento->id_grupo_faturamento;
            $idContaReceber = $faturamento->id_conta_receber;
            $valorFaturamento = $faturamento->valor_total;

            // Se tem grupo (faturamento em lote)
            if ($idGrupo) {
                // Verificar quantos faturamentos existem no grupo
                $totalNoGrupo = FaturamentoLocacao::where('id_grupo_faturamento', $idGrupo)
                    ->where('id_empresa', $idEmpresa)
                    ->count();

                // Se é o último do grupo, excluir a conta a receber
                if ($totalNoGrupo === 1) {
                    if ($faturamento->contaReceber) {
                        $faturamento->contaReceber->delete();
                    }
                } else {
                    // Se não é o último, recalcular o valor da conta a receber
                    if ($faturamento->contaReceber) {
                        $novoValor = $faturamento->contaReceber->valor_total - $valorFaturamento;
                        $faturamento->contaReceber->update([
                            'valor_total' => $novoValor,
                        ]);
                    }
                }
            } else {
                // É faturamento individual, excluir a conta a receber
                if ($faturamento->contaReceber) {
                    $faturamento->contaReceber->delete();
                }
            }

            ActionLogger::log($faturamento, 'cancelamento');

            // Excluir o faturamento
            $faturamento->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Faturamento cancelado com sucesso!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao cancelar faturamento: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar faturamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela todos os faturamentos de um grupo
     * Exclui a conta a receber consolidada e todos os faturamentos
     */
    public function cancelarLote($idGrupo)
    {
        try {
            // Garante que os jobs de log executem imediatamente neste fluxo.
            config(['queue.default' => 'sync']);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            // Buscar faturamentos do grupo
            $faturamentos = FaturamentoLocacao::with('contaReceber')
                ->where('id_grupo_faturamento', $idGrupo)
                ->where('id_empresa', $idEmpresa)
                ->get();

            if ($faturamentos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum faturamento encontrado neste grupo.',
                ], 404);
            }

            // Verificar se alguma conta foi paga
            $contaPaga = $faturamentos->first()->contaReceber;
            if ($contaPaga && $contaPaga->status === 'pago') {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível cancelar um lote com pagamento já realizado.',
                ], 422);
            }

            DB::beginTransaction();

            // Excluir a conta a receber (única para o lote)
            if ($contaPaga) {
                $contaPaga->delete();
            }

            // Excluir todos os faturamentos
            foreach ($faturamentos as $faturamento) {
                ActionLogger::log($faturamento, 'cancelamento');
                $faturamento->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$faturamentos->count()} faturamentos cancelados com sucesso!",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao cancelar lote: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar lote de faturamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna os dados do faturamento para visualização no modal
     */
    public function visualizar($idFaturamento)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $faturamento = FaturamentoLocacao::with([
                'locacao.cliente',
                'locacao.produtos.produto',
                'locacao.servicos',
                'contaReceber',
            ])
                ->where('id_faturamento_locacao', $idFaturamento)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$faturamento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faturamento não encontrado.'
                ], 404);
            }

            // Buscar empresa
            $empresa = DB::table('empresa')
                ->where('id_empresa', $idEmpresa)
                ->first();

            $parcelasConta = collect([]);
            if ($faturamento->contaReceber) {
                if (!empty($faturamento->contaReceber->id_parcelamento)) {
                    $parcelasConta = ContasAReceber::query()
                        ->where('id_empresa', $idEmpresa)
                        ->where('id_parcelamento', $faturamento->contaReceber->id_parcelamento)
                        ->orderBy('numero_parcela')
                        ->get();
                } else {
                    $parcelasConta = collect([$faturamento->contaReceber]);
                }
            }

            $faturamento->setRelation('parcelasConta', $parcelasConta);

            return response()->json([
                'success' => true,
                'faturamento' => $faturamento,
                'empresa' => $empresa,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao visualizar faturamento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar faturamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera PDF do faturamento
     */
    public function gerarPdf(Request $request, $idFaturamento)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $forcarDownload = $request->boolean('download');

            // Buscar faturamento com relacionamentos
            $faturamento = FaturamentoLocacao::with([
                'locacao.cliente',
                'locacao.produtos.produto',
                'locacao.servicos',
                'contaReceber',
            ])
                ->where('id_faturamento_locacao', $idFaturamento)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$faturamento) {
                Log::error("Faturamento não encontrado: ID {$idFaturamento}, Empresa {$idEmpresa}");
                return redirect()->route('financeiro.faturamento.index')
                    ->with('error', 'Faturamento não encontrado.');
            }

            if (!$faturamento->locacao) {
                Log::error("Locação não encontrada para faturamento: ID {$idFaturamento}");
                return redirect()->route('financeiro.faturamento.index')
                    ->with('error', 'Locação vinculada ao faturamento não encontrada.');
            }

            // Buscar empresa
            $empresa = DB::table('empresa')
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$empresa) {
                Log::error("Empresa não encontrada: ID {$idEmpresa}");
                return redirect()->route('financeiro.faturamento.index')
                    ->with('error', 'Dados da empresa não encontrados.');
            }

            // Decodificar configurações JSON
            if (isset($empresa->configuracoes) && is_string($empresa->configuracoes)) {
                $empresa->configuracoes = json_decode($empresa->configuracoes, true);
            }

            // Normalizar logo da empresa
            $this->normalizarLogoEmpresa($empresa);

            // Verificar se é um faturamento em lote
            if ($faturamento->id_grupo_faturamento) {
                // Buscar todos os faturamentos do lote
                $faturamentosDoLote = FaturamentoLocacao::with([
                    'locacao.cliente',
                    'locacao.produtos.produto',
                    'locacao.servicos',
                    'contaReceber',
                ])
                    ->where('id_grupo_faturamento', $faturamento->id_grupo_faturamento)
                    ->where('id_empresa', $idEmpresa)
                    ->orderBy('id_faturamento_locacao')
                    ->get();

                $contextosLote = [];
                foreach ($faturamentosDoLote as $fatLote) {
                    $contextosLote[$fatLote->id_faturamento_locacao] = $this->montarContextoPdfFatura($fatLote);
                }

                // Gerar PDF consolidado do lote
                $pdf = Pdf::loadView('financeiro.faturamento.pdf-lote', [
                    'faturamentos' => $faturamentosDoLote,
                    'empresa' => $empresa,
                    'contextosFatura' => $contextosLote,
                ]);

                // Nome do arquivo para lote
                $nomeArquivo = "Fatura_Lote_" . str_replace('LOTE-', '', $faturamento->id_grupo_faturamento) . "_" . now()->format('dmY') . ".pdf";

                return $forcarDownload
                    ? $pdf->download($nomeArquivo)
                    : $pdf->stream($nomeArquivo);
            }

            // Faturamento individual - gerar PDF normal
            $contextoFatura = $this->montarContextoPdfFatura($faturamento);
            $pdf = Pdf::loadView('financeiro.faturamento.pdf', [
                'faturamento' => $faturamento,
                'empresa' => $empresa,
                'contextoFatura' => $contextoFatura,
            ]);

            // Nome do arquivo
            $numeroContrato = $faturamento->locacao->numero_contrato ?? $faturamento->locacao->id_locacao ?? $idFaturamento;
            $nomeArquivo = "Fatura_Locacao_{$numeroContrato}_" . now()->format('dmY') . ".pdf";

            return $forcarDownload
                ? $pdf->download($nomeArquivo)
                : $pdf->stream($nomeArquivo);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Faturamento não encontrado: ' . $e->getMessage());
            return redirect()->route('financeiro.faturamento.index')
                ->with('error', 'Faturamento não encontrado.');
                
        } catch (\Exception $e) {
            Log::error('Erro ao gerar PDF de faturamento: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->route('financeiro.faturamento.index')
                ->with('error', 'Erro ao gerar PDF: ' . $e->getMessage());
        }
    }

    private function montarContextoPdfFatura(FaturamentoLocacao $faturamento): array
    {
        $descricao = trim((string) ($faturamento->descricao ?? ''));
        $origem = trim((string) ($faturamento->origem ?? ''));
        $descricaoLower = mb_strtolower($descricao);

        $isMedicao = $origem === 'faturamento_medicao' || str_contains($descricaoLower, 'faturamento medição');
        [$periodoInicio, $periodoFim] = $this->extrairPeriodoMedicaoDescricao($descricao);

        if ($isMedicao && (!$periodoInicio || !$periodoFim) && $faturamento->locacao) {
            $periodoInicio = !empty($faturamento->locacao->data_inicio)
                ? Carbon::parse((string) $faturamento->locacao->data_inicio)->startOfDay()
                : null;
            $periodoFim = !empty($faturamento->locacao->data_fim)
                ? Carbon::parse((string) $faturamento->locacao->data_fim)->endOfDay()
                : null;
        }

        $diasPeriodo = ($periodoInicio && $periodoFim && $periodoFim->gte($periodoInicio))
            ? max(1, $periodoInicio->copy()->startOfDay()->diffInDays($periodoFim->copy()->startOfDay()) + 1)
            : null;

        $itens = [];
        $totalItens = 0.0;

        $produtos = $faturamento->locacao->produtos ?? collect();
        foreach ($produtos as $item) {
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $valorUnitario = (float) ($item->preco_unitario ?? 0);
            $nomeProduto = $item->produto->nome ?? $item->produto->descricao ?? 'Produto';

            $diasItem = null;
            $inicioEfetivo = null;
            $fimEfetivo = null;
            $subtotal = (float) ($item->preco_total ?? ($quantidade * $valorUnitario));

            if ($isMedicao && $periodoInicio && $periodoFim) {
                if (empty($item->data_inicio)) {
                    continue;
                }

                $inicioItem = Carbon::parse((string) $item->data_inicio)->startOfDay();
                $itemRetornado = (int) ($item->estoque_status ?? 0) === 2
                    || !in_array($item->status_retorno, [null, '', 'pendente'], true);

                $fimBase = ($itemRetornado && !empty($item->data_fim))
                    ? Carbon::parse((string) $item->data_fim)->endOfDay()
                    : $periodoFim->copy()->endOfDay();

                $inicioCalculo = $inicioItem->copy()->max($periodoInicio->copy()->startOfDay());
                $fimCalculo = $fimBase->copy()->min($periodoFim->copy()->endOfDay());

                if ($fimCalculo->lt($inicioCalculo)) {
                    continue;
                }

                $diasItem = max(1, $inicioCalculo->copy()->startOfDay()->diffInDays($fimCalculo->copy()->startOfDay()) + 1);
                $subtotal = round($valorUnitario * $quantidade * $diasItem, 2);
                $inicioEfetivo = $inicioCalculo;
                $fimEfetivo = $fimCalculo;
            }

            $totalItens += $subtotal;

            $itens[] = [
                'quantidade' => $quantidade,
                'produto' => $nomeProduto,
                'valor_unitario' => $valorUnitario,
                'dias' => $diasItem,
                'subtotal' => round($subtotal, 2),
                'inicio_efetivo' => $inicioEfetivo,
                'fim_efetivo' => $fimEfetivo,
            ];
        }

        $rotuloPeriodo = ($periodoInicio && $periodoFim)
            ? $periodoInicio->format('d/m/Y') . ' até ' . $periodoFim->format('d/m/Y')
            : (
                ($faturamento->locacao && $faturamento->locacao->data_inicio && $faturamento->locacao->data_fim)
                    ? Carbon::parse((string) $faturamento->locacao->data_inicio)->format('d/m/Y') . ' até ' . Carbon::parse((string) $faturamento->locacao->data_fim)->format('d/m/Y')
                    : '-'
            );

        // Buscar parcelas da conta a receber vinculada
        $parcelas = collect([]);
        if ($faturamento->contaReceber && $faturamento->contaReceber->id_parcelamento) {
            $parcelas = ContasAReceber::where('id_parcelamento', $faturamento->contaReceber->id_parcelamento)
                ->orderBy('numero_parcela')
                ->get();
        } elseif ($faturamento->contaReceber) {
            // Se não tem parcelamento mas tem conta, adiciona ela como parcela única
            $parcelas = collect([$faturamento->contaReceber]);
        }

        return [
            'is_medicao' => $isMedicao,
            'periodo_inicio' => $periodoInicio,
            'periodo_fim' => $periodoFim,
            'periodo_rotulo' => $rotuloPeriodo,
            'dias_periodo' => $diasPeriodo,
            'itens' => $itens,
            'total_itens' => round($totalItens, 2),
            'parcelas' => $parcelas,
        ];
    }

    private function extrairPeriodoMedicaoDescricao(string $descricao): array
    {
        if (!preg_match('/\((\d{2}\/\d{2}\/\d{4})\s+[aàá]\s+(\d{2}\/\d{2}\/\d{4})\)/iu', $descricao, $matches)) {
            return [null, null];
        }

        try {
            $inicio = Carbon::createFromFormat('d/m/Y', $matches[1])->startOfDay();
            $fim = Carbon::createFromFormat('d/m/Y', $matches[2])->endOfDay();
            return [$inicio, $fim];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /**
     * Normaliza a logo da empresa para garantir que esteja acessível
     */
    private function normalizarLogoEmpresa($empresa): void
    {
        if (!$empresa) {
            return;
        }

        $configuracoes = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logoAtual = $configuracoes['logo_url'] ?? null;
        $logoNormalizada = $this->resolverLogoLegada($logoAtual);

        if ($logoNormalizada) {
            $configuracoes['logo_url'] = $logoNormalizada;
            $empresa->configuracoes = $configuracoes;
        }
    }

    /**
     * Resolve o caminho da logo legada
     */
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

        if (file_exists($logoPublica)) {
            return $logoPublicaUrl;
        }

        $origens = array_filter([
            $logoPath ? public_path(ltrim($logoPath, '/')) : null,
            storage_path('app/public/logos-empresa/' . $nomeArquivo),
        ]);

        foreach ($origens as $origem) {
            if (!file_exists($origem) || !is_file($origem)) {
                continue;
            }

            if (!file_exists($diretorioPublico)) {
                mkdir($diretorioPublico, 0755, true);
            }

            copy($origem, $logoPublica);
            return $logoPublicaUrl;
        }

        return $logoUrl;
    }
}
