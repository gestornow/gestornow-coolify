@extends('layouts.layoutMaster')

@section('title', 'Nova Locação')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('content')
<style>
    .sticky-footer-bar {
        position: fixed;
        bottom: 1rem;
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
        z-index: 1030;
        box-shadow: 0 -6px 24px rgba(0,0,0,0.12);
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        /* Posição será definida via JS */
        left: 0;
        right: 0;
    }
    .sticky-footer-bar .info-text {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6c757d;
        font-size: 0.9rem;
    }
    .sticky-footer-bar .btn-group {
        display: flex;
        gap: 0.5rem;
    }
    .content-with-sticky-footer {
        padding-bottom: 90px;
    }
    .nav-pills-full .nav-item {
        flex: 1;
        text-align: center;
    }
    .nav-pills-full .nav-link {
        width: 100%;
    }
    .info-cliente-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border: 1px solid #dee2e6;
    }
    .info-cliente-card .info-item {
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .info-cliente-card .info-item:last-child {
        border-bottom: none;
    }
    .periodo-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .periodo-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .periodo-icon i {
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .flatpickr-input {
        background-color: #fff !important;
        border-color: #d9dee3;
    }
    .modal .modal-header[class*="bg-"] {
        display: flex;
        align-items: center;
        min-height: 56px;
        padding-top: .65rem;
        padding-bottom: .65rem;
    }
    .modal .modal-header[class*="bg-"] .modal-title {
        color: #fff;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        line-height: 1;
    }
    .modal .modal-header[class*="bg-"] .modal-title i {
        display: inline-flex;
        align-items: center;
        line-height: 1;
    }
    .modal .modal-header[class*="bg-"] .btn-close {
        background-color: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        background-image: var(--bs-btn-close-bg) !important;
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 1 !important;
    }
    #modalProdutoTerceiro .row.g-3.mb-4,
    #modalServicoTerceiro .row.g-3.mb-4 {
        margin-bottom: 1rem !important;
    }
    #modalProdutoTerceiro .form-label,
    #modalServicoTerceiro .form-label {
        margin-bottom: .35rem;
    }
    #modalProdutoTerceiro .form-control,
    #modalProdutoTerceiro .form-select,
    #modalServicoTerceiro .form-control,
    #modalServicoTerceiro .form-select {
        min-height: 38px;
    }
    #modalProdutoTerceiro small.text-muted,
    #modalServicoTerceiro small.text-muted {
        display: block;
        min-height: 16px;
        margin-top: .35rem;
        line-height: 1.2;
    }
    #modalProdutoTerceiro .form-check,
    #modalServicoTerceiro .form-check {
        min-height: 38px;
        display: flex;
        align-items: center;
    }
    #modalProdutoTerceiro .card .card-body,
    #modalServicoTerceiro .card .card-body {
        padding-top: .9rem;
        padding-bottom: .9rem;
    }
    .select2-container--open {
        z-index: 9999;
    }
    .select2-container--open .select2-dropdown {
        z-index: 9999;
    }
    html.dark-style .modal-content {
        background-color: #2f3349;
        color: #d8deff;
    }
    html.dark-style .modal-header,
    html.dark-style .modal-footer {
        border-color: rgba(255, 255, 255, .12);
    }
    html.dark-style .modal-body .text-muted,
    html.dark-style .modal-body small.text-muted {
        color: rgba(216, 222, 255, .72) !important;
    }
    html.dark-style .modal-body .form-control,
    html.dark-style .modal-body .form-select {
        background-color: #25293c;
        border-color: #444b6e;
        color: #d8deff;
    }
    html.dark-style .modal-body .form-control::placeholder {
        color: rgba(216, 222, 255, .55);
    }

    .nav-pills-full {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        gap: .5rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding-bottom: 0;
    }

    .nav-pills-full::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }

    .nav-pills-full .nav-item {
        flex: 1 0 auto;
        min-width: 125px;
    }

    @media (max-width: 991.98px) {
        .sticky-footer-bar {
            left: .5rem;
            right: .5rem;
            bottom: .5rem;
            padding: .75rem 1rem;
            flex-direction: column;
            align-items: stretch;
            gap: .75rem;
        }

        .sticky-footer-bar .btn-group {
            width: 100%;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .sticky-footer-bar .btn-group .btn {
            flex: 1 1 auto;
            min-width: 140px;
        }

        .info-cliente-card,
        .periodo-card {
            margin-top: .75rem;
        }
    }

    html.dark-style .sticky-footer-bar {
        background: #2f3349;
        border-color: #444b6e;
        box-shadow: 0 -6px 24px rgba(0,0,0,0.35);
    }

    html.dark-style .sticky-footer-bar .info-text {
        color: rgba(216, 222, 255, .78);
    }

    html.dark-style .info-cliente-card {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
        border-color: #444b6e;
    }

    html.dark-style .periodo-card {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
        border-color: #444b6e;
        box-shadow: none;
    }

    html.dark-style .flatpickr-input {
        background-color: #25293c !important;
        border-color: #444b6e;
        color: #d8deff;
    }

    html.dark-style .info-cliente-card .text-muted,
    html.dark-style .periodo-card .text-muted {
        color: rgba(216, 222, 255, .72) !important;
    }
</style>
<div class="container-fluid flex-grow-1 content-with-sticky-footer">
    <div class="row">
        <div class="col-12">
            <!-- Exibir erros de validação -->
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <strong><i class="ti ti-alert-circle me-2"></i>Erro ao salvar:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Exibir mensagens de erro da sessão -->
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form action="{{ route('locacoes.store') }}" method="POST" id="formLocacao">
                @csrf
                <input type="hidden" name="aba" value="{{ old('aba', request('aba', 'ativos')) }}">
                <input type="hidden" name="status" id="status_locacao" value="{{ old('status', $statusInicial ?? 'orcamento') }}">

                <!-- Cabeçalho -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="ti ti-file-plus me-2"></i>
                            Nova Locação
                            <span class="badge bg-label-{{ (old('status', $statusInicial ?? 'orcamento') === 'aprovado') ? 'success' : 'secondary' }} ms-2 align-middle">
                                {{ (old('status', $statusInicial ?? 'orcamento') === 'aprovado') ? 'Aprovado' : 'Orçamento' }}
                            </span>
                        </h5>
                        <a href="{{ route('locacoes.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>

                <!-- Nav Pills -->
                <div class="card mb-4">
                    <div class="card-body py-3">
                        <ul class="nav nav-pills nav-pills-full d-flex" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-clientes">
                                    <i class="ti ti-user me-1"></i> Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-periodo">
                                    <i class="ti ti-calendar me-1"></i> Período
                                </a>
                            </li>
                            @if(!($isMedicao ?? false))
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-salas">
                                    <i class="ti ti-layout me-1"></i> Salas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-produtos">
                                    <i class="ti ti-package me-1"></i> Produtos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-servicos">
                                    <i class="ti ti-settings me-1"></i> Serviços
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-despesas">
                                    <i class="ti ti-receipt-2 me-1"></i> Despesas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-resumo">
                                    <i class="ti ti-report-money me-1"></i> Resumo
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="card">
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Tab: Clientes -->
                            <div class="tab-pane fade show active" id="tab-clientes" role="tabpanel">
                                <div class="row">
                                    <!-- Coluna Esquerda: Formulário -->
                                    <div class="col-md-7">
                                        <h5 class="mb-4">
                                            <i class="ti ti-user me-2"></i>
                                            Informações do Contrato
                                        </h5>
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label">Cliente <span class="text-danger">*</span></label>
                                                <select name="id_cliente" id="id_cliente" class="form-select select2-busca-cliente" required>
                                                    <option value="">Buscar cliente...</option>
                                                </select>
                                            </div>
                                            @if(($isMedicao ?? false))
                                            <div class="col-md-12">
                                                <label class="form-label">Valor limite de gasto (Medição)</label>
                                                <input type="text" name="valor_limite_medicao" class="form-control money" value="{{ old('valor_limite_medicao') }}" placeholder="0,00" inputmode="decimal" autocomplete="off">
                                                <small class="text-muted">Quando atingir este valor, novos envios serão bloqueados até ajuste do limite.</small>
                                            </div>
                                            @endif
                                            @if(!empty($permiteNumeroManualLocacao))
                                                @if(!($isMedicao ?? false))
                                                <div class="col-md-6">
                                                    <label class="form-label">Número do Contrato/Orçamento (Opcional)</label>
                                                    <input type="number" name="numero_manual" class="form-control" min="0" step="1" value="{{ old('numero_manual') }}" placeholder="Deixe vazio para automático">
                                                    <small class="text-muted">Preencha para forçar a numeração. Vazio ou 0 segue automático.</small>
                                                </div>
                                                @endif
                                            @endif
                                            <div class="col-md-12">
                                                <label class="form-label">Funcionário Responsável</label>
                                                <select name="id_usuario" id="id_usuario" class="form-select">
                                                    <option value="">Selecione um funcionário...</option>
                                                    @if(isset($usuarios))
                                                        @foreach($usuarios as $usuario)
                                                            <option value="{{ $usuario->id_usuario }}" {{ (string) old('id_usuario') === (string) $usuario->id_usuario ? 'selected' : '' }}>{{ $usuario->name }}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Coluna Direita: Info do Cliente -->
                                    <div class="col-md-5">
                                        <div class="info-cliente-card p-4 h-100" id="infoClienteCard">
                                            <!-- Placeholder quando nenhum cliente selecionado -->
                                            <div class="text-center text-muted py-4 info-cliente-placeholder" id="infoClientePlaceholder">
                                                <i class="ti ti-user-search display-4 mb-3 d-block opacity-50"></i>
                                                <p class="mb-0">Selecione um cliente para ver seus dados</p>
                                            </div>
                                            <!-- Conteúdo quando cliente selecionado -->
                                            <div class="info-cliente-content" id="infoClienteContent" style="display: none;">
                                                <h6 class="mb-3">
                                                    <i class="ti ti-id me-1"></i>
                                                    Informações do Cliente
                                                </h6>
                                                <div class="d-flex align-items-center gap-3 mb-3">
                                                    <div id="clienteFoto" class="flex-shrink-0">
                                                        <div class="avatar avatar-lg">
                                                            <span class="avatar-initial rounded bg-label-primary">
                                                                <i class="ti ti-user"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <strong id="clienteNome" class="fs-5">-</strong>
                                                    </div>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Documento</small>
                                                        <div id="clienteDocumento">-</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">Telefone</small>
                                                        <div id="clienteTelefone">-</div>
                                                    </div>
                                                    <div class="col-12">
                                                        <small class="text-muted">E-mail</small>
                                                        <div id="clienteEmail">-</div>
                                                    </div>
                                                    <div class="col-12">
                                                        <small class="text-muted">Endereço</small>
                                                        <div id="clienteEndereco">-</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Período -->
                            <div class="tab-pane fade" id="tab-periodo" role="tabpanel">
                                <div class="row">
                                    <!-- Período Principal -->
                                    <div class="col-lg-8">
                                        <div class="periodo-card p-4 mb-4">
                                            <h5 class="mb-4">
                                                <i class="ti ti-calendar-event me-2 text-primary"></i>
                                                Período da Locação
                                            </h5>
                                            @if(!($isMedicao ?? false))
                                            <div class="form-check form-switch mb-3">
                                                <input type="hidden" name="locacao_por_hora" value="0">
                                                <input class="form-check-input" type="checkbox" id="locacao_por_hora" name="locacao_por_hora" value="1" {{ old('locacao_por_hora') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="locacao_por_hora">Locação por hora</label>
                                            </div>
                                            <div class="form-check form-switch mb-4">
                                                <input type="hidden" name="renovacao_automatica" value="0">
                                                <input class="form-check-input" type="checkbox" id="renovacao_automatica" name="renovacao_automatica" value="1" {{ old('renovacao_automatica') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="renovacao_automatica">Renovação automática (aditivo)</label>
                                            </div>
                                            @else
                                            <input type="hidden" name="locacao_por_hora" id="locacao_por_hora" value="0">
                                            <input type="hidden" name="renovacao_automatica" id="renovacao_automatica" value="0">
                                            @endif
                                            <div class="row g-4">
                                                <div class="{{ ($isMedicao ?? false) ? 'col-md-12' : 'col-md-6' }}">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="periodo-icon bg-success-subtle text-success flex-shrink-0">
                                                            <i class="ti ti-calendar-plus"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <label class="form-label fw-semibold mb-2">Saída <span class="text-danger">*</span></label>
                                                            <div class="row g-2">
                                                                <div class="col-7">
                                                                    <input type="text" name="data_inicio" id="data_inicio" class="form-control form-control-sm js-date-picker" required value="{{ date('Y-m-d') }}" autocomplete="off">
                                                                </div>
                                                                <div class="col-5">
                                                                    <input type="text" name="hora_inicio" id="hora_inicio" class="form-control form-control-sm js-time-picker" value="08:00" autocomplete="off">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @if(!($isMedicao ?? false))
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="periodo-icon bg-danger-subtle text-danger flex-shrink-0">
                                                            <i class="ti ti-calendar-minus"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <label class="form-label fw-semibold mb-2">Retorno <span class="text-danger">*</span></label>
                                                            <div class="row g-2">
                                                                <div class="col-7">
                                                                    <input type="text" name="data_fim" id="data_fim" class="form-control form-control-sm js-date-picker" required autocomplete="off">
                                                                </div>
                                                                <div class="col-5">
                                                                    <input type="text" name="hora_fim" id="hora_fim" class="form-control form-control-sm js-time-picker" value="18:00" autocomplete="off">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @else
                                                    <input type="hidden" name="data_fim" id="data_fim" value="{{ date('Y-m-d') }}">
                                                    <input type="hidden" name="hora_fim" id="hora_fim" value="08:00">
                                                @endif
                                            </div>

                                            @if(!($isMedicao ?? false))
                                            <div class="mt-4 pt-3 border-top">
                                                <input type="hidden" id="periodo_padrao_contrato" value="">
                                                <label class="form-label fw-semibold mb-2">Período padrão</label>
                                                <div class="d-flex flex-wrap gap-2" id="periodosPadraoContrato">
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-periodo-padrao" data-periodo="diaria">Diária</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-periodo-padrao" data-periodo="semanal">Semanal</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-periodo-padrao" data-periodo="quinzena">Quinzena</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-periodo-padrao" data-periodo="mensal">Mensal</button>
                                                </div>
                                                <small class="text-muted d-block mt-2">Ao selecionar, o retorno é calculado automaticamente a partir da data de saída.</small>
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Endereço e Contato -->
                                        <div class="periodo-card p-4">
                                            <h6 class="mb-3">
                                                <i class="ti ti-map-pin me-2 text-info"></i>
                                                Local de Entrega
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="form-check">
                                                        <input type="hidden" name="usar_endereco_cliente" value="0">
                                                        <input type="checkbox" class="form-check-input" id="usar_endereco_cliente" name="usar_endereco_cliente" value="1" checked>
                                                        <label class="form-check-label" for="usar_endereco_cliente">Usar endereço do cliente</label>
                                                    </div>
                                                </div>
                                                <div class="col-12" id="endereco_entrega_container" style="display: none;">
                                                    <label class="form-label">Endereço de Entrega</label>
                                                    <textarea name="local_entrega" id="endereco_entrega" class="form-control" rows="2"></textarea>
                                                </div>
                                                @if(!($isMedicao ?? false))
                                                <div class="col-md-6">
                                                    <label class="form-label">Contato Responsável</label>
                                                    <input type="text" name="contato_responsavel" class="form-control" placeholder="Nome do responsável">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Telefone Responsável</label>
                                                    <input type="text" name="telefone_responsavel" class="form-control telefone" placeholder="(00) 00000-0000">
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Resumo Lateral -->
                                    @if(!($isMedicao ?? false))
                                    <div class="col-lg-4">
                                        <div class="info-cliente-card p-4 h-100">
                                            <h6 class="mb-4">
                                                <i class="ti ti-calendar-stats me-2"></i>
                                                Resumo do Período
                                            </h6>
                                            <div class="text-center mb-4">
                                                <div class="display-4 fw-bold text-primary" id="totalDiasDisplay">
                                                    1
                                                </div>
                                                <small class="text-muted" id="unidadePeriodoLabel">dia(s) de locação</small>
                                                <input type="hidden" name="quantidade_dias" id="quantidade_dias" value="1">
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Saída:</span>
                                                <strong id="resumoSaida">{{ date('d/m/Y') }}</strong>
                                            </div>
                                            @if(!($isMedicao ?? false))
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Retorno:</span>
                                                <strong id="resumoRetorno">-</strong>
                                            </div>
                                            @else
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Tipo:</span>
                                                <strong>Medição (sem retorno)</strong>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            @if(!($isMedicao ?? false))
                            <!-- Tab: Salas -->
                            <div class="tab-pane fade" id="tab-salas" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <i class="ti ti-layout me-2"></i>
                                        Salas / Ambientes
                                    </h5>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnAdicionarSala">
                                        <i class="ti ti-plus me-1"></i>
                                        Adicionar Sala
                                    </button>
                                </div>
                        <div id="listaSalas">
                            <div class="text-center py-3 text-muted" id="semSalas">
                                <i class="ti ti-info-circle me-1"></i>
                                Nenhuma sala adicionada. Os produtos serão listados sem agrupamento.
                            </div>
                        </div>
                            </div>

                            <!-- Tab: Produtos -->
                            <div class="tab-pane fade" id="tab-produtos" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <i class="ti ti-package me-2"></i>
                                        Produtos da Locação
                                    </h5>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary active" data-tipo-produto="proprio" id="btnProdutoProprio">
                                            <i class="ti ti-home me-1"></i> Próprio
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" data-tipo-produto="terceiro" id="btnProdutoTerceiro">
                                            <i class="ti ti-users me-1"></i> Terceiro
                                        </button>
                                    </div>
                                </div>
                        <!-- Adicionar Produto PRÓPRIO via Modal -->
                        <div class="row mb-4" id="divProdutoProprio">
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" id="btnAbrirModalProdutos">
                                    <i class="ti ti-plus me-1"></i> Adicionar Produto Próprio
                                </button>
                                <small class="text-muted ms-3">
                                    <i class="ti ti-info-circle me-1"></i>
                                    Clique para buscar e adicionar produtos do seu estoque
                                </small>
                            </div>
                        </div>
                        
                        <!-- Adicionar Produto DE TERCEIRO via Modal -->
                        <div class="row mb-4" id="divProdutoTerceiro" style="display: none;">
                            <div class="col-12">
                                <button type="button" class="btn btn-warning" id="btnAbrirModalProdutoTerceiro">
                                    <i class="ti ti-plus me-1"></i> Adicionar Produto de Terceiro
                                </button>
                                <small class="text-muted ms-3">
                                    <i class="ti ti-info-circle me-1"></i>
                                    Clique para adicionar produtos/equipamentos de fornecedores
                                </small>
                            </div>
                        </div>

                        <!-- Lista de Produtos Adicionados -->
                        <div id="listaProdutosLocacao">
                            <div id="semProdutos" class="text-center py-4 text-muted border rounded">
                                <i class="ti ti-package-off ti-lg d-block mb-2"></i>
                                Nenhum produto adicionado à locação
                            </div>
                        </div>

                        <!-- Resumo dos Produtos -->
                        <div class="border-top pt-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">
                                    <i class="ti ti-shopping-cart me-1"></i>
                                    <span id="qtdProdutosLocacao">0</span> produto(s) na locação
                                    <span id="infoTerceiros" class="ms-2 text-warning" style="display: none;">
                                        (<span id="qtdTerceiros">0</span> de terceiros)
                                    </span>
                                </span>
                                <div>
                                    <strong>Subtotal Produtos: <span id="subtotalProdutos" class="text-primary">R$ 0,00</span></strong>
                                    <small class="text-warning ms-2" id="custoTerceirosInfo" style="display: none;">
                                        (Custo terceiros: <span id="custoTotalTerceiros">R$ 0,00</span>)
                                    </small>
                                </div>
                            </div>
                        </div>
                            </div>

                            <!-- Tab: Serviços -->
                            <div class="tab-pane fade" id="tab-servicos" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <i class="ti ti-settings me-2"></i>
                                        Serviços Adicionais
                                    </h5>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary active" data-tipo-servico="proprio" id="btnServicoProprio">
                                            <i class="ti ti-home me-1"></i> Próprio
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" data-tipo-servico="terceiro" id="btnServicoTerceiro">
                                            <i class="ti ti-users me-1"></i> Terceiro
                                        </button>
                                    </div>
                                </div>
                        <!-- Botões para Adicionar Serviços via Modal -->
                        <div class="row mb-3" id="divServicoProprio">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary" id="btnAbrirModalServicoProprio">
                                    <i class="ti ti-plus me-1"></i> Adicionar Serviço Próprio
                                </button>
                                <small class="text-muted ms-3">
                                    <i class="ti ti-info-circle me-1"></i>
                                    Clique para adicionar serviços prestados por você
                                </small>
                            </div>
                        </div>
                        
                        <!-- Botão para Serviço de Terceiro -->
                        <div class="row mb-3" id="divServicoTerceiro" style="display: none;">
                            <div class="col-12">
                                <button type="button" class="btn btn-warning" id="btnAbrirModalServicoTerceiro">
                                    <i class="ti ti-plus me-1"></i> Adicionar Serviço de Terceiro
                                </button>
                                <small class="text-muted ms-3">
                                    <i class="ti ti-info-circle me-1"></i>
                                    Clique para adicionar serviços de fornecedores
                                </small>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table" id="tabelaServicos">
                                <thead>
                                    <tr>
                                        <th width="5%">Tipo</th>
                                        <th width="45%">Descrição</th>
                                        <th width="10%">Qtd</th>
                                        <th width="15%">Valor Unit.</th>
                                        <th width="15%">Subtotal</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="servicosBody">
                                    <!-- Serviços serão adicionados via JS -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal Serviços:</strong></td>
                                        <td><strong id="subtotalServicos">R$ 0,00</strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                            </div>

                            <!-- Tab: Despesas -->
                            <div class="tab-pane fade" id="tab-despesas" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <i class="ti ti-receipt-2 me-2"></i>
                                        Despesas da Locação
                                    </h5>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-body">
                                        <input type="hidden" id="despesaEditIndex" value="">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="despesaDescricao" placeholder="Ex: Combustível para entrega">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Tipo</label>
                                                <select class="form-select" id="despesaTipo">
                                                    <option value="transporte">Transporte/Frete</option>
                                                    <option value="montagem">Montagem</option>
                                                    <option value="desmontagem">Desmontagem</option>
                                                    <option value="seguro">Seguro</option>
                                                    <option value="taxa">Taxa</option>
                                                    <option value="multa">Multa</option>
                                                    <option value="outros" selected>Outros</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Valor <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control money" id="despesaValor" value="0,00">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Data despesa</label>
                                                <input type="date" class="form-control" id="despesaData">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Vencimento</label>
                                                <input type="date" class="form-control" id="despesaVencimento">
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label">Parcelas</label>
                                                <input type="number" class="form-control" id="despesaParcelas" min="1" value="1">
                                            </div>
                                            <div class="col-md-8 d-flex align-items-end">
                                                <small class="text-muted">
                                                    <i class="ti ti-info-circle me-1"></i>
                                                    As despesas serão lançadas automaticamente no contas a pagar e vinculadas à locação.
                                                </small>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end justify-content-md-end justify-content-start gap-2">
                                                <button type="button" class="btn btn-outline-secondary" id="btnCancelarEdicaoDespesa" style="display: none;">
                                                    Cancelar
                                                </button>
                                                <button type="button" class="btn btn-primary" id="btnAdicionarDespesa">
                                                    <i class="ti ti-plus me-1"></i> Adicionar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table" id="tabelaDespesas">
                                        <thead>
                                            <tr>
                                                <th width="28%">Descrição</th>
                                                <th width="14%">Tipo</th>
                                                <th width="12%">Data</th>
                                                <th width="12%">Vencimento</th>
                                                <th width="8%">Parcelas</th>
                                                <th width="16%">Valor</th>
                                                <th width="10%"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="despesasBody"></tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" class="text-end"><strong>Subtotal Despesas:</strong></td>
                                                <td><strong id="subtotalDespesas">R$ 0,00</strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab: Resumo -->
                            <div class="tab-pane fade" id="tab-resumo" role="tabpanel">
                                <h5 class="mb-3">
                                    <i class="ti ti-report-money me-2"></i>
                                    Resumo da Locação
                                </h5>

                                <div class="row">
                                    <!-- Card de Resumo Financeiro -->
                                    <div class="col-lg-6 col-md-12 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="ti ti-calculator me-2"></i>
                                                    Resumo Financeiro
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless mb-0">
                                                    <tbody>
                                                        <tr>
                                                            <td class="text-muted">Subtotal Produtos:</td>
                                                            <td class="text-end fw-semibold" id="resumoSubtotalProdutos">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted">Subtotal Serviços:</td>
                                                            <td class="text-end fw-semibold" id="resumoSubtotalServicos">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted">Subtotal Despesas:</td>
                                                            <td class="text-end fw-semibold" id="resumoSubtotalDespesas">R$ 0,00</td>
                                                        </tr>
                                                        <tr class="border-top">
                                                            <td class="text-muted"><label for="input_frete_entrega">Frete Entrega:</label></td>
                                                            <td class="text-end">
                                                                <input type="text" class="form-control form-control-sm text-end fw-semibold money" id="input_frete_entrega" value="0,00" inputmode="decimal" style="max-width: 120px; display: inline-block;">
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted"><label for="input_frete_retirada">Frete Retirada:</label></td>
                                                            <td class="text-end">
                                                                <input type="text" class="form-control form-control-sm text-end fw-semibold money" id="input_frete_retirada" value="0,00" inputmode="decimal" style="max-width: 120px; display: inline-block;">
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted text-danger"><label for="input_desconto">Desconto:</label></td>
                                                            <td class="text-end">
                                                                <input type="text" class="form-control form-control-sm text-end fw-semibold text-danger money" id="input_desconto" value="0,00" inputmode="decimal" style="max-width: 120px; display: inline-block;">
                                                            </td>
                                                        </tr>
                                                        <tr class="border-top">
                                                            <td class="text-primary fw-bold fs-5">TOTAL:</td>
                                                            <td class="text-end text-primary fw-bold fs-5" id="resumoValorTotal">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-muted">Lucratividade:</td>
                                                            <td class="text-end fw-bold" id="resumoLucratividade">R$ 0,00</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card de Observações -->
                                    <div class="col-lg-6 col-md-12 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">
                                                    <i class="ti ti-notes me-2"></i>
                                                    Observações por Documento
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label for="obs_orcamento" class="form-label fw-semibold mb-1">Obs para Orçamento</label>
                                                    <small class="text-muted d-block mb-1">Este texto aparece na impressão do orçamento.</small>
                                                    <textarea id="obs_orcamento" name="observacoes_orcamento" class="form-control" rows="2" placeholder="Observação exibida no orçamento..."></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="obs_contrato" class="form-label fw-semibold mb-1">Obs para Contrato</label>
                                                    <small class="text-muted d-block mb-1">Este texto aparece no contrato.</small>
                                                    <textarea id="obs_contrato" name="observacoes" class="form-control" rows="2" placeholder="Observação exibida no contrato..."></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="obs_recibo" class="form-label fw-semibold mb-1">Obs para Recibo</label>
                                                    <small class="text-muted d-block mb-1">Este texto aparece no documento Recibo.</small>
                                                    <textarea id="obs_recibo" name="observacoes_recibo" class="form-control" rows="2" placeholder="Observação exibida no Recibo..."></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="obs_entrega" class="form-label fw-semibold mb-1">Obs para Entrega</label>
                                                    <small class="text-muted d-block mb-1">Este texto aparece no Comprovante de Entrega.</small>
                                                    <textarea id="obs_entrega" name="observacoes_entrega" class="form-control" rows="2" placeholder="Observação exibida no Comprovante de Entrega..."></textarea>
                                                </div>

                                                <div class="mb-0">
                                                    <label for="obs_checklist" class="form-label fw-semibold mb-1">Obs para Checklist</label>
                                                    <small class="text-muted d-block mb-1">Este texto aparece no Checklist.</small>
                                                    <textarea id="obs_checklist" name="observacoes_checklist" class="form-control" rows="2" placeholder="Observação exibida no Checklist..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Inputs Hidden para form -->
                                <input type="hidden" name="desconto" id="desconto_geral" value="0,00">
                                <input type="hidden" name="taxa_entrega" id="taxa_entrega" value="0,00">
                                <input type="hidden" name="frete_entrega" id="frete_entrega" value="0,00">
                                <input type="hidden" name="frete_retirada" id="frete_retirada" value="0,00">
                                <input type="hidden" name="valor_total" id="valor_total_input" value="0">
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Barra Fixa de Ações -->
<div class="sticky-footer-bar" id="stickyFooterBar">
    <div class="d-flex justify-content-between align-items-center w-100">
        <div>
            <span class="text-muted">
                <i class="ti ti-info-circle me-1"></i>
                Preencha os dados para criar a locação
            </span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('locacoes.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-x me-1"></i> Cancelar
            </a>
            <button type="submit" form="formLocacao" class="btn btn-primary">
                <i class="ti ti-check me-1"></i> {{ $tituloAcaoCriacao ?? 'Criar Locação' }}
            </button>
        </div>
    </div>
</div>

<script>
// Posicionar barra sticky alinhada ao container do formulário
(function() {
    function posicionarBarraSticky() {
        var form = document.getElementById('formLocacao');
        var bar = document.getElementById('stickyFooterBar');
        if (!form || !bar) return;
        
        var formRect = form.getBoundingClientRect();
        var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        
        bar.style.left = (formRect.left + scrollLeft) + 'px';
        bar.style.width = formRect.width + 'px';
    }
    
    // Executar ao carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', posicionarBarraSticky);
    } else {
        posicionarBarraSticky();
    }
    
    // Executar ao redimensionar
    window.addEventListener('resize', posicionarBarraSticky);
    
    // Executar após um pequeno delay para garantir que o layout está completo
    setTimeout(posicionarBarraSticky, 100);
    setTimeout(posicionarBarraSticky, 500);
})();
</script>

<!-- Template Card de Produto -->
<template id="templateProdutoCard">
    <div class="card produto-card mb-3 border" data-index="INDEX">
        <div class="card-body">
            <div class="row">
                <!-- Coluna Esquerda: Foto e Info Básica -->
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="produto-foto">FOTO_HTML</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 produto-nome">NOME</h6>
                            <span class="badge bg-label-info patrimonio-badge d-none"><i class="ti ti-qrcode me-1"></i>Com patrimônios</span>
                        </div>
                    </div>
                </div>
                
                <!-- Coluna Central: Datas e Configurações -->
                <div class="col-md-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">Data Início</label>
                            <input type="date" class="form-control form-control-sm input-data-inicio" data-index="INDEX">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Data Fim</label>
                            <input type="date" class="form-control form-control-sm input-data-fim" data-index="INDEX">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Quantidade</label>
                            <input type="number" class="form-control form-control-sm input-qtd-locacao" data-index="INDEX" min="1" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Tabela de Preço</label>
                            <select class="form-select form-select-sm select-tabela-preco" data-index="INDEX">
                                <option value="">Preço padrão</option>
                            </select>
                        </div>
                        <div class="col-12 select-valor-tabela-container d-none">
                            <label class="form-label small mb-1">Valor da Tabela</label>
                            <select class="form-select form-select-sm select-valor-tabela" data-index="INDEX">
                                <option value="">Selecione o valor...</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Coluna Direita: Valores e Ações -->
                <div class="col-md-3">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small mb-1">Valor Unitário</label>
                            <input type="text" class="form-control form-control-sm money input-valor-locacao" data-index="INDEX" value="0,00">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input check-valor-fechado" data-index="INDEX" id="valorFechado_INDEX">
                                <label class="form-check-label small" for="valorFechado_INDEX">Valor fechado pelo período</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <strong class="subtotal-item text-primary">R$ 0,00</strong>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-warning btn-vincular-patrimonios d-none" data-index="INDEX" title="Vincular patrimônios">
                                    <i class="ti ti-qrcode"></i> <span class="badge-pat">0/0</span>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-remover-produto" data-index="INDEX" title="Remover">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Linha de Observações (opcional) -->
            <div class="row mt-2">
                <div class="col-12">
                    <input type="text" class="form-control form-control-sm input-observacoes" data-index="INDEX" placeholder="Observações do item (opcional)">
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Modal Vincular Patrimônios -->
<div class="modal fade" id="modalPatrimonios" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vincular Patrimônios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="ti ti-info-circle me-1"></i>
                    <strong id="modalProdutoNome">Produto</strong>
                    <br><small>Quantidade na locação: <strong id="modalQtdNecessaria">0</strong></small>
                    <br><small class="text-warning"><i class="ti ti-alert-triangle me-1"></i>Patrimônios usados, locados ou em manutenção estão desabilitados.</small>
                </div>
                <input type="hidden" id="modalProdutoIndex">
                <div id="listaPatrimoniosVincular">
                    <!-- Selects de patrimônios serão adicionados aqui -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarPatrimonios">
                    <i class="ti ti-check me-1"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Produto -->
<div class="modal fade" id="modalAdicionarProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="ti ti-package-import me-2"></i>
                    Adicionar Produto à Locação
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalAddProdutoId">
                <input type="hidden" id="modalAddProdutoIndex">
                
                <!-- Info do Produto -->
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <div id="modalAddProdutoFoto" class="me-3">
                        <div class="avatar avatar-lg">
                            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-package"></i></span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="modalAddProdutoNome">Produto</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-label-success" id="modalAddProdutoDisponivel">0 disponíveis</span>
                            <span class="badge bg-label-primary" id="modalAddProdutoEstoque">Estoque: 0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Alerta de preferência de estoque -->
                <div class="alert alert-info mb-4" id="alertaPreferenciaEstoque">
                    <i class="ti ti-info-circle me-2"></i>
                    <span id="textoPreferenciaEstoque">
                        Defina o período específico deste produto. A disponibilidade será calculada com base nessas datas.
                    </span>
                </div>
                
                <!-- Quantidade e Valores -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" id="modalAddQtd" class="form-control" min="1" value="1">
                        <small class="text-muted" id="modalAddMaxQtd">Máx. disponível: 0</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor Unitário <span class="text-danger">*</span></label>
                        <input type="text" id="modalAddValor" class="form-control money" value="0,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tabela de Preço</label>
                        <select id="modalAddTabela" class="form-select">
                            <option value="">Padrão</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sala</label>
                        <select id="modalAddSala" class="form-select">
                            <option value="">Sem sala</option>
                        </select>
                    </div>
                </div>
                
                <!-- Período do Produto -->
                <div class="card mb-4 border-primary" id="cardPeriodoProduto">
                    <div class="card-header bg-label-primary">
                        <h6 class="mb-0">
                            <i class="ti ti-calendar me-1"></i>
                            Período do Produto
                            <small class="text-muted ms-2">(usado para cálculo de estoque)</small>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Data Início <span class="text-danger">*</span></label>
                                <input type="date" id="modalAddDataInicio" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hora Saída</label>
                                <input type="time" id="modalAddHoraInicio" class="form-control" value="08:00">
                            </div>
                            <div class="col-md-3" @if($isMedicao ?? false) style="display:none;" @endif>
                                <label class="form-label">Data Fim <span class="text-danger">*</span></label>
                                <input type="date" id="modalAddDataFim" class="form-control">
                            </div>
                            <div class="col-md-3" @if($isMedicao ?? false) style="display:none;" @endif>
                                <label class="form-label">Hora Retorno</label>
                                <input type="time" id="modalAddHoraFim" class="form-control" value="18:00">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div id="modalAddDisponibilidadeInfo" class="alert alert-secondary mb-0">
                                    <i class="ti ti-clock me-1"></i>
                                    Selecione as datas para verificar a disponibilidade.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Opções Adicionais -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modalAddValorFechado">
                            <label class="form-check-label" for="modalAddValorFechado">
                                Valor fechado <small class="text-muted">(não multiplicar por período)</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observações</label>
                        <input type="text" id="modalAddObservacoes" class="form-control" placeholder="Observações do item (opcional)">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarAdicionarProduto">
                    <i class="ti ti-check me-1"></i> Adicionar Produto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Produto (para alterar datas após adicionado) -->
<div class="modal fade" id="modalEditarProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="ti ti-pencil me-2"></i>
                    Editar Produto da Locação
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalEditProdutoIndex">
                
                <!-- Info do Produto -->
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                    <div id="modalEditProdutoFoto" class="me-3">
                        <div class="avatar avatar-lg">
                            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-package"></i></span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1" id="modalEditProdutoNome">Produto</h5>
                    </div>
                </div>
                
                <!-- Quantidade e Valores -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" id="modalEditQtd" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor Unitário <span class="text-danger">*</span></label>
                        <input type="text" id="modalEditValor" class="form-control money" value="0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sala</label>
                        <select id="modalEditSala" class="form-select">
                            <option value="">Sem sala</option>
                        </select>
                    </div>
                </div>
                
                <!-- Disponibilidade -->
                <div class="card mb-4 border-warning" id="cardEditPeriodoProduto">
                    <div class="card-header bg-label-warning">
                        <h6 class="mb-0">
                            <i class="ti ti-package me-1"></i>
                            Período do Produto e Disponibilidade
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Data Início <span class="text-danger">*</span></label>
                                <input type="date" id="modalEditDataInicio" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hora Início</label>
                                <input type="time" id="modalEditHoraInicio" class="form-control" value="08:00">
                            </div>
                            <div class="col-md-3" @if($isMedicao ?? false) style="display:none;" @endif>
                                <label class="form-label">Data Fim <span class="text-danger">*</span></label>
                                <input type="date" id="modalEditDataFim" class="form-control">
                            </div>
                            <div class="col-md-3" @if($isMedicao ?? false) style="display:none;" @endif>
                                <label class="form-label">Hora Fim</label>
                                <input type="time" id="modalEditHoraFim" class="form-control" value="18:00">
                            </div>
                        </div>
                        <div id="modalEditDisponibilidadeInfo" class="alert alert-secondary mt-2 mb-3">
                            <i class="ti ti-clock me-1"></i>
                            Verificando disponibilidade...
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-info" id="btnVerDetalhesEstoqueEdit">
                                <i class="ti ti-calendar-stats me-1"></i>
                                Ver detalhes dia a dia
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Opções Adicionais -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modalEditValorFechado">
                            <label class="form-check-label" for="modalEditValorFechado">
                                Valor fechado <small class="text-muted">(não multiplicar por período)</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarEditarProduto">
                    <i class="ti ti-check me-1"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Produto de Terceiro -->
<div class="modal fade" id="modalProdutoTerceiro" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalProdutoTerceiroTitulo">
                    <i class="ti ti-users me-2"></i>
                    Adicionar Produto de Terceiro
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalTerceiroEditIndex" value="">
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input type="text" id="modalTerceiroDescricao" class="form-control" placeholder="Nome/descrição do produto ou equipamento">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sala/Ambiente</label>
                        <select id="modalTerceiroSala" class="form-select">
                            <option value="">Sem sala</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="modalTerceiroQtd" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Custo Unit.</label>
                        <input type="text" id="modalTerceiroCusto" class="form-control money" value="0,00">
                        <small class="text-muted">Pago ao fornecedor</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor Venda</label>
                        <input type="text" id="modalTerceiroValor" class="form-control money" value="0,00">
                        <small class="text-muted">Cobrado do cliente</small>
                    </div>
                </div>
                
                <!-- Período -->
                <!-- Opções -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modalTerceiroContaPagar" checked>
                            <label class="form-check-label" for="modalTerceiroContaPagar">
                                <i class="ti ti-receipt me-1"></i>
                                Gerar conta a pagar automaticamente
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modalTerceiroValorFechado" checked>
                            <label class="form-check-label" for="modalTerceiroValorFechado">
                                Valor fechado (não multiplicar por período)
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Campos de Conta a Pagar (visível quando marcado) -->
                <div id="camposContaPagar" class="card mb-3 border-info">
                    <div class="card-header bg-label-info py-2">
                        <h6 class="mb-0">
                            <i class="ti ti-receipt me-1"></i>
                            Dados da Conta a Pagar
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Data de Vencimento</label>
                                <input type="date" id="modalTerceiroVencimento" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valor da Conta</label>
                                <input type="text" id="modalTerceiroValorConta" class="form-control money" value="0,00">
                                <small class="text-muted">Total a pagar ao fornecedor</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Parcelas</label>
                                <input type="number" id="modalTerceiroParcelas" class="form-control" min="1" max="48" value="1">
                                <small class="text-muted">Quantidade de parcelas</small>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarProdutoTerceiro">
                    <i class="ti ti-check me-1"></i> <span id="modalProdutoTerceiroBotao">Adicionar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Serviço Próprio -->
<div class="modal fade" id="modalServicoProprio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalServicoProprioTitulo">
                    <i class="ti ti-settings me-2"></i>
                    Adicionar Serviço Próprio
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalServicoProprioEditIndex" value="">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Descrição do Serviço <span class="text-danger">*</span></label>
                        <input type="text" id="modalServicoProprioDescricao" class="form-control" placeholder="Ex: Montagem, Instalação, Frete...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="modalServicoProprioQtd" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor Unitário</label>
                        <input type="text" id="modalServicoProprioValor" class="form-control money" value="0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sala/Ambiente</label>
                        <select id="modalServicoProprioSala" class="form-select">
                            <option value="">Sem sala</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarServicoProprio">
                    <i class="ti ti-check me-1"></i> <span id="modalServicoProprioBotao">Adicionar Serviço</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Serviço de Terceiro -->
<div class="modal fade" id="modalServicoTerceiro" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalServicoTerceiroTitulo">
                    <i class="ti ti-users-group me-2"></i>
                    Adicionar Serviço de Terceiro
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalServicoTerceiroEditIndex" value="">
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label">Descrição do Serviço <span class="text-danger">*</span></label>
                        <input type="text" id="modalServicoTerceiroDescricao" class="form-control" placeholder="Ex: DJ, Buffet, Decoração...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sala/Ambiente</label>
                        <select id="modalServicoTerceiroSala" class="form-select">
                            <option value="">Sem sala</option>
                        </select>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Quantidade</label>
                        <input type="number" id="modalServicoTerceiroQtd" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Custo Unitário</label>
                        <input type="text" id="modalServicoTerceiroCusto" class="form-control money" value="0,00">
                        <small class="text-muted">Pago ao fornecedor</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor Venda</label>
                        <input type="text" id="modalServicoTerceiroValor" class="form-control money" value="0,00">
                        <small class="text-muted">Cobrado do cliente</small>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modalServicoTerceiroContaPagar" checked>
                            <label class="form-check-label" for="modalServicoTerceiroContaPagar">
                                <i class="ti ti-receipt me-1"></i>
                                Gerar conta a pagar automaticamente
                            </label>
                        </div>
                    </div>
                </div>

                <div id="camposContaPagarServico" class="card mt-3 mb-3 border-info">
                    <div class="card-header bg-label-info py-2">
                        <h6 class="mb-0">
                            <i class="ti ti-receipt me-1"></i>
                            Dados da Conta a Pagar
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Data de Vencimento</label>
                                <input type="date" id="modalServicoTerceiroVencimento" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valor da Conta</label>
                                <input type="text" id="modalServicoTerceiroValorConta" class="form-control money" value="0,00">
                                <small class="text-muted">Total a pagar ao fornecedor</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Parcelas</label>
                                <input type="number" id="modalServicoTerceiroParcelas" class="form-control" min="1" max="48" value="1">
                                <small class="text-muted">Quantidade de parcelas</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarServicoTerceiro">
                    <i class="ti ti-check me-1"></i> <span id="modalServicoTerceiroBotao">Adicionar Serviço</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Busca Avançada de Produtos -->
<div class="modal fade" id="modalBuscaProdutos" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="ti ti-list-search me-2"></i>
                    Selecionar Produtos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Filtros -->
                <div class="p-3 border-bottom bg-light">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="text" id="filtroBuscaNome" class="form-control" placeholder="Buscar por nome ou código...">
                        </div>
                        <div class="col-md-3">
                            <select id="filtroDisponibilidade" class="form-select">
                                <option value="">Todos</option>
                                <option value="disponivel" selected>Com disponibilidade</option>
                                <option value="indisponivel">Sem disponibilidade</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="button" class="btn btn-outline-secondary flex-grow-1" id="btnLimparFiltros">
                                <i class="ti ti-x"></i> Limpar
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary w-100" id="btnFiltrarProdutos">
                                <i class="ti ti-search me-1"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="ti ti-calendar me-1"></i>
                            Período: <strong id="infoPeriodoModal">--</strong>
                        </small>
                    </div>
                </div>
                
                <!-- Lista de Produtos -->
                <div id="listaProdutosModal" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center py-4">
                        <i class="ti ti-loader ti-spin ti-lg"></i>
                        <br>Carregando produtos...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <span class="badge bg-primary" id="qtdSelecionados">0</span> produtos selecionados
                </div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAdicionarSelecionados" disabled>
                    <i class="ti ti-plus me-1"></i> Adicionar Selecionados
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes Estoque Dia a Dia -->
<div class="modal fade" id="modalDetalhesEstoque" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="ti ti-calendar-stats me-2"></i>
                    Disponibilidade por Dia
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="mb-3" id="detalhesEstoqueNomeProduto">-</h6>
                <div class="row g-2 mb-3">
                    <div class="col-4 text-center">
                        <div class="card bg-label-primary">
                            <div class="card-body p-2">
                                <small class="d-block">Estoque Total</small>
                                <h5 class="mb-0" id="detalhesEstoqueTotal">0</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="card bg-label-warning">
                            <div class="card-body p-2">
                                <small class="d-block">Pico Reservas</small>
                                <h5 class="mb-0" id="detalhesPicoPeriodo">0</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="card bg-label-success">
                            <div class="card-body p-2">
                                <small class="d-block">Disponível</small>
                                <h5 class="mb-0" id="detalhesDisponivelPeriodo">0</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="mb-2"><i class="ti ti-calendar me-1"></i> Calendário:</h6>
                <div class="d-flex flex-wrap gap-1" id="diasDisponibilidade">
                    <!-- Badges dos dias serão inseridos aqui -->
                </div>
                <div class="mt-3" id="listaConflitos" style="display: none;">
                    <small class="text-danger d-block mb-2"><i class="ti ti-alert-triangle me-1"></i>Conflitos no período:</small>
                    <div id="conflitosLista" class="small"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    if (!document.getElementById('swal2-front-modal-zindex')) {
        $('head').append('<style id="swal2-front-modal-zindex">.swal2-container{z-index:200000 !important;}</style>');
    }

    var servicoIndex = 0;
    var produtosDisponiveis = [];
    var produtosLocacao = []; // Produtos adicionados à locação
    var servicosLocacao = []; // Serviços adicionados à locação
    var despesasLocacao = []; // Despesas da locação
    var salasLocacao = []; // Salas criadas para a locação
    var salaIndex = 0;
    var despesaIndex = 0;
    var totalDias = 1;
    var produtoIndex = 0;
    var dataContratoInicio = '';
    var dataContratoFim = '';
    var horaContratoInicio = '08:00';
    var horaContratoFim = '18:00';
    var pickerDataInicio = null;
    var pickerDataFim = null;
    var pickerHoraFim = null;
    var aplicandoPeriodoPadrao = false;
    var isMedicao = @json((bool) ($isMedicao ?? false));

    function sincronizarPeriodoMedicaoContrato() {
        if (!isMedicao) return;
        var dataInicio = $('#data_inicio').val() || '{{ date('Y-m-d') }}';
        var horaInicio = $('#hora_inicio').val() || '08:00';
        $('#data_fim').val(dataInicio);
        $('#hora_fim').val(horaInicio);
        dataContratoFim = dataInicio;
        horaContratoFim = horaInicio;
    }

    function atualizarLimitesPeriodoContrato() {
        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        var horaInicio = $('#hora_inicio').val() || '00:00';
        var porHora = isLocacaoPorHora();

        if (pickerDataFim && dataInicio) {
            pickerDataFim.set('minDate', dataInicio);
            pickerDataFim.set('maxDate', porHora ? dataInicio : null);
        }

        if (dataInicio && dataFim && (dataFim < dataInicio || (porHora && dataFim !== dataInicio))) {
            $('#data_fim').val(dataInicio);
            if (pickerDataFim) {
                pickerDataFim.setDate(dataInicio, false);
            }
            dataFim = dataInicio;
        }

        if (pickerHoraFim) {
            if (dataInicio && dataFim && dataInicio === dataFim) {
                pickerHoraFim.set('minTime', horaInicio);
                var horaFimAtual = $('#hora_fim').val() || '23:59';
                if (horaFimAtual < horaInicio) {
                    $('#hora_fim').val(horaInicio);
                    pickerHoraFim.setDate(horaInicio, false, 'H:i');
                }
            } else {
                pickerHoraFim.set('minTime', '00:00');
            }
        }
    }

    function inicializarSeletoresPeriodoContrato() {
        if (typeof flatpickr === 'undefined') {
            return;
        }

        pickerDataInicio = flatpickr('#data_inicio', {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            allowInput: false,
            defaultDate: $('#data_inicio').val() || null,
            onChange: function() {
                atualizarLimitesPeriodoContrato();
                atualizarResumoPeriodo();
                validarPeriodoContratoFrontend();
            }
        });

        if (!isMedicao) {
            pickerDataFim = flatpickr('#data_fim', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: false,
                defaultDate: $('#data_fim').val() || null,
                onChange: function() {
                    atualizarLimitesPeriodoContrato();
                    atualizarResumoPeriodo();
                    validarPeriodoContratoFrontend();
                }
            });
        } else {
            pickerDataFim = null;
        }

        flatpickr('#hora_inicio', {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            time_24hr: true,
            minuteIncrement: 5,
            allowInput: false,
            defaultDate: $('#hora_inicio').val() || '08:00',
            onChange: function() {
                atualizarLimitesPeriodoContrato();
                validarPeriodoContratoFrontend();
            }
        });

        if (!isMedicao) {
            pickerHoraFim = flatpickr('#hora_fim', {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                minuteIncrement: 5,
                allowInput: false,
                defaultDate: $('#hora_fim').val() || '18:00',
                onChange: function() {
                    validarPeriodoContratoFrontend();
                }
            });
        } else {
            pickerHoraFim = null;
        }

        atualizarLimitesPeriodoContrato();
    }

    // ==============================
    // EXIBIR INFORMAÇÕES DO CLIENTE
    // ==============================
    function resolverUrlFotoCliente(cliente) {
        var baseUploads = 'https://api.gestornow.com';
        var origem = String((cliente && (cliente.foto_url || cliente.foto)) || '').trim();

        if (!origem) {
            return '';
        }

        // Corrigir URLs malformadas sem ":" (ex: "https/uploads/..." ou "http/uploads/...")
        origem = origem.replace(/^https\/(?!\/)/i, 'https://').replace(/^http\/(?!\/)/i, 'https://');

        // Se já é URL completa válida, retornar como está
        if (/^https?:\/\//i.test(origem)) {
            return origem;
        }

        if (/^\/?uploads\//i.test(origem)) {
            return baseUploads + '/' + origem.replace(/^\/+/, '');
        }

        if (origem.charAt(0) === '/') {
            return origem;
        }

        return baseUploads + '/uploads/' + origem.replace(/^\/+/, '');
    }

    function exibirInfoCliente(clienteId) {
        var $card = $('#infoClienteCard');
        var $placeholder = $card.find('#infoClientePlaceholder, .info-cliente-placeholder');
        var $content = $card.find('#infoClienteContent, .info-cliente-content');
        
        if (!clienteId) {
            $placeholder.show();
            $content.hide();
            return;
        }
        
        $.ajax({
            url: '/clientes/' + clienteId + '/json',
            method: 'GET',
            success: function(cliente) {
                $('#clienteNome').text(cliente.nome || '-');
                $('#clienteDocumento').text(cliente.documento || cliente.cpf_cnpj || '-');
                $('#clienteTelefone').text(cliente.telefone || cliente.celular || '-');
                $('#clienteEmail').text(cliente.email || '-');

                var nomeCliente = String(cliente.nome || '').trim();
                var iniciais = nomeCliente
                    ? nomeCliente.split(/\s+/).slice(0, 2).map(function(parte) { return parte.charAt(0).toUpperCase(); }).join('')
                    : 'CL';

                var fotoUrl = resolverUrlFotoCliente(cliente);

                if (fotoUrl) {
                    var $fotoContainer = $('#clienteFoto');
                    var $img = $('<img>', {
                        src: fotoUrl,
                        class: 'rounded',
                        alt: 'Foto do cliente',
                        css: { width: '56px', height: '56px', objectFit: 'cover' }
                    });
                    $img.on('error', function() {
                        $fotoContainer.html(
                            '<div class="avatar avatar-lg">' +
                                '<span class="avatar-initial rounded bg-label-primary">' + iniciais + '</span>' +
                            '</div>'
                        );
                    });
                    $fotoContainer.empty().append($img);
                } else {
                    $('#clienteFoto').html(
                        '<div class="avatar avatar-lg">' +
                            '<span class="avatar-initial rounded bg-label-primary">' + iniciais + '</span>' +
                        '</div>'
                    );
                }
                
                var endereco = [];
                if (cliente.endereco) endereco.push(cliente.endereco);
                if (cliente.numero) endereco.push(cliente.numero);
                if (cliente.bairro) endereco.push(cliente.bairro);
                if (cliente.cidade) endereco.push(cliente.cidade);
                $('#clienteEndereco').text(endereco.join(', ') || '-');

                if ($('#usar_endereco_cliente').is(':checked')) {
                    var enderecoEntrega = [];
                    if (cliente.endereco_entrega) {
                        enderecoEntrega.push(cliente.endereco_entrega);
                        if (cliente.numero_entrega) enderecoEntrega.push(cliente.numero_entrega);
                        if (cliente.complemento_entrega) enderecoEntrega.push(cliente.complemento_entrega);
                        if (cliente.cep_entrega) enderecoEntrega.push('CEP ' + cliente.cep_entrega);
                    } else {
                        if (cliente.endereco) enderecoEntrega.push(cliente.endereco);
                        if (cliente.numero) enderecoEntrega.push(cliente.numero);
                        if (cliente.complemento) enderecoEntrega.push(cliente.complemento);
                        if (cliente.bairro) enderecoEntrega.push(cliente.bairro);
                        if (cliente.cep) enderecoEntrega.push('CEP ' + cliente.cep);
                    }

                    $('#endereco_entrega').val(enderecoEntrega.join(', '));
                }
                
                $placeholder.hide();
                $content.show();
            },
            error: function() {
                $placeholder.html('<i class="ti ti-alert-circle text-warning"></i> Erro ao carregar cliente');
            }
        });
    }
    
    // Evento change do select cliente
    $('#id_cliente').on('change', function() {
        exibirInfoCliente($(this).val());
    });

    var clienteSelecionadoInicial = $('#id_cliente').val();
    if (clienteSelecionadoInicial) {
        exibirInfoCliente(clienteSelecionadoInicial);
    }

    function parseDateLocal(dateString) {
        if (!dateString) return null;
        var partes = String(dateString).substring(0, 10).split('-');
        if (partes.length !== 3) return null;
        return new Date(parseInt(partes[0], 10), parseInt(partes[1], 10) - 1, parseInt(partes[2], 10));
    }

    function formatarDataBrSafe(dateString) {
        var data = parseDateLocal(dateString);
        if (!data) return '-';
        return data.toLocaleDateString('pt-BR');
    }

    function formatarMoedaBr(valor) {
        var numero = parseFloat(valor);
        if (isNaN(numero)) {
            numero = 0;
        }
        return numero.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function calcularDiasInclusivo(dataInicio, dataFim) {
        var inicio = parseDateLocal(dataInicio);
        var fim = parseDateLocal(dataFim);
        if (!inicio || !fim) return 1;
        var diff = Math.ceil((fim - inicio) / (1000 * 60 * 60 * 24)) + 1;
        return diff > 0 ? diff : 1;
    }

    function normalizarHoraParaCalculo(hora, fallback) {
        var valor = (hora || fallback || '00:00').toString().trim();
        if (!valor) {
            valor = fallback || '00:00';
        }

        var partes = valor.split(':');
        var hh = String(partes[0] || '00').padStart(2, '0');
        var mm = String(partes[1] || '00').padStart(2, '0');

        return hh + ':' + mm;
    }

    function calcularHorasPeriodo(dataInicio, horaInicio, dataFim, horaFim) {
        if (!dataInicio || !dataFim) return 1;

        var horaInicioNormalizada = normalizarHoraParaCalculo(horaInicio, '00:00');
        var horaFimNormalizada = normalizarHoraParaCalculo(horaFim, '23:59');

        var inicio = new Date(dataInicio + 'T' + horaInicioNormalizada + ':00');
        var fim = new Date(dataFim + 'T' + horaFimNormalizada + ':00');
        var diffMs = fim - inicio;

        if (isNaN(diffMs) || diffMs < 0) {
            return 1;
        }

        var horas = Math.ceil(diffMs / (1000 * 60 * 60));
        return horas > 0 ? horas : 1;
    }

    function isLocacaoPorHora() {
        if (isMedicao) {
            return false;
        }
        return $('#locacao_por_hora').is(':checked');
    }

    function atualizarBloqueioLocacaoPorHora() {
        var temProdutos = Array.isArray(produtosLocacao) && produtosLocacao.length > 0;
        var $input = $('#locacao_por_hora');

        $input.prop('disabled', temProdutos);
        if (temProdutos) {
            $input.attr('title', 'Remova os produtos para alterar este campo.');
        } else {
            $input.removeAttr('title');
        }
    }

    // ==============================
    // ATUALIZAR RESUMO DO PERÍODO
    // ==============================
    function atualizarResumoPeriodo() {
        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        var horaInicio = $('#hora_inicio').val() || '00:00';
        var horaFim = $('#hora_fim').val() || '23:59';

        if (isMedicao) {
            sincronizarPeriodoMedicaoContrato();
            dataFim = dataInicio;
            horaFim = horaInicio;
        }
        
        if (dataInicio) {
            $('#resumoSaida').text(formatarDataBrSafe(dataInicio));
        }
        if (dataFim) {
            $('#resumoRetorno').text(formatarDataBrSafe(dataFim));
        } else {
            $('#resumoRetorno').text('-');
        }
        
        if (dataInicio && dataFim) {
                    var diff = isLocacaoPorHora()
                        ? calcularHorasPeriodo(dataInicio, horaInicio, dataFim, horaFim)
                        : calcularDiasInclusivo(dataInicio, dataFim);

            $('#totalDiasDisplay').text(diff);
            $('#quantidade_dias').val(diff);
                    $('#unidadePeriodoLabel').text(isLocacaoPorHora() ? 'hora(s) de locação' : 'dia(s) de locação');
            totalDias = diff;
        }
    }

    function validarPeriodoContratoFrontend() {
        if (isMedicao) {
            sincronizarPeriodoMedicaoContrato();
            return true;
        }

        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        var horaInicio = $('#hora_inicio').val() || '00:00';
        var horaFim = $('#hora_fim').val() || '23:59';

        if (!dataInicio || !dataFim) {
            $('#data_fim')[0].setCustomValidity('');
            return true;
        }

        if (isLocacaoPorHora() && dataInicio !== dataFim) {
            $('#data_fim')[0].setCustomValidity('Em locação por hora, a data de fim deve ser igual à data de início.');
            return false;
        }

        var inicio = new Date(dataInicio + 'T' + horaInicio + ':00');
        var fim = new Date(dataFim + 'T' + horaFim + ':00');
        var valido = fim >= inicio;

        $('#data_fim')[0].setCustomValidity(valido ? '' : 'A data/hora de fim não pode ser anterior ao início.');
        return valido;
    }

    function aplicarRestricaoLocacaoPorHora() {
        var dataInicio = $('#data_inicio').val();

        if (!pickerDataFim) {
            return;
        }

        if (isLocacaoPorHora()) {
            if (dataInicio) {
                $('#data_fim').val(dataInicio);
                pickerDataFim.setDate(dataInicio, false);
                pickerDataFim.set('minDate', dataInicio);
                pickerDataFim.set('maxDate', dataInicio);
            }
        } else {
            pickerDataFim.set('maxDate', null);
        }
    }

    function formatarDataIsoLocal(dataObj) {
        if (!(dataObj instanceof Date) || isNaN(dataObj.getTime())) {
            return '';
        }

        var ano = dataObj.getFullYear();
        var mes = String(dataObj.getMonth() + 1).padStart(2, '0');
        var dia = String(dataObj.getDate()).padStart(2, '0');

        return ano + '-' + mes + '-' + dia;
    }

    function somarDiasDataIso(dataIso, dias) {
        var dataBase = parseDateLocal(dataIso);
        if (!dataBase) {
            return '';
        }

        dataBase.setDate(dataBase.getDate() + dias);
        return formatarDataIsoLocal(dataBase);
    }

    function limparSelecaoPeriodoPadrao() {
        $('#periodo_padrao_contrato').val('');
        $('#periodosPadraoContrato .btn-periodo-padrao').removeClass('active');
    }

    function atualizarEstadoBotoesPeriodoPadrao() {
        if (isMedicao) {
            return;
        }

        var porHora = isLocacaoPorHora();
        $('#periodosPadraoContrato .btn-periodo-padrao').each(function () {
            var periodo = $(this).data('periodo');
            var bloquear = porHora && periodo !== 'diaria';

            $(this).prop('disabled', bloquear);
            $(this).toggleClass('disabled', bloquear);
        });
    }

    function aplicarPeriodoPadraoContrato(periodo, manterSelecao) {
        if (isMedicao) {
            return;
        }

        var mapaDias = {
            diaria: 0,
            semanal: 6,
            quinzena: 14,
            mensal: 29
        };

        var dias = mapaDias[periodo];
        if (typeof dias === 'undefined') {
            return;
        }

        if (isLocacaoPorHora() && periodo !== 'diaria') {
            Swal.fire('Locação por hora', 'Para locação por hora, apenas o período diária pode ser aplicado.', 'info');
            return;
        }

        var dataInicio = $('#data_inicio').val();
        if (!dataInicio) {
            Swal.fire('Período', 'Informe a data de saída antes de aplicar um período padrão.', 'warning');
            return;
        }

        var novaDataFim = somarDiasDataIso(dataInicio, dias);
        if (!novaDataFim) {
            return;
        }

        aplicandoPeriodoPadrao = true;
        $('#data_fim').val(novaDataFim);
        if (pickerDataFim) {
            pickerDataFim.setDate(novaDataFim, false);
        }
        aplicandoPeriodoPadrao = false;

        if (manterSelecao !== false) {
            $('#periodo_padrao_contrato').val(periodo);
            $('#periodosPadraoContrato .btn-periodo-padrao').removeClass('active');
            $('#periodosPadraoContrato .btn-periodo-padrao[data-periodo="' + periodo + '"]').addClass('active');
        }

        calcularDias();
        atualizarLimitesPeriodoContrato();
        atualizarLimitesDatasProdutosNosModais();
        atualizarResumoPeriodo();
        validarPeriodoContratoFrontend();
    }

    $('#periodosPadraoContrato').on('click', '.btn-periodo-padrao', function () {
        var periodo = $(this).data('periodo');
        aplicarPeriodoPadraoContrato(periodo, true);
    });

    $('#data_inicio').on('change', function () {
        var periodoSelecionado = $('#periodo_padrao_contrato').val();
        if (periodoSelecionado) {
            aplicarPeriodoPadraoContrato(periodoSelecionado, false);
        }
    });

    $('#data_fim').on('change', function () {
        if (!aplicandoPeriodoPadrao) {
            limparSelecaoPeriodoPadrao();
        }
    });
    
    $('#data_inicio, #data_fim, #hora_inicio, #hora_fim').on('change', function() {
        if (isMedicao) {
            sincronizarPeriodoMedicaoContrato();
        }
        atualizarLimitesPeriodoContrato();
        aplicarRestricaoLocacaoPorHora();
        atualizarResumoPeriodo();
        validarPeriodoContratoFrontend();
    });

    $('#locacao_por_hora').on('change', function() {
        atualizarLimitesPeriodoContrato();
        aplicarRestricaoLocacaoPorHora();
        atualizarEstadoBotoesPeriodoPadrao();
        var periodoSelecionado = $('#periodo_padrao_contrato').val();
        if (isLocacaoPorHora() && periodoSelecionado && periodoSelecionado !== 'diaria') {
            aplicarPeriodoPadraoContrato('diaria', true);
        }
        atualizarResumoPeriodo();
        validarPeriodoContratoFrontend();
    });

    // === CARREGAR PRODUTOS PARA SELECT (COM DISPONIBILIDADE NO PERÍODO) ===
    function carregarProdutosSelect() {
        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        var horaInicio = $('#hora_inicio').val() || '08:00';
        var horaFim = $('#hora_fim').val() || '18:00';
        
        var url = '{{ route("locacoes.produtos-disponiveis-periodo") }}';
        var params = {};
        if (dataInicio && dataFim) {
            params.data_inicio = dataInicio;
            params.data_fim = dataFim;
            params.hora_inicio = horaInicio;
            params.hora_fim = horaFim;
            params.preferencia_estoque = getPreferenciaEstoque();
        }
        
        console.log('=== CARREGANDO PRODUTOS ===');
        console.log('URL:', url);
        console.log('Params:', params);
        
        $.ajax({
            url: url,
            method: 'GET',
            data: params,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: 10000,
            success: function(data) {
                console.log('✓ Produtos carregados com sucesso:', data);
                
                // Converter objeto indexado numericamente para array e remover _csrf_token
                if (typeof data === 'object' && data !== null && !Array.isArray(data)) {
                    var produtos = [];
                    for (var key in data) {
                        if (key !== '_csrf_token' && data.hasOwnProperty(key) && !isNaN(key)) {
                            produtos.push(data[key]);
                        }
                    }
                    // Se temos um campo data, usar aquele
                    if (data.data && Array.isArray(data.data)) {
                        produtosDisponiveis = data.data;
                    } else {
                        produtosDisponiveis = produtos;
                    }
                } else if (Array.isArray(data)) {
                    produtosDisponiveis = data;
                } else {
                    produtosDisponiveis = [];
                }
                
                // Atualizar CSRF token se recebido
                if (data._csrf_token) {
                    window.updateCsrfToken(data._csrf_token);
                }
                
                console.log('Produtos disponíveis:', produtosDisponiveis);
                
                // Destruir Select2 existente se houver
                try {
                    if ($('.select2-produtos').length && $('.select2-produtos').hasClass('select2-hidden-accessible')) {
                        $('.select2-produtos').select2('destroy');
                    }
                } catch(e) {
                    console.warn('Erro ao destruir Select2:', e);
                }
                
                // Limpar e popular o select
                var $select = $('.select2-produtos');
                if (!$select.length) {
                    console.error('Select com class .select2-produtos não encontrado no DOM');
                    return;
                }
                
                $select.empty();
                $select.append('<option value="">Buscar produto...</option>');
                
                produtosDisponiveis.forEach(function(p) {
                    var qtdDisp = p.quantidade_disponivel || 0;
                    var valorPadrao = parseFloat(p.preco_diaria || p.preco_locacao || p.preco_venda || 0);
                    var dispInfo = dataInicio && dataFim ? ' (Disp. período: ' + qtdDisp + ')' : ' (Estoque: ' + qtdDisp + ')';
                    var text = p.nome + dispInfo + ' - R$ ' + valorPadrao.toFixed(2).replace('.', ',');
                    var statusAtual = $('[name="status"]').val() || 'orcamento';
                    var permitirSemEstoque = statusAtual === 'orcamento';
                    var disabled = (!permitirSemEstoque && qtdDisp <= 0) ? 'disabled' : '';
                    $select.append('<option value="' + p.id_produto + '" ' + disabled + '>' + text + '</option>');
                });
                
                // Inicializar Select2
                $select.select2({
                    placeholder: 'Buscar produto...',
                    allowClear: true,
                    minimumInputLength: 0
                });
                
                console.log('Select2 inicializado com ' + produtosDisponiveis.length + ' produtos');
            },
            error: function(xhr, status, error) {
                console.error('✗ Erro ao carregar produtos');
                console.error('Status:', xhr.status);
                console.error('Status text:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                console.error('Response JSON:', xhr.responseJSON);
                
                // Se for erro 401, não mostrar SweetAlert (sessão expirada é tratada globalmente)
                if (xhr.status === 401) {
                    console.warn('Sessão expirada ao tentar carregar produtos');
                    return;
                }
                
                // Mostrar erro apenas se não for 419 (CSRF - já tratado globalmente)
                if (xhr.status !== 419) {
                    // Fallback sem produtos
                    var $select = $('.select2-produtos');
                    if ($select.length) {
                        $select.empty();
                        $select.append('<option value="">Erro ao carregar produtos. Recarregue a página.</option>');
                        $select.select2({
                            placeholder: 'Erro ao carregar produtos',
                            allowClear: false,
                            minimumInputLength: 0
                        });
                    }
                }
            }
        });
    }

    // === ATUALIZAR INFORMAÇÃO DE DISPONIBILIDADE ===
    $('#selectProduto').on('select2:select', function(e) {
        var idProduto = e.params.data.id;
        atualizarDisponibilidade(idProduto);
    });
    
    $('#selectProduto').on('select2:clear', function() {
        $('#disponibilidadeInfo').html(`
            <small class="text-muted">
                <i class="ti ti-info-circle me-1"></i>
                Selecione um produto para ver a disponibilidade no período.
            </small>
        `);
    });
    
    function atualizarDisponibilidade(idProduto) {
        var produto = produtosDisponiveis.find(p => p.id_produto == idProduto);
        if (!produto) {
            $('#disponibilidadeInfo').html(`
                <small class="text-muted">
                    <i class="ti ti-info-circle me-1"></i>
                    Selecione um produto para ver a disponibilidade no período.
                </small>
            `);
            return;
        }
        
        var qtdDisp = produto.quantidade_disponivel || 0;
        var qtdReservada = produto.quantidade_reservada || 0;
        var qtdLocada = produto.quantidade_em_locacao || 0;
        var estoqueTotal = produto.estoque_total || 0;
        var conflitos = produto.conflitos || [];
        
        var html = '<div class="d-flex flex-wrap gap-2 align-items-center py-1">';
        
        // Status principal
        if (qtdDisp > 0) {
            html += `<span class="badge bg-success fs-6"><i class="ti ti-check me-1"></i>${qtdDisp} disponível(eis)</span>`;
        } else {
            html += `<span class="badge bg-danger fs-6"><i class="ti ti-x me-1"></i>Indisponível</span>`;
        }
        
        // Detalhes adicionais
        html += `<span class="badge bg-label-primary"><i class="ti ti-package me-1"></i>Estoque total: ${estoqueTotal}</span>`;
        
        if (qtdReservada > 0) {
            html += `<span class="badge bg-label-warning"><i class="ti ti-calendar me-1"></i>Reservados: ${qtdReservada}</span>`;
        }
        
        if (qtdLocada > 0) {
            html += `<span class="badge bg-label-info"><i class="ti ti-truck me-1"></i>Em locação: ${qtdLocada}</span>`;
        }
        
        // Botão de detalhes se houver conflitos
        if (conflitos.length > 0) {
            html += `<button type="button" class="btn btn-sm btn-outline-danger" onclick="mostrarConflitos(${idProduto})">
                <i class="ti ti-alert-triangle me-1"></i>Ver ${conflitos.length} conflito(s)
            </button>`;
        }
        
        html += '</div>';
        
        $('#disponibilidadeInfo').html(html);
    }
    
    // Função para mostrar conflitos em modal
    window.mostrarConflitos = function(idProduto) {
        var produto = produtosDisponiveis.find(p => p.id_produto == idProduto);
        if (!produto || !produto.conflitos || produto.conflitos.length === 0) {
            Swal.fire('Info', 'Não há conflitos para este produto.', 'info');
            return;
        }
        
        var html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead class="table-light"><tr><th>Contrato</th><th>Status</th><th>Qtd</th><th>Período</th><th>Patrimônio</th></tr></thead>';
        html += '<tbody>';
        
        produto.conflitos.forEach(function(c) {
            var statusBadge = {
                'reserva': '<span class="badge bg-warning">Reserva</span>',
                'em_andamento': '<span class="badge bg-primary">Em Andamento</span>',
                'orcamento': '<span class="badge bg-secondary">Orçamento</span>',
                'manutencao': '<span class="badge bg-info">Em Manutenção</span>'
            }[c.status] || c.status;

            var referencia = '-';
            if (c.status === 'manutencao') {
                referencia = `Manutenção #${c.id_manutencao || '-'}`;
            } else {
                referencia = `<a href="/locacoes/${c.id_locacao}" target="_blank">#${c.numero_contrato || c.id_locacao}</a>`;
            }
            
            html += `<tr>
                <td>${referencia}</td>
                <td>${statusBadge}</td>
                <td>${c.quantidade}</td>
                <td>${c.periodo || '-'}</td>
                <td>${c.patrimonio || '-'}</td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        
        Swal.fire({
            title: `Conflitos - ${produto.nome}`,
            html: html,
            width: 700,
            confirmButtonText: 'Fechar',
            customClass: {
                popup: 'text-start'
            }
        });
    };

    // === GESTÃO DE SALAS ===
    $('#btnAdicionarSala').on('click', function() {
        Swal.fire({
            title: 'Nova Sala/Ambiente',
            input: 'text',
            inputLabel: 'Nome da sala',
            inputPlaceholder: 'Ex: Salão Principal, Área Externa, etc.',
            showCancelButton: true,
            confirmButtonText: 'Adicionar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) {
                    return 'Digite o nome da sala!';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                adicionarSala(result.value);
            }
        });
    });

    function adicionarSala(nome) {
        nome = (nome || '').trim();
        if (!nome) return;

        var sala = {
            index: salaIndex,
            nome: nome,
            cor: getCorSala(salaIndex)
        };
        salasLocacao.push(sala);
        salaIndex++;
        
        renderizarSalas();
        atualizarSelectsSalas();
    }

    function getCorSala(index) {
        var cores = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
        return cores[index % cores.length];
    }

    function renderizarSalas() {
        var container = $('#listaSalas');
        container.empty();
        
        if (salasLocacao.length === 0) {
            container.html(`
                <div class="text-center py-3 text-muted" id="semSalas">
                    <i class="ti ti-info-circle me-1"></i>
                    Nenhuma sala adicionada. Os produtos serão listados sem agrupamento.
                </div>
            `);
            return;
        }
        
        salasLocacao.forEach(function(sala) {
            container.append(`
                <div class="card border-0 shadow-sm mb-2 sala-item" data-index="${sala.index}">
                    <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-${sala.cor}"><i class="ti ti-layout-grid ti-xs"></i></span>
                            <span class="fw-semibold">${sala.nome}</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-editar-sala" data-index="${sala.index}" title="Editar">
                                <i class="ti ti-pencil ti-xs"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-remover-sala" data-index="${sala.index}" title="Remover">
                                <i class="ti ti-trash ti-xs"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="salas[${sala.index}][nome]" value="${sala.nome}">
                </div>
            `);
        });

        $('.btn-editar-sala').off('click').on('click', function() {
            var index = $(this).data('index');
            var sala = salasLocacao.find(s => s.index == index);
            if (!sala) return;

            Swal.fire({
                title: 'Editar Sala/Ambiente',
                input: 'text',
                inputLabel: 'Nome da sala',
                inputValue: sala.nome,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Digite o nome da sala!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    sala.nome = result.value.trim();
                    renderizarSalas();
                    atualizarSelectsSalas();
                    renderizarProdutosLocacao();
                    renderizarServicosLocacao();
                }
            });
        });
        
        // Bind evento remover
        $('.btn-remover-sala').off('click').on('click', function() {
            var index = $(this).data('index');
            salasLocacao = salasLocacao.filter(s => s.index != index);
            // Remover sala dos produtos e serviços
            produtosLocacao.forEach(function(p) {
                if (p.id_sala == index) p.id_sala = null;
            });
            servicosLocacao.forEach(function(s) {
                if (s.id_sala == index) s.id_sala = null;
            });
            renderizarSalas();
            atualizarSelectsSalas();
            renderizarProdutosLocacao();
            renderizarServicosLocacao();
        });
    }

    function atualizarSelectsSalas() {
        var options = '<option value="">Sem sala</option>';
        salasLocacao.forEach(function(sala) {
            options += `<option value="${sala.index}">${sala.nome}</option>`;
        });
        
        $('#selectSalaProduto, #selectSalaTerceiro, #salaServicoProprio, #modalAddSala, #modalTerceiroSala, #modalServicoProprioSala, #modalServicoTerceiroSala, .select-sala-item').html(options);
    }

    // === TOGGLE PRODUTO PRÓPRIO / TERCEIRO ===
    $('#btnProdutoProprio, #btnProdutoTerceiro').on('click', function() {
        var tipo = $(this).data('tipo-produto');
        
        $('#btnProdutoProprio, #btnProdutoTerceiro').removeClass('active');
        $(this).addClass('active');
        
        if (tipo === 'proprio') {
            $('#divProdutoProprio').show();
            $('#divProdutoTerceiro').hide();
        } else {
            $('#divProdutoProprio').hide();
            $('#divProdutoTerceiro').show();
        }
    });

    // === TOGGLE SERVIÇO PRÓPRIO / TERCEIRO ===
    $('#btnServicoProprio, #btnServicoTerceiro').on('click', function() {
        var tipo = $(this).data('tipo-servico');
        
        $('#btnServicoProprio, #btnServicoTerceiro').removeClass('active');
        $(this).addClass('active');
        
        if (tipo === 'proprio') {
            $('#divServicoProprio').show();
            $('#divServicoTerceiro').hide();
        } else {
            $('#divServicoProprio').hide();
            $('#divServicoTerceiro').show();
        }
    });

    // === CARREGAR FORNECEDORES ===
    function carregarFornecedoresSelect() {
        $('.select2-fornecedores').select2({
            ajax: {
                url: '{{ route("locacoes.buscar-fornecedores") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { q: params.term || '' };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(f) {
                            return {
                                id: f.id_fornecedor,
                                text: f.nome + (f.cpf_cnpj ? ' - ' + f.cpf_cnpj : '')
                            };
                        })
                    };
                },
                cache: true
            },
            placeholder: 'Selecione o fornecedor...',
            minimumInputLength: 0,
            allowClear: true
        });
        
        // Inicializar select2 dentro dos modais
        $('.select2-fornecedores-modal').each(function() {
            var $this = $(this);
            var $modal = $this.closest('.modal');

            if ($this.hasClass('select2-hidden-accessible')) {
                $this.select2('destroy');
            }
            
            $this.select2({
                dropdownParent: $modal,
                width: '100%',
                ajax: {
                    url: '{{ route("locacoes.buscar-fornecedores") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term || '' };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(function(f) {
                                return {
                                    id: f.id_fornecedor,
                                    text: f.nome + (f.cpf_cnpj ? ' - ' + f.cpf_cnpj : '')
                                };
                            })
                        };
                    },
                    cache: true
                },
                placeholder: 'Selecione o fornecedor (opcional)...',
                minimumInputLength: 0,
                allowClear: true
            });
        });
    }

    // === PREENCHER SELECT DE VALORES DA TABELA DE PREÇO ===
    function preencherSelectValorTabela($select, tabela, valorSelecionado) {
        $select.empty();
        $select.append('<option value="">Selecione o valor...</option>');
        
        // Dias disponíveis na tabela
        var diasDisponiveis = [
            {campo: 'd1', label: '1 dia', valor: tabela.d1},
            {campo: 'd2', label: '2 dias', valor: tabela.d2},
            {campo: 'd3', label: '3 dias', valor: tabela.d3},
            {campo: 'd4', label: '4 dias', valor: tabela.d4},
            {campo: 'd5', label: '5 dias', valor: tabela.d5},
            {campo: 'd6', label: '6 dias', valor: tabela.d6},
            {campo: 'd7', label: '7 dias (semana)', valor: tabela.d7},
            {campo: 'd15', label: '15 dias (quinzena)', valor: tabela.d15},
            {campo: 'd30', label: '30 dias (mês)', valor: tabela.d30},
            {campo: 'd60', label: '60 dias', valor: tabela.d60},
            {campo: 'd90', label: '90 dias', valor: tabela.d90},
            {campo: 'd120', label: '120 dias', valor: tabela.d120},
            {campo: 'd360', label: '360 dias (ano)', valor: tabela.d360}
        ];
        
        diasDisponiveis.forEach(function(d) {
            if (d.valor && d.valor > 0) {
                var selected = valorSelecionado == d.campo ? 'selected' : '';
                $select.append(`<option value="${d.valor}" data-campo="${d.campo}" ${selected}>${d.label}: R$ ${d.valor.toFixed(2).replace('.', ',')}</option>`);
            }
        });
    }

    // === OBTER TODOS OS PATRIMÔNIOS JÁ USADOS EM OUTROS PRODUTOS ===
    function getPatrimoniosUsados(excludeIndex) {
        var usados = [];
        produtosLocacao.forEach(function(item) {
            if (item.index != excludeIndex && item.patrimonios_vinculados) {
                usados = usados.concat(item.patrimonios_vinculados);
            }
        });
        return usados;
    }

    // === OBTER PREFERÊNCIA DE ESTOQUE ATUAL ===
    function getPreferenciaEstoque() {
        return 'data_item';
    }

    function isLocacaoAprovada() {
        return (($('[name="status"]').val() || 'orcamento') === 'aprovado');
    }

    function aplicarLimiteQuantidadeModalAdd(qtdDisponivel) {
        var qtd = parseInt(qtdDisponivel || 0);

        $('#modalAddMaxQtd').text('Máx. disponível: ' + qtd);
        if (isLocacaoAprovada()) {
            $('#modalAddQtd').attr('max', qtd).data('max-disponivel', qtd);

            var qtdAtual = parseInt($('#modalAddQtd').val()) || 1;
            if (qtdAtual > qtd) {
                $('#modalAddQtd').val(Math.max(1, qtd));
            }
        } else {
            $('#modalAddQtd').removeAttr('max').removeData('max-disponivel');
        }
    }

    function aplicarLimiteQuantidadeModalEdit(qtdDisponivel) {
        var qtd = parseInt(qtdDisponivel || 0);

        if (isLocacaoAprovada()) {
            $('#modalEditQtd').attr('max', qtd).data('max-disponivel', qtd);

            var qtdAtual = parseInt($('#modalEditQtd').val()) || 1;
            if (qtdAtual > qtd) {
                $('#modalEditQtd').val(Math.max(1, qtd));
            }
        } else {
            $('#modalEditQtd').removeAttr('max').removeData('max-disponivel');
        }
    }

    function atualizarLimitesDatasProdutosNosModais() {
        var dataInicioContratoAtual = $('#data_inicio').val() || dataContratoInicio || '';
        var dataFimContratoAtual = isMedicao
            ? dataInicioContratoAtual
            : ($('#data_fim').val() || dataContratoFim || '');

        $('#modalAddDataInicio, #modalAddDataFim, #modalEditDataInicio, #modalEditDataFim')
            .attr('min', dataInicioContratoAtual || null)
            .attr('max', dataFimContratoAtual || null);
    }

    // === ABRIR MODAL PARA ADICIONAR PRODUTO ===
    function abrirModalAdicionarProduto(produto, quantidade, idSala) {
        var preferencia = getPreferenciaEstoque();
        var valorPadrao = parseFloat(produto.preco_diaria || produto.preco_locacao || produto.preco_venda || 0);
        
        // Preencher dados do produto
        $('#modalAddProdutoId').val(produto.id_produto);
        $('#modalAddProdutoNome').text(produto.nome);
        $('#modalAddProdutoDisponivel').text(produto.quantidade_disponivel + ' disponíveis');
        $('#modalAddProdutoEstoque').text('Estoque: ' + produto.estoque_total);
        aplicarLimiteQuantidadeModalAdd(produto.quantidade_disponivel || 0);
        
        // Foto
        if (produto.foto_url) {
            $('#modalAddProdutoFoto').html('<img src="' + produto.foto_url + '" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">');
        } else {
            $('#modalAddProdutoFoto').html('<div class="avatar avatar-lg"><span class="avatar-initial rounded bg-label-primary"><i class="ti ti-package"></i></span></div>');
        }
        
        // Valores
        $('#modalAddQtd').val(quantidade);
        aplicarLimiteQuantidadeModalAdd(produto.quantidade_disponivel || 0);
        $('#modalAddValor').val(valorPadrao.toFixed(2).replace('.', ','));
        $('#modalAddValorFechado').prop('checked', false);
        $('#modalAddObservacoes').val('');
        $('#modalAddDataInicio').val(dataContratoInicio || '');
        $('#modalAddDataFim').val(isMedicao ? '' : (dataContratoFim || ''));
        $('#modalAddHoraInicio').val(horaContratoInicio || '08:00');
        $('#modalAddHoraFim').val(isMedicao ? '' : (horaContratoFim || '18:00'));
        atualizarLimitesDatasProdutosNosModais();
        
        // Tabela de preço
        var $selectTabela = $('#modalAddTabela');
        $selectTabela.empty().append('<option value="">Padrão</option>');
        if (produto.tabelas_preco && produto.tabelas_preco.length > 0) {
            produto.tabelas_preco.forEach(function(t) {
                $selectTabela.append('<option value="' + t.id_tabela + '">' + t.nome + '</option>');
            });
        }
        
        // Salas
        var $selectSala = $('#modalAddSala');
        $selectSala.empty().append('<option value="">Sem sala</option>');
        salasLocacao.forEach(function(s) {
            var selected = s.index == idSala ? 'selected' : '';
            $selectSala.append('<option value="' + s.index + '" ' + selected + '>' + s.nome + '</option>');
        });
        
        // Atualizar texto de preferência - SIMPLIFICADO
        $('#alertaPreferenciaEstoque').removeClass('d-none alert-warning').addClass('alert-secondary');
        $('#textoPreferenciaEstoque').text('O estoque será calculado pelas datas e horas do item.');
        $('#cardPeriodoProduto').removeClass('d-none');
        
        // Guardar dados do produto no modal
        $('#modalAdicionarProduto').data('produto', produto);
        
        // Aplicar máscara money (mesmo padrão do modal de edição)
        $('#modalAddValor').mask('#.##0,00', {reverse: true});
        
        // Mostrar modal
        $('#modalAdicionarProduto').modal('show');
        
        // Verificar disponibilidade inicial
        verificarDisponibilidadeModal();
    }

    // === VERIFICAR DISPONIBILIDADE NO MODAL ===
    function verificarDisponibilidadeModal() {
        var produto = $('#modalAdicionarProduto').data('produto');
        if (!produto) return;
        
        var dataInicio = $('#modalAddDataInicio').val();
        var dataFim = $('#modalAddDataFim').val();
        var horaInicio = $('#modalAddHoraInicio').val();
        var horaFim = $('#modalAddHoraFim').val();

        if (isMedicao) {
            dataFim = dataInicio;
            horaFim = horaInicio || '08:00';
        }
        
        if (!dataInicio || (!isMedicao && !dataFim)) {
            $('#modalAddDisponibilidadeInfo').html('<i class="ti ti-clock me-1"></i>Selecione as datas para verificar a disponibilidade.');
            return;
        }

        var validacaoPeriodo = validarPeriodoModalBusca({
            data_inicio: dataInicio,
            data_fim: dataFim,
            hora_inicio: horaInicio,
            hora_fim: horaFim
        });
        if (!validacaoPeriodo.ok) {
            $('#modalAddDisponibilidadeInfo')
                .removeClass('alert-secondary alert-success alert-warning')
                .addClass('alert-danger')
                .html('<i class="ti ti-alert-triangle me-1"></i>' + validacaoPeriodo.mensagem);
            return;
        }
        
        $('#modalAddDisponibilidadeInfo').html('<i class="ti ti-loader ti-spin me-1"></i>Verificando disponibilidade...');
        
        $.ajax({
            url: '{{ route("locacoes.verificar-disponibilidade") }}',
            method: 'GET',
            data: {
                id_produto: produto.id_produto,
                data_inicio: dataInicio,
                data_fim: dataFim,
                hora_inicio: horaInicio,
                hora_fim: horaFim,
                preferencia_estoque: getPreferenciaEstoque()
            },
            success: function(data) {
                var qtdDisp = data.disponivel || 0;
                var estoqueTotal = data.estoque_total || 0;
                var reservado = data.reservado || 0;
                var emLocacao = data.em_locacao || 0;
                var alertClass = qtdDisp > 0 ? 'alert-success' : (isLocacaoAprovada() ? 'alert-danger' : 'alert-warning');
                
                // Construir HTML com cálculo detalhado
                var html = '<div class="d-flex flex-column gap-1">';
                
                // Título com resultado
                html += '<div class="d-flex align-items-center justify-content-between">';
                html += '<strong class="' + (qtdDisp > 0 ? 'text-success' : 'text-danger') + '">';
                html += '<i class="ti ti-' + (qtdDisp > 0 ? 'check' : 'x') + ' me-1"></i>';
                html += qtdDisp + ' disponível(eis) no período selecionado';
                html += '</strong>';
                html += '</div>';
                
                // Detalhamento do cálculo
                html += '<div class="small mt-2 p-2 bg-light rounded">';
                html += '<div class="fw-bold mb-1"><i class="ti ti-calculator me-1"></i>Cálculo do Estoque:</div>';
                html += '<div class="ms-2">';
                html += '<div>Estoque Total: <strong>' + estoqueTotal + '</strong></div>';
                if (reservado > 0) {
                    html += '<div class="text-warning">- Reservados: <strong>' + reservado + '</strong></div>';
                }
                if (emLocacao > 0) {
                    html += '<div class="text-primary">- Em Locação: <strong>' + emLocacao + '</strong></div>';
                }
                html += '<div class="border-top pt-1 mt-1">';
                html += '<strong class="' + (qtdDisp > 0 ? 'text-success' : 'text-danger') + '">';
                html += '= Disponível: ' + qtdDisp + '</strong>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                // Mostrar conflitos se houver
                if (data.conflitos && data.conflitos.length > 0) {
                    html += '<div class="small mt-2 text-danger">';
                    html += '<div class="fw-bold"><i class="ti ti-alert-triangle me-1"></i>' + data.conflitos.length + ' Conflito(s):</div>';
                    data.conflitos.forEach(function(c) {
                        html += '<div class="ms-2">';
                        html += '• Contrato #' + (c.numero_contrato || c.id_locacao);
                        html += ' (' + c.status + ')';
                        html += ' - Qtd: ' + c.quantidade;
                        if (c.periodo) html += ' - ' + c.periodo;
                        html += '</div>';
                    });
                    html += '</div>';
                }
                
                html += '</div>';
                
                $('#modalAddDisponibilidadeInfo').removeClass('alert-secondary alert-success alert-danger alert-warning').addClass(alertClass).html(html);
                aplicarLimiteQuantidadeModalAdd(qtdDisp);
            },
            error: function() {
                $('#modalAddDisponibilidadeInfo').html('<i class="ti ti-alert-triangle me-1"></i>Erro ao verificar disponibilidade.');
            }
        });
    }

    // === EVENTOS DO MODAL ADICIONAR PRODUTO ===
    $('#modalAddDataInicio, #modalAddDataFim, #modalAddHoraInicio, #modalAddHoraFim').on('change', function() {
        if (isMedicao) {
            $('#modalAddDataFim').val('');
            $('#modalAddHoraFim').val('');
        }
        verificarDisponibilidadeModal();
    });

    $('#modalAddQtd').on('input change', function() {
        if (!isLocacaoAprovada()) {
            return;
        }

        var maxDisponivel = parseInt($(this).data('max-disponivel'));
        if (isNaN(maxDisponivel)) {
            maxDisponivel = parseInt($(this).attr('max')) || 1;
        }

        var valor = parseInt($(this).val()) || 1;
        if (valor > maxDisponivel) {
            $(this).val(Math.max(1, maxDisponivel));
        }
    });

    // === CONFIRMAR ADIÇÃO DO PRODUTO ===
    $('#btnConfirmarAdicionarProduto').on('click', function() {
        var produto = $('#modalAdicionarProduto').data('produto');
        if (!produto) {
            Swal.fire('Erro', 'Produto não encontrado.', 'error');
            return;
        }
        
        var quantidade = parseInt($('#modalAddQtd').val()) || 1;
        var valorUnitario = parseFloat($('#modalAddValor').val().replace('.', '').replace(',', '.')) || 0;
        var valorFechado = $('#modalAddValorFechado').is(':checked');
        var idTabela = $('#modalAddTabela').val() || null;
        var idSala = $('#modalAddSala').val() || null;
        var observacoes = $('#modalAddObservacoes').val() || '';
        var dataInicio = $('#modalAddDataInicio').val() || dataContratoInicio;
        var dataFim = isMedicao ? '' : ($('#modalAddDataFim').val() || dataContratoFim);
        var horaInicio = $('#modalAddHoraInicio').val() || horaContratoInicio;
        var horaFim = isMedicao ? '' : ($('#modalAddHoraFim').val() || horaContratoFim);
        var maxDisponivel = parseInt($('#modalAddQtd').data('max-disponivel'));
        
        // Validações
        if (!dataInicio || (!isMedicao && !dataFim)) {
            Swal.fire('Atenção', 'Defina as datas do produto.', 'warning');
            return;
        }

        var validacaoPeriodo = validarPeriodoModalBusca({
            data_inicio: dataInicio,
            data_fim: dataFim,
            hora_inicio: horaInicio,
            hora_fim: horaFim
        });
        if (!validacaoPeriodo.ok) {
            Swal.fire('Período inválido', validacaoPeriodo.mensagem, 'warning');
            return;
        }

        if (isLocacaoAprovada() && !isNaN(maxDisponivel) && quantidade > maxDisponivel) {
            Swal.fire('Estoque insuficiente', 'A quantidade informada ultrapassa o disponível para o período selecionado.', 'warning');
            return;
        }
        
        var temPatrimonio = produto.patrimonios && produto.patrimonios.length > 0;
        if (temPatrimonio) {
            for (var i = 0; i < quantidade; i++) {
                produtosLocacao.push({
                    index: produtoIndex,
                    id_produto: produto.id_produto,
                    nome: produto.nome,
                    foto_url: produto.foto_url,
                    tipo_item: 'proprio',
                    quantidade: 1,
                    valor_unitario: valorUnitario,
                    valor_fechado: valorFechado,
                    data_inicio: dataInicio,
                    data_fim: dataFim,
                    hora_inicio: horaInicio,
                    hora_fim: horaFim,
                    id_tabela_preco: idTabela,
                    id_sala: idSala,
                    id_fornecedor: null,
                    custo_fornecedor: 0,
                    gerar_conta_pagar: false,
                    observacoes: observacoes,
                    patrimonios: produto.patrimonios || [],
                    tabelas_preco: produto.tabelas_preco || [],
                    patrimonios_vinculados: []
                });
                produtoIndex++;
            }
        } else {
            produtosLocacao.push({
                index: produtoIndex,
                id_produto: produto.id_produto,
                nome: produto.nome,
                foto_url: produto.foto_url,
                tipo_item: 'proprio',
                quantidade: quantidade,
                valor_unitario: valorUnitario,
                valor_fechado: valorFechado,
                data_inicio: dataInicio,
                data_fim: dataFim,
                hora_inicio: horaInicio,
                hora_fim: horaFim,
                id_tabela_preco: idTabela,
                id_sala: idSala,
                id_fornecedor: null,
                custo_fornecedor: 0,
                gerar_conta_pagar: false,
                observacoes: observacoes,
                patrimonios: produto.patrimonios || [],
                tabelas_preco: produto.tabelas_preco || [],
                patrimonios_vinculados: []
            });
            produtoIndex++;
        }
        
        // Fechar modal e limpar
        $('#modalAdicionarProduto').modal('hide');
        $('#selectProduto').val(null).trigger('change');
        $('#qtdProdutoProprio').val(1);
        $('#disponibilidadeInfo').html('<i class="ti ti-info-circle me-1"></i>Selecione um produto para ver a disponibilidade no período.');
        
        renderizarProdutosLocacao();
        calcularTotais();
        
        Swal.fire({
            icon: 'success',
            title: 'Produto adicionado!',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // === ABRIR MODAL PRODUTO DE TERCEIRO ===
    $('#btnAbrirModalProdutoTerceiro').on('click', function() {
        $('#modalTerceiroEditIndex').val('');
        $('#modalProdutoTerceiroTitulo').html('<i class="ti ti-users me-2"></i>Adicionar Produto de Terceiro');
        $('#modalProdutoTerceiroBotao').text('Adicionar');

        // Limpar campos
        $('#modalTerceiroDescricao').val('');
        $('#modalTerceiroQtd').val(1);
        $('#modalTerceiroCusto').val('0,00');
        $('#modalTerceiroValor').val('0,00');
        $('#modalTerceiroContaPagar').prop('checked', true);
        $('#modalTerceiroValorFechado').prop('checked', true);
        
        // Campos de conta a pagar
        $('#modalTerceiroVencimento').val('');
        $('#modalTerceiroValorConta').val('0,00');
        $('#modalTerceiroParcelas').val(1);
        toggleCamposContaPagar();
        
        // Popular salas
        popularSalasModal('#modalTerceiroSala');

        // Reforçar máscara de moeda no modal
        $('#modalTerceiroCusto, #modalTerceiroValor, #modalTerceiroValorConta').mask('#.##0,00', {reverse: true});
        
        // Abrir modal
        $('#modalProdutoTerceiro').modal('show');
    });

    function abrirModalProdutoTerceiroParaEdicao(index) {
        var item = produtosLocacao.find(p => p.index == index && p.tipo_item === 'terceiro');
        if (!item) {
            Swal.fire('Erro', 'Produto de terceiro não encontrado.', 'error');
            return;
        }

        $('#modalTerceiroEditIndex').val(item.index);
        $('#modalProdutoTerceiroTitulo').html('<i class="ti ti-users me-2"></i>Editar Produto de Terceiro');
        $('#modalProdutoTerceiroBotao').text('Salvar Alterações');

        $('#modalTerceiroDescricao').val(item.nome || '');
        $('#modalTerceiroQtd').val(item.quantidade || 1);
        $('#modalTerceiroCusto').val((parseFloat(item.custo_fornecedor) || 0).toFixed(2).replace('.', ','));
        $('#modalTerceiroValor').val((parseFloat(item.valor_unitario) || 0).toFixed(2).replace('.', ','));
        $('#modalTerceiroContaPagar').prop('checked', !!item.gerar_conta_pagar);
        $('#modalTerceiroValorFechado').prop('checked', !!item.valor_fechado);
        $('#modalTerceiroVencimento').val(item.conta_vencimento || '');
        $('#modalTerceiroValorConta').val((parseFloat(item.conta_valor) || 0).toFixed(2).replace('.', ','));
        $('#modalTerceiroParcelas').val(parseInt(item.conta_parcelas) || 1);

        popularSalasModal('#modalTerceiroSala');
        $('#modalTerceiroSala').val(item.id_sala !== null && item.id_sala !== undefined ? String(item.id_sala) : '');

        $('#modalTerceiroCusto, #modalTerceiroValor, #modalTerceiroValorConta').mask('#.##0,00', {reverse: true});
        toggleCamposContaPagar();
        $('#modalProdutoTerceiro').modal('show');
    }
    
    // === TOGGLE CAMPOS CONTA A PAGAR ===
    function toggleCamposContaPagar() {
        if ($('#modalTerceiroContaPagar').is(':checked')) {
            $('#camposContaPagar').show();
            // Sincronizar valor com o custo
            var custo = parseFloat($('#modalTerceiroCusto').val().replace('.', '').replace(',', '.')) || 0;
            var qtd = parseInt($('#modalTerceiroQtd').val()) || 1;
            $('#modalTerceiroValorConta').val((custo * qtd).toFixed(2).replace('.', ','));
        } else {
            $('#camposContaPagar').hide();
        }
    }
    
    $('#modalTerceiroContaPagar').on('change', toggleCamposContaPagar);
    
    // Sincronizar valor da conta quando custo ou quantidade mudar
    $('#modalTerceiroCusto, #modalTerceiroQtd').on('change keyup', function() {
        if ($('#modalTerceiroContaPagar').is(':checked')) {
            var custo = parseFloat($('#modalTerceiroCusto').val().replace('.', '').replace(',', '.')) || 0;
            var qtd = parseInt($('#modalTerceiroQtd').val()) || 1;
            $('#modalTerceiroValorConta').val((custo * qtd).toFixed(2).replace('.', ','));
        }
    });

    // === CONFIRMAR PRODUTO DE TERCEIRO ===
    $('#btnConfirmarProdutoTerceiro').on('click', function() {
        var descricao = $('#modalTerceiroDescricao').val().trim();
        var editIndex = $('#modalTerceiroEditIndex').val();
        
        if (!descricao) {
            Swal.fire('Atenção', 'Informe a descrição do produto.', 'warning');
            return;
        }

        var dadosProduto = {
            id_produto: null,
            nome: descricao,
            foto_url: null,
            tipo_item: 'terceiro',
            quantidade: parseInt($('#modalTerceiroQtd').val()) || 1,
            valor_unitario: parseFloat($('#modalTerceiroValor').val().replace('.', '').replace(',', '.')) || 0,
            valor_fechado: $('#modalTerceiroValorFechado').is(':checked'),
            data_inicio: dataContratoInicio,
            data_fim: dataContratoFim,
            hora_inicio: horaContratoInicio,
            hora_fim: horaContratoFim,
            id_tabela_preco: null,
            id_sala: $('#modalTerceiroSala').val() || null,
            id_fornecedor: null,
            fornecedor_nome: '',
            custo_fornecedor: parseFloat($('#modalTerceiroCusto').val().replace('.', '').replace(',', '.')) || 0,
            gerar_conta_pagar: $('#modalTerceiroContaPagar').is(':checked'),
            // Dados da conta a pagar
            conta_vencimento: $('#modalTerceiroVencimento').val() || null,
            conta_valor: parseFloat($('#modalTerceiroValorConta').val().replace('.', '').replace(',', '.')) || 0,
            conta_parcelas: parseInt($('#modalTerceiroParcelas').val()) || 1,
            patrimonios: [],
            tabelas_preco: [],
            patrimonios_vinculados: []
        };

        if (editIndex !== '') {
            var itemExistente = produtosLocacao.find(p => p.index == editIndex && p.tipo_item === 'terceiro');
            if (!itemExistente) {
                Swal.fire('Erro', 'Produto de terceiro não encontrado para edição.', 'error');
                return;
            }
            Object.assign(itemExistente, dadosProduto);
        } else {
            dadosProduto.index = produtoIndex;
            produtosLocacao.push(dadosProduto);
            produtoIndex++;
        }
        
        $('#modalProdutoTerceiro').modal('hide');
        renderizarProdutosLocacao();
        calcularTotais();
        
        Swal.fire({
            icon: 'success',
            title: editIndex !== '' ? 'Produto de terceiro atualizado!' : 'Produto de terceiro adicionado!',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // === ABRIR MODAL SERVIÇO PRÓPRIO ===
    $('#btnAbrirModalServicoProprio').on('click', function() {
        // Limpar campos
        $('#modalServicoProprioEditIndex').val('');
        $('#modalServicoProprioTitulo').html('<i class="ti ti-settings me-2"></i>Adicionar Serviço Próprio');
        $('#modalServicoProprioBotao').text('Adicionar Serviço');
        $('#modalServicoProprioDescricao').val('');
        $('#modalServicoProprioQtd').val(1);
        $('#modalServicoProprioValor').val('0,00');
        
        // Popular salas
        popularSalasModal('#modalServicoProprioSala');

        // Máscara do valor
        $('#modalServicoProprioValor').mask('#.##0,00', {reverse: true});
        
        // Abrir modal
        $('#modalServicoProprio').modal('show');
    });

    // === CONFIRMAR SERVIÇO PRÓPRIO ===
    $('#btnConfirmarServicoProprio').on('click', function() {
        var descricao = $('#modalServicoProprioDescricao').val().trim();
        var editIndex = $('#modalServicoProprioEditIndex').val();
        
        if (!descricao) {
            Swal.fire('Atenção', 'Informe a descrição do serviço.', 'warning');
            return;
        }

        var dadosServico = {
            descricao: descricao,
            quantidade: parseInt($('#modalServicoProprioQtd').val()) || 1,
            valor_unitario: parseFloat($('#modalServicoProprioValor').val().replace('.', '').replace(',', '.')) || 0,
            id_sala: $('#modalServicoProprioSala').val() || null,
            tipo_item: 'proprio',
            id_fornecedor: null,
            fornecedor_nome: '',
            custo_fornecedor: 0,
            gerar_conta_pagar: false
        };

        if (editIndex !== '') {
            var servicoExistente = servicosLocacao.find(s => s.index == editIndex);
            if (!servicoExistente) {
                Swal.fire('Erro', 'Serviço não encontrado para edição.', 'error');
                return;
            }
            Object.assign(servicoExistente, dadosServico);
        } else {
            dadosServico.index = servicoIndex;
            servicosLocacao.push(dadosServico);
            servicoIndex++;
        }
        
        $('#modalServicoProprio').modal('hide');
        renderizarServicosLocacao();
        calcularTotais();
        
        Swal.fire({
            icon: 'success',
            title: editIndex !== '' ? 'Serviço atualizado!' : 'Serviço adicionado!',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // === ABRIR MODAL SERVIÇO DE TERCEIRO ===
    $('#btnAbrirModalServicoTerceiro').on('click', function() {
        // Limpar campos
        $('#modalServicoTerceiroEditIndex').val('');
        $('#modalServicoTerceiroTitulo').html('<i class="ti ti-users-group me-2"></i>Adicionar Serviço de Terceiro');
        $('#modalServicoTerceiroBotao').text('Adicionar Serviço');
        $('#modalServicoTerceiroDescricao').val('');
        $('#modalServicoTerceiroQtd').val(1);
        $('#modalServicoTerceiroCusto').val('0,00');
        $('#modalServicoTerceiroValor').val('0,00');
        $('#modalServicoTerceiroVencimento').val('');
        $('#modalServicoTerceiroValorConta').val('0,00');
        $('#modalServicoTerceiroParcelas').val(1);
        $('#modalServicoTerceiroContaPagar').prop('checked', true);
        
        // Popular salas
        popularSalasModal('#modalServicoTerceiroSala');

        // Reforçar máscara
        $('#modalServicoTerceiroCusto, #modalServicoTerceiroValor, #modalServicoTerceiroValorConta').mask('#.##0,00', {reverse: true});
        toggleCamposContaPagarServico();
        
        // Abrir modal
        $('#modalServicoTerceiro').modal('show');
    });

    function toggleCamposContaPagarServico() {
        if ($('#modalServicoTerceiroContaPagar').is(':checked')) {
            $('#camposContaPagarServico').show();
            var custo = parseFloat($('#modalServicoTerceiroCusto').val().replace('.', '').replace(',', '.')) || 0;
            var qtd = parseInt($('#modalServicoTerceiroQtd').val()) || 1;
            $('#modalServicoTerceiroValorConta').val((custo * qtd).toFixed(2).replace('.', ','));
        } else {
            $('#camposContaPagarServico').hide();
        }
    }

    $('#modalServicoTerceiroContaPagar').on('change', toggleCamposContaPagarServico);

    $('#modalServicoTerceiroCusto, #modalServicoTerceiroQtd').on('change keyup', function() {
        if ($('#modalServicoTerceiroContaPagar').is(':checked')) {
            var custo = parseFloat($('#modalServicoTerceiroCusto').val().replace('.', '').replace(',', '.')) || 0;
            var qtd = parseInt($('#modalServicoTerceiroQtd').val()) || 1;
            $('#modalServicoTerceiroValorConta').val((custo * qtd).toFixed(2).replace('.', ','));
        }
    });

    // === CONFIRMAR SERVIÇO DE TERCEIRO ===
    $('#btnConfirmarServicoTerceiro').on('click', function() {
        var descricao = $('#modalServicoTerceiroDescricao').val().trim();
        var editIndex = $('#modalServicoTerceiroEditIndex').val();
        
        if (!descricao) {
            Swal.fire('Atenção', 'Informe a descrição do serviço.', 'warning');
            return;
        }

        var dadosServico = {
            descricao: descricao,
            quantidade: parseInt($('#modalServicoTerceiroQtd').val()) || 1,
            valor_unitario: parseFloat($('#modalServicoTerceiroValor').val().replace('.', '').replace(',', '.')) || 0,
            id_sala: $('#modalServicoTerceiroSala').val() || null,
            tipo_item: 'terceiro',
            id_fornecedor: null,
            fornecedor_nome: '',
            custo_fornecedor: parseFloat($('#modalServicoTerceiroCusto').val().replace('.', '').replace(',', '.')) || 0,
            gerar_conta_pagar: $('#modalServicoTerceiroContaPagar').is(':checked'),
            conta_vencimento: $('#modalServicoTerceiroVencimento').val() || null,
            conta_valor: parseFloat($('#modalServicoTerceiroValorConta').val().replace('.', '').replace(',', '.')) || 0,
            conta_parcelas: parseInt($('#modalServicoTerceiroParcelas').val()) || 1
        };

        if (editIndex !== '') {
            var servicoExistente = servicosLocacao.find(s => s.index == editIndex);
            if (!servicoExistente) {
                Swal.fire('Erro', 'Serviço não encontrado para edição.', 'error');
                return;
            }
            Object.assign(servicoExistente, dadosServico);
        } else {
            dadosServico.index = servicoIndex;
            servicosLocacao.push(dadosServico);
            servicoIndex++;
        }
        
        $('#modalServicoTerceiro').modal('hide');
        renderizarServicosLocacao();
        calcularTotais();
        
        Swal.fire({
            icon: 'success',
            title: editIndex !== '' ? 'Serviço de terceiro atualizado!' : 'Serviço de terceiro adicionado!',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // === POPULAR SALAS NOS MODAIS ===
    function popularSalasModal(seletorSelect) {
        var $select = $(seletorSelect);
        $select.empty().append('<option value="">Sem sala</option>');
        
        if (salasLocacao.length > 0) {
            salasLocacao.forEach(function(sala) {
                $select.append('<option value="' + sala.index + '">' + sala.nome + '</option>');
            });
        }
    }

    // === RENDERIZAR PRODUTOS NA LOCAÇÃO ===
    function renderizarProdutosLocacao() {
        var container = $('#listaProdutosLocacao');
        container.empty();
        
        console.log('Renderizando produtos:', produtosLocacao.length, 'produtos');
        
        if (produtosLocacao.length === 0) {
            container.html(`
                <div id="semProdutos" class="text-center py-4 text-muted border rounded">
                    <i class="ti ti-package-off ti-lg d-block mb-2"></i>
                    Nenhum produto adicionado à locação
                </div>
            `);
            $('#qtdProdutosLocacao').text(0);
            $('#infoTerceiros').hide();
            $('#custoTerceirosInfo').hide();
            atualizarBloqueioLocacaoPorHora();
            return;
        }
        
        // Agrupar por sala se houver salas
        var produtosSemSala = produtosLocacao.filter(function(p) {
            return p.id_sala === '' || p.id_sala === null || p.id_sala === undefined;
        });
        var produtosPorSala = {};
        var qtdTerceiros = 0;
        var custoTotalTerceiros = 0;
        
        produtosLocacao.forEach(function(item) {
            if (item.tipo_item === 'terceiro') {
                qtdTerceiros++;
                custoTotalTerceiros += (parseFloat(item.custo_fornecedor) || 0) * (parseInt(item.quantidade) || 1);
            }
            if (item.id_sala !== '' && item.id_sala !== null && item.id_sala !== undefined) {
                if (!produtosPorSala[item.id_sala]) {
                    produtosPorSala[item.id_sala] = [];
                }
                produtosPorSala[item.id_sala].push(item);
            }
        });
        
        console.log('Produtos sem sala:', produtosSemSala.length);
        
        // Renderizar produtos sem sala
        if (produtosSemSala.length > 0) {
            produtosSemSala.forEach(function(item) {
                try {
                    var cardHtml = criarCardProduto(item);
                    container.append(cardHtml);
                } catch (e) {
                    console.error('Erro ao criar card do produto:', e, item);
                }
            });
        }
        
        // Renderizar produtos por sala
        salasLocacao.forEach(function(sala) {
            var produtosDaSala = produtosPorSala[sala.index] || [];
            if (produtosDaSala.length > 0) {
                container.append(`
                    <div class="border-start border-3 border-${sala.cor} ps-3 mb-3">
                        <h6 class="text-${sala.cor} mb-2"><i class="ti ti-layout me-1"></i>${sala.nome}</h6>
                        <div class="produtos-sala-${sala.index}">
                        </div>
                    </div>
                `);
                produtosDaSala.forEach(function(item) {
                    container.find(`.produtos-sala-${sala.index}`).append(criarCardProduto(item));
                });
            }
        });
        
        // Aplicar máscaras
        container.find('.money').mask('#.##0,00', {reverse: true});
        
        // Bind eventos
        bindEventosProdutosLocacao();
        
        // Atualizar contadores
        $('#qtdProdutosLocacao').text(produtosLocacao.length);
        atualizarBloqueioLocacaoPorHora();
        
        if (qtdTerceiros > 0) {
            $('#infoTerceiros').show().find('#qtdTerceiros').text(qtdTerceiros);
            $('#custoTerceirosInfo').show().find('#custoTotalTerceiros').text('R$ ' + custoTotalTerceiros.toFixed(2).replace('.', ','));
        } else {
            $('#infoTerceiros').hide();
            $('#custoTerceirosInfo').hide();
        }
    }

    // === CRIAR CARD DE PRODUTO (SIMPLIFICADO) ===
    function criarCardProduto(item) {
        // Garantir valores seguros
        item.quantidade = parseInt(item.quantidade) || 1;
        item.valor_unitario = parseFloat(item.valor_unitario) || 0;
        item.patrimonios = item.patrimonios || [];
        item.patrimonios_vinculados = item.patrimonios_vinculados || [];
        item.tabelas_preco = item.tabelas_preco || [];
        item.hora_inicio = item.hora_inicio || horaContratoInicio || '08:00';
        item.hora_fim = item.hora_fim || horaContratoFim || '18:00';
        item.data_inicio = item.data_inicio || dataContratoInicio || '';
        item.data_fim = item.data_fim || dataContratoFim || '';
        
        var fotoHtml = item.foto_url 
            ? `<img src="${item.foto_url}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">`
            : `<div class="d-flex align-items-center justify-content-center rounded" style="width: 50px; height: 50px; font-size: 9px; text-align: center; line-height: 1.2; background-color: #6c757d; color: #fff;">Sem<br>Foto</div>`;
        
        var tipoLabel = item.tipo_item === 'terceiro' 
            ? `<span class="badge bg-warning me-1">Terceiro</span>`
            : '';
        
        var valorFechadoBadge = item.valor_fechado 
            ? `<span class="badge bg-label-info me-1">Fechado</span>`
            : '';
            
        var salaInfo = '';
        if (item.id_sala !== '' && item.id_sala !== null && item.id_sala !== undefined) {
            var sala = salasLocacao.find(function(s) { return s.index == item.id_sala; });
            if (sala) {
                salaInfo = `<span class="badge bg-label-${sala.cor} me-1">${sala.nome}</span>`;
            }
        }
        
        var subtotal = calcularSubtotalItem(item);
        var dias = calcularDiasItem(item);
        var unidadePeriodoItem = isLocacaoPorHora() ? 'hora(s)' : 'dia(s)';
        var itemRetornado = isProdutoRetornado(item);
        
        // Botão de patrimônios
        var btnPatrimonio = '';
        if (item.patrimonios && item.patrimonios.length > 0) {
            var corBtn = item.patrimonios_vinculados.length >= item.quantidade ? 'success' : 'warning';
            btnPatrimonio = `
                <button type="button" class="btn btn-sm btn-outline-${corBtn} btn-vincular-patrimonios" data-index="${item.index}" title="Vincular patrimônios">
                    <i class="ti ti-qrcode me-1"></i>${item.patrimonios_vinculados.length}/${item.quantidade}
                </button>`;
        }
        
        // Resumo de valores
        var resumoValor = item.valor_fechado 
            ? `${item.quantidade}x R$ ${item.valor_unitario.toFixed(2).replace('.', ',')}`
            : `${item.quantidade}x R$ ${item.valor_unitario.toFixed(2).replace('.', ',')} x ${dias} ${unidadePeriodoItem}`;
        
        // Período formatado
        var periodoFormatado = '';
        if (item.data_inicio && item.data_fim) {
            periodoFormatado = `${formatarDataBrSafe(item.data_inicio)} até ${formatarDataBrSafe(item.data_fim)}`;
        }
        
        var template = `
            <div class="card produto-card mb-2 shadow-sm ${item.tipo_item === 'terceiro' ? 'border-warning border-2' : 'border'}" data-index="${item.index}">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center gap-3">
                        <!-- Foto -->
                        <div class="produto-foto flex-shrink-0">${fotoHtml}</div>
                        
                        <!-- Info -->
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${item.nome}</h6>
                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                        ${tipoLabel}
                                        ${valorFechadoBadge}
                                        ${salaInfo}
                                    </div>
                                    <small class="text-muted d-block">${resumoValor}</small>
                                    ${periodoFormatado ? `<small class="text-muted d-block"><i class="ti ti-calendar ti-xs me-1"></i>${periodoFormatado}</small>` : ''}
                                </div>
                                <div class="text-end">
                                    <h5 class="mb-2 text-primary subtotal-item">R$ ${subtotal.toFixed(2).replace('.', ',')}</h5>
                                    <div class="btn-group">
                                        ${itemRetornado ? '' : `
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-editar-produto" data-index="${item.index}" title="Editar produto">
                                            <i class="ti ti-pencil"></i>
                                        </button>`}
                                        ${btnPatrimonio}
                                        ${itemRetornado ? '' : `
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remover-produto" data-index="${item.index}" title="Remover">
                                            <i class="ti ti-trash"></i>
                                        </button>`}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${item.tipo_item === 'terceiro' && item.gerar_conta_pagar ? `
                        <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                            <i class="ti ti-receipt me-1"></i>Conta a pagar:
                            ${(parseInt(item.conta_parcelas) || 1) > 1 ? (parseInt(item.conta_parcelas) || 1) + 'x ' : ''}
                            R$ ${(parseFloat(item.conta_valor) || 0).toFixed(2).replace('.', ',')}
                            ${item.conta_vencimento ? ' • Venc.: ' + formatarDataBrSafe(item.conta_vencimento) : ''}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        return template;
    }

    function isProdutoRetornado(item) {
        if (!item) return false;

        if (Number(item.estoque_status || 0) === 2) {
            return true;
        }

        var statusRetorno = String(item.status_retorno || '').trim().toLowerCase();
        if (statusRetorno && statusRetorno !== 'pendente') {
            return true;
        }

        var statusLocacao = String(item.status_locacao || '').trim().toLowerCase();
        if (statusLocacao === 'retornado' || statusLocacao === 'devolvido') {
            return true;
        }

        return item.retornado === true || Number(item.retornado || 0) === 1;
    }

    // === CALCULAR SUBTOTAL DO ITEM ===
    function calcularSubtotalItem(item) {
        var dias = calcularDiasItem(item);
        var subtotal;
        
        if (item.valor_fechado) {
            // Valor fechado: não multiplica pelos dias
            subtotal = item.quantidade * item.valor_unitario;
        } else {
            // Valor diária: multiplica pelos dias
            subtotal = item.quantidade * item.valor_unitario * dias;
        }
        
        return subtotal > 0 ? subtotal : 0;
    }

    // === CALCULAR DIAS DO ITEM ===
    function calcularDiasItem(item) {
        if (item.data_inicio && item.data_fim) {
            if (isLocacaoPorHora()) {
                return calcularHorasPeriodo(item.data_inicio, item.hora_inicio, item.data_fim, item.hora_fim);
            }
            return calcularDiasInclusivo(item.data_inicio, item.data_fim);
        }
        return totalDias;
    }

    // === BIND EVENTOS DOS PRODUTOS NA LOCAÇÃO (SIMPLIFICADO) ===
    function bindEventosProdutosLocacao() {
        // Remover produto
        $('.btn-remover-produto').off('click').on('click', function() {
            var index = $(this).data('index');
            var item = produtosLocacao.find(p => p.index == index);
            if (isProdutoRetornado(item)) {
                Swal.fire('Atenção', 'Produto retornado não pode ser removido.', 'info');
                return;
            }
            Swal.fire({
                title: 'Remover produto?',
                text: 'Deseja realmente remover este produto da locação?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    produtosLocacao = produtosLocacao.filter(p => p.index != index);
                    renderizarProdutosLocacao();
                    calcularTotais();
                }
            });
        });
        
        // Editar produto (abrir modal)
        $('.btn-editar-produto').off('click').on('click', function() {
            var index = $(this).data('index');
            var item = produtosLocacao.find(p => p.index == index);
            if (isProdutoRetornado(item)) {
                Swal.fire('Atenção', 'Produto retornado não pode ser editado.', 'info');
                return;
            }
            if (item && item.tipo_item === 'terceiro') {
                abrirModalProdutoTerceiroParaEdicao(index);
                return;
            }
            abrirModalEditarProduto(index);
        });
        
        // Vincular patrimônios
        $('.btn-vincular-patrimonios').off('click').on('click', function() {
            var index = $(this).data('index');
            abrirModalPatrimonios(index);
        });
    }

    // === MODAL EDITAR PRODUTO ===
    function abrirModalEditarProduto(index) {
        var item = produtosLocacao.find(p => p.index == index);
        if (!item) {
            Swal.fire('Erro', 'Produto não encontrado.', 'error');
            return;
        }
        var isProdutoProprio = item.tipo_item !== 'terceiro' && !!item.id_produto;
        
        // Preencher dados do produto
        $('#modalEditProdutoIndex').val(index);
        $('#modalEditProdutoNome').text(item.nome);
        
        // Foto
        if (item.foto_url) {
            $('#modalEditProdutoFoto').html('<img src="' + item.foto_url + '" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">');
        } else {
            $('#modalEditProdutoFoto').html('<div class="avatar avatar-lg"><span class="avatar-initial rounded bg-label-primary"><i class="ti ti-package"></i></span></div>');
        }
        
        // Valores
        $('#modalEditQtd').val(item.quantidade);
        $('#modalEditValor').val(item.valor_unitario.toFixed(2).replace('.', ','));
        $('#modalEditValorFechado').prop('checked', item.valor_fechado);
        $('#modalEditDataInicio').val(item.data_inicio || dataContratoInicio || '');
        $('#modalEditDataFim').val(item.data_fim || dataContratoFim || '');
        $('#modalEditHoraInicio').val(item.hora_inicio || horaContratoInicio || '08:00');
        $('#modalEditHoraFim').val(item.hora_fim || horaContratoFim || '18:00');
        atualizarLimitesDatasProdutosNosModais();
        
        // Salas
        var $selectSala = $('#modalEditSala');
        $selectSala.empty().append('<option value="">Sem sala</option>');
        salasLocacao.forEach(function(s) {
            var selected = s.index == item.id_sala ? 'selected' : '';
            $selectSala.append('<option value="' + s.index + '" ' + selected + '>' + s.nome + '</option>');
        });
        
        // Guardar dados do item no modal
        $('#modalEditarProduto').data('item', item);
        $('#btnVerDetalhesEstoqueEdit').data('produto-id', isProdutoProprio ? item.id_produto : null);

        if (isProdutoProprio) {
            $('#cardEditPeriodoProduto').removeClass('d-none');
        } else {
            $('#cardEditPeriodoProduto').addClass('d-none');
            $('#modalEditDisponibilidadeInfo').removeClass('alert-success alert-danger alert-warning').addClass('alert-secondary').html('<i class="ti ti-info-circle me-1"></i>Sem cálculo de estoque para produto de terceiro.');
        }

        var itemPatrimonial = isProdutoProprio && item.patrimonios && item.patrimonios.length > 0;
        if (itemPatrimonial) {
            $('#modalEditQtd').val(1).attr('min', 1).attr('max', 1).prop('readonly', true);
        } else {
            $('#modalEditQtd').attr('min', 1).prop('readonly', false);
        }
        
        // Aplicar máscara money
        $('#modalEditValor').mask('#.##0,00', {reverse: true});
        
        // Mostrar modal
        $('#modalEditarProduto').modal('show');
        
        // Verificar disponibilidade inicial
        if (isProdutoProprio) {
            verificarDisponibilidadeModalEdit();
        }
    }

    // === VERIFICAR DISPONIBILIDADE NO MODAL DE EDIÇÃO ===
    function verificarDisponibilidadeModalEdit() {
        var item = $('#modalEditarProduto').data('item');
        if (!item || !item.id_produto) return;
        
        var dataInicio = $('#modalEditDataInicio').val() || item.data_inicio || dataContratoInicio;
        var dataFim = $('#modalEditDataFim').val() || item.data_fim || dataContratoFim;
        var horaInicio = $('#modalEditHoraInicio').val() || item.hora_inicio || horaContratoInicio || '08:00';
        var horaFim = $('#modalEditHoraFim').val() || item.hora_fim || horaContratoFim || '18:00';
        
        if (!dataInicio || !dataFim) {
            $('#modalEditDisponibilidadeInfo').removeClass('alert-success alert-danger alert-warning').addClass('alert-secondary').html('<i class="ti ti-clock me-1"></i>Defina o período do contrato para verificar a disponibilidade.');
            return;
        }

        var validacaoPeriodo = validarPeriodoModalBusca({
            data_inicio: dataInicio,
            data_fim: dataFim,
            hora_inicio: horaInicio,
            hora_fim: horaFim
        });
        if (!validacaoPeriodo.ok) {
            $('#modalEditDisponibilidadeInfo')
                .removeClass('alert-secondary alert-success alert-warning')
                .addClass('alert-danger')
                .html('<i class="ti ti-alert-triangle me-1"></i>' + validacaoPeriodo.mensagem);
            return;
        }
        
        $('#modalEditDisponibilidadeInfo').html('<i class="ti ti-loader ti-spin me-1"></i>Verificando disponibilidade...');
        
        $.ajax({
            url: '{{ route("locacoes.verificar-disponibilidade") }}',
            method: 'GET',
            data: {
                id_produto: item.id_produto,
                data_inicio: dataInicio,
                data_fim: dataFim,
                hora_inicio: horaInicio,
                hora_fim: horaFim,
                preferencia_estoque: getPreferenciaEstoque()
            },
            success: function(data) {
                var qtdDisp = data.disponivel || 0;
                var estoqueTotal = data.estoque_total || 0;
                var reservado = data.reservado || 0;
                var emLocacao = data.em_locacao || 0;
                var comprometido = reservado + emLocacao;
                var alertClass = qtdDisp > 0 ? 'alert-success' : (isLocacaoAprovada() ? 'alert-danger' : 'alert-warning');

                var html = '<div class="d-flex flex-column gap-2 mt-2">';
                html += '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">';
                html += '<div class="fw-semibold ' + (qtdDisp > 0 ? 'text-success' : 'text-danger') + '">';
                html += '<i class="ti ti-' + (qtdDisp > 0 ? 'check' : 'x') + ' me-1"></i>';
                html += qtdDisp + ' disponível(eis) no período selecionado';
                html += '</div>';
                html += '<span class="badge bg-label-' + (qtdDisp > 0 ? 'success' : 'danger') + '">Disponível: ' + qtdDisp + '</span>';
                html += '</div>';

                html += '<div class="small p-2 bg-light rounded mt-1">';
                html += '<div class="fw-semibold mb-2"><i class="ti ti-calculator me-1"></i>Resumo do estoque</div>';
                html += '<div class="row g-2">';
                html += '<div class="col-md-4"><div class="rounded p-2 text-center bg-label-primary"><div class="text-primary">Estoque total</div><div class="fw-bold text-primary fs-5">' + estoqueTotal + '</div></div></div>';
                html += '<div class="col-md-4"><div class="rounded p-2 text-center bg-label-warning"><div class="text-warning">Comprometido</div><div class="fw-bold text-warning fs-5">' + comprometido + '</div></div></div>';
                html += '<div class="col-md-4"><div class="rounded p-2 text-center bg-label-success"><div class="text-success">Disponível</div><div class="fw-bold text-success fs-5">' + qtdDisp + '</div></div></div>';
                html += '</div>';
                html += '</div>';

                if (data.conflitos && data.conflitos.length > 0) {
                    html += '<div class="small mt-1">';
                    html += '<div class="fw-semibold text-danger mb-1"><i class="ti ti-alert-triangle me-1"></i>' + data.conflitos.length + ' conflito(s) no período</div>';
                    html += '<ul class="list-group list-group-flush border rounded">';
                    data.conflitos.forEach(function(c) {
                        html += '<li class="list-group-item px-2 py-1">';
                        html += '<span class="fw-semibold">Contrato #' + (c.numero_contrato || c.id_locacao) + '</span>';
                        html += ' <span class="text-muted">(' + (c.status || '-') + ')</span>';
                        html += ' • Qtd: <span class="fw-semibold">' + c.quantidade + '</span>';
                        if (c.periodo) html += '<br><span class="text-muted">' + c.periodo + '</span>';
                        html += '</li>';
                    });
                    html += '</ul></div>';
                }

                html += '</div>';

                $('#modalEditDisponibilidadeInfo').removeClass('alert-secondary alert-success alert-danger alert-warning').addClass(alertClass).html(html);
                aplicarLimiteQuantidadeModalEdit(qtdDisp);
            },
            error: function() {
                $('#modalEditDisponibilidadeInfo').html('<i class="ti ti-alert-triangle me-1"></i>Erro ao verificar disponibilidade.');
            }
        });
    }

    $('#btnVerDetalhesEstoqueEdit').on('click', function() {
        var idProduto = $(this).data('produto-id');
        if (idProduto) {
            mostrarDetalhesEstoque(idProduto);
        }
    });

    $('#modalEditDataInicio, #modalEditHoraInicio, #modalEditDataFim, #modalEditHoraFim').on('change', function() {
        verificarDisponibilidadeModalEdit();
    });

    // === CONFIRMAR EDIÇÃO DO PRODUTO ===
    $('#btnConfirmarEditarProduto').on('click', function() {
        var index = $('#modalEditProdutoIndex').val();
        var item = produtosLocacao.find(p => p.index == index);
        
        if (!item) {
            Swal.fire('Erro', 'Produto não encontrado.', 'error');
            return;
        }
        
        var quantidadeEdit = parseInt($('#modalEditQtd').val()) || 1;
        var maxDisponivelEdit = parseInt($('#modalEditQtd').data('max-disponivel'));
        var dataInicioEdit = $('#modalEditDataInicio').val() || item.data_inicio || dataContratoInicio;
        var dataFimEdit = $('#modalEditDataFim').val() || item.data_fim || dataContratoFim;
        var horaInicioEdit = $('#modalEditHoraInicio').val() || item.hora_inicio || horaContratoInicio || '08:00';
        var horaFimEdit = $('#modalEditHoraFim').val() || item.hora_fim || horaContratoFim || '18:00';

        var validacaoPeriodoItem = validarPeriodoModalBusca({
            data_inicio: dataInicioEdit,
            data_fim: dataFimEdit,
            hora_inicio: horaInicioEdit,
            hora_fim: horaFimEdit
        });
        if (!validacaoPeriodoItem.ok) {
            Swal.fire('Período inválido', validacaoPeriodoItem.mensagem, 'warning');
            return;
        }

        if (isNaN(maxDisponivelEdit)) {
            maxDisponivelEdit = parseInt($('#modalEditQtd').attr('max')) || quantidadeEdit;
        }

        if (isLocacaoAprovada() && !isNaN(maxDisponivelEdit) && quantidadeEdit > maxDisponivelEdit) {
            Swal.fire('Estoque insuficiente', 'A quantidade informada ultrapassa o disponível para o período selecionado.', 'warning');
            return;
        }

        if (item.tipo_item !== 'terceiro' && item.patrimonios && item.patrimonios.length > 0 && quantidadeEdit !== 1) {
            Swal.fire('Atenção', 'Itens patrimoniais são exibidos individualmente. Para aumentar, adicione novas linhas do produto.', 'warning');
            return;
        }

        // Atualizar dados
        item.quantidade = quantidadeEdit;
        item.valor_unitario = parseFloat($('#modalEditValor').val().replace('.', '').replace(',', '.')) || 0;
        item.valor_fechado = $('#modalEditValorFechado').is(':checked');
        item.id_sala = $('#modalEditSala').val() || null;
        item.data_inicio = dataInicioEdit;
        item.data_fim = dataFimEdit;
        item.hora_inicio = horaInicioEdit;
        item.hora_fim = horaFimEdit;
        
        // Se diminuiu a quantidade, remover patrimônios excedentes
        if (item.patrimonios_vinculados && item.patrimonios_vinculados.length > item.quantidade) {
            item.patrimonios_vinculados = item.patrimonios_vinculados.slice(0, item.quantidade);
        }
        
        // Fechar modal
        $('#modalEditarProduto').modal('hide');
        
        renderizarProdutosLocacao();
        calcularTotais();
        
        Swal.fire({
            icon: 'success',
            title: 'Produto atualizado!',
            timer: 1500,
            showConfirmButton: false
        });
    });

    // === SERVIÇOS ===
    $('#btnAdicionarServico').on('click', function() {
        var descricao = $('#descricaoServicoProprio').val().trim();
        var quantidade = parseInt($('#qtdServicoProprio').val()) || 1;
        var valorUnit = parseFloat($('#valorServicoProprio').val().replace('.', '').replace(',', '.')) || 0;
        var idSala = $('#salaServicoProprio').val() || null;
        
        if (!descricao) {
            Swal.fire('Atenção', 'Informe a descrição do serviço.', 'warning');
            return;
        }
        
        var servico = {
            index: servicoIndex,
            descricao: descricao,
            tipo_item: 'proprio',
            quantidade: quantidade,
            valor_unitario: valorUnit,
            id_sala: idSala,
            id_fornecedor: null,
            fornecedor_nome: null,
            custo_fornecedor: 0,
            gerar_conta_pagar: false
        };
        
        servicosLocacao.push(servico);
        servicoIndex++;
        
        // Limpar campos
        $('#descricaoServicoProprio').val('');
        $('#qtdServicoProprio').val(1);
        $('#valorServicoProprio').val('0,00');
        
        renderizarServicosLocacao();
        calcularTotais();
    });

    $('#btnAdicionarServicoTerceiro').on('click', function() {
        var idFornecedor = $('#selectFornecedorServico').val();
        var descricao = $('#descricaoServicoTerceiro').val().trim();
        var quantidade = parseInt($('#qtdServicoTerceiro').val()) || 1;
        var custoUnit = parseFloat($('#custoServicoTerceiro').val().replace('.', '').replace(',', '.')) || 0;
        var valorUnit = parseFloat($('#valorServicoTerceiro').val().replace('.', '').replace(',', '.')) || 0;
        var gerarContaPagar = $('#gerarContaPagarServicoTerceiro').is(':checked');
        
        if (!idFornecedor) {
            Swal.fire('Atenção', 'Selecione um fornecedor.', 'warning');
            return;
        }
        
        if (!descricao) {
            Swal.fire('Atenção', 'Informe a descrição do serviço.', 'warning');
            return;
        }
        
        var fornecedorNome = $('#selectFornecedorServico').select2('data')[0].text.split(' - ')[0];
        
        var servico = {
            index: servicoIndex,
            descricao: descricao,
            tipo_item: 'terceiro',
            quantidade: quantidade,
            valor_unitario: valorUnit,
            id_sala: null,
            id_fornecedor: idFornecedor,
            fornecedor_nome: fornecedorNome,
            custo_fornecedor: custoUnit,
            gerar_conta_pagar: gerarContaPagar
        };
        
        servicosLocacao.push(servico);
        servicoIndex++;
        
        // Limpar campos
        $('#selectFornecedorServico').val(null).trigger('change');
        $('#descricaoServicoTerceiro').val('');
        $('#qtdServicoTerceiro').val(1);
        $('#custoServicoTerceiro').val('0,00');
        $('#valorServicoTerceiro').val('0,00');
        
        renderizarServicosLocacao();
        calcularTotais();
    });

    function renderizarServicosLocacao() {
        var $tbody = $('#servicosBody');
        $tbody.empty();
        
        if (servicosLocacao.length === 0) {
            return;
        }
        
        var grupos = {};
        var ordemGrupos = [];

        servicosLocacao.forEach(function(servico) {
            var chaveGrupo = (servico.id_sala !== '' && servico.id_sala !== null && servico.id_sala !== undefined)
                ? String(servico.id_sala)
                : '__sem_sala__';

            if (!grupos[chaveGrupo]) {
                grupos[chaveGrupo] = [];
                ordemGrupos.push(chaveGrupo);
            }

            grupos[chaveGrupo].push(servico);
        });

        ordemGrupos.forEach(function(chaveGrupo) {
            var salaLabel = 'Sem sala';
            var salaClass = 'secondary';

            if (chaveGrupo !== '__sem_sala__') {
                var sala = salasLocacao.find(s => String(s.index) == String(chaveGrupo));
                if (sala) {
                    salaLabel = sala.nome;
                    salaClass = sala.cor || 'secondary';
                } else {
                    salaLabel = 'Sala ' + chaveGrupo;
                }
            }

            $tbody.append(`
                <tr class="table-light">
                    <td colspan="6" class="fw-semibold text-${salaClass}">
                        <i class="ti ti-layout me-1"></i>${salaLabel}
                    </td>
                </tr>
            `);

            grupos[chaveGrupo].forEach(function(servico) {
                var tipoIcon = servico.tipo_item === 'terceiro'
                    ? '<span class="badge bg-warning"><i class="ti ti-users ti-xs"></i></span>'
                    : '<span class="badge bg-primary"><i class="ti ti-home ti-xs"></i></span>';
            
                var subtotal = servico.quantidade * servico.valor_unitario;
            
                var row = `
                    <tr class="servico-row" data-index="${servico.index}">
                        <td>${tipoIcon}</td>
                        <td>
                            ${servico.descricao}
                            ${servico.gerar_conta_pagar ? `<small class="text-warning"><i class="ti ti-receipt ti-xs"></i> Conta a pagar: ${(servico.conta_parcelas > 1 ? servico.conta_parcelas + 'x ' : '')}R$ ${(parseFloat(servico.conta_valor) || 0).toFixed(2).replace('.', ',')}${servico.conta_vencimento ? ' • Venc.: ' + formatarDataBrSafe(servico.conta_vencimento) : ''}</small>` : ''}
                            <input type="hidden" name="servicos[${servico.index}][descricao]" value="${servico.descricao}">
                            <input type="hidden" name="servicos[${servico.index}][tipo_item]" value="${servico.tipo_item}">
                            <input type="hidden" name="servicos[${servico.index}][id_fornecedor]" value="${servico.id_fornecedor || ''}">
                            <input type="hidden" name="servicos[${servico.index}][custo_fornecedor]" value="${servico.custo_fornecedor}">
                            <input type="hidden" name="servicos[${servico.index}][gerar_conta_pagar]" value="${servico.gerar_conta_pagar ? '1' : '0'}">
                            <input type="hidden" name="servicos[${servico.index}][conta_vencimento]" value="${servico.conta_vencimento || ''}">
                            <input type="hidden" name="servicos[${servico.index}][conta_valor]" value="${servico.conta_valor || 0}">
                            <input type="hidden" name="servicos[${servico.index}][conta_parcelas]" value="${servico.conta_parcelas || 1}">
                            <input type="hidden" name="servicos[${servico.index}][id_sala]" value="${servico.id_sala !== null && servico.id_sala !== undefined ? servico.id_sala : ''}">
                        </td>
                        <td>
                            ${servico.quantidade}
                            <input type="hidden" name="servicos[${servico.index}][quantidade]" value="${servico.quantidade}">
                        </td>
                        <td>
                            R$ ${servico.valor_unitario.toFixed(2).replace('.', ',')}
                            <input type="hidden" name="servicos[${servico.index}][valor_unitario]" value="${servico.valor_unitario.toFixed(2).replace('.', ',')}">
                        </td>
                        <td><strong>R$ ${subtotal.toFixed(2).replace('.', ',')}</strong></td>
                        <td>
                            <button type="button" class="btn btn-xs btn-outline-primary btn-editar-servico" data-index="${servico.index}" title="Editar">
                                <i class="ti ti-pencil ti-xs"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger btn-remover-servico" data-index="${servico.index}">
                                <i class="ti ti-trash ti-xs"></i>
                            </button>
                        </td>
                    </tr>
                `;

                $tbody.append(row);
            });
        });

        $('.btn-editar-servico').off('click').on('click', function() {
            var index = $(this).data('index');
            var servico = servicosLocacao.find(s => s.index == index);
            if (!servico) return;

            if (servico.tipo_item === 'terceiro') {
                $('#modalServicoTerceiroEditIndex').val(servico.index);
                $('#modalServicoTerceiroTitulo').html('<i class="ti ti-users-group me-2"></i>Editar Serviço de Terceiro');
                $('#modalServicoTerceiroBotao').text('Salvar Alterações');
                $('#modalServicoTerceiroDescricao').val(servico.descricao || '');
                $('#modalServicoTerceiroQtd').val(servico.quantidade || 1);
                $('#modalServicoTerceiroCusto').val((parseFloat(servico.custo_fornecedor) || 0).toFixed(2).replace('.', ','));
                $('#modalServicoTerceiroValor').val((parseFloat(servico.valor_unitario) || 0).toFixed(2).replace('.', ','));
                $('#modalServicoTerceiroContaPagar').prop('checked', !!servico.gerar_conta_pagar);
                $('#modalServicoTerceiroVencimento').val(servico.conta_vencimento || '');
                $('#modalServicoTerceiroValorConta').val((parseFloat(servico.conta_valor) || 0).toFixed(2).replace('.', ','));
                $('#modalServicoTerceiroParcelas').val(parseInt(servico.conta_parcelas) || 1);
                popularSalasModal('#modalServicoTerceiroSala');
                $('#modalServicoTerceiroSala').val(servico.id_sala !== null && servico.id_sala !== undefined ? String(servico.id_sala) : '');
                $('#modalServicoTerceiroCusto, #modalServicoTerceiroValor, #modalServicoTerceiroValorConta').mask('#.##0,00', {reverse: true});
                toggleCamposContaPagarServico();
                $('#modalServicoTerceiro').modal('show');
                return;
            }

            $('#modalServicoProprioEditIndex').val(servico.index);
            $('#modalServicoProprioTitulo').html('<i class="ti ti-settings me-2"></i>Editar Serviço Próprio');
            $('#modalServicoProprioBotao').text('Salvar Alterações');
            $('#modalServicoProprioDescricao').val(servico.descricao || '');
            $('#modalServicoProprioQtd').val(servico.quantidade || 1);
            $('#modalServicoProprioValor').val((parseFloat(servico.valor_unitario) || 0).toFixed(2).replace('.', ','));
            popularSalasModal('#modalServicoProprioSala');
            $('#modalServicoProprioSala').val(servico.id_sala !== null && servico.id_sala !== undefined ? String(servico.id_sala) : '');
            $('#modalServicoProprioValor').mask('#.##0,00', {reverse: true});
            $('#modalServicoProprio').modal('show');
        });
        
        // Bind evento remover
        $('.btn-remover-servico').off('click').on('click', function() {
            var index = $(this).data('index');
            servicosLocacao = servicosLocacao.filter(s => s.index != index);
            renderizarServicosLocacao();
            calcularTotais();
        });
    }

    // === ATUALIZAR SUBTOTAL DE UM CARD ===
    function atualizarSubtotalCard(index) {
        var item = produtosLocacao.find(p => p.index == index);
        if (item) {
            var subtotal = calcularSubtotalItem(item);
            $(`.produto-card[data-index="${index}"] .subtotal-item`).text('R$ ' + subtotal.toFixed(2).replace('.', ','));
        }
    }

    function normalizarStatusLocacao(status) {
        return String(status || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim()
            .toLowerCase();
    }

    function normalizarLabelPatrimonio(valor) {
        return String(valor || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function obterPatrimoniosBloqueadosNoPeriodo(item, callback) {
        if (!item || !item.id_produto || !item.data_inicio || !item.data_fim) {
            callback([]);
            return;
        }

        $.ajax({
            url: '{{ route("locacoes.verificar-disponibilidade") }}',
            method: 'GET',
            data: {
                id_produto: item.id_produto,
                data_inicio: item.data_inicio,
                data_fim: item.data_fim,
                hora_inicio: item.hora_inicio || horaContratoInicio || '08:00',
                hora_fim: item.hora_fim || horaContratoFim || '18:00',
                preferencia_estoque: getPreferenciaEstoque()
            },
            success: function(data) {
                var bloqueados = [];
                (data.conflitos || []).forEach(function(c) {
                    if (c && c.patrimonio) {
                        bloqueados.push(normalizarLabelPatrimonio(c.patrimonio));
                    }
                });
                callback(Array.from(new Set(bloqueados)));
            },
            error: function() {
                callback([]);
            }
        });
    }

    function montarHtmlPatrimoniosModal(item, patrimoniosUsadosOutros, patrimoniosBloqueadosPeriodo) {
        var html = '';
        for (var i = 0; i < item.quantidade; i++) {
            var selectedId = item.patrimonios_vinculados[i] || '';
            html += `
                <div class="mb-2">
                    <label class="form-label small">Patrimônio ${i + 1}</label>
                    <select class="form-select select-patrimonio-modal" data-posicao="${i}">
                        <option value="">Selecione...</option>
                        ${item.patrimonios.map(p => {
                            var usadoNesteProduto = item.patrimonios_vinculados.includes(p.id_patrimonio) && item.patrimonios_vinculados.indexOf(p.id_patrimonio) != i;
                            var usadoOutroProduto = patrimoniosUsadosOutros.includes(p.id_patrimonio);
                            var baseLabel = p.numero_serie || ('PAT-' + p.id_patrimonio);
                            var bloqueadoPorPeriodo = patrimoniosBloqueadosPeriodo.includes(normalizarLabelPatrimonio(baseLabel));
                            var selected = p.id_patrimonio == selectedId ? 'selected' : '';
                            var bloqueadoFixo = usadoOutroProduto || bloqueadoPorPeriodo;
                            var disabled = (usadoNesteProduto || bloqueadoFixo) && p.id_patrimonio != selectedId ? 'disabled' : '';
                            var label = baseLabel;
                            if (bloqueadoPorPeriodo && p.id_patrimonio != selectedId) {
                                label += ' (indisponível no período)';
                            } else if (usadoOutroProduto && p.id_patrimonio != selectedId) {
                                label += ' (usado em outro produto)';
                            } else if (usadoNesteProduto) {
                                label += ' (já selecionado)';
                            }
                            return `<option value="${p.id_patrimonio}" ${selected} ${disabled} data-base-label="${baseLabel}" data-usado-outro="${usadoOutroProduto ? 1 : 0}" data-bloqueado-periodo="${bloqueadoPorPeriodo ? 1 : 0}">${label}</option>`;
                        }).join('')}
                    </select>
                </div>
            `;
        }
        return html;
    }

    // === MODAL DE PATRIMÔNIOS ===
    function abrirModalPatrimonios(index) {
        var item = produtosLocacao.find(p => p.index == index);
        if (!item) return;
        
        $('#modalProdutoNome').text(item.nome);
        $('#modalQtdNecessaria').text(item.quantidade);
        $('#modalProdutoIndex').val(index);
        
        // Obter patrimônios já usados em outros produtos
        var patrimoniosUsadosOutros = getPatrimoniosUsados(index);

        $('#listaPatrimoniosVincular').html('<div class="text-muted small"><i class="ti ti-loader ti-spin me-1"></i>Carregando patrimônios disponíveis no período do item...</div>');

        obterPatrimoniosBloqueadosNoPeriodo(item, function(patrimoniosBloqueadosPeriodo) {
            var html = montarHtmlPatrimoniosModal(item, patrimoniosUsadosOutros, patrimoniosBloqueadosPeriodo);
            $('#listaPatrimoniosVincular').html(html);
            $('.select-patrimonio-modal').off('change').on('change', function() {
                atualizarSelectsPatrimonioModal();
            });
            atualizarSelectsPatrimonioModal();
        });
        
        $('#modalPatrimonios').modal('show');
    }
    
    // === ATUALIZAR SELECTS DE PATRIMÔNIO EM TEMPO REAL ===
    function atualizarSelectsPatrimonioModal() {
        var selecionados = [];
        $('.select-patrimonio-modal').each(function() {
            var val = $(this).val();
            if (val) {
                selecionados.push(parseInt(val));
            }
        });
        
        // Desabilitar opções já selecionadas em outros selects
        $('.select-patrimonio-modal').each(function() {
            var $select = $(this);
            var valorAtual = $select.val();
            
            $select.find('option').each(function() {
                var $opt = $(this);
                var optVal = $opt.val();
                var baseLabel = $opt.data('base-label') || $opt.text().split(' (')[0];
                var usadoOutroProduto = parseInt($opt.data('usado-outro') || 0, 10) === 1;
                var bloqueadoPorPeriodo = parseInt($opt.data('bloqueado-periodo') || 0, 10) === 1;
                
                if (optVal && optVal != valorAtual) {
                    if (bloqueadoPorPeriodo) {
                        $opt.prop('disabled', true);
                        $opt.text(baseLabel + ' (indisponível no período)');
                    } else if (usadoOutroProduto) {
                        $opt.prop('disabled', true);
                        $opt.text(baseLabel + ' (usado em outro produto)');
                    } else if (selecionados.includes(parseInt(optVal))) {
                        $opt.prop('disabled', true);
                        $opt.text(baseLabel + ' (já selecionado)');
                    } else {
                        $opt.prop('disabled', false);
                        $opt.text(baseLabel);
                    }
                }
            });
        });
    }

    // === SALVAR PATRIMÔNIOS ===
    $('#btnSalvarPatrimonios').on('click', function() {
        var index = $('#modalProdutoIndex').val();
        var item = produtosLocacao.find(p => p.index == index);
        if (!item) return;
        
        var patrimoniosSelecionados = [];
        $('.select-patrimonio-modal').each(function() {
            var val = $(this).val();
            if (val) {
                patrimoniosSelecionados.push(parseInt(val));
            }
        });
        
        item.patrimonios_vinculados = patrimoniosSelecionados;
        
        $('#modalPatrimonios').modal('hide');
        renderizarProdutosLocacao();
    });

    // === INICIALIZAR SELECT2 CLIENTE ===
    $('.select2-busca-cliente').select2({
        ajax: {
            url: '{{ route("locacoes.buscar-clientes") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term || '' };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(cliente) {
                        return {
                            id: cliente.id_clientes,
                            text: cliente.nome + (cliente.cpf_cnpj ? ' - ' + cliente.cpf_cnpj : '')
                        };
                    })
                };
            },
            cache: true
        },
        placeholder: 'Selecione um cliente...',
        minimumInputLength: 0,
        allowClear: true
    });

    // === MÁSCARAS ===
    $('.money').mask('#.##0,00', {reverse: true});

    // === CALCULAR PERÍODO DO CONTRATO ===
    function calcularDias() {
        var inicio = $('#data_inicio').val();
        var fim = $('#data_fim').val();
        
        dataContratoInicio = inicio;
        dataContratoFim = fim;
        horaContratoInicio = $('#hora_inicio').val() || '08:00';
        horaContratoFim = $('#hora_fim').val() || '18:00';
        
        if (inicio && fim) {
            totalDias = isLocacaoPorHora()
                ? calcularHorasPeriodo(inicio, horaContratoInicio, fim, horaContratoFim)
                : calcularDiasInclusivo(inicio, fim);
            $('#total_dias').val(totalDias + (isLocacaoPorHora() ? ' horas' : ' dias'));
            
            // Atualizar datas dos produtos existentes que usam a data do contrato
            produtosLocacao.forEach(function(item) {
                if (!item.data_inicio || item.data_inicio < inicio) {
                    item.data_inicio = inicio;
                    item.hora_inicio = horaContratoInicio;
                }
                if (!item.data_fim || item.data_fim > fim) {
                    item.data_fim = fim;
                    item.hora_fim = horaContratoFim;
                }
            });
            
            // Recarregar produtos com disponibilidade do período
            carregarProdutosSelect();
            
            return totalDias;
        }
        totalDias = 1;
        return 1;
    }

    $('#data_inicio, #data_fim').on('change', function() {
        calcularDias();
        renderizarProdutosLocacao();
        calcularTotais();
        atualizarLimitesDatasProdutosNosModais();
        if (!$('#despesaVencimento').val()) {
            $('#despesaVencimento').val($('#data_fim').val() || '');
        }
    });
    
    // Atualizar hora do contrato
    $('#hora_inicio, #hora_fim').on('change', function() {
        horaContratoInicio = $('#hora_inicio').val() || '08:00';
        horaContratoFim = $('#hora_fim').val() || '18:00';
        calcularDias();
        renderizarProdutosLocacao();
        calcularTotais();
        atualizarLimitesDatasProdutosNosModais();
        carregarProdutosSelect();
    });

    // === TOGGLE ENDEREÇO ENTREGA ===
    $('#usar_endereco_cliente').on('change', function() {
        $('#endereco_entrega_container').toggle(!this.checked);

        if (this.checked) {
            exibirInfoCliente($('#id_cliente').val());
        }
    });

    function parseMoedaBr(valor) {
        var texto = String(valor || '').trim();
        if (!texto) {
            return 0;
        }

        var normalizado = texto
            .replace(/\s+/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
            .replace(/[^\d.-]/g, '');

        var numero = parseFloat(normalizado);
        return isNaN(numero) ? 0 : numero;
    }

    function formatarMoedaBr(valor) {
        return (Number(valor) || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // === CALCULAR TOTAIS ===
    function calcularTotais() {
        var totalProdutos = 0;
        var totalServicos = 0;
        var totalDespesas = 0;
        
        // Somar produtos na locação
        produtosLocacao.forEach(function(item) {
            totalProdutos += calcularSubtotalItem(item);
        });
        
        // Somar serviços na locação
        servicosLocacao.forEach(function(servico) {
            totalServicos += servico.quantidade * servico.valor_unitario;
        });

        // Somar despesas da locação
        despesasLocacao.forEach(function(despesa) {
            totalDespesas += parseFloat(despesa.valor) || 0;
        });
        
        // Ler dos inputs editáveis se existirem, senão dos hidden fields
        var descontoGeral = parseMoedaBr($('#input_desconto').val() || $('#desconto_geral').val() || '0');
        var freteEntrega = parseMoedaBr($('#input_frete_entrega').val() || $('#frete_entrega').val() || $('#taxa_entrega').val() || '0');
        var freteRetirada = parseMoedaBr($('#input_frete_retirada').val() || $('#frete_retirada').val() || '0');
        var freteTotal = freteEntrega + freteRetirada;
        
        // Sincronizar com hidden fields
        $('#desconto_geral').val(formatarMoedaBr(descontoGeral));
        $('#taxa_entrega').val(formatarMoedaBr(freteEntrega));
        $('#frete_entrega').val(formatarMoedaBr(freteEntrega));
        $('#frete_retirada').val(formatarMoedaBr(freteRetirada));
        
        var totalContrato = totalProdutos + totalServicos - descontoGeral + freteTotal;
        var lucratividade = totalContrato - totalDespesas;
        
        $('#subtotalProdutos').text('R$ ' + totalProdutos.toFixed(2).replace('.', ','));
        $('#subtotalServicos').text('R$ ' + totalServicos.toFixed(2).replace('.', ','));
        $('#subtotalDespesas').text('R$ ' + totalDespesas.toFixed(2).replace('.', ','));
        $('#resumoProdutos').text('R$ ' + totalProdutos.toFixed(2).replace('.', ','));
        $('#resumoServicos').text('R$ ' + totalServicos.toFixed(2).replace('.', ','));
        $('#resumoDespesas').text('R$ ' + totalDespesas.toFixed(2).replace('.', ','));
        $('#valorTotal').text('R$ ' + totalContrato.toFixed(2).replace('.', ','));
        $('#resumoLucratividade')
            .text('R$ ' + lucratividade.toFixed(2).replace('.', ','))
            .toggleClass('text-danger', lucratividade < 0)
            .toggleClass('text-success', lucratividade >= 0);
        $('#valor_total_input').val(totalContrato.toFixed(2));

        // Atualizar aba de resumo financeiro
        $('#resumoSubtotalProdutos').text('R$ ' + totalProdutos.toFixed(2).replace('.', ','));
        $('#resumoSubtotalServicos').text('R$ ' + totalServicos.toFixed(2).replace('.', ','));
        $('#resumoSubtotalDespesas').text('R$ ' + totalDespesas.toFixed(2).replace('.', ','));
        $('#resumoValorTotal').text('R$ ' + totalContrato.toFixed(2).replace('.', ','));
    }

    $('#desconto_geral, #taxa_entrega, #frete_entrega, #frete_retirada, #input_desconto, #input_frete_entrega, #input_frete_retirada').on('change keyup', function() {
        calcularTotais();
    });

    $('#input_desconto, #input_frete_entrega, #input_frete_retirada').on('blur', function() {
        $(this).val(formatarMoedaBr(parseMoedaBr($(this).val())));
        calcularTotais();
    });

    function limparFormularioDespesa() {
        $('#despesaEditIndex').val('');
        $('#despesaDescricao').val('');
        $('#despesaTipo').val('outros');
        $('#despesaValor').val('0,00');
        $('#despesaParcelas').val(1);
        $('#despesaData').val($('#data_inicio').val() || new Date().toISOString().slice(0, 10));
        $('#despesaVencimento').val($('#data_fim').val() || new Date().toISOString().slice(0, 10));
        $('#btnAdicionarDespesa').html('<i class="ti ti-plus me-1"></i> Adicionar');
        $('#btnCancelarEdicaoDespesa').hide();
    }

    function labelTipoDespesa(tipo) {
        var labels = {
            transporte: 'Transporte/Frete',
            montagem: 'Montagem',
            desmontagem: 'Desmontagem',
            seguro: 'Seguro',
            taxa: 'Taxa',
            multa: 'Multa',
            outros: 'Outros'
        };
        return labels[tipo] || 'Outros';
    }

    function renderizarDespesasLocacao() {
        var $tbody = $('#despesasBody');
        $tbody.empty();

        despesasLocacao.forEach(function(despesa) {
            var row = `
                <tr data-index="${despesa.index}">
                    <td>
                        ${despesa.descricao}
                        <input type="hidden" name="despesas[${despesa.index}][descricao]" value="${despesa.descricao}">
                        <input type="hidden" name="despesas[${despesa.index}][tipo]" value="${despesa.tipo}">
                        <input type="hidden" name="despesas[${despesa.index}][valor]" value="${despesa.valor.toFixed(2).replace('.', ',')}">
                        <input type="hidden" name="despesas[${despesa.index}][data_despesa]" value="${despesa.data_despesa || ''}">
                        <input type="hidden" name="despesas[${despesa.index}][conta_vencimento]" value="${despesa.conta_vencimento || ''}">
                        <input type="hidden" name="despesas[${despesa.index}][conta_parcelas]" value="${despesa.conta_parcelas || 1}">
                    </td>
                    <td>${labelTipoDespesa(despesa.tipo)}</td>
                    <td>${despesa.data_despesa ? formatarDataBrSafe(despesa.data_despesa) : '-'}</td>
                    <td>${despesa.conta_vencimento ? formatarDataBrSafe(despesa.conta_vencimento) : '-'}</td>
                    <td>${despesa.conta_parcelas || 1}x</td>
                    <td><strong>R$ ${(parseFloat(despesa.valor) || 0).toFixed(2).replace('.', ',')}</strong></td>
                    <td>
                        <button type="button" class="btn btn-xs btn-outline-primary btn-editar-despesa" data-index="${despesa.index}" title="Editar">
                            <i class="ti ti-pencil ti-xs"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-danger btn-remover-despesa" data-index="${despesa.index}" title="Remover">
                            <i class="ti ti-trash ti-xs"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });

        $tbody.off('click', '.btn-editar-despesa').on('click', '.btn-editar-despesa', function() {
            var index = $(this).data('index');
            var despesa = despesasLocacao.find(d => d.index == index);
            if (!despesa) return;

            $('#despesaEditIndex').val(despesa.index);
            $('#despesaDescricao').val(despesa.descricao || '');
            $('#despesaTipo').val(despesa.tipo || 'outros');
            $('#despesaValor').val((parseFloat(despesa.valor) || 0).toFixed(2).replace('.', ','));
            $('#despesaData').val(despesa.data_despesa || '');
            $('#despesaVencimento').val(despesa.conta_vencimento || '');
            $('#despesaParcelas').val(parseInt(despesa.conta_parcelas) || 1);
            $('#btnAdicionarDespesa').html('<i class="ti ti-device-floppy me-1"></i> Salvar');
            $('#btnCancelarEdicaoDespesa').show();
        });

        $tbody.off('click', '.btn-remover-despesa').on('click', '.btn-remover-despesa', function() {
            var index = $(this).data('index');
            despesasLocacao = despesasLocacao.filter(d => d.index != index);
            renderizarDespesasLocacao();
            calcularTotais();
        });
    }

    $('#btnAdicionarDespesa').on('click', function() {
        var descricao = ($('#despesaDescricao').val() || '').trim();
        var tipo = $('#despesaTipo').val() || 'outros';
        var valor = parseFloat(($('#despesaValor').val() || '0').replace(/\./g, '').replace(',', '.')) || 0;
        var dataDespesa = $('#despesaData').val() || '';
        var contaVencimento = $('#despesaVencimento').val() || '';
        var contaParcelas = Math.max(1, parseInt($('#despesaParcelas').val()) || 1);
        var editIndex = $('#despesaEditIndex').val();

        if (!descricao) {
            Swal.fire('Atenção', 'Informe a descrição da despesa.', 'warning');
            return;
        }

        if (valor <= 0) {
            Swal.fire('Atenção', 'Informe um valor de despesa maior que zero.', 'warning');
            return;
        }

        var dadosDespesa = {
            index: editIndex !== '' ? parseInt(editIndex) : despesaIndex,
            descricao: descricao,
            tipo: tipo,
            valor: valor,
            data_despesa: dataDespesa,
            conta_vencimento: contaVencimento,
            conta_parcelas: contaParcelas
        };

        if (editIndex !== '') {
            var despesaExistente = despesasLocacao.find(d => d.index == editIndex);
            if (despesaExistente) {
                Object.assign(despesaExistente, dadosDespesa);
            }
        } else {
            despesasLocacao.push(dadosDespesa);
            despesaIndex++;
        }

        renderizarDespesasLocacao();
        calcularTotais();
        limparFormularioDespesa();
    });

    $('#btnCancelarEdicaoDespesa').on('click', function() {
        limparFormularioDespesa();
    });

    // === SUBMIT DO FORMULÁRIO ===
    $('#formLocacao').on('submit', function(e) {
        e.preventDefault();

        if (!validarPeriodoContratoFrontend()) {
            Swal.fire('Período inválido', 'A data/hora de fim não pode ser anterior à data/hora de início.', 'warning');
            return false;
        }
        
        // Somente o cliente é obrigatório
        var idCliente = $('#id_cliente').val();
        if (!idCliente) {
            Swal.fire('Atenção', 'Selecione um cliente para a locação.', 'warning');
            return false;
        }

        // Verificar patrimônios pendentes (apenas se houver produtos próprios)
        var temPendente = false;
        if (isLocacaoAprovada()) {
            produtosLocacao.forEach(function(item) {
                if (item.tipo_item === 'proprio' && item.patrimonios.length > 0 && item.patrimonios_vinculados.length !== item.quantidade) {
                    temPendente = true;
                }
            });
        }
        
        if (temPendente) {
            Swal.fire('Atenção', 'Existem produtos com divergência de patrimônios. A quantidade vinculada deve ser igual à quantidade do item.', 'warning');
            return false;
        }

        // Remover inputs antigos
        $('input[name^="produtos["]').remove();
        $('input[name^="produtos_terceiros["]').remove();
        $('input[name^="despesas["]').remove();
        $('input[name^="salas["]').not('#listaSalas input').remove();

        // Separar produtos próprios e de terceiros
        var produtosProprios = produtosLocacao.filter(p => p.tipo_item !== 'terceiro');
        var produtosTerceiros = produtosLocacao.filter(p => p.tipo_item === 'terceiro');

        // Adicionar inputs hidden com os produtos próprios
        produtosProprios.forEach(function(item, i) {
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][id_produto]" value="${item.id_produto || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][tipo_item]" value="${item.tipo_item}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][descricao]" value="${item.nome}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][quantidade]" value="${item.quantidade}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][valor_unitario]" value="${item.valor_unitario.toFixed(2).replace('.', ',')}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][valor_fechado]" value="${item.valor_fechado ? '1' : '0'}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][data_inicio]" value="${item.data_inicio}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][data_fim]" value="${item.data_fim}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][hora_inicio]" value="${item.hora_inicio || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][hora_fim]" value="${item.hora_fim || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][id_tabela_preco]" value="${item.id_tabela_preco || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][id_sala]" value="${item.id_sala !== null && item.id_sala !== undefined ? item.id_sala : ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][observacoes]" value="${item.observacoes || ''}">`);
            
            // Adicionar patrimônios vinculados
            if (item.patrimonios_vinculados) {
                item.patrimonios_vinculados.forEach(function(patId, j) {
                    $('#formLocacao').append(`<input type="hidden" name="produtos[${i}][patrimonios][${j}]" value="${patId}">`);
                });
            }
        });

        // Adicionar inputs hidden com os produtos de terceiros
        produtosTerceiros.forEach(function(item, i) {
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][id_produto_terceiro]" value="${item.id_produto_terceiro || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][nome_produto_manual]" value="${item.nome || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][descricao_manual]" value="${item.descricao || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][id_fornecedor]" value="${item.id_fornecedor || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][id_sala]" value="${item.id_sala !== null && item.id_sala !== undefined ? item.id_sala : ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][quantidade]" value="${item.quantidade}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][preco_unitario]" value="${item.valor_unitario.toFixed(2).replace('.', ',')}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][valor_fechado]" value="${item.valor_fechado ? '1' : '0'}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][custo_fornecedor]" value="${item.custo_fornecedor || 0}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][tipo_movimentacao]" value="${item.tipo_movimentacao || 'entrega'}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][observacoes]" value="${item.observacoes || ''}">`);
            // Campos de conta a pagar
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][gerar_conta_pagar]" value="${item.gerar_conta_pagar ? '1' : '0'}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][conta_vencimento]" value="${item.conta_vencimento || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][conta_valor]" value="${item.conta_valor || 0}">`);
            $('#formLocacao').append(`<input type="hidden" name="produtos_terceiros[${i}][conta_parcelas]" value="${item.conta_parcelas || 1}">`);
        });

        despesasLocacao.forEach(function(despesa, i) {
            $('#formLocacao').append(`<input type="hidden" name="despesas[${i}][descricao]" value="${despesa.descricao || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="despesas[${i}][tipo]" value="${despesa.tipo || 'outros'}">`);
            $('#formLocacao').append(`<input type="hidden" name="despesas[${i}][valor]" value="${(parseFloat(despesa.valor) || 0).toFixed(2).replace('.', ',')}">`);
            $('#formLocacao').append(`<input type="hidden" name="despesas[${i}][data_despesa]" value="${despesa.data_despesa || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="despesas[${i}][conta_vencimento]" value="${despesa.conta_vencimento || ''}">`);
            $('#formLocacao').append(`<input type="hidden" name="despesas[${i}][conta_parcelas]" value="${despesa.conta_parcelas || 1}">`);
        });
        
        // Mostrar loading
        var $btn = $(this).find('button[type="submit"]');
        var btnOriginal = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');
        
        var formEl = this;
        
        console.log('=== ENVIANDO LOCAÇÃO ===');
        console.log('Produtos:', produtosLocacao.length);
        console.log('Serviços:', servicosLocacao.length);
        console.log('Salas:', salasLocacao.length);
        
        // Buscar token CSRF fresco antes de enviar
        $.ajax({
            url: '{{ route("csrf-token") }}',
            method: 'GET',
            cache: false,
            timeout: 5000,
            success: function(response) {
                var token = response.token || response._csrf_token;
                if (token) {
                    // Atualizar o token no formulário
                    $('input[name="_token"]').val(token);
                    $('meta[name="csrf-token"]').attr('content', token);
                }
                // Usar HTMLFormElement.submit() nativo para não disparar o evento novamente
                HTMLFormElement.prototype.submit.call(formEl);
            },
            error: function(xhr) {
                console.error('Erro ao buscar CSRF token:', xhr);
                // Se falhar, tentar submeter assim mesmo
                HTMLFormElement.prototype.submit.call(formEl);
            }
        });
    });

    // === MODAL BUSCA AVANÇADA DE PRODUTOS ===
    var produtosSelecionadosModal = {};

    function obterPeriodoModalBusca() {
        var dataInicioAtual = $('#data_inicio').val() || dataContratoInicio || '';
        var dataFimAtual = isMedicao
            ? dataInicioAtual
            : ($('#data_fim').val() || dataContratoFim || '');
        var horaInicioAtual = $('#hora_inicio').val() || horaContratoInicio || '08:00';
        var horaFimAtual = isMedicao
            ? horaInicioAtual
            : ($('#hora_fim').val() || horaContratoFim || '18:00');

        dataContratoInicio = dataInicioAtual;
        dataContratoFim = dataFimAtual;
        horaContratoInicio = horaInicioAtual;
        horaContratoFim = horaFimAtual;

        return {
            data_inicio: dataInicioAtual,
            data_fim: dataFimAtual,
            hora_inicio: horaInicioAtual,
            hora_fim: horaFimAtual
        };
    }

    function atualizarTextoPeriodoModalBusca(periodoObj) {
        if (!periodoObj || !periodoObj.data_inicio || !periodoObj.data_fim) {
            $('#infoPeriodoModal').text('--');
            return;
        }
        var periodo = formatarDataBrSafe(periodoObj.data_inicio) + ' até ' + formatarDataBrSafe(periodoObj.data_fim);
        if (periodoObj.hora_inicio && periodoObj.hora_fim) {
            periodo += ' (' + periodoObj.hora_inicio + ' às ' + periodoObj.hora_fim + ')';
        }
        $('#infoPeriodoModal').text(periodo);
    }

    function validarPeriodoModalBusca(periodoObj) {
        if (!periodoObj.data_inicio || (!isMedicao && !periodoObj.data_fim)) {
            return { ok: false, mensagem: 'Informe data de início e fim do produto.' };
        }

        if (isMedicao) {
            return { ok: true };
        }

        function normalizarDataIso(valorData) {
            if (!valorData) return '';
            var texto = String(valorData).trim();
            if (texto.includes('/')) {
                var partes = texto.split('/');
                if (partes.length === 3) {
                    return `${partes[2]}-${partes[1].padStart(2, '0')}-${partes[0].padStart(2, '0')}`;
                }
            }
            return texto;
        }

        function toDateTime(data, horaPadrao) {
            var dataIso = normalizarDataIso(data);
            if (!dataIso) return null;

            var hora = (horaPadrao || '00:00').toString().substring(0, 5);
            var dt = new Date(`${dataIso}T${hora}:00`);
            if (isNaN(dt.getTime())) {
                return null;
            }

            return dt;
        }

        var dataInicioContratoAtual = $('#data_inicio').val() || dataContratoInicio;
        var dataFimContratoAtual = $('#data_fim').val() || dataContratoFim;
        var horaInicioContratoAtual = $('#hora_inicio').val() || horaContratoInicio || '00:00';
        var horaFimContratoAtual = $('#hora_fim').val() || horaContratoFim || '23:59';

        var inicioModal = toDateTime(periodoObj.data_inicio, periodoObj.hora_inicio || '00:00');
        var fimModal = toDateTime(periodoObj.data_fim, periodoObj.hora_fim || '23:59');
        var inicioContrato = toDateTime(dataInicioContratoAtual, horaInicioContratoAtual);
        var fimContrato = toDateTime(dataFimContratoAtual, horaFimContratoAtual);

        if (!inicioModal || !fimModal) {
            return { ok: false, mensagem: 'Período inválido.' };
        }

        if (inicioModal > fimModal) {
            return { ok: false, mensagem: 'A data/hora de fim não pode ser menor que a de início.' };
        }

        if (inicioContrato && fimContrato && (inicioModal < inicioContrato || fimModal > fimContrato)) {
            return { ok: false, mensagem: 'O período do produto deve ficar dentro do período do contrato.' };
        }

        return { ok: true };
    }

    $('#btnAbrirModalProdutos').on('click', function() {
        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        
        if (!dataInicio || !dataFim) {
            Swal.fire('Atenção', 'Defina o período da locação antes de buscar produtos.', 'warning');
            return;
        }

        var periodoModal = obterPeriodoModalBusca();
        atualizarTextoPeriodoModalBusca(periodoModal);
        
        // Limpar seleção
        produtosSelecionadosModal = {};
        atualizarContadorSelecionados();
        
        // Renderizar lista; cálculo de disponibilidade é individual por item
        carregarProdutosModal();
        
        $('#modalBuscaProdutos').modal('show');
    });
    
    function carregarProdutosModal() {
        var filtroNome = $('#filtroBuscaNome').val().toLowerCase();
        var filtroDisp = $('#filtroDisponibilidade').val();
        
        var $lista = $('#listaProdutosModal');
        $lista.html('<div class="text-center py-4"><i class="ti ti-loader ti-spin ti-lg"></i><br>Carregando...</div>');
        
        // Filtrar produtos
        var produtosFiltrados = produtosDisponiveis.filter(function(p) {
            // Filtro por nome
            if (filtroNome && !p.nome.toLowerCase().includes(filtroNome) && (!p.codigo || !p.codigo.toLowerCase().includes(filtroNome))) {
                return false;
            }
            
            // Filtro por disponibilidade
            if (filtroDisp === 'disponivel' && (p.quantidade_disponivel || 0) <= 0) {
                return false;
            }
            if (filtroDisp === 'indisponivel' && (p.quantidade_disponivel || 0) > 0) {
                return false;
            }
            
            return true;
        });
        
        if (produtosFiltrados.length === 0) {
            $lista.html('<div class="text-center py-4 text-muted"><i class="ti ti-package-off ti-lg d-block mb-2"></i>Nenhum produto encontrado</div>');
            return;
        }
        
        var html = '';
        produtosFiltrados.forEach(function(p) {
            var periodoPadrao = obterPeriodoModalBusca();
            var qtdDisp = p.quantidade_disponivel || 0;
            var estoqueTotal = p.estoque_total || 0;
            var valorPadrao = parseFloat(p.preco_locacao || p.preco_diaria || p.preco_venda || 0);
            var temPatrimonios = p.patrimonios && p.patrimonios.length > 0;
            var isChecked = produtosSelecionadosModal[p.id_produto] ? 'checked' : '';
            var qtdSelecionada = produtosSelecionadosModal[p.id_produto]?.quantidade || 1;
            var valorSelecionado = produtosSelecionadosModal[p.id_produto]?.valor || valorPadrao;
            var tabelaSelecionada = produtosSelecionadosModal[p.id_produto]?.id_tabela || '';
            var valorFechado = produtosSelecionadosModal[p.id_produto]?.valor_fechado || false;
            var salaSelecionada = produtosSelecionadosModal[p.id_produto]?.id_sala;
            var dataInicioSelecionada = produtosSelecionadosModal[p.id_produto]?.data_inicio || periodoPadrao.data_inicio || '';
            var horaInicioSelecionada = produtosSelecionadosModal[p.id_produto]?.hora_inicio || periodoPadrao.hora_inicio || '08:00';
            var dataFimSelecionada = produtosSelecionadosModal[p.id_produto]?.data_fim || (isMedicao ? '' : (periodoPadrao.data_fim || ''));
            var horaFimSelecionada = produtosSelecionadosModal[p.id_produto]?.hora_fim || (isMedicao ? '' : (periodoPadrao.hora_fim || '18:00'));
            salaSelecionada = salaSelecionada !== null && salaSelecionada !== undefined ? salaSelecionada : '';
            
            var fotoHtml = p.foto_url 
                ? `<img src="${p.foto_url}" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">`
                : `<div class="d-flex align-items-center justify-content-center rounded" style="width: 60px; height: 60px; font-size: 10px; background-color: #6c757d; color: #fff;">Sem<br>Foto</div>`;
            
            var dispClass = qtdDisp > 0 ? 'text-success' : 'text-danger';
            var restringirPorEstoque = isLocacaoAprovada();
            var disabledClass = (restringirPorEstoque && qtdDisp <= 0) ? 'opacity-50' : '';
            var disabledAttr = (restringirPorEstoque && qtdDisp <= 0) ? 'disabled' : '';
            var maxAttrQtd = restringirPorEstoque ? `max="${qtdDisp}"` : '';
            
            // Opções de tabela de preço
            var optionsTabela = '<option value="">Preço Padrão</option>';
            if (p.tabelas_preco && p.tabelas_preco.length > 0) {
                p.tabelas_preco.forEach(function(t) {
                    var selected = tabelaSelecionada == t.id_tabela ? 'selected' : '';
                    optionsTabela += `<option value="${t.id_tabela}" ${selected}>${t.nome}</option>`;
                });
            }

            // Opcoes de sala
            var optionsSala = '<option value="">Sem sala</option>';
            if (salasLocacao.length > 0) {
                salasLocacao.forEach(function(sala) {
                    var selectedSala = salaSelecionada == sala.index ? 'selected' : '';
                    optionsSala += `<option value="${sala.index}" ${selectedSala}>${sala.nome}</option>`;
                });
            }
            
            html += `
                <div class="produto-card-modal border-bottom py-2 px-3 ${disabledClass}" data-id="${p.id_produto}">
                    <div class="d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input check-produto-modal" 
                               value="${p.id_produto}" ${isChecked} ${disabledAttr} style="width: 1.2rem; height: 1.2rem;">
                        ${fotoHtml}
                        <div style="width: 180px; min-width: 180px;">
                            <strong class="d-block text-truncate">${p.nome}</strong>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge ${qtdDisp > 0 ? 'bg-success' : 'bg-danger'} badge-sm badge-disponibilidade-modal" data-id="${p.id_produto}">${qtdDisp} disp.</span>
                                <button type="button" class="btn btn-xs btn-outline-info btn-ver-detalhes-modal py-0 px-1" 
                                        data-id="${p.id_produto}" title="Ver disponibilidade">
                                    <i class="ti ti-calendar-stats ti-xs"></i>
                                </button>
                            </div>
                        </div>
                        <select class="form-select form-select-sm select-tabela-modal" data-id="${p.id_produto}" ${disabledAttr} style="width: 140px;">
                            ${optionsTabela}
                        </select>
                        <select class="form-select form-select-sm select-valor-tabela-modal" data-id="${p.id_produto}" ${disabledAttr} style="width: 140px; display: none;">
                            <option value="">Período...</option>
                        </select>
                        <div class="form-check mb-0" style="min-width: 70px;">
                            <input type="checkbox" class="form-check-input check-valor-fechado-modal" 
                                   data-id="${p.id_produto}" ${valorFechado ? 'checked' : ''} ${disabledAttr}>
                            <label class="form-check-label small">Fechado</label>
                        </div>
                        <input type="number" class="form-control form-control-sm input-qtd-modal" 
                               data-id="${p.id_produto}" min="1" ${maxAttrQtd} value="${qtdSelecionada}" ${disabledAttr} style="width: 55px;" placeholder="Qtd">
                        <input type="text" class="form-control form-control-sm input-valor-modal" 
                               data-id="${p.id_produto}" value="${valorSelecionado.toFixed(2).replace('.', ',')}" ${disabledAttr} style="width: 85px;" placeholder="Valor">
                        <select class="form-select form-select-sm select-sala-modal" data-id="${p.id_produto}" ${disabledAttr} style="width: 100px;">
                            ${optionsSala}
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 ms-4 ps-1 flex-wrap">
                        <small class="text-muted">Período do produto:</small>
                        <input type="date" class="form-control form-control-sm input-data-inicio-modal" data-id="${p.id_produto}" value="${dataInicioSelecionada}" ${disabledAttr} style="width: 140px;">
                        <input type="time" class="form-control form-control-sm input-hora-inicio-modal" data-id="${p.id_produto}" value="${horaInicioSelecionada}" ${disabledAttr} style="width: 110px;">
                        ${isMedicao ? '' : '<span class="text-muted">até</span>'}
                        ${isMedicao ? '' : `<input type="date" class="form-control form-control-sm input-data-fim-modal" data-id="${p.id_produto}" value="${dataFimSelecionada}" ${disabledAttr} style="width: 140px;">`}
                        ${isMedicao ? '' : `<input type="time" class="form-control form-control-sm input-hora-fim-modal" data-id="${p.id_produto}" value="${horaFimSelecionada}" ${disabledAttr} style="width: 110px;">`}
                    </div>
                </div>
            `;
        });
        
        $lista.html(html);
        
        // Aplicar máscara money nos campos de valor
        $lista.find('.input-valor-modal').each(function() {
            $(this).mask('#.##0,00', {reverse: true});
        });
        
        // Bind eventos
        bindEventosModalProdutos();
    }
    
    // Reaplicar máscara quando modal abrir
    $('#modalBuscaProdutos').on('shown.bs.modal', function() {
        $('#listaProdutosModal .input-valor-modal').each(function() {
            $(this).unmask();
            $(this).mask('#.##0,00', {reverse: true});
        });
    });
    
    function bindEventosModalProdutos() {
        var timersDisponibilidadeModal = {};

        function agendarAtualizacaoDisponibilidadeModal(idProduto, $card) {
            if (timersDisponibilidadeModal[idProduto]) {
                clearTimeout(timersDisponibilidadeModal[idProduto]);
            }

            timersDisponibilidadeModal[idProduto] = setTimeout(function() {
                atualizarDisponibilidadeProdutoModal(idProduto, $card);
            }, 250);
        }

        function atualizarDisponibilidadeProdutoModal(idProduto, $card) {
            var produto = produtosDisponiveis.find(function(p) { return p.id_produto == idProduto; });
            if (!produto || !$card || !$card.length) {
                return;
            }

            var dataInicio = $card.find('.input-data-inicio-modal').val();
            var dataFim = isMedicao ? dataInicio : $card.find('.input-data-fim-modal').val();
            var horaInicio = $card.find('.input-hora-inicio-modal').val() || '08:00';
            var horaFim = isMedicao ? horaInicio : ($card.find('.input-hora-fim-modal').val() || '18:00');
            var $badgeDisp = $card.find('.badge-disponibilidade-modal');

            if (!dataInicio || !dataFim) {
                return;
            }

            $badgeDisp.removeClass('bg-success bg-danger').addClass('bg-secondary').text('...');

            $.ajax({
                url: '{{ route("locacoes.verificar-disponibilidade") }}',
                method: 'GET',
                data: {
                    id_produto: produto.id_produto,
                    data_inicio: dataInicio,
                    data_fim: dataFim,
                    hora_inicio: horaInicio,
                    hora_fim: horaFim,
                    preferencia_estoque: getPreferenciaEstoque()
                },
                success: function(data) {
                    var qtdDisp = parseInt(data.disponivel || 0);
                    var restringirPorEstoque = isLocacaoAprovada();
                    var $check = $card.find('.check-produto-modal');
                    var $qtdInput = $card.find('.input-qtd-modal');
                    var qtdAtual = parseInt($qtdInput.val()) || 1;

                    $badgeDisp.removeClass('bg-secondary bg-success bg-danger').addClass(qtdDisp > 0 ? 'bg-success' : 'bg-danger').text(qtdDisp + ' disp.');

                    if (restringirPorEstoque) {
                        $qtdInput.attr('max', qtdDisp).data('max-disponivel', qtdDisp);
                    } else {
                        $qtdInput.removeAttr('max').removeData('max-disponivel');
                    }

                    if (restringirPorEstoque && qtdDisp <= 0) {
                        $check.prop('checked', false).prop('disabled', true);
                        delete produtosSelecionadosModal[idProduto];
                        $card.removeClass('bg-light border-primary');
                        atualizarContadorSelecionados();
                    } else {
                        $check.prop('disabled', false);

                        if (restringirPorEstoque && qtdAtual > qtdDisp) {
                            var novaQtd = Math.max(1, qtdDisp);
                            $qtdInput.val(novaQtd);
                            if (produtosSelecionadosModal[idProduto]) {
                                produtosSelecionadosModal[idProduto].quantidade = novaQtd;
                            }
                        }
                    }

                    if (produtosSelecionadosModal[idProduto]) {
                        produtosSelecionadosModal[idProduto].data_inicio = dataInicio;
                        produtosSelecionadosModal[idProduto].hora_inicio = horaInicio;
                        produtosSelecionadosModal[idProduto].data_fim = dataFim;
                        produtosSelecionadosModal[idProduto].hora_fim = horaFim;
                    }
                },
                error: function() {
                    $badgeDisp.removeClass('bg-secondary').addClass('bg-danger').text('erro');
                }
            });
        }

        // Checkbox de produto
        $('.check-produto-modal').off('change').on('change', function() {
            var idProduto = $(this).val();
            var produto = produtosDisponiveis.find(p => p.id_produto == idProduto);
            var $card = $(this).closest('.produto-card-modal');
            
            if ($(this).is(':checked')) {
                var qtd = parseInt($card.find('.input-qtd-modal').val()) || 1;
                var clean = typeof $card.find('.input-valor-modal').cleanVal === 'function'
                    ? $card.find('.input-valor-modal').cleanVal()
                    : $card.find('.input-valor-modal').val().replace(/\D/g, '');
                var valor = clean ? (parseInt(clean, 10) / 100) : 0;
                var idTabela = $card.find('.select-tabela-modal').val() || null;
                var valorFechado = $card.find('.check-valor-fechado-modal').is(':checked');
                var idSala = $card.find('.select-sala-modal').val() || null;
                var dataInicio = $card.find('.input-data-inicio-modal').val() || dataContratoInicio;
                var horaInicio = $card.find('.input-hora-inicio-modal').val() || horaContratoInicio || '08:00';
                var dataFim = isMedicao ? '' : ($card.find('.input-data-fim-modal').val() || dataContratoFim);
                var horaFim = isMedicao ? '' : ($card.find('.input-hora-fim-modal').val() || horaContratoFim || '18:00');
                
                produtosSelecionadosModal[idProduto] = {
                    produto: produto,
                    quantidade: qtd,
                    valor: valor,
                    id_tabela: idTabela,
                    valor_fechado: valorFechado,
                    id_sala: idSala,
                    data_inicio: dataInicio,
                    hora_inicio: horaInicio,
                    data_fim: dataFim,
                    hora_fim: horaFim
                };
                $card.addClass('bg-light border-primary');
            } else {
                delete produtosSelecionadosModal[idProduto];
                $card.removeClass('bg-light border-primary');
            }
            
            atualizarContadorSelecionados();
        });
        
        // Input quantidade
        $('.input-qtd-modal').off('change input').on('change input', function() {
            var idProduto = $(this).data('id');
            var qtd = parseInt($(this).val()) || 1;
            var produto = produtosDisponiveis.find(p => p.id_produto == idProduto);
            
            // Validar quantidade
            if (isLocacaoAprovada()) {
                var max = parseInt($(this).data('max-disponivel'));
                if (isNaN(max)) {
                    max = produto ? (produto.quantidade_disponivel || 0) : 1;
                }
                if (qtd > max) {
                    qtd = max;
                    $(this).val(max);
                }
            }
            
            if (produtosSelecionadosModal[idProduto]) {
                produtosSelecionadosModal[idProduto].quantidade = qtd;
            }
        });
        
        // Input valor
        $('.input-valor-modal').off('input').on('input', function() {
            var idProduto = $(this).data('id');
            var clean = typeof $(this).cleanVal === 'function' ? $(this).cleanVal() : $(this).val().replace(/\D/g, '');
            var valor = clean ? (parseInt(clean, 10) / 100) : 0;
            if (produtosSelecionadosModal[idProduto]) {
                produtosSelecionadosModal[idProduto].valor = valor;
            }
        });

        $('.select-sala-modal').off('change').on('change', function() {
            var idProduto = $(this).data('id');
            var idSala = $(this).val() || null;
            if (produtosSelecionadosModal[idProduto]) {
                produtosSelecionadosModal[idProduto].id_sala = idSala;
            }
        });

        $('.input-data-inicio-modal, .input-hora-inicio-modal, .input-data-fim-modal, .input-hora-fim-modal').off('change').on('change', function() {
            var idProduto = $(this).data('id');
            var $card = $(this).closest('.produto-card-modal');

            if (produtosSelecionadosModal[idProduto]) {
                produtosSelecionadosModal[idProduto].data_inicio = $card.find('.input-data-inicio-modal').val() || dataContratoInicio;
                produtosSelecionadosModal[idProduto].hora_inicio = $card.find('.input-hora-inicio-modal').val() || horaContratoInicio || '08:00';
                produtosSelecionadosModal[idProduto].data_fim = isMedicao ? '' : ($card.find('.input-data-fim-modal').val() || dataContratoFim);
                produtosSelecionadosModal[idProduto].hora_fim = isMedicao ? '' : ($card.find('.input-hora-fim-modal').val() || horaContratoFim || '18:00');
            }

            agendarAtualizacaoDisponibilidadeModal(idProduto, $card);
        });
        
        // Select tabela de preço
        $('.select-tabela-modal').off('change').on('change', function() {
            var idProduto = $(this).data('id');
            var idTabela = $(this).val();
            var produto = produtosDisponiveis.find(p => p.id_produto == idProduto);
            var $selectValor = $(`.select-valor-tabela-modal[data-id="${idProduto}"]`);
            
            if (idTabela && produto && produto.tabelas_preco) {
                var tabela = produto.tabelas_preco.find(t => t.id_tabela == idTabela);
                if (tabela) {
                    var optionsValor = '<option value="">Período...</option>';
                    var diasDisponiveis = [
                        {campo: 'd1', label: '1d', valor: tabela.d1},
                        {campo: 'd2', label: '2d', valor: tabela.d2},
                        {campo: 'd3', label: '3d', valor: tabela.d3},
                        {campo: 'd4', label: '4d', valor: tabela.d4},
                        {campo: 'd5', label: '5d', valor: tabela.d5},
                        {campo: 'd6', label: '6d', valor: tabela.d6},
                        {campo: 'd7', label: '7d', valor: tabela.d7},
                        {campo: 'd15', label: '15d', valor: tabela.d15},
                        {campo: 'd30', label: '30d', valor: tabela.d30}
                    ];
                    diasDisponiveis.forEach(function(d) {
                        if (d.valor && parseFloat(d.valor) > 0) {
                            optionsValor += `<option value="${d.valor}">${d.label}: ${parseFloat(d.valor).toFixed(2).replace('.', ',')}</option>`;
                        }
                    });
                    $selectValor.html(optionsValor).show();
                }
            } else {
                $selectValor.hide();
            }
            
            if (produtosSelecionadosModal[idProduto]) {
                produtosSelecionadosModal[idProduto].id_tabela = idTabela;
            }
        });
        
        // Select valor da tabela
        $('.select-valor-tabela-modal').off('change').on('change', function() {
            var idProduto = $(this).data('id');
            var valor = parseFloat($(this).val()) || 0;
            var $card = $(this).closest('.produto-card-modal');
            
            if (valor > 0) {
                $card.find('.input-valor-modal').val(formatarMoedaBr(valor));
                if (produtosSelecionadosModal[idProduto]) {
                    produtosSelecionadosModal[idProduto].valor = valor;
                }
            }
        });
        
        // Checkbox valor fechado
        $('.check-valor-fechado-modal').off('change').on('change', function() {
            var idProduto = $(this).data('id');
            var valorFechado = $(this).is(':checked');
            
            if (produtosSelecionadosModal[idProduto]) {
                produtosSelecionadosModal[idProduto].valor_fechado = valorFechado;
            }
        });
        
        // Ver detalhes (abre modal separado)
        $('.btn-ver-detalhes-modal').off('click').on('click', function(e) {
            e.stopPropagation();
            var idProduto = $(this).data('id');
            mostrarDetalhesEstoque(idProduto);
        });
    }
    
    function mostrarDetalhesEstoque(idProduto) {
        var produto = produtosDisponiveis.find(p => p.id_produto == idProduto);
        if (!produto) return;
        
        $('#detalhesEstoqueNomeProduto').text(produto.nome);
        $('#detalhesEstoqueTotal').text(produto.estoque_total || 0);
        
        var picoPeriodo = (produto.quantidade_reservada || 0) + (produto.quantidade_em_locacao || 0);
        $('#detalhesPicoPeriodo').text(picoPeriodo);
        $('#detalhesDisponivelPeriodo').text(produto.quantidade_disponivel || 0);
        
        // Gerar calendário de disponibilidade
        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        var diasHtml = '';
        
        if (dataInicio && dataFim) {
            var d1 = parseDateLocal(dataInicio);
            var d2 = parseDateLocal(dataFim);
            var qtdDisp = produto.quantidade_disponivel || 0;
            
            while (d1 && d2 && d1 <= d2) {
                var dia = d1.getDate().toString().padStart(2, '0');
                var mes = (d1.getMonth() + 1).toString().padStart(2, '0');
                var bgClass = qtdDisp > 0 ? 'bg-success' : 'bg-danger';
                
                diasHtml += `<span class="badge ${bgClass}" title="${dia}/${mes}: ${qtdDisp} disponível">${dia}/${mes}: ${qtdDisp}</span>`;
                
                d1.setDate(d1.getDate() + 1);
            }
        }
        
        $('#diasDisponibilidade').html(diasHtml || '<span class="text-muted">Período não definido</span>');
        
        // Mostrar conflitos se houver
        if (produto.conflitos && produto.conflitos.length > 0) {
            var conflitosHtml = '<ul class="list-unstyled mb-0">';
            produto.conflitos.forEach(function(c) {
                conflitosHtml += `<li class="mb-1"><i class="ti ti-alert-triangle me-1 text-danger"></i>${c.cliente || 'Contrato'} #${c.numero_contrato || c.id_locacao} - ${c.quantidade}un (${c.data_inicio} a ${c.data_fim})</li>`;
            });
            conflitosHtml += '</ul>';
            $('#conflitosLista').html(conflitosHtml);
            $('#listaConflitos').show();
        } else {
            $('#listaConflitos').hide();
        }
        
        $('#modalDetalhesEstoque').modal('show');
    }
    
    function atualizarContadorSelecionados() {
        var count = Object.keys(produtosSelecionadosModal).length;
        $('#qtdSelecionados').text(count);
        $('#btnAdicionarSelecionados').prop('disabled', count === 0);
    }
    
    // Check todos
    $('#checkTodosProdutos').on('change', function() {
        var isChecked = $(this).is(':checked');
        
        $('.check-produto-modal:not(:disabled)').each(function() {
            $(this).prop('checked', isChecked).trigger('change');
        });
    });
    
    // Filtrar
    $('#btnFiltrarProdutos').on('click', function() {
        carregarProdutosModal();
    });
    
    // Enter no campo de busca
    $('#filtroBuscaNome').on('keypress', function(e) {
        if (e.which === 13) {
            carregarProdutosModal();
        }
    });
    
    // Limpar filtros
    $('#btnLimparFiltros').on('click', function() {
        $('#filtroBuscaNome').val('');
        $('#filtroDisponibilidade').val('disponivel');
        carregarProdutosModal();
    });
    
    // Adicionar produtos selecionados
    $('#btnAdicionarSelecionados').on('click', function() {
        var added = 0;
        var selecionados = Object.values(produtosSelecionadosModal);
        var invalidos = [];

        selecionados.forEach(function(item) {
            var periodoItem = {
                data_inicio: item.data_inicio || dataContratoInicio,
                data_fim: isMedicao ? '' : (item.data_fim || dataContratoFim),
                hora_inicio: item.hora_inicio || horaContratoInicio || '08:00',
                hora_fim: isMedicao ? '' : (item.hora_fim || horaContratoFim || '18:00')
            };
            var validacaoItem = validarPeriodoModalBusca(periodoItem);
            if (!validacaoItem.ok) {
                invalidos.push((item.produto?.nome || 'Produto') + ': ' + validacaoItem.mensagem);
            }
        });

        if (invalidos.length > 0) {
            Swal.fire('Período inválido', invalidos[0], 'warning');
            return;
        }
        
        selecionados.forEach(function(item) {
            var produto = item.produto;
            var quantidade = item.quantidade;
            var valorUnitario = item.valor;
            var idTabela = item.id_tabela || null;
            var valorFechado = item.valor_fechado || false;
            var idSala = item.id_sala || null;
            var dataInicioItem = item.data_inicio || dataContratoInicio;
            var dataFimItem = isMedicao ? '' : (item.data_fim || dataContratoFim);
            var horaInicioItem = item.hora_inicio || horaContratoInicio || '08:00';
            var horaFimItem = isMedicao ? '' : (item.hora_fim || horaContratoFim || '18:00');
            
            var temPatrimonio = produto.patrimonios && produto.patrimonios.length > 0;
            if (temPatrimonio) {
                for (var i = 0; i < quantidade; i++) {
                    produtosLocacao.push({
                        index: produtoIndex,
                        id_produto: produto.id_produto,
                        nome: produto.nome,
                        foto_url: produto.foto_url,
                        tipo_item: 'proprio',
                        quantidade: 1,
                        valor_unitario: valorUnitario,
                        valor_fechado: valorFechado,
                        data_inicio: dataInicioItem,
                        data_fim: dataFimItem,
                        hora_inicio: horaInicioItem,
                        hora_fim: horaFimItem,
                        id_tabela_preco: idTabela,
                        id_sala: idSala,
                        id_fornecedor: null,
                        custo_fornecedor: 0,
                        gerar_conta_pagar: false,
                        observacoes: '',
                        patrimonios: produto.patrimonios || [],
                        tabelas_preco: produto.tabelas_preco || [],
                        patrimonios_vinculados: []
                    });
                    produtoIndex++;
                    added++;
                }
            } else {
                produtosLocacao.push({
                    index: produtoIndex,
                    id_produto: produto.id_produto,
                    nome: produto.nome,
                    foto_url: produto.foto_url,
                    tipo_item: 'proprio',
                    quantidade: quantidade,
                    valor_unitario: valorUnitario,
                    valor_fechado: valorFechado,
                    data_inicio: dataInicioItem,
                    data_fim: dataFimItem,
                    hora_inicio: horaInicioItem,
                    hora_fim: horaFimItem,
                    id_tabela_preco: idTabela,
                    id_sala: idSala,
                    id_fornecedor: null,
                    custo_fornecedor: 0,
                    gerar_conta_pagar: false,
                    observacoes: '',
                    patrimonios: produto.patrimonios || [],
                    tabelas_preco: produto.tabelas_preco || [],
                    patrimonios_vinculados: []
                });
                produtoIndex++;
                added++;
            }
        });
        
        $('#modalBuscaProdutos').modal('hide');
        
        if (added > 0) {
            renderizarProdutosLocacao();
            calcularTotais();
            
            Swal.fire({
                icon: 'success',
                title: 'Produtos adicionados!',
                text: `${added} produto(s) adicionado(s) à locação.`,
                timer: 2000,
                showConfirmButton: false
            });
        }
    });

    // === INICIALIZAÇÃO ===
    inicializarSeletoresPeriodoContrato();
    if (isMedicao) {
        $('#tab-clientes .col-lg-7').removeClass('col-lg-7').addClass('col-lg-12');
        sincronizarPeriodoMedicaoContrato();
    }
    atualizarLimitesPeriodoContrato();
    atualizarLimitesDatasProdutosNosModais();
    atualizarBloqueioLocacaoPorHora();
    atualizarEstadoBotoesPeriodoPadrao();
    aplicarRestricaoLocacaoPorHora();
    atualizarResumoPeriodo();
    validarPeriodoContratoFrontend();
    carregarProdutosSelect();
    carregarFornecedoresSelect();
    calcularDias();
    renderizarSalas();
    limparFormularioDespesa();
    renderizarDespesasLocacao();
});
</script>
@endsection
