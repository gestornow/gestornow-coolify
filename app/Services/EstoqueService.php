<?php

namespace App\Services;

use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\Patrimonio;
use App\Domain\Produto\Models\Manutencao;
use App\Domain\Produto\Models\PatrimonioHistorico;
use App\Domain\Produto\Models\ProdutoHistorico;
use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoProduto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstoqueService
{
    /**
     * Calcular quantidade disponível de um produto em um período específico
     * 
     * SIMPLIFICADO: Usa apenas data de transporte ou data do contrato
     * - data_transporte: Usa as datas de transporte da locação (preferencial)
     * - data_contrato: Usa as datas do contrato (fallback)
     * 
     * @param int $idProduto
     * @param int $idEmpresa
     * @param string $dataInicio
     * @param string $dataFim
     * @param string|null $horaInicio
     * @param string|null $horaFim
     * @param int|null $excluirLocacao ID da locação a excluir da verificação (para edições)
    * @param string $preferenciaEstoque 'data_item', 'data_contrato' ou 'data_transporte'
     * @return array
     */
    public function calcularDisponibilidade(
        int $idProduto,
        int $idEmpresa,
        string $dataInicio,
        string $dataFim,
        ?string $horaInicio = null,
        ?string $horaFim = null,
        ?int $excluirLocacao = null,
        string $preferenciaEstoque = 'data_item'
    ): array {
        $produto = Produto::where('id_produto', $idProduto)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$produto) {
            return [
                'disponivel' => 0,
                'estoque_total' => 0,
                'reservado' => 0,
                'em_locacao' => 0,
                'patrimonios_disponiveis' => [],
                'conflitos' => [],
            ];
        }

        $estoqueTotal = $produto->estoque_total ?? $produto->quantidade ?? 0;
        
        // Buscar locações ativas para cálculo de ocupação no período solicitado
        $locacoesConflitantes = Locacao::where('id_empresa', $idEmpresa)
            ->whereIn('status', ['aprovado', 'reserva', 'em_andamento', 'retirada', 'atrasada', 'medicao'])
            ->when($excluirLocacao, function ($q) use ($excluirLocacao) {
                $q->where('id_locacao', '!=', $excluirLocacao);
            })
            ->with(['produtos' => function ($q) use ($idProduto) {
                $q->where('id_produto', $idProduto);
            }])
            ->get();

        $inicioSolicitado = Carbon::parse(($dataInicio instanceof \DateTime ? $dataInicio->format('Y-m-d') : $dataInicio) . ' ' . ($horaInicio ?? '00:00:00'));
        $fimSolicitado = Carbon::parse(($dataFim instanceof \DateTime ? $dataFim->format('Y-m-d') : $dataFim) . ' ' . ($horaFim ?? '23:59:59'));

        $eventosTotais = [];
        $eventosReservados = [];
        $eventosEmLocacao = [];
        $quantidadeAtrasadaBloqueada = 0;

        $ocupacaoInicialTotal = 0;
        $ocupacaoInicialReservados = 0;
        $ocupacaoInicialEmLocacao = 0;

        $patrimoniosOcupados = [];
        $conflitos = [];

        foreach ($locacoesConflitantes as $locacao) {
            foreach ($locacao->produtos as $item) {
                $datasEfetivas = $this->obterDatasEfetivasItem($locacao, $item, $preferenciaEstoque);

                $inicioExistente = Carbon::parse($datasEfetivas['data_inicio'] . ' ' . ($datasEfetivas['hora_inicio'] ?? '00:00:00'));
                $fimExistente = Carbon::parse($datasEfetivas['data_fim'] . ' ' . ($datasEfetivas['hora_fim'] ?? '23:59:59'));
                $quantidadeItem = (int)($item->quantidade ?? 0);

                if ($quantidadeItem <= 0) {
                    continue;
                }

                if ($this->deveBloquearPorRetornoAtrasado($locacao, $item, $fimExistente, $inicioSolicitado)) {
                    $quantidadeBloqueadaAtraso = $this->obterQuantidadeBloqueioAtraso($item);
                    if ($quantidadeBloqueadaAtraso <= 0) {
                        continue;
                    }

                    $quantidadeAtrasadaBloqueada += $quantidadeBloqueadaAtraso;
                    $ocupacaoInicialTotal += $quantidadeBloqueadaAtraso;
                    $ocupacaoInicialEmLocacao += $quantidadeBloqueadaAtraso;

                    if ($item->id_patrimonio) {
                        $patrimoniosOcupados[] = $item->id_patrimonio;
                    }

                    $conflitos[] = [
                        'id_locacao' => $locacao->id_locacao,
                        'numero_contrato' => $locacao->numero_contrato,
                        'status' => 'atrasada',
                        'quantidade' => $quantidadeBloqueadaAtraso,
                        'data_inicio' => $datasEfetivas['data_inicio'],
                        'hora_inicio' => $datasEfetivas['hora_inicio'],
                        'data_fim' => $datasEfetivas['data_fim'],
                        'hora_fim' => $datasEfetivas['hora_fim'],
                        'periodo' => $datasEfetivas['data_inicio'] . ' ' . $datasEfetivas['hora_inicio'] . ' - ' . $datasEfetivas['data_fim'] . ' ' . $datasEfetivas['hora_fim'],
                        'atrasado' => true,
                        'motivo' => 'Retorno atrasado: item permanece em aberto após data/hora de fim.',
                        'patrimonio' => $item->patrimonio ? ($item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie) : null,
                    ];

                    continue;
                }

                if (!$this->verificarConflitoHorario(
                    $dataInicio,
                    $dataFim,
                    $horaInicio,
                    $horaFim,
                    $datasEfetivas['data_inicio'],
                    $datasEfetivas['data_fim'],
                    $datasEfetivas['hora_inicio'],
                    $datasEfetivas['hora_fim']
                )) {
                    continue;
                }

                $eventoCategoria = in_array($locacao->status, ['aprovado', 'reserva'])
                    ? 'reservado'
                    : 'em_locacao';

                // Ocupação já ativa no início da janela
                if ($inicioExistente->lte($inicioSolicitado) && $fimExistente->gt($inicioSolicitado)) {
                    $ocupacaoInicialTotal += $quantidadeItem;
                    if ($eventoCategoria === 'reservado') {
                        $ocupacaoInicialReservados += $quantidadeItem;
                    } else {
                        $ocupacaoInicialEmLocacao += $quantidadeItem;
                    }
                }

                if ($inicioExistente->gt($inicioSolicitado) && $inicioExistente->lt($fimSolicitado)) {
                    $eventosTotais[] = ['time' => $inicioExistente->timestamp, 'delta' => $quantidadeItem, 'tipo' => 'start'];
                    if ($eventoCategoria === 'reservado') {
                        $eventosReservados[] = ['time' => $inicioExistente->timestamp, 'delta' => $quantidadeItem, 'tipo' => 'start'];
                    } else {
                        $eventosEmLocacao[] = ['time' => $inicioExistente->timestamp, 'delta' => $quantidadeItem, 'tipo' => 'start'];
                    }
                }

                if ($fimExistente->gt($inicioSolicitado) && $fimExistente->lt($fimSolicitado)) {
                    $eventosTotais[] = ['time' => $fimExistente->timestamp, 'delta' => -$quantidadeItem, 'tipo' => 'end'];
                    if ($eventoCategoria === 'reservado') {
                        $eventosReservados[] = ['time' => $fimExistente->timestamp, 'delta' => -$quantidadeItem, 'tipo' => 'end'];
                    } else {
                        $eventosEmLocacao[] = ['time' => $fimExistente->timestamp, 'delta' => -$quantidadeItem, 'tipo' => 'end'];
                    }
                }

                if ($item->id_patrimonio) {
                    $patrimoniosOcupados[] = $item->id_patrimonio;
                }

                $conflitos[] = [
                    'id_locacao' => $locacao->id_locacao,
                    'numero_contrato' => $locacao->numero_contrato,
                    'status' => $locacao->status,
                    'quantidade' => $quantidadeItem,
                    'data_inicio' => $datasEfetivas['data_inicio'],
                    'hora_inicio' => $datasEfetivas['hora_inicio'],
                    'data_fim' => $datasEfetivas['data_fim'],
                    'hora_fim' => $datasEfetivas['hora_fim'],
                    'periodo' => $datasEfetivas['data_inicio'] . ' ' . $datasEfetivas['hora_inicio'] . ' - ' . $datasEfetivas['data_fim'] . ' ' . $datasEfetivas['hora_fim'],
                    'patrimonio' => $item->patrimonio ? ($item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie) : null,
                ];
            }
        }

        // Manutenções com patrimônio ocupam o item no período (como se estivesse locado)
        $colunasManutencao = Schema::getColumnListing('manutencoes');
        $temDataPrevisaoManutencao = in_array('data_previsao', $colunasManutencao, true);
        $temHoraManutencao = in_array('hora_manutencao', $colunasManutencao, true);
        $temHoraPrevisao = in_array('hora_previsao', $colunasManutencao, true);
        $temQuantidadeManutencao = in_array('quantidade', $colunasManutencao, true);
        $patrimoniosManutencaoContados = [];

        $manutencoesConflitantes = Manutencao::where('id_empresa', $idEmpresa)
            ->where('id_produto', $idProduto)
            ->whereIn('status', ['em_andamento', 'pendente'])
            ->get();

        foreach ($manutencoesConflitantes as $manutencao) {
            $dataInicioManutencao = $manutencao->data_manutencao
                ? Carbon::parse($manutencao->data_manutencao)->format('Y-m-d')
                : null;

            if (!$dataInicioManutencao) {
                continue;
            }

            $dataFimManutencao = $dataInicioManutencao;
            if ($temDataPrevisaoManutencao && !empty($manutencao->data_previsao)) {
                $dataFimManutencao = Carbon::parse($manutencao->data_previsao)->format('Y-m-d');
            }

            $horaInicioManutencao = '00:00:00';
            $horaFimManutencao = '23:59:59';

            if ($temHoraManutencao && !empty($manutencao->hora_manutencao)) {
                $horaInicioManutencao = substr((string) $manutencao->hora_manutencao, 0, 8);
            }

            if ($temHoraPrevisao && !empty($manutencao->hora_previsao)) {
                $horaFimManutencao = substr((string) $manutencao->hora_previsao, 0, 8);
            }

            if (!$this->verificarConflitoHorario(
                $dataInicio,
                $dataFim,
                $horaInicio,
                $horaFim,
                $dataInicioManutencao,
                $dataFimManutencao,
                $horaInicioManutencao,
                $horaFimManutencao
            )) {
                continue;
            }

            $ocupacaoManutencao = 1;

            if (!empty($manutencao->id_patrimonio)) {
                $idPatrimonio = (int) $manutencao->id_patrimonio;
                if (in_array($idPatrimonio, $patrimoniosManutencaoContados, true)) {
                    continue;
                }

                $patrimoniosManutencaoContados[] = $idPatrimonio;
                $patrimoniosOcupados[] = $idPatrimonio;
                $ocupacaoManutencao = 1;
            } else {
                $ocupacaoManutencao = $temQuantidadeManutencao
                    ? max(1, (int) ($manutencao->quantidade ?? 1))
                    : 1;
            }

            if ($ocupacaoManutencao <= 0) {
                continue;
            }

            $inicioManutencao = Carbon::parse($dataInicioManutencao . ' ' . ($horaInicioManutencao ?? '00:00:00'));
            $fimManutencao = Carbon::parse($dataFimManutencao . ' ' . ($horaFimManutencao ?? '23:59:59'));

            if ($inicioManutencao->lte($inicioSolicitado) && $fimManutencao->gt($inicioSolicitado)) {
                $ocupacaoInicialTotal += $ocupacaoManutencao;
                $ocupacaoInicialEmLocacao += $ocupacaoManutencao;
            }

            if ($inicioManutencao->gt($inicioSolicitado) && $inicioManutencao->lt($fimSolicitado)) {
                $eventosTotais[] = ['time' => $inicioManutencao->timestamp, 'delta' => $ocupacaoManutencao, 'tipo' => 'start'];
                $eventosEmLocacao[] = ['time' => $inicioManutencao->timestamp, 'delta' => $ocupacaoManutencao, 'tipo' => 'start'];
            }

            if ($fimManutencao->gt($inicioSolicitado) && $fimManutencao->lt($fimSolicitado)) {
                $eventosTotais[] = ['time' => $fimManutencao->timestamp, 'delta' => -$ocupacaoManutencao, 'tipo' => 'end'];
                $eventosEmLocacao[] = ['time' => $fimManutencao->timestamp, 'delta' => -$ocupacaoManutencao, 'tipo' => 'end'];
            }

            $conflitos[] = [
                'id_manutencao' => $manutencao->id_manutencao,
                'numero_contrato' => null,
                'status' => 'manutencao',
                'quantidade' => $ocupacaoManutencao,
                'data_inicio' => $dataInicioManutencao,
                'hora_inicio' => $horaInicioManutencao,
                'data_fim' => $dataFimManutencao,
                'hora_fim' => $horaFimManutencao,
                'periodo' => $dataInicioManutencao . ' ' . $horaInicioManutencao . ' - ' . $dataFimManutencao . ' ' . $horaFimManutencao,
                'patrimonio' => $manutencao->patrimonio
                    ? ($manutencao->patrimonio->codigo_patrimonio ?? $manutencao->patrimonio->numero_serie)
                    : null,
                'descricao' => $manutencao->descricao,
            ];
        }

        $patrimoniosOcupados = array_values(array_unique(array_map('intval', $patrimoniosOcupados)));

        $quantidadeOcupada = $this->calcularPicoOcupacao($eventosTotais, $ocupacaoInicialTotal);
        $quantidadeReservada = $this->calcularPicoOcupacao($eventosReservados, $ocupacaoInicialReservados);
        $quantidadeEmLocacao = $this->calcularPicoOcupacao($eventosEmLocacao, $ocupacaoInicialEmLocacao);

        // Calcular patrimônios disponíveis
        $patrimoniosDisponiveis = [];
        if ($produto->patrimonios) {
            $patrimoniosDisponiveis = Patrimonio::where('id_produto', $idProduto)
                ->where('id_empresa', $idEmpresa)
                ->where('status', 'Ativo')
                ->whereNotIn('id_patrimonio', $patrimoniosOcupados)
                ->get()
                ->map(function ($p) {
                    return [
                        'id_patrimonio' => $p->id_patrimonio,
                        'numero_serie' => $p->codigo_patrimonio ?? $p->numero_serie ?? 'PAT-' . $p->id_patrimonio,
                        'status' => $p->status_locacao ?? 'Disponivel',
                    ];
                })
                ->toArray();
        }

        $quantidadeDisponivel = max(0, $estoqueTotal - $quantidadeOcupada);

        // Se tem patrimônios, a disponibilidade é baseada neles
        if (count($patrimoniosDisponiveis) > 0 || count($patrimoniosOcupados) > 0) {
            $quantidadeDisponivel = count($patrimoniosDisponiveis);
        }

        return [
            'disponivel' => $quantidadeDisponivel,
            'estoque_total' => $estoqueTotal,
            'reservado' => $quantidadeReservada,
            'em_locacao' => $quantidadeEmLocacao,
            'quantidade_atrasada_bloqueada' => $quantidadeAtrasadaBloqueada,
            'patrimonios_disponiveis' => $patrimoniosDisponiveis,
            'patrimonios_ocupados' => $patrimoniosOcupados,
            'conflitos' => $conflitos,
        ];
    }

    private function deveBloquearPorRetornoAtrasado(
        Locacao $locacao,
        LocacaoProduto $item,
        Carbon $fimExistente,
        Carbon $inicioSolicitado
    ): bool {
        if ($inicioSolicitado->lt($fimExistente)) {
            return false;
        }

        if (Carbon::now()->lte($fimExistente)) {
            return false;
        }

        if (($item->status_retorno ?? 'pendente') !== 'pendente') {
            return false;
        }

        $statusLocacao = strtolower((string)($locacao->status ?? ''));
        if (!in_array($statusLocacao, ['aprovado', 'reserva', 'em_andamento', 'retirada', 'atrasada', 'medicao'], true)) {
            return false;
        }

        if (isset($item->estoque_status)) {
            return (int)$item->estoque_status === 1;
        }

        return in_array($statusLocacao, ['em_andamento', 'retirada', 'atrasada', 'medicao'], true);
    }

    private function obterQuantidadeBloqueioAtraso(LocacaoProduto $item): int
    {
        if (!empty($item->id_patrimonio)) {
            return 1;
        }

        return max(0, (int)($item->quantidade ?? 0));
    }

    /**
     * Obter datas efetivas de uma locação para cálculo de estoque
     * Prioridade: data_transporte (se preenchida) > data do contrato
     * 
     * REGRA SIMPLIFICADA:
     * - Se data_transporte_ida está preenchida, usa ela como base
     * - Senão, usa data_inicio do contrato
     */
    public function obterDatasEfetivas(Locacao $locacao): array
    {
        // Verificar se tem data de transporte preenchida
        if ($locacao->data_transporte_ida) {
            $dataFimTransporte = $locacao->data_transporte_volta instanceof \DateTime
                ? $locacao->data_transporte_volta->format('Y-m-d')
                : ($locacao->data_transporte_volta ?? ($locacao->data_fim instanceof \DateTime ? $locacao->data_fim->format('Y-m-d') : $locacao->data_fim));

            return [
                'data_inicio' => $locacao->data_transporte_ida instanceof \DateTime 
                    ? $locacao->data_transporte_ida->format('Y-m-d') 
                    : $locacao->data_transporte_ida,
                'data_fim' => $dataFimTransporte ?: '2099-12-31',
                'hora_inicio' => $locacao->hora_transporte_ida ?? $locacao->hora_inicio ?? '00:00',
                'hora_fim' => $locacao->hora_transporte_volta ?? $locacao->hora_fim ?? '23:59',
            ];
        }
        
        // Fallback: usar datas do contrato (data_inicio e data_fim)
        $dataFimContrato = $locacao->data_fim instanceof \DateTime
            ? $locacao->data_fim->format('Y-m-d')
            : $locacao->data_fim;

        return [
            'data_inicio' => $locacao->data_inicio instanceof \DateTime 
                ? $locacao->data_inicio->format('Y-m-d') 
                : $locacao->data_inicio,
            'data_fim' => $dataFimContrato ?: '2099-12-31',
            'hora_inicio' => $locacao->hora_inicio ?? '00:00',
            'hora_fim' => $locacao->hora_fim ?? '23:59',
        ];
    }

    /**
     * Verificar conflito de horário entre dois períodos
     */
    private function verificarConflitoHorario(
        $dataInicioSolicitado, $dataFimSolicitado, $horaInicioSolicitado, $horaFimSolicitado,
        $dataInicioExistente, $dataFimExistente, $horaInicioExistente, $horaRetornoExistente
    ): bool {
        // Converter para timestamps para comparação precisa
        $dataInicioSolicitadoStr = $dataInicioSolicitado instanceof \DateTime 
            ? $dataInicioSolicitado->format('Y-m-d') 
            : $dataInicioSolicitado;
        $dataFimSolicitadoStr = $dataFimSolicitado instanceof \DateTime 
            ? $dataFimSolicitado->format('Y-m-d') 
            : $dataFimSolicitado;
        $dataInicioExistenteStr = $dataInicioExistente instanceof \DateTime 
            ? $dataInicioExistente->format('Y-m-d') 
            : $dataInicioExistente;
        $dataFimExistenteStr = $dataFimExistente instanceof \DateTime 
            ? $dataFimExistente->format('Y-m-d') 
            : $dataFimExistente;
            
        $inicioSolicitado = strtotime($dataInicioSolicitadoStr . ' ' . ($horaInicioSolicitado ?? '00:00:00'));
        $fimSolicitado = strtotime($dataFimSolicitadoStr . ' ' . ($horaFimSolicitado ?? '23:59:59'));
        
        $inicioExistente = strtotime($dataInicioExistenteStr . ' ' . ($horaInicioExistente ?? '00:00:00'));
        $fimExistente = strtotime($dataFimExistenteStr . ' ' . ($horaRetornoExistente ?? '23:59:59'));

        // Verifica sobreposição
        return $inicioSolicitado < $fimExistente && $fimSolicitado > $inicioExistente;
    }

    /**
     * Calcula o pico de ocupação (máximo simultâneo) para um conjunto de eventos.
     */
    private function calcularPicoOcupacao(array $eventos, int $ocupacaoInicial = 0): int
    {
        $ocupacaoAtual = max(0, $ocupacaoInicial);
        $pico = $ocupacaoAtual;

        usort($eventos, function ($a, $b) {
            if ($a['time'] === $b['time']) {
                // Em horários iguais, processa término antes de início
                if ($a['tipo'] === $b['tipo']) {
                    return 0;
                }
                return $a['tipo'] === 'end' ? -1 : 1;
            }
            return $a['time'] <=> $b['time'];
        });

        foreach ($eventos as $evento) {
            $ocupacaoAtual += (int)($evento['delta'] ?? 0);
            if ($ocupacaoAtual > $pico) {
                $pico = $ocupacaoAtual;
            }
        }

        return max(0, $pico);
    }

    /**
     * Obtém o período efetivo de um item da locação.
     */
    private function obterDatasEfetivasItem(Locacao $locacao, LocacaoProduto $item, string $preferenciaEstoque = 'data_contrato'): array
    {
        if ($preferenciaEstoque === 'data_transporte' && $locacao->data_transporte_ida) {
            return $this->obterDatasEfetivas($locacao);
        }

        if ($preferenciaEstoque === 'data_contrato') {
            $dataInicioContrato = $item->data_contrato
                ? ($item->data_contrato instanceof \DateTime ? $item->data_contrato->format('Y-m-d') : $item->data_contrato)
                : ($locacao->data_inicio instanceof \DateTime ? $locacao->data_inicio->format('Y-m-d') : $locacao->data_inicio);

            $dataFimContrato = $item->data_contrato_fim
                ? ($item->data_contrato_fim instanceof \DateTime ? $item->data_contrato_fim->format('Y-m-d') : $item->data_contrato_fim)
                : ($locacao->data_fim instanceof \DateTime ? $locacao->data_fim->format('Y-m-d') : $locacao->data_fim);

            $dataFimContrato = $item->data_contrato_fim
                ? ($item->data_contrato_fim instanceof \DateTime ? $item->data_contrato_fim->format('Y-m-d') : $item->data_contrato_fim)
                : ($locacao->data_fim instanceof \DateTime ? $locacao->data_fim->format('Y-m-d') : $locacao->data_fim);

            return [
                'data_inicio' => $dataInicioContrato,
                'data_fim' => $dataFimContrato ?: '2099-12-31',
                'hora_inicio' => $item->hora_contrato ?? $locacao->hora_inicio ?? '00:00',
                'hora_fim' => $item->hora_contrato_fim ?? $locacao->hora_fim ?? '23:59',
            ];
        }

        $dataFimItem = $item->data_fim
            ? ($item->data_fim instanceof \DateTime ? $item->data_fim->format('Y-m-d') : $item->data_fim)
            : ($locacao->data_fim instanceof \DateTime ? $locacao->data_fim->format('Y-m-d') : $locacao->data_fim);

        return [
            'data_inicio' => $item->data_inicio
                ? ($item->data_inicio instanceof \DateTime ? $item->data_inicio->format('Y-m-d') : $item->data_inicio)
                : ($locacao->data_inicio instanceof \DateTime ? $locacao->data_inicio->format('Y-m-d') : $locacao->data_inicio),
            'data_fim' => $dataFimItem ?: '2099-12-31',
            'hora_inicio' => $item->hora_inicio ?? $locacao->hora_inicio ?? '00:00',
            'hora_fim' => $item->hora_fim ?? $locacao->hora_fim ?? '23:59',
        ];
    }

    /**
     * Registrar saída de produto para locação
     * Chamado quando a locação muda para status "em_andamento"
     */
    public function registrarSaidaLocacao(LocacaoProduto $produtoLocacao, int $idUsuario = null)
    {
        if ((int) ($produtoLocacao->estoque_status ?? 0) !== 0) {
            return;
        }

        $locacao = $produtoLocacao->locacao;
        $produto = $produtoLocacao->produto;
        
        // Registrar no histórico do produto
        ProdutoHistorico::registrar([
            'id_empresa' => $produtoLocacao->id_empresa,
            'id_produto' => $produtoLocacao->id_produto,
            'id_locacao' => $produtoLocacao->id_locacao,
            'id_cliente' => $locacao->id_cliente,
            'tipo_movimentacao' => 'saida',
            'quantidade' => $produtoLocacao->quantidade,
            'estoque_anterior' => $produto->quantidade ?? 0,
            'estoque_novo' => max(0, ($produto->quantidade ?? 0) - $produtoLocacao->quantidade),
            'motivo' => 'Saída para locação #' . $locacao->numero_contrato,
            'id_usuario' => $idUsuario,
        ]);

        // Atualizar saldo real do produto em estoque
        if ($produto) {
            $estoqueAtual = (int) ($produto->quantidade ?? 0);
            $produto->quantidade = max(0, $estoqueAtual - (int) ($produtoLocacao->quantidade ?? 0));
            $produto->save();
        }

        // Se tem patrimônio, registrar movimentação e atualizar status
        if ($produtoLocacao->id_patrimonio) {
            $patrimonio = $produtoLocacao->patrimonio;
            
            if ($patrimonio) {
                PatrimonioHistorico::registrar([
                    'id_empresa' => $produtoLocacao->id_empresa,
                    'id_patrimonio' => $produtoLocacao->id_patrimonio,
                    'id_produto' => $produtoLocacao->id_produto,
                    'id_locacao' => $produtoLocacao->id_locacao,
                    'id_cliente' => $locacao->id_cliente,
                    'tipo_movimentacao' => 'saida_locacao',
                    'status_anterior' => $patrimonio->status_locacao ?? 'Disponivel',
                    'status_novo' => 'Locado',
                    'local_destino' => $locacao->local_entrega,
                    'observacoes' => 'Locação #' . $locacao->numero_contrato,
                    'id_usuario' => $idUsuario,
                ]);

                // Atualizar patrimônio para Locado
                $patrimonio->update([
                    'status_locacao' => 'Locado',
                    'id_locacao_atual' => $produtoLocacao->id_locacao,
                    'localizacao_atual' => $locacao->local_entrega,
                    'data_ultima_movimentacao' => now(),
                ]);
            }
        }
        
        // Atualizar status do item na locação
        $produtoLocacao->update([
            'status_retorno' => 'pendente',
        ]);
    }

    /**
     * Registrar retorno de produto da locação
     * Chamado quando o usuário finaliza o contrato
     */
    public function registrarRetornoLocacao(
        LocacaoProduto $produtoLocacao,
        string $statusRetorno = 'normal',
        ?string $observacoes = null,
        int $idUsuario = null,
        ?int $quantidadeRetorno = null
    ) {
        if ((int) ($produtoLocacao->estoque_status ?? 0) !== 1) {
            return;
        }

        if (($produtoLocacao->status_retorno ?? 'pendente') !== 'pendente') {
            return;
        }

        $statusRetornoNormalizado = $this->normalizarStatusRetornoPatrimonio($statusRetorno);
        $statusRetornoProduto = $statusRetornoNormalizado === 'normal' ? 'devolvido' : $statusRetornoNormalizado;
        $quantidadeItem = max(1, (int) ($produtoLocacao->quantidade ?? 1));
        $quantidadeRetornar = $quantidadeRetorno === null
            ? $quantidadeItem
            : max(0, min($quantidadeItem, (int) $quantidadeRetorno));

        $locacao = $produtoLocacao->locacao;
        $produto = $produtoLocacao->produto;
        
        // Registrar no histórico do produto
        if ($quantidadeRetornar > 0) {
            ProdutoHistorico::registrar([
                'id_empresa' => $produtoLocacao->id_empresa,
                'id_produto' => $produtoLocacao->id_produto,
                'id_locacao' => $produtoLocacao->id_locacao,
                'id_cliente' => $locacao->id_cliente,
                'tipo_movimentacao' => 'retorno',
                'quantidade' => $quantidadeRetornar,
                'estoque_anterior' => $produto->quantidade ?? 0,
                'estoque_novo' => ($produto->quantidade ?? 0) + $quantidadeRetornar,
                'motivo' => 'Retorno de locação #' . $locacao->numero_contrato,
                'observacoes' => $observacoes,
                'id_usuario' => $idUsuario,
            ]);
        }

        // Atualizar saldo real do produto em estoque
        if ($produto && $quantidadeRetornar > 0) {
            $estoqueAtual = (int) ($produto->quantidade ?? 0);
            $produto->quantidade = $estoqueAtual + $quantidadeRetornar;
            $produto->save();
        }

        // Se tem patrimônio, registrar retorno e atualizar status
        if ($produtoLocacao->id_patrimonio) {
            $patrimonio = $produtoLocacao->patrimonio;
            
            if ($patrimonio) {
                $statusNovoPatrimonio = 'Disponivel';
                if ($statusRetornoNormalizado === 'avariado') {
                    $statusNovoPatrimonio = 'Em Manutencao';
                } elseif ($statusRetornoNormalizado === 'extraviado') {
                    $statusNovoPatrimonio = 'Extraviado';
                }
                
                PatrimonioHistorico::registrar([
                    'id_empresa' => $produtoLocacao->id_empresa,
                    'id_patrimonio' => $produtoLocacao->id_patrimonio,
                    'id_produto' => $produtoLocacao->id_produto,
                    'id_locacao' => $produtoLocacao->id_locacao,
                    'id_cliente' => $locacao->id_cliente,
                    'tipo_movimentacao' => 'retorno_locacao',
                    'status_anterior' => 'Locado',
                    'status_novo' => $statusNovoPatrimonio,
                    'local_origem' => $locacao->local_entrega,
                    'local_destino' => 'Estoque',
                    'observacoes' => $observacoes ?? ('Retorno de locação #' . $locacao->numero_contrato),
                    'id_usuario' => $idUsuario,
                ]);

                // Atualizar patrimônio para disponível
                $patrimonio->update([
                    'status_locacao' => $statusNovoPatrimonio,
                    'id_locacao_atual' => null,
                    'localizacao_atual' => 'Estoque',
                    'data_ultima_movimentacao' => now(),
                ]);
            }
        }

        // Atualizar status do item na locação
        $produtoLocacao->update([
            'status_retorno' => $statusRetornoProduto,
        ]);
    }

    private function normalizarStatusRetornoPatrimonio(string $status): string
    {
        $status = trim(strtolower($status));

        if ($status === 'devolvido') {
            return 'normal';
        }

        return in_array($status, ['normal', 'avariado', 'extraviado'], true)
            ? $status
            : 'normal';
    }

    /**
     * Obter produtos disponíveis para locação em um período
     */
    public function getProdutosDisponiveis(
        int $idEmpresa,
        string $dataInicio,
        string $dataFim,
        ?string $horaInicio = null,
        ?string $horaFim = null,
        ?int $excluirLocacao = null,
        string $preferenciaEstoque = 'data_item'
    ): array {
        $produtos = Produto::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->with(['patrimonios' => function ($q) {
                $q->whereNotIn('status', ['baixado', 'Baixado', 'extraviado', 'Extraviado', 'inativo', 'Inativo']);
            }, 'tabelasPreco'])
            ->orderBy('nome')
            ->get();

        $resultado = [];

        foreach ($produtos as $produto) {
            $disponibilidade = $this->calcularDisponibilidade(
                $produto->id_produto,
                $idEmpresa,
                $dataInicio,
                $dataFim,
                $horaInicio,
                $horaFim,
                $excluirLocacao,
                $preferenciaEstoque
            );

            $resultado[] = [
                'id_produto' => $produto->id_produto,
                'nome' => $produto->nome,
                'codigo' => $produto->codigo,
                'foto_url' => $produto->foto_url,
                'preco_locacao' => $produto->preco_locacao ?? $produto->preco_venda ?? 0,
                'estoque_total' => $disponibilidade['estoque_total'],
                'quantidade_disponivel' => $disponibilidade['disponivel'],
                'quantidade_reservada' => $disponibilidade['reservado'],
                'quantidade_em_locacao' => $disponibilidade['em_locacao'],
                'quantidade_atrasada_bloqueada' => $disponibilidade['quantidade_atrasada_bloqueada'] ?? 0,
                'patrimonios_disponiveis' => $disponibilidade['patrimonios_disponiveis'],
                'conflitos' => $disponibilidade['conflitos'],
                'patrimonios' => $produto->patrimonios->map(function ($p) {
                    return [
                        'id_patrimonio' => $p->id_patrimonio,
                        'numero_serie' => $p->codigo_patrimonio ?? $p->numero_serie ?? ('PAT-' . $p->id_patrimonio),
                        'status' => $p->status,
                        'status_locacao' => $p->status_locacao ?? 'Disponivel',
                    ];
                })->toArray(),
                'tabelas_preco' => $produto->tabelasPreco->map(function ($t) {
                    return [
                        'id_tabela' => $t->id_tabela,
                        'nome' => $t->nome,
                        'd1' => floatval($t->d1 ?? 0),
                        'd2' => floatval($t->d2 ?? 0),
                        'd3' => floatval($t->d3 ?? 0),
                        'd4' => floatval($t->d4 ?? 0),
                        'd5' => floatval($t->d5 ?? 0),
                        'd6' => floatval($t->d6 ?? 0),
                        'd7' => floatval($t->d7 ?? 0),
                        'd15' => floatval($t->d15 ?? 0),
                        'd30' => floatval($t->d30 ?? 0),
                    ];
                })->toArray(),
            ];
        }

        return $resultado;
    }
}
