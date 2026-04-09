@extends('layouts.layoutMaster')

@section('title', 'Detalhes da Locação')

@section('content')
<div class="container-xxl flex-grow-1">
    @php
        $periodoQuantidade = max(1, (int) ($locacao->periodo_qtd_exibicao ?? $locacao->quantidade_dias ?? 1));
        $periodoUnidadeExibicao = (string) ($locacao->periodo_unidade_exibicao ?? (($locacao->locacao_por_hora_exibicao ?? false) ? 'hora(s)' : 'dia(s)'));
        $periodoTitulo = ($locacao->locacao_por_hora_exibicao ?? false) ? 'Total de Horas' : 'Total de Dias';
        $podeEditarLocacao = \Perm::pode(auth()->user(), 'locacoes.editar');
        $podeAlterarStatusLocacao = \Perm::pode(auth()->user(), 'locacoes.alterar-status');
        $podeContratoPdfLocacao = \Perm::pode(auth()->user(), 'locacoes.contrato-pdf');
        $podeAssinaturaDigitalLocacao = \Perm::pode(auth()->user(), 'locacoes.assinatura-digital');

        $subtotalProdutosProprios = (float) (($locacao->produtos ?? collect())->sum(function ($item) {
            $valorUnitario = (float) ($item->preco_unitario ?? $item->valor_unitario ?? 0);
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $periodo = max(1, (int) ($item->periodo_qtd_exibicao ?? 1));
            $fator = !empty($item->valor_fechado) ? 1 : $periodo;

            return $valorUnitario * $quantidade * $fator;
        }));
        $subtotalProdutosTerceiros = (float) (($locacao->produtosTerceiros ?? collect())->sum(function ($item) {
            $valorUnitario = (float) ($item->preco_unitario ?? 0);
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $periodo = max(1, (int) ($item->periodo_qtd_exibicao ?? 1));
            $fator = !empty($item->valor_fechado) ? 1 : $periodo;

            return $valorUnitario * $quantidade * $fator;
        }));
        $subtotalProdutos = $subtotalProdutosProprios + $subtotalProdutosTerceiros;
        $subtotalServicos = (float) (($locacao->servicos ?? collect())->sum(function ($item) {
            return (float) ($item->valor_total ?? 0);
        }));

        $descontoResumo = (float) ($locacao->valor_desconto ?? $locacao->desconto ?? 0);
        $acrescimoResumo = (float) ($locacao->valor_acrescimo ?? $locacao->taxa_entrega ?? 0);
    @endphp
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="ti ti-file-text me-2"></i>
                            Locação #{{ $locacao->numero_contrato }}
                        </h5>
                        <small class="text-muted">Criada em {{ $locacao->created_at->format('d/m/Y H:i') }}</small>
                    </div>
                    <div class="d-flex gap-2">
                        @php
                            $statusColors = [
                                'orcamento' => 'secondary',
                                'reserva' => 'primary',
                                'em_andamento' => 'info',
                                'finalizada' => 'success',
                                'cancelada' => 'danger'
                            ];
                            $statusLabels = [
                                'orcamento' => 'Orçamento',
                                'reserva' => 'Reserva',
                                'em_andamento' => 'Em Andamento',
                                'finalizada' => 'Finalizada',
                                'cancelada' => 'Cancelada'
                            ];
                        @endphp
                        <span class="badge bg-label-{{ $statusColors[$locacao->status] ?? 'secondary' }} fs-6">
                            {{ $statusLabels[$locacao->status] ?? $locacao->status }}
                        </span>
                        @if($podeEditarLocacao)
                            <a href="{{ route('locacoes.edit', $locacao->id_locacao) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-pencil me-1"></i> Editar
                            </a>
                        @endif
                        <a href="{{ route('locacoes.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Informações do Cliente -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ti ti-user me-2"></i>
                                Cliente
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($locacao->cliente)
                                <h6 class="fw-semibold">{{ $locacao->cliente->nome }}</h6>
                                <p class="mb-1">
                                    <i class="ti ti-id me-1"></i>
                                    {{ $locacao->cliente->cpf_cnpj ?? 'Não informado' }}
                                </p>
                                @if($locacao->cliente->email)
                                    <p class="mb-1">
                                        <i class="ti ti-mail me-1"></i>
                                        {{ $locacao->cliente->email }}
                                    </p>
                                @endif
                                @if($locacao->cliente->celular)
                                    <p class="mb-1">
                                        <i class="ti ti-phone me-1"></i>
                                        {{ $locacao->cliente->celular }}
                                    </p>
                                @endif
                                @if($locacao->cliente->endereco)
                                    <p class="mb-0">
                                        <i class="ti ti-map-pin me-1"></i>
                                        {{ $locacao->cliente->endereco }}
                                        @if($locacao->cliente->cidade)
                                            , {{ $locacao->cliente->cidade }}
                                        @endif
                                        @if($locacao->cliente->uf)
                                            - {{ $locacao->cliente->uf }}
                                        @endif
                                    </p>
                                @endif
                            @else
                                <p class="text-muted mb-0">Cliente não encontrado</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Período e Detalhes -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ti ti-calendar me-2"></i>
                                Período da Locação
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <small class="text-muted">Data/Hora de Saída</small>
                                    <h6 class="mb-0">
                                        {{ optional($locacao->data_inicio)->format('d/m/Y') ?? '-' }}
                                        @if($locacao->hora_inicio)
                                            às {{ substr($locacao->hora_inicio, 0, 5) }}
                                        @endif
                                    </h6>
                                </div>
                                <div class="col-6 mb-3">
                                    <small class="text-muted">Data/Hora de Retorno</small>
                                    <h6 class="mb-0">
                                        {{ optional($locacao->data_fim)->format('d/m/Y') ?? '-' }}
                                        @if($locacao->hora_fim)
                                            às {{ substr($locacao->hora_fim, 0, 5) }}
                                        @endif
                                    </h6>
                                </div>
                                <div class="col-6 mb-3">
                                    <small class="text-muted">{{ $periodoTitulo }}</small>
                                    <h6 class="mb-0">{{ $periodoQuantidade }} {{ $periodoUnidadeExibicao }}</h6>
                                </div>
                                <div class="col-6 mb-3">
                                    <small class="text-muted">Tipo de Locação</small>
                                    <h6 class="mb-0">{{ ucfirst($locacao->tipo_locacao ?? 'Locação') }}</h6>
                                </div>
                                
                                @if($locacao->data_transporte_ida || $locacao->data_transporte_volta)
                                    <div class="col-12 mt-2 border-top pt-3">
                                        <h6 class="text-muted mb-2"><i class="ti ti-truck me-1"></i> Transporte</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Ida</small>
                                                <p class="mb-0">
                                                    {{ optional($locacao->data_transporte_ida)->format('d/m/Y') ?? '-' }}
                                                    @if($locacao->hora_transporte_ida)
                                                        às {{ $locacao->hora_transporte_ida }}
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Volta</small>
                                                <p class="mb-0">
                                                    {{ optional($locacao->data_transporte_volta)->format('d/m/Y') ?? '-' }}
                                                    @if($locacao->hora_transporte_volta)
                                                        às {{ $locacao->hora_transporte_volta }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        @if($locacao->preferencia_estoque)
                                            <small class="text-info d-block mt-2">
                                                <i class="ti ti-info-circle me-1"></i>
                                                Estoque calculado pela 
                                                @if($locacao->preferencia_estoque == 'data_transporte')
                                                    data do transporte
                                                @else
                                                    data do contrato
                                                @endif
                                            </small>
                                        @endif
                                    </div>
                                @endif
                                
                                @if($locacao->local_entrega)
                                    <div class="col-12 mt-2">
                                        <small class="text-muted">Endereço de Entrega</small>
                                        <h6 class="mb-0">{{ $locacao->local_entrega }}</h6>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Produtos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-package me-2"></i>
                        Produtos Locados
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Produto</th>
                                    <th>Sala</th>
                                    <th>Patrimônio</th>
                                    <th>Período</th>
                                    <th>Qtd</th>
                                    <th>Qtd {{ ($locacao->locacao_por_hora_exibicao ?? false) ? 'Horas' : 'Dias' }}</th>
                                    <th>Valor Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $temItensProdutos = (($locacao->produtos ?? collect())->count() + ($locacao->produtosTerceiros ?? collect())->count()) > 0;
                                @endphp

                                @if($temItensProdutos)
                                    @foreach($locacao->produtos as $produto)
                                        @php
                                            $valorUnitarioProduto = (float) ($produto->preco_unitario ?? $produto->valor_unitario ?? 0);
                                            $quantidadeProduto = max(1, (int) ($produto->quantidade ?? 1));
                                            $periodoProduto = max(1, (int) ($produto->periodo_qtd_exibicao ?? $periodoQuantidade));
                                            $fatorCobrancaProduto = !empty($produto->valor_fechado) ? 1 : $periodoProduto;
                                            $subtotalProduto = $valorUnitarioProduto * $quantidadeProduto * $fatorCobrancaProduto;
                                            $dataInicioProduto = optional($produto->data_inicio)->format('d/m') ?? optional($locacao->data_inicio)->format('d/m');
                                            $dataFimProduto = optional($produto->data_fim)->format('d/m') ?? optional($locacao->data_fim)->format('d/m');
                                            $horaInicioProduto = !empty($produto->hora_inicio) ? substr((string) $produto->hora_inicio, 0, 5) : (!empty($locacao->hora_inicio) ? substr((string) $locacao->hora_inicio, 0, 5) : '');
                                            $horaFimProduto = !empty($produto->hora_fim) ? substr((string) $produto->hora_fim, 0, 5) : (!empty($locacao->hora_fim) ? substr((string) $locacao->hora_fim, 0, 5) : '');
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary" title="Produto próprio">
                                                    <i class="ti ti-home ti-xs"></i>
                                                </span>
                                            </td>
                                            <td>{{ $produto->produto->nome ?? $produto->descricao ?? 'Item não encontrado' }}</td>
                                            <td>
                                                @if($produto->sala)
                                                    <span class="badge bg-label-info">{{ $produto->sala->nome }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $produto->patrimonio->codigo_patrimonio ?? $produto->patrimonio->numero_serie ?? '-' }}</td>
                                            <td>
                                                <small>
                                                    {{ $dataInicioProduto }}@if($horaInicioProduto) {{ $horaInicioProduto }}@endif
                                                    -
                                                    {{ $dataFimProduto }}@if($horaFimProduto) {{ $horaFimProduto }}@endif
                                                </small>
                                            </td>
                                            <td>{{ $quantidadeProduto }}</td>
                                            <td>{{ $periodoProduto }}</td>
                                            <td>R$ {{ number_format($valorUnitarioProduto, 2, ',', '.') }}</td>
                                            <td class="text-end">R$ {{ number_format($subtotalProduto, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach

                                    @foreach($locacao->produtosTerceiros as $produtoTerceiro)
                                        @php
                                            $valorUnitarioTerceiro = (float) ($produtoTerceiro->preco_unitario ?? 0);
                                            $quantidadeTerceiro = max(1, (int) ($produtoTerceiro->quantidade ?? 1));
                                            $periodoTerceiro = max(1, (int) ($produtoTerceiro->periodo_qtd_exibicao ?? $periodoQuantidade));
                                            $fatorCobrancaTerceiro = !empty($produtoTerceiro->valor_fechado) ? 1 : $periodoTerceiro;
                                            $subtotalTerceiro = $valorUnitarioTerceiro * $quantidadeTerceiro * $fatorCobrancaTerceiro;
                                            $horaInicioLocacao = !empty($locacao->hora_inicio) ? substr((string) $locacao->hora_inicio, 0, 5) : '';
                                            $horaFimLocacao = !empty($locacao->hora_fim) ? substr((string) $locacao->hora_fim, 0, 5) : '';
                                        @endphp
                                        <tr class="table-warning">
                                            <td>
                                                <span class="badge bg-warning" title="Produto de terceiro">
                                                    <i class="ti ti-users ti-xs"></i>
                                                </span>
                                            </td>
                                            <td>
                                                {{ $produtoTerceiro->nome_produto ?? $produtoTerceiro->nome_produto_manual ?? ($produtoTerceiro->produtoTerceiro->nome ?? 'Produto de terceiro') }}
                                                @if($produtoTerceiro->fornecedor)
                                                    <br><small class="text-muted">{{ $produtoTerceiro->fornecedor->nome ?? '-' }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($produtoTerceiro->sala)
                                                    <span class="badge bg-label-info">{{ $produtoTerceiro->sala->nome }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>-</td>
                                            <td>
                                                <small>
                                                    {{ optional($locacao->data_inicio)->format('d/m') }}@if($horaInicioLocacao) {{ $horaInicioLocacao }}@endif
                                                    -
                                                    {{ optional($locacao->data_fim)->format('d/m') }}@if($horaFimLocacao) {{ $horaFimLocacao }}@endif
                                                </small>
                                            </td>
                                            <td>{{ $quantidadeTerceiro }}</td>
                                            <td>{{ $periodoTerceiro }}</td>
                                            <td>R$ {{ number_format($valorUnitarioTerceiro, 2, ',', '.') }}</td>
                                            <td class="text-end">R$ {{ number_format($subtotalTerceiro, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Nenhum produto adicionado</td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="8" class="text-end"><strong>Subtotal Produtos:</strong></td>
                                    <td class="text-end"><strong>R$ {{ number_format($subtotalProdutos, 2, ',', '.') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Serviços -->
            @if($locacao->servicos && $locacao->servicos->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-settings me-2"></i>
                            Serviços Adicionais
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Quantidade</th>
                                        <th>Qtd {{ ($locacao->locacao_por_hora_exibicao ?? false) ? 'Horas' : 'Dias' }}</th>
                                        <th>Valor Unitário</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($locacao->servicos as $servico)
                                        @php
                                            $servicoTerceiro = (string) ($servico->tipo_item ?? 'proprio') === 'terceiro';
                                            $valorUnitarioServico = (float) ($servico->preco_unitario ?? $servico->valor_unitario ?? 0);
                                            $subtotalServico = (float) ($servico->valor_total ?? 0);
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($servicoTerceiro)
                                                    <span class="badge bg-warning" title="Serviço de terceiro">
                                                        <i class="ti ti-users ti-xs"></i>
                                                    </span>
                                                @else
                                                    <span class="badge bg-primary" title="Serviço próprio">
                                                        <i class="ti ti-home ti-xs"></i>
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $servico->descricao }}
                                                @if($servicoTerceiro && !empty($servico->fornecedor_nome))
                                                    <br><small class="text-muted">{{ $servico->fornecedor_nome }}</small>
                                                @endif
                                            </td>
                                            <td>{{ (int) ($servico->quantidade ?? 1) }}</td>
                                            <td>{{ (int) ($servico->periodo_qtd_exibicao ?? $periodoQuantidade) }}</td>
                                            <td>R$ {{ number_format($valorUnitarioServico, 2, ',', '.') }}</td>
                                            <td class="text-end">R$ {{ number_format($subtotalServico, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="5" class="text-end"><strong>Subtotal Serviços:</strong></td>
                                        <td class="text-end"><strong>R$ {{ number_format($subtotalServicos, 2, ',', '.') }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Despesas -->
            @if($locacao->despesas && $locacao->despesas->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-receipt me-2"></i>
                            Despesas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($locacao->despesas as $despesa)
                                        <tr>
                                            <td>{{ ucfirst($despesa->tipo) }}</td>
                                            <td>{{ $despesa->descricao ?? '-' }}</td>
                                            <td class="text-end">R$ {{ number_format($despesa->valor, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="2" class="text-end"><strong>Total Despesas:</strong></td>
                                        <td class="text-end"><strong>R$ {{ number_format($locacao->despesas->sum('valor'), 2, ',', '.') }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <!-- Observações -->
                @if($locacao->observacoes)
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Observações</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">{{ $locacao->observacoes }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Resumo Financeiro -->
                <div class="col-md-{{ $locacao->observacoes ? '6' : '12' }} mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Resumo Financeiro</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <td>Subtotal Produtos:</td>
                                    <td class="text-end">R$ {{ number_format($subtotalProdutos, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>Subtotal Serviços:</td>
                                    <td class="text-end">R$ {{ number_format($subtotalServicos, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>Desconto:</td>
                                    <td class="text-end text-danger">- R$ {{ number_format($descontoResumo, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>Taxa de Entrega:</td>
                                    <td class="text-end">R$ {{ number_format($acrescimoResumo, 2, ',', '.') }}</td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>VALOR TOTAL:</strong></td>
                                    <td class="text-end"><strong class="fs-5">R$ {{ number_format($locacao->valor_total, 2, ',', '.') }}</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 justify-content-between">
                        <div class="d-flex gap-2">
                            @if(($locacao->status === 'orcamento' || $locacao->status === 'reserva') && $podeAlterarStatusLocacao)
                                <button type="button" class="btn btn-info btn-alterar-status" data-status="em_andamento">
                                    <i class="ti ti-truck-delivery me-1"></i> Iniciar Locação
                                </button>
                            @endif
                            @if($locacao->status === 'em_andamento' && $podeAlterarStatusLocacao)
                                <button type="button" class="btn btn-success btn-finalizar-locacao">
                                    <i class="ti ti-check me-1"></i> Finalizar Locação
                                </button>
                            @endif
                            @if($locacao->status !== 'finalizada' && $locacao->status !== 'cancelada' && $podeAlterarStatusLocacao)
                                <button type="button" class="btn btn-outline-danger btn-alterar-status" data-status="cancelada">
                                    <i class="ti ti-x me-1"></i> Cancelar
                                </button>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            @if($podeEditarLocacao)
                                <a href="{{ route('locacoes.edit', $locacao->id_locacao) }}" class="btn btn-primary">
                                    <i class="ti ti-pencil me-1"></i> Editar
                                </a>
                            @endif
                            @if($podeContratoPdfLocacao || $podeAssinaturaDigitalLocacao)
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="ti ti-printer me-1"></i> Imprimir
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @php
                                        $modelosContratoDisponiveis = ($modelosContratoAtivos ?? collect());
                                        $assinaturaDigital = $locacao->assinaturaDigital ?? null;
                                        $assinaturasContratoAssinadas = ($locacao->assinaturasDigitais ?? collect())
                                            ->filter(function ($assinaturaContrato) {
                                                return ($assinaturaContrato->status ?? '') === 'assinado' && !empty($assinaturaContrato->token);
                                            })
                                            ->sortByDesc('id_assinatura')
                                            ->values();
                                        $assinaturaContratoPadrao = $assinaturasContratoAssinadas->first(function ($assinaturaContrato) {
                                            return empty($assinaturaContrato->id_modelo);
                                        }) ?: $assinaturasContratoAssinadas->first();
                                    @endphp
                                    @if($assinaturaDigital && $podeAssinaturaDigitalLocacao)
                                        <li>
                                            <span class="dropdown-item-text">
                                                Status assinatura:
                                                <span class="badge bg-label-{{ $assinaturaDigital->status === 'assinado' ? 'success' : 'warning' }} ms-1">
                                                    {{ $assinaturaDigital->status === 'assinado' ? 'Assinado' : 'Pendente' }}
                                                </span>
                                            </span>
                                        </li>
                                        @if($assinaturaDigital->status === 'assinado' && $assinaturaDigital->token)
                                        <li>
                                            <a class="dropdown-item text-success" target="_blank"
                                               href="{{ route('locacoes.assinatura-digital.contrato-assinado', $assinaturaDigital->token) }}">
                                                <i class="ti ti-certificate me-1"></i> Ver Contrato Assinado (Espelho)
                                            </a>
                                        </li>
                                        @endif
                                        <li><hr class="dropdown-divider"></li>
                                    @endif
                                    @forelse($modelosContratoDisponiveis as $modeloContrato)
                                        <li>
                                            @if($podeContratoPdfLocacao)
                                                <a class="dropdown-item" target="_blank"
                                                   href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato&id_modelo={{ $modeloContrato->id_modelo }}">
                                                    Imprimir {{ $modeloContrato->nome }}
                                                </a>
                                            @endif
                                        </li>
                                        @php
                                            $assinaturaModeloAssinado = $assinaturasContratoAssinadas->first(function ($assinaturaContrato) use ($modeloContrato) {
                                                return (int) ($assinaturaContrato->id_modelo ?? 0) === (int) $modeloContrato->id_modelo;
                                            });
                                        @endphp
                                        <li>
                                            @if($assinaturaModeloAssinado)
                                                @if($podeAssinaturaDigitalLocacao)
                                                    <a class="dropdown-item text-success" target="_blank"
                                                       href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinaturaModeloAssinado->token, 'tipo' => 'contrato', 'id_modelo' => $modeloContrato->id_modelo]) }}">
                                                        Imprimir Assinado - {{ $modeloContrato->nome }}
                                                    </a>
                                                @endif
                                            @else
                                                @if($podeAssinaturaDigitalLocacao)
                                                    <a class="dropdown-item"
                                                       href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}?id_modelo={{ $modeloContrato->id_modelo }}">
                                                        Enviar {{ $modeloContrato->nome }} para Assinatura Digital
                                                    </a>
                                                @endif
                                            @endif
                                        </li>
                                    @empty
                                        <li>
                                            @if($podeContratoPdfLocacao)
                                                <a class="dropdown-item" target="_blank"
                                                   href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato">
                                                    Imprimir Contrato
                                                </a>
                                            @endif
                                        </li>
                                        <li>
                                            @if($assinaturaContratoPadrao)
                                                @if($podeAssinaturaDigitalLocacao)
                                                    <a class="dropdown-item text-success" target="_blank"
                                                       href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinaturaContratoPadrao->token, 'tipo' => 'contrato', 'id_modelo' => $assinaturaContratoPadrao->id_modelo]) }}">
                                                        Imprimir Assinado - Contrato
                                                    </a>
                                                @endif
                                            @else
                                                @if($podeAssinaturaDigitalLocacao)
                                                    <a class="dropdown-item"
                                                       href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}">
                                                        Enviar para Assinatura Digital
                                                    </a>
                                                @endif
                                            @endif
                                        </li>
                                    @endforelse
                                </ul>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Retorno de Patrimônios -->
<div class="modal fade" id="modalRetornoPatrimonios" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-package-import me-2"></i>
                    Confirmar Retorno de Patrimônios
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <i class="ti ti-info-circle me-1"></i>
                    Confirme o status de retorno de cada patrimônio antes de finalizar a locação.
                </div>
                
                <div id="listaPatrimoniosRetorno">
                    <!-- Patrimônios serão carregados via JS -->
                </div>
                
                <div id="erroRetorno" class="alert alert-danger d-none">
                    <i class="ti ti-alert-circle me-1"></i>
                    <span id="mensagemErroRetorno"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarRetorno">
                    <i class="ti ti-check me-1"></i> Confirmar e Finalizar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Alterar status (exceto finalizar)
    $('.btn-alterar-status').on('click', function() {
        var status = $(this).data('status');
        var labels = {
            'em_andamento': 'iniciar',
            'finalizada': 'finalizar',
            'cancelada': 'cancelar'
        };
        
        Swal.fire({
            title: 'Confirmar ação',
            text: `Deseja realmente ${labels[status]} esta locação?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, confirmar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                alterarStatus(status);
            }
        });
    });
    
    // Finalizar locação - verificar patrimônios pendentes
    $('.btn-finalizar-locacao').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Verificando...');
        
        // Buscar patrimônios pendentes de retorno
        $.get('{{ route("locacoes.patrimonios-pendentes", $locacao->id_locacao) }}')
            .done(function(response) {
                btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Finalizar Locação');
                
                if (response.success && response.patrimonios && response.patrimonios.length > 0) {
                    // Há patrimônios para confirmar retorno
                    renderizarModalRetorno(response.patrimonios);
                    $('#erroRetorno').addClass('d-none');
                    $('#modalRetornoPatrimonios').modal('show');
                } else {
                    // Sem patrimônios, finalizar direto
                    confirmarFinalizacao();
                }
            })
            .fail(function(xhr) {
                btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Finalizar Locação');
                
                // Em caso de erro na verificação, tenta finalizar direto
                console.warn('Erro ao buscar patrimônios pendentes, tentando finalizar diretamente');
                confirmarFinalizacao();
            });
    });
    
    function confirmarFinalizacao() {
        Swal.fire({
            title: 'Confirmar finalização',
            text: 'Deseja realmente finalizar esta locação?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, finalizar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                alterarStatus('finalizada', true);
            }
        });
    }
    
    // Renderizar modal de retorno de patrimônios
    function renderizarModalRetorno(patrimonios) {
        var html = '<div class="table-responsive"><table class="table table-sm">';
        html += '<thead><tr><th>Produto</th><th>Patrimônio</th><th>Status de Retorno</th><th>Observação</th></tr></thead>';
        html += '<tbody>';
        
        patrimonios.forEach(function(p, index) {
            html += `
                <tr>
                    <td>${p.produto_nome || 'Produto'}</td>
                    <td><strong>${p.numero_serie || ('PAT-' + p.id_patrimonio)}</strong></td>
                    <td>
                        <select class="form-select form-select-sm select-status-retorno" 
                                data-patrimonio="${p.id_patrimonio}" 
                                data-produto="${p.id_produto_locacao}">
                            <option value="normal" selected>Normal (Ok)</option>
                            <option value="avariado">Avariado (Manutenção)</option>
                            <option value="extraviado">Extraviado (Perdido)</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm input-obs-retorno" 
                               data-patrimonio="${p.id_patrimonio}" 
                               placeholder="Observações (opcional)">
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        
        $('#listaPatrimoniosRetorno').html(html);
    }
    
    // Confirmar retorno de patrimônios
    $('#btnConfirmarRetorno').on('click', function() {
        var btn = $(this);
        var retornos = [];
        
        $('.select-status-retorno').each(function() {
            var idPatrimonio = $(this).data('patrimonio');
            var idProdutoLocacao = $(this).data('produto');
            var status = $(this).val();
            var obs = $(`.input-obs-retorno[data-patrimonio="${idPatrimonio}"]`).val();
            
            retornos.push({
                id_patrimonio: idPatrimonio,
                id_produto_locacao: idProdutoLocacao,
                status: status,
                observacoes: obs || ''
            });
        });
        
        if (retornos.length === 0) {
            // Sem retornos para processar, finalizar direto
            $('#modalRetornoPatrimonios').modal('hide');
            alterarStatus('finalizada', true);
            return;
        }
        
        // Verificar se há extraviados
        var extraviados = retornos.filter(r => r.status === 'extraviado').length;
        var avariados = retornos.filter(r => r.status === 'avariado').length;
        
        var avisos = [];
        if (extraviados > 0) {
            avisos.push(`<strong>${extraviados}</strong> patrimônio(s) marcado(s) como <span class="text-danger">EXTRAVIADO</span>`);
        }
        if (avariados > 0) {
            avisos.push(`<strong>${avariados}</strong> patrimônio(s) marcado(s) como <span class="text-warning">AVARIADO</span> (serão enviados para manutenção)`);
        }
        
        var msgConfirm = 'Confirmar retorno dos patrimônios e finalizar a locação?';
        if (avisos.length > 0) {
            msgConfirm = 'Atenção!<br>' + avisos.join('<br>') + '<br><br>Deseja confirmar?';
        }
        
        Swal.fire({
            title: 'Confirmar Retorno',
            html: msgConfirm,
            icon: avisos.length > 0 ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, confirmar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Processando...');
                $('#erroRetorno').addClass('d-none');
                
                // Enviar retornos
                $.ajax({
                    url: '{{ route("locacoes.registrar-retorno-patrimonios", $locacao->id_locacao) }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        retornos: retornos
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#modalRetornoPatrimonios').modal('hide');
                            // Agora finalizar a locação
                            alterarStatus('finalizada', true);
                        } else {
                            btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Confirmar e Finalizar');
                            $('#erroRetorno').removeClass('d-none');
                            $('#mensagemErroRetorno').text(response.message || 'Erro ao registrar retornos.');
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Confirmar e Finalizar');
                        var mensagem = 'Erro ao registrar retornos.';
                        
                        if (xhr.responseJSON?.message) {
                            mensagem = xhr.responseJSON.message;
                        } else if (xhr.status === 500) {
                            mensagem = 'Erro interno do servidor. Verifique os logs.';
                        }
                        
                        $('#erroRetorno').removeClass('d-none');
                        $('#mensagemErroRetorno').text(mensagem);
                    }
                });
            }
        });
    });
    
    // Função para alterar status
    function alterarStatus(status, confirmarPatrimonios = false) {
        Swal.fire({
            title: 'Processando...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '{{ route("locacoes.alterar-status", $locacao->id_locacao) }}',
            type: 'PATCH',
            data: { 
                _token: '{{ csrf_token() }}',
                status: status,
                confirmar_retorno_patrimonios: confirmarPatrimonios
            },
            success: function(response) {
                Swal.close();
                
                if (response.success) {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: response.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else if (response.requires_patrimonio_return) {
                    // Há patrimônios pendentes de retorno
                    renderizarModalRetorno(response.patrimonios_pendentes);
                    $('#modalRetornoPatrimonios').modal('show');
                } else {
                    Swal.fire('Atenção', response.message, 'warning');
                }
            },
            error: function(xhr) {
                Swal.close();
                
                var mensagem = 'Erro ao alterar status.';
                if (xhr.responseJSON?.message) {
                    mensagem = xhr.responseJSON.message;
                }
                
                Swal.fire('Erro!', mensagem, 'error');
            }
        });
    }
});
    }
});
</script>
@endsection
