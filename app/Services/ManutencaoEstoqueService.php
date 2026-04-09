<?php

namespace App\Services;

use App\Domain\Produto\Models\Manutencao;
use App\Domain\Produto\Models\Patrimonio;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\ProdutoHistorico;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManutencaoEstoqueService
{
    private $colunasManutencao;

    public function processarAgendadas(?Carbon $agora = null): array
    {
        $agora = $agora ?: Carbon::now();

        $iniciadas = 0;
        $concluidas = 0;

        $manutencoes = Manutencao::whereIn('status', ['em_andamento', 'pendente'])
            ->orderBy('data_manutencao')
            ->get();

        foreach ($manutencoes as $manutencao) {
            if ($this->iniciarSeElegivel($manutencao, $agora)) {
                $iniciadas++;
            }

            if ($this->concluirSeElegivel($manutencao, $agora, false)) {
                $concluidas++;
            }
        }

        return [
            'iniciadas' => $iniciadas,
            'concluidas' => $concluidas,
        ];
    }

    public function sincronizarAoSalvar(Manutencao $manutencao, ?string $statusAnterior = null): void
    {
        $agora = Carbon::now();
        $statusAtual = $this->normalizarStatus($manutencao->status);
        $statusAnteriorNormalizado = $this->normalizarStatus($statusAnterior);

        if ($statusAtual === 'concluida' && $statusAnteriorNormalizado !== 'concluida') {
            $this->concluirSeElegivel($manutencao, $agora, true);
            return;
        }

        if (in_array($statusAtual, ['em_andamento', 'pendente'], true)) {
            $this->iniciarSeElegivel($manutencao, $agora);
        }
    }

    public function iniciarSeElegivel(Manutencao $manutencao, ?Carbon $agora = null): bool
    {
        $agora = $agora ?: Carbon::now();

        if (!in_array($this->normalizarStatus($manutencao->status), ['em_andamento', 'pendente'], true)) {
            return false;
        }

        if (!$this->inicioAtingido($manutencao, $agora)) {
            return false;
        }

        if (!$this->estaDentroJanelaAtiva($manutencao, $agora)) {
            return false;
        }

        if ($this->obterEstoqueStatus($manutencao) === 1) {
            return false;
        }

        DB::transaction(function () use ($manutencao, $agora) {
            $manutencao = Manutencao::where('id_manutencao', $manutencao->id_manutencao)
                ->lockForUpdate()
                ->first();

            if (!$manutencao || $this->obterEstoqueStatus($manutencao) === 1) {
                return;
            }

            if (!empty($manutencao->id_patrimonio)) {
                $patrimonio = Patrimonio::where('id_patrimonio', $manutencao->id_patrimonio)
                    ->lockForUpdate()
                    ->first();

                if ($patrimonio) {
                    $patrimonio->status_locacao = 'Em Manutencao';
                    $patrimonio->data_ultima_movimentacao = $agora;
                    $patrimonio->save();
                }
            } else {
                $produto = Produto::where('id_produto', $manutencao->id_produto)
                    ->lockForUpdate()
                    ->first();

                if ($produto) {
                    $quantidade = max(1, (int) ($manutencao->quantidade ?? 1));
                    $estoqueAnterior = (int) ($produto->quantidade ?? 0);
                    $produto->quantidade = max(0, $estoqueAnterior - $quantidade);
                    $produto->save();

                    ProdutoHistorico::registrar([
                        'id_empresa' => $manutencao->id_empresa,
                        'id_produto' => $manutencao->id_produto,
                        'tipo_movimentacao' => 'saida',
                        'quantidade' => $quantidade,
                        'estoque_anterior' => $estoqueAnterior,
                        'estoque_novo' => $produto->quantidade,
                        'motivo' => 'Início de manutenção #' . $manutencao->id_manutencao,
                        'observacoes' => $manutencao->descricao,
                    ]);
                }
            }

            $this->definirEstoqueStatus($manutencao, 1);
            $manutencao->save();
        });

        return true;
    }

    public function concluirSeElegivel(Manutencao $manutencao, ?Carbon $agora = null, bool $forcar = false): bool
    {
        $agora = $agora ?: Carbon::now();

        $statusAtual = $this->normalizarStatus($manutencao->status);
        if (!$forcar && $statusAtual !== 'em_andamento' && $statusAtual !== 'pendente') {
            return false;
        }

        if (!$forcar && !$this->previsaoAtingida($manutencao, $agora)) {
            return false;
        }

        if ($this->obterEstoqueStatus($manutencao) >= 2) {
            return false;
        }

        DB::transaction(function () use ($manutencao, $agora) {
            $manutencao = Manutencao::where('id_manutencao', $manutencao->id_manutencao)
                ->lockForUpdate()
                ->first();

            if (!$manutencao || $this->obterEstoqueStatus($manutencao) >= 2) {
                return;
            }

            $estoqueStatus = $this->obterEstoqueStatus($manutencao);

            if ($estoqueStatus === 1) {
                if (!empty($manutencao->id_patrimonio)) {
                    $patrimonio = Patrimonio::where('id_patrimonio', $manutencao->id_patrimonio)
                        ->lockForUpdate()
                        ->first();

                    if ($patrimonio) {
                        $patrimonio->status_locacao = 'Disponivel';
                        $patrimonio->ultima_manutencao = $agora;
                        $patrimonio->data_ultima_movimentacao = $agora;
                        $patrimonio->save();
                    }
                } else {
                    $produto = Produto::where('id_produto', $manutencao->id_produto)
                        ->lockForUpdate()
                        ->first();

                    if ($produto) {
                        $quantidade = max(1, (int) ($manutencao->quantidade ?? 1));
                        $estoqueAnterior = (int) ($produto->quantidade ?? 0);
                        $produto->quantidade = $estoqueAnterior + $quantidade;
                        $produto->save();

                        ProdutoHistorico::registrar([
                            'id_empresa' => $manutencao->id_empresa,
                            'id_produto' => $manutencao->id_produto,
                            'tipo_movimentacao' => 'entrada',
                            'quantidade' => $quantidade,
                            'estoque_anterior' => $estoqueAnterior,
                            'estoque_novo' => $produto->quantidade,
                            'motivo' => 'Conclusão de manutenção #' . $manutencao->id_manutencao,
                            'observacoes' => $manutencao->descricao,
                        ]);
                    }
                }
            } elseif (!empty($manutencao->id_patrimonio)) {
                $patrimonio = Patrimonio::where('id_patrimonio', $manutencao->id_patrimonio)
                    ->lockForUpdate()
                    ->first();

                if ($patrimonio && $patrimonio->status_locacao === 'Em Manutencao') {
                    $patrimonio->status_locacao = 'Disponivel';
                    $patrimonio->ultima_manutencao = $agora;
                    $patrimonio->data_ultima_movimentacao = $agora;
                    $patrimonio->save();
                }
            }

            $manutencao->status = 'concluida';
            $this->definirEstoqueStatus($manutencao, 2);
            $manutencao->save();
        });

        return true;
    }

    private function inicioAtingido(Manutencao $manutencao, Carbon $agora): bool
    {
        $inicio = $this->resolverMomentoAgendado(
            $manutencao,
            'data_manutencao',
            'hora_manutencao',
            '00:00:00'
        );

        if (!$inicio) {
            return false;
        }

        return $inicio->lessThanOrEqualTo($agora);
    }

    private function previsaoAtingida(Manutencao $manutencao, Carbon $agora): bool
    {
        $previsao = $this->resolverMomentoAgendado(
            $manutencao,
            'data_previsao',
            'hora_previsao',
            '23:59:59'
        );

        if (!$previsao) {
            return false;
        }

        return $previsao->lessThanOrEqualTo($agora);
    }

    private function estaDentroJanelaAtiva(Manutencao $manutencao, Carbon $agora): bool
    {
        $inicio = $this->resolverMomentoAgendado(
            $manutencao,
            'data_manutencao',
            'hora_manutencao',
            '00:00:00'
        );

        if (!$inicio || $agora->lessThan($inicio)) {
            return false;
        }

        $previsao = $this->resolverMomentoAgendado(
            $manutencao,
            'data_previsao',
            'hora_previsao',
            '23:59:59'
        );

        if (!$previsao) {
            return true;
        }

        return $agora->lessThanOrEqualTo($previsao);
    }

    private function resolverMomentoAgendado(
        Manutencao $manutencao,
        string $campoData,
        string $campoHora,
        string $horaPadrao
    ): ?Carbon {
        if (!$this->temColuna($campoData) || empty($manutencao->{$campoData})) {
            return null;
        }

        if ($this->temColuna($campoHora) && !empty($manutencao->{$campoHora})) {
            $data = $this->normalizarData($manutencao->{$campoData});
            if (!$data) {
                return null;
            }

            $hora = substr((string) $manutencao->{$campoHora}, 0, 8);

            return Carbon::parse($data . ' ' . $hora);
        }

        $raw = method_exists($manutencao, 'getRawOriginal')
            ? $manutencao->getRawOriginal($campoData)
            : null;

        $momento = $this->normalizarDataHora($raw ?? $manutencao->{$campoData});
        if (!$momento) {
            return null;
        }

        if ($momento->format('H:i:s') !== '00:00:00') {
            return $momento;
        }

        return Carbon::parse($momento->format('Y-m-d') . ' ' . $horaPadrao);
    }

    private function normalizarDataHora($valor): ?Carbon
    {
        if ($valor instanceof \Carbon\CarbonInterface) {
            return Carbon::instance($valor);
        }

        if ($valor instanceof \DateTimeInterface) {
            return Carbon::instance($valor);
        }

        if (is_string($valor) && trim($valor) !== '') {
            return Carbon::parse($valor);
        }

        return null;
    }

    private function normalizarData($valor): ?string
    {
        if ($valor instanceof \Carbon\CarbonInterface) {
            return $valor->format('Y-m-d');
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_string($valor) && trim($valor) !== '') {
            return Carbon::parse($valor)->format('Y-m-d');
        }

        return null;
    }

    private function normalizarStatus(?string $status): string
    {
        $status = trim((string) $status);

        if ($status === 'pendente') {
            return 'em_andamento';
        }

        if ($status === 'concluida') {
            return 'concluida';
        }

        return 'em_andamento';
    }

    private function obterEstoqueStatus(Manutencao $manutencao): int
    {
        if ($this->temColuna('estoque_status')) {
            return (int) ($manutencao->estoque_status ?? 0);
        }

        return $this->normalizarStatus($manutencao->status) === 'concluida' ? 2 : 0;
    }

    private function definirEstoqueStatus(Manutencao $manutencao, int $status): void
    {
        if ($this->temColuna('estoque_status')) {
            $manutencao->estoque_status = $status;
        }
    }

    private function temColuna(string $coluna): bool
    {
        return in_array($coluna, $this->obterColunasManutencao(), true);
    }

    private function obterColunasManutencao(): array
    {
        if ($this->colunasManutencao === null) {
            $this->colunasManutencao = Schema::hasTable('manutencoes')
                ? Schema::getColumnListing('manutencoes')
                : [];
        }

        return $this->colunasManutencao;
    }
}
