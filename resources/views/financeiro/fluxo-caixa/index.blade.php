@extends('layouts.layoutMaster')

@section('title', 'Fluxo de Caixa')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
<style>
    .fluxo-caixa-page .card-header-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        scrollbar-width: thin;
    }

    .fluxo-caixa-page .card-header-tabs .nav-link {
        white-space: nowrap;
    }

    .fluxo-caixa-page .table-responsive {
        overflow-x: auto;
    }

    .fluxo-caixa-page .fluxo-caixa-header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    @media (max-width: 991.98px) {
        .fluxo-caixa-page .d-flex.justify-content-between.align-items-center.mb-4 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 0.75rem;
        }

        .fluxo-caixa-page .fluxo-caixa-header-actions {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .fluxo-caixa-page .fluxo-caixa-header-actions .btn {
            border-radius: 0.375rem !important;
        }

        .fluxo-caixa-page #chartEntradas,
        .fluxo-caixa-page #chartSaidas {
            min-height: 280px !important;
            padding: 0.75rem !important;
        }

        .fluxo-caixa-page #tabelaDetalhadaEntradas,
        .fluxo-caixa-page #tabelaDetalhadaSaidas {
            font-size: 0.85rem;
        }

        .fluxo-caixa-page #tabelaDetalhadaEntradas th:nth-child(5),
        .fluxo-caixa-page #tabelaDetalhadaEntradas td:nth-child(5),
        .fluxo-caixa-page #tabelaDetalhadaSaidas th:nth-child(5),
        .fluxo-caixa-page #tabelaDetalhadaSaidas td:nth-child(5) {
            display: none;
        }

        .fluxo-caixa-page .acoes-fluxo {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            justify-content: center;
        }
    }
</style>
@endsection

@section('content')
@php($podeCriarContaPagar = \App\Facades\Perm::pode(auth()->user(), 'financeiro.contas-pagar.criar'))
@php($podeCriarContaReceber = \App\Facades\Perm::pode(auth()->user(), 'financeiro.contas-receber.criar'))
<div class="container-xxl flex-grow-1 fluxo-caixa-page">

    <!-- Mensagens de Sucesso e Erro -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <h6 class="alert-heading mb-1">Sucesso!</h6>
            <p class="mb-0">{{ session('success') }}</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            <h6 class="alert-heading mb-1">Erro!</h6>
            <p class="mb-0">{{ session('error') }}</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->has('csrf'))
        <div class="alert alert-warning alert-dismissible" role="alert">
            <h6 class="alert-heading mb-1">Atenção!</h6>
            <p class="mb-0">{{ $errors->first('csrf') }}</p>
            <p class="mb-0 mt-2"><small>Se o problema persistir, recarregue a página (F5).</small></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="ti ti-chart-line ti-sm me-2"></i>
                Fluxo de Caixa Consolidado
            </h4>
            <p class="text-body-secondary mb-0">Análise completa de entradas e saídas financeiras</p>
        </div>
        <div class="fluxo-caixa-header-actions">
            @if($podeCriarContaPagar || $podeCriarContaReceber)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoLancamentoFluxo">
                    <i class="ti ti-plus me-1"></i>
                    Novo Lançamento
                </button>
            @endif
            <button type="button" class="btn btn-danger" onclick="gerarRelatorioPDF()">
                <i class="ti ti-file-type-pdf me-1"></i>
                Gerar PDF
            </button>
            <button type="button" class="btn btn-success" onclick="gerarRelatorioExcel()">
                <i class="ti ti-file-spreadsheet me-1"></i>
                Exportar Excel
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="ti ti-filter me-2"></i>
                Filtros
            </h5>
        </div>
        <div class="card-body">
            <form id="formFiltros">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Visualização</label>
                        <select class="form-select" id="tipo_visualizacao" name="tipo_visualizacao">
                            <option value="diario">Diária</option>
                            <option value="semanal">Semanal</option>
                            <option value="mensal" selected>Mensal</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Data Início <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                            value="{{ date('Y-m-01') }}" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Data Fim <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" 
                            value="{{ date('Y-m-t') }}" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Propriedade</label>
                        <select class="form-select select2" id="id_propriedade" name="id_propriedade">
                            <option value="">Todas as Propriedades</option>
                            <!-- Será preenchido dinamicamente -->
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Comparar com Período</label>
                        <select class="form-select" id="comparar_periodo" name="comparar_periodo">
                            <option value="">Sem Comparação</option>
                            <option value="anterior">Período Anterior</option>
                            <option value="ano_anterior">Mesmo Período Ano Anterior</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Banco / Saldo Inicial</label>
                        <select class="form-select select2" id="banco_saldo" name="banco_saldo" onchange="atualizarSaldoBanco()">
                            <option value="todos" selected>Saldo Total (Soma de Todos os Bancos)</option>
                            @foreach($bancos as $banco)
                                <option value="{{ $banco->id_bancos }}" data-saldo="{{ $banco->saldo_inicial }}">
                                    {{ $banco->nome_banco }}
                                    @if($banco->agencia && $banco->conta)
                                        (Ag: {{ $banco->agencia }} / Cc: {{ $banco->conta }})
                                    @endif
                                    - R$ {{ number_format($banco->saldo_inicial ?? 0, 2, ',', '.') }}
                                </option>
                            @endforeach
                            <option value="manual">Definir Saldo Manualmente</option>
                        </select>
                        <input type="hidden" id="saldo_inicial" name="saldo_inicial" value="{{ $bancos->sum('saldo_inicial') }}">
                        <div id="div_saldo_manual" class="mt-2" style="display: none;">
                            <input type="text" class="form-control" id="saldo_manual" 
                                placeholder="R$ 0,00" value="0,00" onkeyup="atualizarSaldoManual()">
                        </div>
                        <small class="text-muted" id="info_saldo">Saldo: R$ {{ number_format($bancos->sum('saldo_inicial'), 2, ',', '.') }}</small>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-primary w-100" onclick="gerarRelatorio()">
                            <i class="ti ti-refresh me-1"></i>
                            Atualizar Relatório
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalNovoLancamentoFluxo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Lançamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($podeCriarContaPagar || $podeCriarContaReceber)
                    <ul class="nav nav-pills mb-3" role="tablist">
                        @if($podeCriarContaPagar)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $podeCriarContaPagar ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabNovoPagar" type="button" role="tab">
                                    <i class="ti ti-arrow-down me-1"></i>Conta a Pagar
                                </button>
                            </li>
                        @endif
                        @if($podeCriarContaReceber)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ !$podeCriarContaPagar && $podeCriarContaReceber ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tabNovoReceber" type="button" role="tab">
                                    <i class="ti ti-arrow-up me-1"></i>Conta a Receber
                                </button>
                            </li>
                        @endif
                    </ul>

                    <div class="tab-content">
                        @if($podeCriarContaPagar)
                        <div class="tab-pane fade show {{ $podeCriarContaPagar ? 'active' : '' }}" id="tabNovoPagar" role="tabpanel">
                            @if ($errors->any() && old('redirect_to') === 'fluxo-caixa')
                                <div class="alert alert-danger alert-dismissible" role="alert">
                                    <h6 class="alert-heading mb-1">Erro ao salvar a conta</h6>
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('financeiro.store') }}">
                                @csrf
                                <input type="hidden" name="tipo_lancamento" value="unico">
                                <input type="hidden" name="redirect_to" value="fluxo-caixa">
                                <input type="hidden" name="status" value="pago">

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                        <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" required>
                                        @error('descricao')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Valor Total <span class="text-danger">*</span></label>
                                        <input type="text" name="valor_total" data-valor-total="pagar" class="form-control mask-money @error('valor_total') is-invalid @enderror" value="{{ old('valor_total', '0,00') }}" required>
                                        @error('valor_total')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                                        <input type="date" name="data_vencimento" class="form-control @error('data_vencimento') is-invalid @enderror" value="{{ old('data_vencimento') }}" required>
                                        @error('data_vencimento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Fornecedor</label>
                                        <select class="form-select" name="id_fornecedores">
                                            <option value="">Selecione</option>
                                            @foreach($fornecedores as $fornecedor)
                                                <option value="{{ $fornecedor->id_fornecedores }}">{{ $fornecedor->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="id_categoria_contas">
                                            <option value="">Selecione</option>
                                            @foreach($categoriasDespesa as $categoria)
                                                <option value="{{ $categoria->id_categoria_contas }}">{{ $categoria->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Banco</label>
                                        <select class="form-select" name="id_bancos">
                                            <option value="">Selecione</option>
                                            @foreach($bancos as $banco)
                                                <option value="{{ $banco->id_bancos }}">{{ $banco->nome_banco }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <input type="text" class="form-control" value="Pago (Baixado no fluxo)" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Desconto</label>
                                        <input type="text" name="desconto" class="form-control mask-money" value="0,00">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Juros</label>
                                        <input type="text" name="juros" class="form-control mask-money" value="0,00">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Multa</label>
                                        <input type="text" name="multa" class="form-control mask-money" value="0,00">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Valor Pago</label>
                                        <input type="text" name="valor_pago" data-valor-pago="pagar" class="form-control mask-money" value="0,00" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Forma de Pagamento</label>
                                        <select class="form-select @error('id_forma_pagamento') is-invalid @enderror" name="id_forma_pagamento" data-forma-pagamento="pagar">
                                            <option value="">Selecione</option>
                                            @foreach($formasPagamento as $forma)
                                                <option value="{{ $forma->id_forma_pagamento }}" {{ old('id_forma_pagamento') == $forma->id_forma_pagamento ? 'selected' : '' }}>{{ $forma->nome }}</option>
                                            @endforeach
                                        </select>
                                        @error('id_forma_pagamento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Data de Pagamento <span class="text-danger">*</span></label>
                                        <input type="date" name="data_pagamento" data-data-pagamento="pagar" class="form-control @error('data_pagamento') is-invalid @enderror" value="{{ old('data_pagamento', date('Y-m-d')) }}" required>
                                        @error('data_pagamento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Observações</label>
                                        <textarea class="form-control" name="observacoes" rows="2"></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar Conta a Pagar</button>
                                </div>
                            </form>
                        </div>
                        @endif

                        @if($podeCriarContaReceber)
                        <div class="tab-pane fade {{ !$podeCriarContaPagar && $podeCriarContaReceber ? 'show active' : '' }}" id="tabNovoReceber" role="tabpanel">
                            @if ($errors->any() && old('redirect_to') === 'fluxo-caixa')
                                <div class="alert alert-danger alert-dismissible" role="alert">
                                    <h6 class="alert-heading mb-1">Erro ao salvar a conta</h6>
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('financeiro.contas-a-receber.store') }}">
                                @csrf
                                <input type="hidden" name="tipo_lancamento" value="unico">
                                <input type="hidden" name="redirect_to" value="fluxo-caixa">
                                <input type="hidden" name="status" value="pago">

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                        <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" required>
                                        @error('descricao')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Valor Total <span class="text-danger">*</span></label>
                                        <input type="text" name="valor_total" data-valor-total="receber" class="form-control mask-money @error('valor_total') is-invalid @enderror" value="{{ old('valor_total', '0,00') }}" required>
                                        @error('valor_total')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                                        <input type="date" name="data_vencimento" class="form-control @error('data_vencimento') is-invalid @enderror" value="{{ old('data_vencimento') }}" required>
                                        @error('data_vencimento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cliente</label>
                                        <select class="form-select" name="id_clientes">
                                            <option value="">Selecione</option>
                                            @foreach($clientes as $cliente)
                                                <option value="{{ $cliente->id_clientes }}">{{ $cliente->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="id_categoria_contas">
                                            <option value="">Selecione</option>
                                            @foreach($categoriasReceita as $categoria)
                                                <option value="{{ $categoria->id_categoria_contas }}">{{ $categoria->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Banco</label>
                                        <select class="form-select" name="id_bancos">
                                            <option value="">Selecione</option>
                                            @foreach($bancos as $banco)
                                                <option value="{{ $banco->id_bancos }}">{{ $banco->nome_banco }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <input type="text" class="form-control" value="Pago (Baixado no fluxo)" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Desconto</label>
                                        <input type="text" name="desconto" class="form-control mask-money" value="0,00">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Juros</label>
                                        <input type="text" name="juros" class="form-control mask-money" value="0,00">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Multa</label>
                                        <input type="text" name="multa" class="form-control mask-money" value="0,00">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Valor Recebido</label>
                                        <input type="text" name="valor_pago" data-valor-pago="receber" class="form-control mask-money" value="0,00" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Forma de Pagamento</label>
                                        <select class="form-select @error('id_forma_pagamento') is-invalid @enderror" name="id_forma_pagamento" data-forma-pagamento="receber">
                                            <option value="">Selecione</option>
                                            @foreach($formasPagamento as $forma)
                                                <option value="{{ $forma->id_forma_pagamento }}" {{ old('id_forma_pagamento') == $forma->id_forma_pagamento ? 'selected' : '' }}>{{ $forma->nome }}</option>
                                            @endforeach
                                        </select>
                                        @error('id_forma_pagamento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Data de Recebimento <span class="text-danger">*</span></label>
                                        <input type="date" name="data_pagamento" data-data-pagamento="receber" class="form-control @error('data_pagamento') is-invalid @enderror" value="{{ old('data_pagamento', date('Y-m-d')) }}" required>
                                        @error('data_pagamento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Observações</label>
                                        <textarea class="form-control" name="observacoes" rows="2"></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar Conta a Receber</button>
                                </div>
                            </form>
                        </div>
                        @endif
                    </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            Você não possui permissão para criar lançamentos pelo fluxo de caixa. Entre em contato com o administrador da empresa.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="row g-4 mb-4" id="cardsResumo">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <p class="text-body-secondary mb-1 small">Saldo Inicial</p>
                            <h4 class="mb-0" id="card-saldo-inicial">R$ 0,00</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-secondary">
                                <i class="ti ti-wallet ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-success border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <p class="text-body-secondary mb-1 small">Total de Entradas</p>
                            <h4 class="mb-0 text-success" id="card-total-entradas">R$ 0,00</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-arrow-up ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-danger border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <p class="text-body-secondary mb-1 small">Total de Saídas</p>
                            <h4 class="mb-0 text-danger" id="card-total-saidas">R$ 0,00</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-danger">
                                <i class="ti ti-arrow-down ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-primary border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <p class="text-body-secondary mb-1 small">Saldo Final</p>
                            <h4 class="mb-0 text-primary" id="card-saldo-final">R$ 0,00</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-cash ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs de Detalhamento -->
    <div class="card mb-4">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetalhado" type="button" role="tab">
                        <i class="ti ti-list me-1"></i>
                        Detalhamento
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEntradas" type="button" role="tab">
                        <i class="ti ti-arrow-up me-1"></i>
                        Entradas por Categoria
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSaidas" type="button" role="tab">
                        <i class="ti ti-arrow-down me-1"></i>
                        Saídas por Categoria
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabComparativo" type="button" role="tab">
                        <i class="ti ti-chart-dots me-1"></i>
                        Comparativo
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- Tab Entradas -->
                <div class="tab-pane fade" id="tabEntradas" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Distribuição de Receitas</h6>
                            <div id="chartEntradas" class="p-4 rounded" style="min-height: 380px;">
                                <canvas id="canvasEntradas"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Detalhamento por Categoria</h6>
                            <div class="table-responsive">
                                <table class="table table-sm" id="tabelaEntradas">
                                    <thead>
                                        <tr>
                                            <th>Categoria</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-end">Participação</th>
                                            <th style="min-width: 140px;">Composição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Preenchido via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Saídas -->
                <div class="tab-pane fade" id="tabSaidas" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Distribuição de Despesas</h6>
                            <div id="chartSaidas" class="p-4 rounded" style="min-height: 380px;">
                                <canvas id="canvasSaidas"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Detalhamento por Categoria</h6>
                            <div class="table-responsive">
                                <table class="table table-sm" id="tabelaSaidas">
                                    <thead>
                                        <tr>
                                            <th>Categoria</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-end">Participação</th>
                                            <th style="min-width: 140px;">Composição</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Preenchido via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Detalhado -->
                <div class="tab-pane fade show active" id="tabDetalhado" role="tabpanel">
                    <ul class="nav nav-pills mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#detalhamentoEntradas" type="button" role="tab">
                                <i class="ti ti-arrow-up me-1"></i>Entradas
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#detalhamentoSaidas" type="button" role="tab">
                                <i class="ti ti-arrow-down me-1"></i>Saídas
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Detalhamento Entradas -->
                        <div class="tab-pane fade show active" id="detalhamentoEntradas" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelaDetalhadaEntradas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data</th>
                                            <th>Descrição</th>
                                            <th>Categoria</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-center" width="80">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Preenchido via JS -->
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3">TOTAL</th>
                                            <th class="text-end text-success" id="total-entradas-detalhado">R$ 0,00</th>
                                            <th class="text-end" id="saldo-entradas-detalhado">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                        <tr class="table-info">
                                            <th colspan="3" id="lucratividade-label-entradas">RESULTADO LÍQUIDO</th>
                                            <th class="text-end fw-bold" id="lucratividade-entradas" colspan="2">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Detalhamento Saídas -->
                        <div class="tab-pane fade" id="detalhamentoSaidas" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelaDetalhadaSaidas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data</th>
                                            <th>Descrição</th>
                                            <th>Categoria</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-center" width="80">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Preenchido via JS -->
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3">TOTAL</th>
                                            <th class="text-end text-danger" id="total-saidas-detalhado">R$ 0,00</th>
                                            <th class="text-end" id="saldo-saidas-detalhado">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                        <tr class="table-info">
                                            <th colspan="3" id="lucratividade-label-saidas">QUEIMA DE CAIXA</th>
                                            <th class="text-end fw-bold" id="lucratividade-saidas" colspan="2">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Comparativo -->
                <div class="tab-pane fade" id="tabComparativo" role="tabpanel">
                    <div id="comparativoContent">
                        <div class="text-center text-body-secondary py-5">
                            <i class="ti ti-info-circle ti-lg mb-2"></i>
                            <p>Selecione um período de comparação nos filtros acima</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Evolução -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">
            <div>
                <h5 class="card-title mb-0">Evolução do Fluxo de Caixa</h5>
                <small class="text-body-secondary">Visualização temporal das movimentações</small>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary active" onclick="alternarVisualizacao('linha')">
                    <i class="ti ti-chart-line"></i> Linha
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="alternarVisualizacao('barra')">
                    <i class="ti ti-chart-bar"></i> Barra
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="alternarVisualizacao('area')">
                    <i class="ti ti-chart-area"></i> Área
                </button>
            </div>
        </div>
        <div class="card-body" style="min-height: 420px;">
            <canvas id="chartFluxoCaixa"></canvas>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jquery-mask/jquery.mask.min.js')}}"></script>
<script src="{{asset('assets/js/money-helpers.js')}}"></script>
<!-- PDFKit -->
<script src="https://cdn.jsdelivr.net/npm/pdfkit@0.13.0/js/pdfkit.standalone.js"></script>
<!-- ExcelJS -->
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<!-- Scripts personalizados -->
<script src="{{asset('js/relatorios-pdf.js')}}"></script>
<script src="{{asset('js/relatorios-locacao.js')}}"></script>
@endsection

@section('page-script')
<script>
// Definir variáveis globais para o script externo
window.csrfToken = '{{ csrf_token() }}';
window.fluxoCaixaRoutes = {
    dados: "{{ route('financeiro.fluxo-caixa.dados') }}",
    excel: "{{ route('financeiro.fluxo-caixa.excel') }}",
    pdf: "{{ route('financeiro.fluxo-caixa.pdf') }}",
    logs: "{{ route('financeiro.fluxo-caixa.logs-atividades', ['id' => '__ID__']) }}",
    reciboPagar: "{{ route('financeiro.contas-a-pagar.recibo', ['id' => '__ID__']) }}",
    reciboReceber: "{{ route('financeiro.contas-a-receber.recibo', ['id' => '__ID__']) }}"
};

// Reabrir modal se houver erros de validação
@if ($errors->any() && old('redirect_to') === 'fluxo-caixa')
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('modalNovoLancamentoFluxo'));
    modal.show();
    // Atualizar token CSRF se houver erro
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const newToken = csrfMeta.getAttribute('content');
        document.querySelectorAll('input[name="_token"]').forEach(input => {
            input.value = newToken;
        });
    }
});
@endif

function sincronizarValorPagoComTotal(tipoConta) {
    const totalField = document.querySelector(`[data-valor-total="${tipoConta}"]`);
    const valorPagoField = document.querySelector(`[data-valor-pago="${tipoConta}"]`);
    const formaField = document.querySelector(`[data-forma-pagamento="${tipoConta}"]`);
    const dataField = document.querySelector(`[data-data-pagamento="${tipoConta}"]`);

    if (!totalField || !valorPagoField || !formaField || !dataField) {
        return;
    }

    const valorTotal = window.parseMoneyToFloat
        ? window.parseMoneyToFloat(totalField.value)
        : Number((totalField.value || '0').replace('.', '').replace(',', '.'));

    const valorNormalizado = Number.isFinite(valorTotal) ? Math.max(valorTotal, 0) : 0;

    if (window.formatFloatToMoney) {
        valorPagoField.value = window.formatFloatToMoney(valorNormalizado);
    } else {
        valorPagoField.value = valorNormalizado.toFixed(2).replace('.', ',');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    if (window.applyMoneyMaskToAll) {
        window.applyMoneyMaskToAll('mask-money');
    }

    const modalLancamento = document.getElementById('modalNovoLancamentoFluxo');
    if (modalLancamento) {
        // Atualizar token CSRF quando o modal abrir
        modalLancamento.addEventListener('show.bs.modal', function () {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                const newToken = csrfMeta.getAttribute('content');
                console.log('Atualizando token CSRF no modal:', newToken ? 'Token encontrado' : 'Token não encontrado');
                modalLancamento.querySelectorAll('input[name="_token"]').forEach(input => {
                    input.value = newToken;
                    console.log('Token atualizado no formulário');
                });
            }
        });

        modalLancamento.addEventListener('shown.bs.modal', function () {
            if (window.applyMoneyMaskToAll) {
                window.applyMoneyMaskToAll('mask-money');
            }

            ['pagar', 'receber'].forEach(function (tipoConta) {
                sincronizarValorPagoComTotal(tipoConta);
            });
        });
    }

    ['pagar', 'receber'].forEach(function (tipoConta) {
        const totalField = document.querySelector(`[data-valor-total="${tipoConta}"]`);
        if (!totalField) {
            return;
        }

        totalField.addEventListener('input', function () {
            sincronizarValorPagoComTotal(tipoConta);
        });

        sincronizarValorPagoComTotal(tipoConta);
    });
});

async function gerarRelatorioPDF() {
    const form = document.getElementById('formFiltros');
    const formData = new FormData(form);
    
    // Validar datas
    const dataInicio = formData.get('data_inicio');
    const dataFim = formData.get('data_fim');
    
    if (!dataInicio || !dataFim) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Por favor, informe o período para gerar o relatório'
        });
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: 'Gerando PDF...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Buscar dados via AJAX
        const response = await fetch(window.fluxoCaixaRoutes.pdf, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams(formData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao buscar dados');
        }
        
        const dados = await response.json();
        
        // Gerar PDF usando pdfkit
        await gerarPDFFluxoCaixa(dados);
        
        Swal.close();
        
        Swal.fire({
            icon: 'success',
            title: 'PDF gerado!',
            text: 'O download iniciará automaticamente',
            timer: 2000
        });
        
    } catch (error) {
        Swal.close();
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao gerar PDF: ' + error.message
        });
    }
}

async function gerarRelatorioExcel() {
    const form = document.getElementById('formFiltros');
    const formData = new FormData(form);
    
    // Validar datas
    const dataInicio = formData.get('data_inicio');
    const dataFim = formData.get('data_fim');
    
    if (!dataInicio || !dataFim) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Por favor, informe o período para gerar o relatório'
        });
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: 'Gerando Excel...',
        text: 'Por favor aguarde',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Buscar dados via AJAX
        const response = await fetch(window.fluxoCaixaRoutes.excel, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams(formData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao buscar dados');
        }
        
        const dados = await response.json();
        
        // Gerar Excel usando exceljs
        await gerarExcelFluxoCaixa(dados);
        
        Swal.close();
        
        Swal.fire({
            icon: 'success',
            title: 'Excel gerado!',
            text: 'O download iniciará automaticamente',
            timer: 2000
        });
        
    } catch (error) {
        Swal.close();
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Erro ao gerar Excel: ' + error.message
        });
    }
}
</script>
<script src="{{asset('js/fluxo-caixa.js')}}"></script>
@endsection
