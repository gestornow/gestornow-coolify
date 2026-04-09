@extends('layouts.layoutMaster')

@section('title', 'Configurações da Empresa')

@section('page-style')
<style>
    .settings-nav-horizontal {
        background: #fff;
        border-radius: 0.75rem;
        padding: 0.5rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        border: 1px solid #e7e7e8;
        margin-bottom: 1.5rem;
    }
    
    .settings-nav-horizontal .nav {
        gap: 0.5rem;
    }
    
    .settings-nav-horizontal .nav-link {
        border-radius: 0.5rem;
        padding: 0.875rem 1.5rem;
        color: #566a7f;
        font-weight: 500;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }
    
    .settings-nav-horizontal .nav-link:hover {
        background: rgba(105, 108, 255, 0.06);
        color: #696cff;
    }
    
    .settings-nav-horizontal .nav-link.active {
        background: linear-gradient(135deg, #696cff 0%, #5f61e6 100%);
        color: #fff;
        box-shadow: 0 2px 6px rgba(105, 108, 255, 0.4);
    }
    
    .settings-nav-horizontal .nav-link.active i {
        color: #fff;
    }
    
    .settings-card {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        overflow: hidden;
    }
    
    .settings-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        border-bottom: 1px solid #eceef1;
        padding: 1.25rem 1.5rem;
        margin-bottom: 0;
    }
    
    .settings-card .card-header h6 {
        font-weight: 600;
        color: #566a7f;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .settings-card .card-header h6 i {
        color: #696cff;
    }
    
    .settings-card .card-body {
        padding: 1.75rem 1.5rem;
    }
    
    .logo-upload-area {
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        border: 2px dashed #d9dee3;
        border-radius: 0.75rem;
        padding: 2rem;
        text-align: center;
        transition: all 0.2s ease;
    }
    
    .logo-upload-area:hover {
        border-color: #696cff;
        background: rgba(105, 108, 255, 0.02);
    }
    
    .logo-preview {
        width: 140px;
        height: 140px;
        border-radius: 1rem;
        overflow: hidden;
        border: 3px solid #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }
    
    .logo-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .logo-preview-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #696cff 0%, #5f61e6 100%);
        color: #fff;
        font-size: 2.5rem;
        font-weight: 700;
    }
    
    .form-section-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #a1acb8;
        margin-top: 0.5rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #eceef1;
    }
    
    .preference-card {
        background: #fff;
        border: 1px solid #e7e7e8;
        border-radius: 0.75rem;
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
    }
    
    .preference-card:hover {
        border-color: #696cff;
        box-shadow: 0 4px 12px rgba(105, 108, 255, 0.1);
    }
    
    .preference-card:last-child {
        margin-bottom: 0;
    }
    
    .preference-card .preference-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }
    
    .preference-card .preference-icon {
        width: 42px;
        height: 42px;
        border-radius: 0.5rem;
        background: linear-gradient(135deg, rgba(105, 108, 255, 0.1) 0%, rgba(105, 108, 255, 0.05) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .preference-card .preference-icon i {
        font-size: 1.25rem;
        color: #696cff;
    }
    
    .preference-card .preference-content {
        flex: 1;
    }
    
    .preference-card .preference-content h6 {
        font-weight: 600;
        color: #566a7f;
        margin-bottom: 0.5rem;
    }
    
    .preference-card .preference-content p {
        font-size: 0.8125rem;
        color: #a1acb8;
        margin: 0;
        line-height: 1.5;
    }
    
    .preference-card .preference-help {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eceef1;
    }
    
    .save-card {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    }
    
    .field-readonly {
        background-color: #f8f9fa !important;
        border-color: #e7e7e8 !important;
    }
    
    .field-readonly-badge {
        font-size: 0.6875rem;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
        background: #f1f1f2;
        color: #a1acb8;
        margin-left: 0.5rem;
    }
    
    .btn-save-settings {
        padding: 0.75rem 2rem;
        font-weight: 600;
        border-radius: 0.5rem;
        box-shadow: 0 2px 6px rgba(105, 108, 255, 0.4);
    }
    
    @media (max-width: 991.98px) {
        .settings-nav-horizontal .nav-link {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
        
        .preference-card .preference-header {
            flex-direction: column;
        }
    }
    
    @media (max-width: 575.98px) {
        .settings-nav-horizontal .nav {
            flex-direction: column;
        }
        
        .settings-nav-horizontal .nav-link {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    {{-- Alertas --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="ti ti-check me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="ti ti-alert-circle me-2"></i>
            <strong>Erro!</strong> Verifique os campos abaixo.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Navegação Horizontal --}}
    <div class="settings-nav-horizontal">
        <ul class="nav nav-pills justify-content-center justify-content-md-start" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dados-empresa" type="button">
                    <i class="ti ti-building-store"></i>
                    <span>Dados da Empresa</span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-preferencias-empresa" type="button">
                    <i class="ti ti-adjustments-horizontal"></i>
                    <span>Preferências</span>
                </button>
            </li>
        </ul>
    </div>

    {{-- Conteúdo Principal --}}
    <form action="{{ route('configuracoes.empresa.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="tab-content">
            {{-- Tab: Dados da Empresa --}}
            <div class="tab-pane fade show active" id="tab-dados-empresa" role="tabpanel">
                
                {{-- Card: Logo --}}
                <div class="card settings-card mb-4">
                    <div class="card-header">
                        <h6><i class="ti ti-photo"></i> Logo da Empresa</h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center mb-3 mb-md-0">
                                <div class="logo-preview">
                                    @if(!empty($logoUrl))
                                        <img src="{{ $logoUrl }}" alt="Logo da empresa" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'logo-preview-placeholder\'>GN</div>';">
                                    @else
                                        <div class="logo-preview-placeholder">GN</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="logo-upload-area">
                                            <i class="ti ti-cloud-upload text-primary mb-2" style="font-size: 2rem;"></i>
                                            <h6 class="mb-2">Carregar nova logo</h6>
                                            <p class="text-muted small mb-3">Arraste ou clique para selecionar. PNG, JPG ou WEBP.</p>
                                            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/jpg,image/webp" style="max-width: 300px; margin: 0 auto;">
                                        </div>
                                        <small class="text-muted d-block mt-2 text-center">
                                            <i class="ti ti-info-circle me-1"></i>
                                            A logo será exibida em contratos, romaneios, checklists e ordens de serviço.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Card: Informações Cadastrais --}}
                        <div class="card settings-card mb-4">
                            <div class="card-header">
                                <h6><i class="ti ti-file-certificate"></i> Informações Cadastrais</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-section-title">
                                    <i class="ti ti-lock me-1"></i> Dados Bloqueados
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Razão Social
                                            <span class="field-readonly-badge">Bloqueado</span>
                                        </label>
                                        <input type="text" class="form-control field-readonly" value="{{ $empresa->razao_social }}" disabled readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            CNPJ
                                            <span class="field-readonly-badge">Bloqueado</span>
                                        </label>
                                        <input type="text" class="form-control field-readonly" value="{{ $empresa->cnpj_formatado ?? $empresa->cnpj }}" disabled readonly>
                                    </div>
                                </div>

                                <div class="form-section-title">
                                    <i class="ti ti-edit me-1"></i> Dados Editáveis
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome Fantasia</label>
                                        <input type="text" name="nome_empresa" class="form-control" value="{{ old('nome_empresa', $empresa->nome_empresa) }}" placeholder="Nome comercial">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="email" class="form-control" value="{{ old('email', $empresa->email) }}" placeholder="email@empresa.com">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" name="telefone" class="form-control" value="{{ old('telefone', $empresa->telefone_formatado ?? $empresa->telefone) }}" placeholder="(00) 00000-0000">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control" value="{{ old('cidade', $empresa->cidade) }}" placeholder="Cidade">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">UF</label>
                                        <input type="text" name="uf" class="form-control" maxlength="2" value="{{ old('uf', $empresa->uf) }}" placeholder="SP">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Card: Endereço --}}
                        <div class="card settings-card mb-4">
                            <div class="card-header">
                                <h6><i class="ti ti-map-pin"></i> Endereço</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">CEP</label>
                                        <input type="text" name="cep" class="form-control" value="{{ old('cep', $empresa->cep) }}" placeholder="00000-000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Endereço</label>
                                        <input type="text" name="endereco" class="form-control" value="{{ old('endereco', $empresa->endereco) }}" placeholder="Rua, Avenida...">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Número</label>
                                        <input type="text" name="numero" class="form-control" value="{{ old('numero', $empresa->numero) }}" placeholder="123">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Bairro</label>
                                        <input type="text" name="bairro" class="form-control" value="{{ old('bairro', $empresa->bairro) }}" placeholder="Bairro">
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label">Complemento</label>
                                        <input type="text" name="complemento" class="form-control" value="{{ old('complemento', $empresa->complemento) }}" placeholder="Sala, Andar, Bloco...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab: Preferências --}}
                    <div class="tab-pane fade" id="tab-preferencias-empresa" role="tabpanel">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h6><i class="ti ti-adjustments-horizontal"></i> Preferências do Sistema</h6>
                            </div>
                            <div class="card-body">
                                {{-- Preferência: Numeração --}}
                                <div class="preference-card">
                                    <div class="preference-header">
                                        <div class="d-flex gap-3">
                                            <div class="preference-icon">
                                                <i class="ti ti-numbers"></i>
                                            </div>
                                            <div class="preference-content">
                                                <h6>Numeração de Contratos e Orçamentos</h6>
                                                <p>Defina se contratos e orçamentos terão sequências numéricas separadas ou compartilhadas.</p>
                                            </div>
                                        </div>
                                        <div class="ms-auto">
                                            <input type="hidden" name="orcamentos_contratos" value="0">
                                            <div class="form-check form-switch">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="orcamentos_contratos"
                                                    name="orcamentos_contratos"
                                                    value="1"
                                                    style="width: 3rem; height: 1.5rem;"
                                                    {{ (int) ($empresa->orcamentos_contratos ?? 0) === 1 ? 'checked' : '' }}
                                                    {{ empty($podeAlterarPreferenciaNumeracao) ? 'disabled' : '' }}
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    @if(empty($podeAlterarPreferenciaNumeracao))
                                        <div class="preference-help">
                                            <div class="alert alert-warning mb-0 py-2 px-3 small">
                                                <i class="ti ti-lock me-1"></i>
                                                Esta preferência foi bloqueada após existir pelo menos 1 orçamento e 1 contrato.
                                            </div>
                                        </div>
                                    @else
                                        <div class="preference-help text-muted small">
                                            <strong>Desativado:</strong> Sequências separadas (Orçamento #1, Contrato #1)
                                            <br>
                                            <strong>Ativado:</strong> Sequência única (Orçamento #1, Contrato #2)
                                        </div>
                                    @endif
                                </div>

                                {{-- Preferência: Número Manual --}}
                                <div class="preference-card">
                                    <div class="preference-header">
                                        <div class="d-flex gap-3">
                                            <div class="preference-icon">
                                                <i class="ti ti-keyboard"></i>
                                            </div>
                                            <div class="preference-content">
                                                <h6>Número Manual nas Locações</h6>
                                                <p>Permite digitar manualmente o número do contrato/orçamento ao criar ou editar uma locação.</p>
                                            </div>
                                        </div>
                                        <div class="ms-auto">
                                            <input type="hidden" name="locacao_numero_manual" value="0">
                                            <div class="form-check form-switch">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="locacao_numero_manual"
                                                    name="locacao_numero_manual"
                                                    value="1"
                                                    style="width: 3rem; height: 1.5rem;"
                                                    {{ !empty($permitirNumeroManualLocacao) ? 'checked' : '' }}
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preference-help text-muted small">
                                        <strong>Desativado:</strong> O sistema gera automaticamente o número sequencial
                                        <br>
                                        <strong>Ativado:</strong> Você pode definir o número ou deixar em branco para geração automática
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card: Botão Salvar --}}
                <div class="card save-card mt-4">
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted small">
                            <i class="ti ti-info-circle me-1"></i>
                            Clique em salvar para aplicar as alterações
                        </span>
                        <button type="submit" class="btn btn-primary btn-save-settings">
                            <i class="ti ti-device-floppy me-2"></i>
                            Salvar Configurações
                        </button>
                    </div>
                </div>
            </form>
</div>
@endsection
