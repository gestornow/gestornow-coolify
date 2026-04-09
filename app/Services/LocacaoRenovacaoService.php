<?php

namespace App\Services;

use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoProduto;
use App\Domain\Produto\Models\Produto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

class LocacaoRenovacaoService
{
    public function __construct(private EstoqueService $estoqueService)
    {
    }

    public function renovarManual(Locacao $locacaoOrigem, array $payload, ?int $idUsuario): Locacao
    {
        return DB::transaction(function () use ($locacaoOrigem, $payload, $idUsuario) {
            $dataInicio = (string) ($payload['data_inicio'] ?? '');
            $horaInicio = (string) ($payload['hora_inicio'] ?? '00:00:00');
            $dataFim = (string) ($payload['data_fim'] ?? '');
            $horaFim = (string) ($payload['hora_fim'] ?? '23:59:59');
            $renovacaoAutomatica = isset($payload['renovacao_automatica'])
                ? (bool) $payload['renovacao_automatica']
                : null;

            if ($dataInicio === '' || $dataFim === '') {
                throw new \Exception('Informe o período de renovação.');
            }

            $itensSelecionados = collect($payload['itens'] ?? []);
            if ($itensSelecionados->isEmpty()) {
                throw new \Exception('Selecione ao menos um item para renovar.');
            }

            return $this->renovarLocacaoInterno(
                $locacaoOrigem,
                $dataInicio,
                $horaInicio,
                $dataFim,
                $horaFim,
                $itensSelecionados,
                $idUsuario,
                $renovacaoAutomatica
            );
        });
    }

    public function processarRenovacoesAutomaticas(?int $idEmpresa = null): array
    {
        $agora = now();

        $query = Locacao::query()
            ->where('status', 'aprovado')
            ->whereDate('data_fim', '<=', $agora->toDateString())
            ->where(function ($q) use ($agora) {
                $q->whereDate('data_fim', '<', $agora->toDateString())
                    ->orWhereRaw("COALESCE(hora_fim, '23:59:59') <= ?", [$agora->format('H:i:s')]);
            });

        if ($idEmpresa) {
            $query->where('id_empresa', $idEmpresa);
        }

        if ($this->hasColunaLocacao('renovacao_automatica')) {
            $query->where('renovacao_automatica', 1);
        } else {
            return ['processadas' => 0, 'erros' => 0];
        }

        $locacoes = $query
            ->with(['produtos.produto', 'produtos.patrimonio'])
            ->orderBy('id_locacao')
            ->get();

        $processadas = 0;
        $erros = 0;

        foreach ($locacoes as $locacao) {
            try {
                DB::transaction(function () use ($locacao) {
                    $periodo = $this->calcularProximoPeriodo($locacao);
                    $itens = $this->itensElegiveisParaRenovacao($locacao)
                        ->map(fn (LocacaoProduto $item) => [
                            'id_produto_locacao' => (int) $item->id_produto_locacao,
                            'quantidade' => (int) max(1, $item->quantidade ?? 1),
                        ]);

                    if ($itens->isEmpty()) {
                        throw new \Exception('Sem itens elegíveis para renovação automática.');
                    }

                    $this->renovarLocacaoInterno(
                        $locacao,
                        $periodo['data_inicio'],
                        $periodo['hora_inicio'],
                        $periodo['data_fim'],
                        $periodo['hora_fim'],
                        $itens,
                        null,
                        true
                    );
                });

                $processadas++;
            } catch (\Throwable $e) {
                $erros++;
                logger()->error('Erro ao renovar locação automaticamente', [
                    'id_locacao' => $locacao->id_locacao,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $encerradas = $this->processarEncerramentosAditivosVencidos($idEmpresa);
        $saidasIniciadas = $this->processarSaidasAditivosIniciados($idEmpresa);

        return [
            'processadas' => $processadas,
            'erros' => $erros,
            'encerradas' => $encerradas,
            'saidas_iniciadas' => $saidasIniciadas,
        ];
    }

    private function renovarLocacaoInterno(
        Locacao $locacaoOrigem,
        string $dataInicio,
        string $horaInicio,
        string $dataFim,
        string $horaFim,
        Collection $itensSelecionados,
        ?int $idUsuario,
        ?bool $renovacaoAutomatica = null
    ): Locacao {
        $inicio = $this->combinarDataHoraSegura($dataInicio, $horaInicio, '00:00:00');
        $fim = $this->combinarDataHoraSegura($dataFim, $horaFim, '23:59:59');

        if ($fim->lt($inicio)) {
            throw new \Exception('A data/hora final deve ser maior que a inicial.');
        }

        $locacaoOrigem->loadMissing(['produtos.produto', 'produtos.patrimonio']);

        $itensById = $locacaoOrigem->produtos->keyBy('id_produto_locacao');
        $itensParaNovaLocacao = collect();

        foreach ($itensSelecionados as $itemSelecionado) {
            $idProdutoLocacao = (int) ($itemSelecionado['id_produto_locacao'] ?? 0);
            $quantidadeSolicitada = (int) max(1, $itemSelecionado['quantidade'] ?? 1);
            $itemOrigem = $itensById->get($idProdutoLocacao);

            if (!$itemOrigem) {
                continue;
            }

            if ((int) ($itemOrigem->estoque_status ?? 0) === 2 || !in_array($itemOrigem->status_retorno, [null, '', 'pendente'], true)) {
                continue;
            }

            $quantidade = (int) max(1, $itemOrigem->id_patrimonio ? 1 : min($quantidadeSolicitada, (int) max(1, $itemOrigem->quantidade ?? 1)));

            $dataInicioItem = (string) ($itemSelecionado['data_inicio'] ?? $inicio->toDateString());
            $horaInicioItem = (string) ($itemSelecionado['hora_inicio'] ?? $inicio->format('H:i:s'));
            $dataFimItem = (string) ($itemSelecionado['data_fim'] ?? $fim->toDateString());
            $horaFimItem = (string) ($itemSelecionado['hora_fim'] ?? $fim->format('H:i:s'));

            $inicioItem = $this->combinarDataHoraSegura($dataInicioItem, $horaInicioItem, '00:00:00');
            $fimItem = $this->combinarDataHoraSegura($dataFimItem, $horaFimItem, '23:59:59');

            if ($fimItem->lt($inicioItem)) {
                throw new \Exception('Período inválido em item selecionado para renovação.');
            }

            if ($inicioItem->lt($inicio) || $fimItem->gt($fim)) {
                throw new \Exception('As datas dos itens devem estar dentro do período do aditivo.');
            }

            $itensParaNovaLocacao->push([
                'origem' => $itemOrigem,
                'quantidade' => $quantidade,
                'data_inicio' => $inicioItem->toDateString(),
                'hora_inicio' => $inicioItem->format('H:i:s'),
                'data_fim' => $fimItem->toDateString(),
                'hora_fim' => $fimItem->format('H:i:s'),
            ]);
        }

        if ($itensParaNovaLocacao->isEmpty()) {
            throw new \Exception('Nenhum item elegível foi selecionado para renovação.');
        }

        $this->validarDisponibilidadeRenovacao($locacaoOrigem, $itensParaNovaLocacao, $inicio, $fim);

        if ($this->locacaoOrigemJaVenceu($locacaoOrigem)) {
            $this->encerrarLocacaoOrigem($locacaoOrigem, $idUsuario);
        }

        $novaLocacao = $this->criarNovaLocacaoAditivo($locacaoOrigem, $inicio, $fim, $idUsuario, $renovacaoAutomatica);

        foreach ($itensParaNovaLocacao as $itemData) {
            /** @var LocacaoProduto $origem */
            $origem = $itemData['origem'];
            $quantidade = (int) $itemData['quantidade'];

            $novoItem = $origem->replicate();
            $novoItem->id_locacao = $novaLocacao->id_locacao;
            $novoItem->id_empresa = $novaLocacao->id_empresa;
            $novoItem->id_patrimonio = $origem->id_patrimonio;
            $novoItem->quantidade = $quantidade;
            $novoItem->data_inicio = (string) $itemData['data_inicio'];
            $novoItem->hora_inicio = (string) $itemData['hora_inicio'];
            $novoItem->data_fim = (string) $itemData['data_fim'];
            $novoItem->hora_fim = (string) $itemData['hora_fim'];
            $novoItem->status_retorno = 'pendente';
            $novoItem->estoque_status = 0;
            $novoItem->save();
        }

        $this->sincronizarValorNovaLocacao($novaLocacao);

        foreach ($novaLocacao->produtos()->with(['produto', 'locacao'])->get() as $itemNovo) {
            $inicioItem = $this->combinarDataHoraSegura(
                $itemNovo->data_inicio ?: $novaLocacao->data_inicio,
                $itemNovo->hora_inicio ?: $novaLocacao->hora_inicio,
                '00:00:00'
            );
            if ($inicioItem->lte(now())) {
                $this->estoqueService->registrarSaidaLocacao($itemNovo, $idUsuario);
                $itemNovo->estoque_status = 1;
                $itemNovo->save();
            }
        }

        return $novaLocacao->fresh(['produtos.produto', 'produtos.patrimonio']);
    }

    private function calcularProximoPeriodo(Locacao $locacao): array
    {
        $porHora = $this->ehLocacaoPorHora($locacao);
        $duracao = $porHora
            ? $this->obterDuracaoHorasLocacao($locacao)
            : max(1, (int) ($locacao->quantidade_dias ?? 1));

        $fimAtual = Carbon::parse(
            (optional($locacao->data_fim)->format('Y-m-d') ?: now()->toDateString())
            . ' '
            . ((string) ($locacao->hora_fim ?: '23:59:59'))
        );

        $inicioProximo = $porHora
            ? $fimAtual->copy()
            : $fimAtual->copy()->addDay()->setTimeFromTimeString((string) ($locacao->hora_inicio ?: '00:00:00'));

        $fimProximo = $porHora
            ? $inicioProximo->copy()->addHours($duracao)
            : $inicioProximo->copy()->addDays($duracao - 1)->setTimeFromTimeString((string) ($locacao->hora_fim ?: '23:59:59'));

        return [
            'data_inicio' => $inicioProximo->toDateString(),
            'hora_inicio' => $inicioProximo->format('H:i:s'),
            'data_fim' => $fimProximo->toDateString(),
            'hora_fim' => $fimProximo->format('H:i:s'),
        ];
    }

    private function itensElegiveisParaRenovacao(Locacao $locacao): Collection
    {
        return ($locacao->produtos ?? collect())->filter(function (LocacaoProduto $item) {
            return (int) ($item->estoque_status ?? 0) !== 2
                && in_array($item->status_retorno, [null, '', 'pendente'], true);
        })->values();
    }

    private function validarDisponibilidadeRenovacao(Locacao $locacao, Collection $itensSelecionados, Carbon $inicio, Carbon $fim): void
    {
        $agrupado = [];

        foreach ($itensSelecionados as $itemData) {
            /** @var LocacaoProduto $origem */
            $origem = $itemData['origem'];
            $idProduto = (int) ($origem->id_produto ?? 0);
            if ($idProduto <= 0 || $origem->id_patrimonio) {
                continue;
            }

            $inicioItem = Carbon::parse(((string) $itemData['data_inicio']) . ' ' . ((string) $itemData['hora_inicio']));
            $fimItem = Carbon::parse(((string) $itemData['data_fim']) . ' ' . ((string) $itemData['hora_fim']));

            if ($inicioItem->lt($inicio) || $fimItem->gt($fim)) {
                throw new \Exception('Datas de item fora do período do aditivo.');
            }

            $chave = implode('|', [
                $idProduto,
                $inicioItem->toDateString(),
                $inicioItem->format('H:i:s'),
                $fimItem->toDateString(),
                $fimItem->format('H:i:s'),
            ]);

            if (!isset($agrupado[$chave])) {
                $agrupado[$chave] = [
                    'id_produto' => $idProduto,
                    'quantidade' => 0,
                    'data_inicio' => $inicioItem->toDateString(),
                    'hora_inicio' => $inicioItem->format('H:i:s'),
                    'data_fim' => $fimItem->toDateString(),
                    'hora_fim' => $fimItem->format('H:i:s'),
                ];
            }

            $agrupado[$chave]['quantidade'] += (int) max(1, $itemData['quantidade']);
        }

        foreach ($agrupado as $linha) {
            $idProduto = (int) $linha['id_produto'];
            $quantidade = (int) $linha['quantidade'];

            $disponibilidade = $this->estoqueService->calcularDisponibilidade(
                (int) $idProduto,
                (int) $locacao->id_empresa,
                (string) $linha['data_inicio'],
                (string) $linha['data_fim'],
                (string) $linha['hora_inicio'],
                (string) $linha['hora_fim'],
                (int) $locacao->id_locacao,
                'data_item'
            );

            $disponivel = (int) ($disponibilidade['disponivel'] ?? 0);
            if ($quantidade > $disponivel) {
                $produto = Produto::query()
                    ->where('id_produto', $idProduto)
                    ->where('id_empresa', $locacao->id_empresa)
                    ->first();

                throw new \Exception('Estoque insuficiente para renovar o produto ' . ($produto->nome ?? ('#' . $idProduto)) . '.');
            }
        }
    }

    private function obterDuracaoHorasLocacao(Locacao $locacao): int
    {
        try {
            $inicio = Carbon::parse(
                (optional($locacao->data_inicio)->format('Y-m-d') ?: now()->toDateString())
                . ' '
                . ((string) ($locacao->hora_inicio ?: '00:00:00'))
            );

            $fim = Carbon::parse(
                (optional($locacao->data_fim)->format('Y-m-d') ?: now()->toDateString())
                . ' '
                . ((string) ($locacao->hora_fim ?: '23:59:59'))
            );

            return max(1, (int) ceil($inicio->diffInMinutes($fim) / 60));
        } catch (\Throwable $e) {
            return max(1, (int) ($locacao->quantidade_dias ?? 1));
        }
    }

    private function encerrarLocacaoOrigem(Locacao $locacaoOrigem, ?int $idUsuario): void
    {
        if ($locacaoOrigem->status === 'encerrado') {
            return;
        }

        $locacaoOrigem->loadMissing(['produtos.produto', 'produtos.patrimonio']);

        foreach (($locacaoOrigem->produtos ?? collect()) as $item) {
            if ((int) ($item->estoque_status ?? 0) === 1) {
                $this->estoqueService->registrarRetornoLocacao(
                    $item,
                    'devolvido',
                    'Retorno automático por renovação (aditivo)',
                    $idUsuario
                );
            }

            $item->estoque_status = 2;
            $item->status_retorno = 'devolvido';
            $item->save();
        }

        $locacaoOrigem->status = 'encerrado';
        $locacaoOrigem->save();
    }

    private function locacaoOrigemJaVenceu(Locacao $locacaoOrigem): bool
    {
        $dataFim = $locacaoOrigem->data_fim;
        if (!$dataFim) {
            return true;
        }

        $fim = $this->combinarDataHoraSegura($dataFim, $locacaoOrigem->hora_fim ?: '23:59:59', '23:59:59');

        return now()->gte($fim);
    }

    private function criarNovaLocacaoAditivo(
        Locacao $locacaoOrigem,
        Carbon $inicio,
        Carbon $fim,
        ?int $idUsuario,
        ?bool $renovacaoAutomatica
    ): Locacao {
        $this->ajustarIndiceNumeroContratoParaAditivo();

        $novoAditivo = $this->obterProximoAditivo($locacaoOrigem);

        $novaLocacao = $locacaoOrigem->replicate();
        $novaLocacao->status = 'aprovado';
        $novaLocacao->id_usuario = $idUsuario ?: ($locacaoOrigem->id_usuario ?? null);
        $novaLocacao->numero_contrato = $locacaoOrigem->numero_contrato;
        $novaLocacao->data_inicio = $inicio->toDateString();
        $novaLocacao->hora_inicio = $inicio->format('H:i:s');
        $novaLocacao->data_fim = $fim->toDateString();
        $novaLocacao->hora_fim = $fim->format('H:i:s');
        $novaLocacao->quantidade_dias = $this->calcularQuantidadePeriodo($inicio, $fim, $this->ehLocacaoPorHora($locacaoOrigem));
        $novaLocacao->aditivo = $novoAditivo;
        if ($this->hasColunaLocacao('renovacao_automatica')) {
            $novaLocacao->renovacao_automatica = $renovacaoAutomatica ?? (bool) ($locacaoOrigem->renovacao_automatica ?? false);
        }
        if ($this->hasColunaLocacao('id_locacao_origem')) {
            $novaLocacao->id_locacao_origem = (int) ($locacaoOrigem->id_locacao_origem ?: $locacaoOrigem->id_locacao);
        }
        if ($this->hasColunaLocacao('id_locacao_anterior')) {
            $novaLocacao->id_locacao_anterior = $locacaoOrigem->id_locacao;
        }

        try {
            $novaLocacao->save();
        } catch (QueryException $e) {
            $mensagem = strtolower((string) $e->getMessage());
            if (str_contains($mensagem, 'duplicate entry') && str_contains($mensagem, 'numero_contrato')) {
                throw new \Exception('Não foi possível criar o aditivo: a base ainda possui índice único legado em numero_contrato. Ajuste para UNIQUE(id_empresa, numero_contrato, aditivo).');
            }

            throw $e;
        }

        return $novaLocacao;
    }

    private function obterProximoAditivo(Locacao $locacaoOrigem): int
    {
        if ($this->hasColunaLocacao('id_locacao_origem')) {
            $idRaiz = (int) ($locacaoOrigem->id_locacao_origem ?: $locacaoOrigem->id_locacao);

            $maiorAditivoCadeia = (int) (Locacao::query()
                ->where('id_empresa', $locacaoOrigem->id_empresa)
                ->where(function ($q) use ($idRaiz) {
                    $q->where('id_locacao', $idRaiz)
                        ->orWhere('id_locacao_origem', $idRaiz);
                })
                ->selectRaw('MAX(COALESCE(aditivo, 1)) as maior_aditivo')
                ->value('maior_aditivo') ?? 1);

            return max(1, $maiorAditivoCadeia + 1);
        }

        $numeroContratoRaw = trim((string) ($locacaoOrigem->numero_contrato ?? ''));
        $numeroContratoSemZeros = ltrim($numeroContratoRaw, '0');
        if ($numeroContratoSemZeros === '') {
            $numeroContratoSemZeros = '0';
        }

        $maiorAditivoLegado = (int) (Locacao::query()
            ->where('id_empresa', $locacaoOrigem->id_empresa)
            ->where(function ($q) use ($numeroContratoRaw, $numeroContratoSemZeros) {
                $q->where('numero_contrato', $numeroContratoRaw)
                    ->orWhere('numero_contrato', $numeroContratoSemZeros);
            })
            ->selectRaw('MAX(COALESCE(aditivo, 1)) as maior_aditivo')
            ->value('maior_aditivo') ?? 1);

        return max(1, $maiorAditivoLegado + 1);
    }

    private function sincronizarValorNovaLocacao(Locacao $locacao): void
    {
        $locacao->loadMissing(['produtos']);

        $porHora = $this->ehLocacaoPorHora($locacao);
        $total = 0.0;

        foreach (($locacao->produtos ?? collect()) as $item) {
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $valorFechado = (bool) ($item->valor_fechado ?? false);
            $fatorCalculado = $this->calcularQuantidadePeriodo(
                    $this->combinarDataHoraSegura($item->data_inicio ?: $locacao->data_inicio, $item->hora_inicio ?: $locacao->hora_inicio, '00:00:00'),
                    $this->combinarDataHoraSegura($item->data_fim ?: $locacao->data_fim, $item->hora_fim ?: $locacao->hora_fim, '23:59:59'),
                    $porHora
                );
            $fator = $valorFechado ? 1 : $fatorCalculado;

            $item->preco_total = round((float) ($item->preco_unitario ?? 0) * $quantidade * $fator, 2);
            $item->save();
            $total += (float) $item->preco_total;
        }

        $locacao->valor_total = $total;
        $locacao->valor_final = max(
            0,
            $total
            + (float) ($locacao->valor_frete ?? 0)
            + (float) ($locacao->valor_acrescimo ?? 0)
            + (float) ($locacao->valor_imposto ?? 0)
            + (float) ($locacao->valor_despesas_extras ?? 0)
            - (float) ($locacao->valor_desconto ?? 0)
        );
        $locacao->save();
    }

    private function calcularQuantidadePeriodo(Carbon $inicio, Carbon $fim, bool $porHora): int
    {
        if ($porHora) {
            return max(1, (int) ceil($inicio->diffInMinutes($fim) / 60));
        }

        return max(1, $inicio->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay()) + 1);
    }

    private function ehLocacaoPorHora(Locacao $locacao): bool
    {
        if (empty($locacao->data_inicio) || empty($locacao->data_fim)) {
            return false;
        }

        if (empty($locacao->hora_inicio) || empty($locacao->hora_fim)) {
            return false;
        }

        $dataInicio = $locacao->data_inicio instanceof \DateTimeInterface
            ? $locacao->data_inicio->format('Y-m-d')
            : Carbon::parse((string) $locacao->data_inicio)->toDateString();

        $dataFim = $locacao->data_fim instanceof \DateTimeInterface
            ? $locacao->data_fim->format('Y-m-d')
            : Carbon::parse((string) $locacao->data_fim)->toDateString();

        if ($dataInicio === $dataFim) {
            return true;
        }

        $diasInclusivos = max(1, Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1);
        $quantidadePeriodo = max(1, (int) ($locacao->quantidade_dias ?? 1));

        return $quantidadePeriodo > $diasInclusivos;
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

    private function processarSaidasAditivosIniciados(?int $idEmpresa = null): int
    {
        $agora = now();
        $query = LocacaoProduto::query()
            ->where('estoque_status', 0)
            ->whereHas('locacao', function ($q) use ($idEmpresa) {
                $q->where('status', 'aprovado')
                    ->whereNotNull('id_locacao_anterior');

                if ($idEmpresa) {
                    $q->where('id_empresa', $idEmpresa);
                }
            })
            ->with(['locacao', 'produto', 'patrimonio']);

        $itensPendentes = $query->get();
        if ($itensPendentes->isEmpty()) {
            return 0;
        }

        $iniciados = 0;
        foreach ($itensPendentes as $item) {
            $locacao = $item->locacao;
            if (!$locacao) {
                continue;
            }

            $dataInicio = $item->data_inicio ?: $locacao->data_inicio;
            $horaInicio = $item->hora_inicio ?: ($locacao->hora_inicio ?: '00:00:00');
            if (!$dataInicio) {
                continue;
            }

            $inicioItem = $this->combinarDataHoraSegura($dataInicio, $horaInicio, '00:00:00');
            if ($inicioItem->gt($agora)) {
                continue;
            }

            $this->estoqueService->registrarSaidaLocacao($item);
            $item->estoque_status = 1;
            $item->save();
            $iniciados++;
        }

        return $iniciados;
    }

    private function processarEncerramentosAditivosVencidos(?int $idEmpresa = null): int
    {
        $agora = now();
        $query = Locacao::query()
            ->where('status', 'aprovado')
            ->whereDate('data_fim', '<=', $agora->toDateString())
            ->where(function ($q) use ($agora) {
                $q->whereDate('data_fim', '<', $agora->toDateString())
                    ->orWhereRaw("COALESCE(hora_fim, '23:59:59') <= ?", [$agora->format('H:i:s')]);
            });

        if ($this->hasColunaLocacao('id_locacao_origem')) {
            $query->whereHas('aditivos', function ($q) {
                $q->where('status', 'aprovado');
            });
        } elseif ($this->hasColunaLocacao('aditivo')) {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('locacao as l2')
                    ->whereColumn('l2.id_empresa', 'locacao.id_empresa')
                    ->whereColumn('l2.numero_contrato', 'locacao.numero_contrato')
                    ->whereColumn('l2.aditivo', '>', 'locacao.aditivo')
                    ->where('l2.status', 'aprovado');
            });
        } else {
            return 0;
        }

        if ($idEmpresa) {
            $query->where('id_empresa', $idEmpresa);
        }

        $locacoesVencidas = $query->with(['produtos.produto', 'produtos.patrimonio'])->get();
        if ($locacoesVencidas->isEmpty()) {
            return 0;
        }

        $encerradas = 0;
        foreach ($locacoesVencidas as $locacaoVencida) {
            try {
                DB::transaction(function () use ($locacaoVencida) {
                    $this->encerrarLocacaoOrigem($locacaoVencida, null);
                });
                $encerradas++;
            } catch (\Throwable $e) {
                logger()->error('Erro ao encerrar locação vencida com aditivo', [
                    'id_locacao' => $locacaoVencida->id_locacao,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $encerradas;
    }

    private function combinarDataHoraSegura($data, $hora, string $horaPadrao): Carbon
    {
        $dataNormalizada = $data instanceof \DateTimeInterface
            ? $data->format('Y-m-d')
            : Carbon::parse((string) $data)->toDateString();

        $horaRaw = trim((string) ($hora ?: $horaPadrao));
        $horaNormalizada = Carbon::parse($horaRaw)->format('H:i:s');

        return Carbon::createFromFormat('Y-m-d H:i:s', $dataNormalizada . ' ' . $horaNormalizada);
    }

    private function ajustarIndiceNumeroContratoParaAditivo(): void
    {
        if (!Schema::hasTable('locacao') || !$this->hasColunaLocacao('aditivo')) {
            return;
        }

        try {
            $database = DB::getDatabaseName();
            $indicesUnicosNumeroContrato = DB::select(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'locacao' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'numero_contrato'",
                [$database]
            );

            if (empty($indicesUnicosNumeroContrato)) {
                return;
            }

            foreach ($indicesUnicosNumeroContrato as $indice) {
                $nomeIndice = (string) ($indice->INDEX_NAME ?? '');
                if ($nomeIndice === '') {
                    continue;
                }

                $colunasIndice = DB::select(
                    "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'locacao' AND INDEX_NAME = ?
                     ORDER BY SEQ_IN_INDEX",
                    [$database, $nomeIndice]
                );

                $colunas = array_map(static fn ($c) => (string) ($c->COLUMN_NAME ?? ''), $colunasIndice);
                if (count($colunas) === 1 && $colunas[0] === 'numero_contrato') {
                    DB::statement("ALTER TABLE locacao DROP INDEX {$nomeIndice}");
                    break;
                }
            }

            $indiceCompostoExiste = DB::select(
                "SELECT 1
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = 'locacao'
                   AND INDEX_NAME = 'uq_locacao_empresa_contrato_aditivo'
                 LIMIT 1",
                [$database]
            );

            if (empty($indiceCompostoExiste)) {
                DB::statement('ALTER TABLE locacao ADD UNIQUE KEY uq_locacao_empresa_contrato_aditivo (id_empresa, numero_contrato, aditivo)');
            }
        } catch (\Throwable $e) {
            throw new \Exception('Não foi possível ajustar o índice de numero_contrato automaticamente. Execute: ALTER TABLE locacao DROP INDEX numero_contrato; ALTER TABLE locacao ADD UNIQUE KEY uq_locacao_empresa_contrato_aditivo (id_empresa, numero_contrato, aditivo);');
        }
    }
}
