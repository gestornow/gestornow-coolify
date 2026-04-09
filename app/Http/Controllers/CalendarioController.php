<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalendarioController extends Controller
{
    private const STATUS_LABELS = [
        'orcamento'          => 'Orçamento',
        'aprovado'           => 'Aprovado',
        'medicao'            => 'Medição',
        'medicao_finalizada' => 'Medição Finalizada',
        'retirada'           => 'Retirada',
        'em_andamento'       => 'Em Andamento',
        'atrasada'           => 'Atrasada',
        'encerrado'          => 'Encerrado',
        'cancelado'          => 'Cancelado',
        'cancelada'          => 'Cancelado',
    ];

    private const STATUS_COLORS = [
        'orcamento'          => '#8592a3',
        'aprovado'           => '#03c3ec',
        'medicao'            => '#233446',
        'medicao_finalizada' => '#71dd37',
        'retirada'           => '#ffab00',
        'em_andamento'       => '#696cff',
        'atrasada'           => '#ff3e1d',
        'encerrado'          => '#71dd37',
        'cancelado'          => '#8592a3',
        'cancelada'          => '#8592a3',
    ];

    public function index()
    {
        return view('calendario.index');
    }

    public function eventos(Request $request): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa') ?? optional(Auth::user())->id_empresa;

            if (empty($idEmpresa)) {
                return response()->json([]);
            }

            $start = $request->query('start');
            $end   = $request->query('end');

            $query = DB::table('locacao as l')
                ->leftJoin('clientes as c', 'c.id_clientes', '=', 'l.id_cliente')
                ->where('l.id_empresa', $idEmpresa)
                ->whereNotNull('l.data_inicio')
                ->select(
                    'l.id_locacao',
                    'l.numero_contrato',
                    'l.status',
                    'l.data_inicio',
                    'l.data_fim',
                    'c.nome as cliente_nome'
                )
                ->orderBy('l.data_inicio');

            if ($start) {
                $query->where(DB::raw('COALESCE(l.data_fim, l.data_inicio)'), '>=', Carbon::parse($start)->toDateString());
            }

            if ($end) {
                $query->where('l.data_inicio', '<=', Carbon::parse($end)->toDateString());
            }

            $locacoes = $query->get();
            $resumoProdutosPorLocacao = $this->obterResumoProdutosPorLocacao(
                $locacoes->pluck('id_locacao')->filter()->map(function ($id) {
                    return (int) $id;
                })->all()
            );

            $eventos = [];

            foreach ($locacoes as $row) {
                $statusKey = strtolower(trim((string) $row->status));
                $cor       = self::STATUS_COLORS[$statusKey] ?? '#8592a3';
                $corTexto  = $this->resolverCorTexto($cor);
                $label     = self::STATUS_LABELS[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey ?: 'indefinido'));
                $cliente   = $row->cliente_nome ?: 'Cliente não informado';
                $contrato  = $row->numero_contrato ?: $row->id_locacao;
                $resumoProdutos = $resumoProdutosPorLocacao[(int) $row->id_locacao] ?? $this->resumoProdutosVazio();

                $eventos[] = [
                    'id'              => (string) $row->id_locacao,
                    'title'           => "{$cliente} - #{$contrato}",
                    'start'           => Carbon::parse($row->data_inicio)->toDateString(),
                    'end'             => Carbon::parse($row->data_fim ?? $row->data_inicio)->addDay()->toDateString(),
                    'allDay'          => true,
                    'backgroundColor' => $cor,
                    'borderColor'     => $cor,
                    'textColor'       => $corTexto,
                    'extendedProps'   => [
                        'id_locacao'      => $row->id_locacao,
                        'cliente'         => $cliente,
                        'numero_contrato' => $contrato,
                        'status'          => $statusKey,
                        'status_label'    => $label,
                        'status_color'    => $cor,
                        'status_text_color' => $corTexto,
                        'quantidade_itens' => $resumoProdutos['quantidade_total'],
                        'produtos'         => $resumoProdutos['nomes'],
                        'produtos_detalhados' => $resumoProdutos['rotulos'],
                        'produtos_resumo'  => $resumoProdutos['resumo'],
                        'url_detalhe'     => route('locacoes.show', $row->id_locacao),
                    ],
                ];
            }

            return response()->json($eventos);
        } catch (\Throwable $e) {
            Log::error('Falha ao carregar eventos do calendario', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'error'   => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function obterResumoProdutosPorLocacao(array $locacaoIds): array
    {
        if (empty($locacaoIds)) {
            return [];
        }

        $produtosPorLocacao = DB::table('produto_locacao as pl')
            ->leftJoin('produtos as p', 'p.id_produto', '=', 'pl.id_produto')
            ->whereIn('pl.id_locacao', $locacaoIds)
            ->whereNull('pl.deleted_at')
            ->select('pl.id_locacao', 'pl.quantidade', 'p.nome as produto_nome')
            ->get()
            ->groupBy('id_locacao');

        $produtosTerceirosPorLocacao = DB::table('produto_terceiros_locacao as ptl')
            ->leftJoin('produtos_terceiros as pt', 'pt.id_produto_terceiro', '=', 'ptl.id_produto_terceiro')
            ->whereIn('ptl.id_locacao', $locacaoIds)
            ->whereNull('ptl.deleted_at')
            ->select('ptl.id_locacao', 'ptl.quantidade', 'pt.nome as produto_nome', 'ptl.nome_produto_manual')
            ->get()
            ->groupBy('id_locacao');

        $resumo = [];

        foreach ($locacaoIds as $locacaoId) {
            $itens = [];

            foreach ($produtosPorLocacao->get($locacaoId, collect()) as $item) {
                $nome = trim((string) ($item->produto_nome ?? ''));

                if ($nome === '') {
                    continue;
                }

                $itens[] = [
                    'nome' => $nome,
                    'quantidade' => (int) ($item->quantidade ?? 0),
                ];
            }

            foreach ($produtosTerceirosPorLocacao->get($locacaoId, collect()) as $item) {
                $nome = trim((string) ($item->produto_nome ?: $item->nome_produto_manual ?: ''));

                if ($nome === '') {
                    continue;
                }

                $itens[] = [
                    'nome' => $nome,
                    'quantidade' => (int) ($item->quantidade ?? 0),
                ];
            }

            $resumo[$locacaoId] = $this->formatarResumoProdutos($itens);
        }

        return $resumo;
    }

    private function formatarResumoProdutos(array $itens): array
    {
        if (empty($itens)) {
            return $this->resumoProdutosVazio();
        }

        $agrupados = [];
        $quantidadeTotal = 0;

        foreach ($itens as $item) {
            $nome = trim((string) ($item['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            $quantidade = max(1, (int) ($item['quantidade'] ?? 0));
            $chave = $this->normalizarChaveProduto($nome);

            if (!isset($agrupados[$chave])) {
                $agrupados[$chave] = [
                    'nome' => $nome,
                    'quantidade' => 0,
                ];
            }

            $agrupados[$chave]['quantidade'] += $quantidade;
            $quantidadeTotal += $quantidade;
        }

        if (empty($agrupados)) {
            return $this->resumoProdutosVazio();
        }

        $produtos = array_values($agrupados);

        usort($produtos, function (array $primeiro, array $segundo): int {
            return strcasecmp($primeiro['nome'], $segundo['nome']);
        });

        $nomes = [];
        $rotulos = [];

        foreach ($produtos as $produto) {
            $nomes[] = $produto['nome'];
            $rotulos[] = $produto['quantidade'] > 1
                ? $produto['quantidade'] . 'x ' . $produto['nome']
                : $produto['nome'];
        }

        return [
            'nomes' => $nomes,
            'rotulos' => $rotulos,
            'resumo' => implode(', ', $rotulos),
            'quantidade_total' => $quantidadeTotal,
        ];
    }

    private function resumoProdutosVazio(): array
    {
        return [
            'nomes' => [],
            'rotulos' => [],
            'resumo' => 'Sem produtos vinculados',
            'quantidade_total' => 0,
        ];
    }

    private function normalizarChaveProduto(string $nome): string
    {
        $nomeNormalizado = preg_replace('/\s+/u', ' ', trim($nome));
        $nomeNormalizado = $nomeNormalizado === null ? trim($nome) : $nomeNormalizado;

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($nomeNormalizado, 'UTF-8');
        }

        return strtolower($nomeNormalizado);
    }

    private function resolverCorTexto(string $corHexadecimal): string
    {
        $cor = ltrim(trim($corHexadecimal), '#');

        if (strlen($cor) === 3) {
            $cor = preg_replace('/(.)/', '$1$1', $cor) ?? $cor;
        }

        if (!preg_match('/^[0-9a-fA-F]{6}$/', $cor)) {
            return '#ffffff';
        }

        $red = hexdec(substr($cor, 0, 2)) / 255;
        $green = hexdec(substr($cor, 2, 2)) / 255;
        $blue = hexdec(substr($cor, 4, 2)) / 255;

        $converter = static function (float $canal): float {
            return $canal <= 0.03928
                ? $canal / 12.92
                : pow(($canal + 0.055) / 1.055, 2.4);
        };

        $luminancia =
            (0.2126 * $converter($red)) +
            (0.7152 * $converter($green)) +
            (0.0722 * $converter($blue));

        $contrasteComBranco = 1.05 / ($luminancia + 0.05);
        $contrasteComEscuro = ($luminancia + 0.05) / 0.05;

        return $contrasteComEscuro >= $contrasteComBranco ? '#233446' : '#ffffff';
    }
}
