@extends('layouts.layoutMaster')

@section('title', 'Detalhes da Filial - ' . $empresa->nome_empresa)

@section('vendor-style')
<style>
    /* Centralizar ícones nos avatares do modal de módulos */
    .avatar-initial i {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="row">
                <!-- Informações da Empresa -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Informações da Empresa</h5>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.filiais.edit', $empresa) }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-edit me-1"></i>
                                    Editar
                                </a>
                                <a href="{{ route('admin.filiais.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ti ti-arrow-left me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <!-- Linha 1: Nome e Razão Social -->
                                <div class="col-md-6">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Nome da Empresa</label>
                                        <p class="fw-bold mb-0">{{ $empresa->nome_empresa }}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Razão Social</label>
                                        <p class="fw-bold mb-0">{{ $empresa->razao_social }}</p>
                                    </div>
                                </div>

                                <!-- Linha 2: CNPJ, CPF e Código -->
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">CNPJ</label>
                                        <p class="mb-0">{{ $empresa->cnpj_formatado }}</p>
                                    </div>
                                </div>
                                @if($empresa->cpf)
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">CPF</label>
                                        <p class="mb-0">{{ $empresa->cpf_formatado }}</p>
                                    </div>
                                </div>
                                @endif
                                <div class="col-md-{{ $empresa->cpf ? 4 : 8 }}">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Código</label>
                                        <p class="mb-0">{{ $empresa->codigo ?? 'Não informado' }}</p>
                                    </div>
                                </div>

                                <!-- Linha 3: Tipo, Status, Dados Cadastrais -->
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Tipo</label>
                                        @if($empresa->filial)
                                            <span class="badge bg-label-{{ $empresa->filial == 'Matriz' ? 'primary' : ($empresa->filial == 'Filial' ? 'info' : 'secondary') }}">
                                                {{ $empresa->filial }}
                                            </span>
                                        @else
                                            <p class="mb-0">Não informado</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Status</label>
                                        @php
                                            $statusColors = [
                                                'ativo' => 'success',
                                                'inativo' => 'secondary',
                                                'bloqueado' => 'danger',
                                                'validacao' => 'warning',
                                                'teste' => 'info',
                                                'cancelado' => 'dark',
                                                'teste bloqueado' => 'danger'
                                            ];
                                            $color = $statusColors[$empresa->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-label-{{ $color }}">
                                            {{ ucfirst($empresa->status) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Dados Cadastrais</label>
                                        <p class="mb-0">{{ ucfirst($empresa->dados_cadastrais) }}</p>
                                    </div>
                                </div>

                                <!-- Data de Bloqueio/Cancelamento -->
                                @if(in_array($empresa->status, ['bloqueado', 'inativo']) && $empresa->data_bloqueio)
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">
                                            {{ $empresa->status === 'bloqueado' ? 'Data de Bloqueio' : 'Data de Inativação' }}
                                        </label>
                                        <p class="mb-0">{{ $empresa->data_bloqueio->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->status === 'cancelado' && $empresa->data_cancelamento)
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Data de Cancelamento</label>
                                        <p class="mb-0">{{ $empresa->data_cancelamento->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                                @endif

                                <!-- Linha 4: E-mail e Telefone -->
                                @if($empresa->email)
                                <div class="col-md-6">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">E-mail</label>
                                        <p class="mb-0">{{ $empresa->email }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->telefone)
                                <div class="col-md-{{ $empresa->email ? 6 : 12 }}">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Telefone</label>
                                        <p class="mb-0">{{ $empresa->telefone_formatado }}</p>
                                    </div>
                                </div>
                                @endif

                                <!-- Linha 5: Data de Criação -->
                                <div class="col-md-12">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Data de Criação</label>
                                        <p class="mb-0">{{ $empresa->created_at ? $empresa->created_at->format('d/m/Y H:i') : 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    @if($empresa->endereco || $empresa->cep || $empresa->uf)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Endereço</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                @if($empresa->endereco)
                                <div class="col-md-8">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Endereço</label>
                                        <p class="mb-0">{{ $empresa->endereco }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->numero)
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Número</label>
                                        <p class="mb-0">{{ $empresa->numero }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->bairro)
                                <div class="col-md-6">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Bairro</label>
                                        <p class="mb-0">{{ $empresa->bairro }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->complemento)
                                <div class="col-md-6">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">Complemento</label>
                                        <p class="mb-0">{{ $empresa->complemento }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->uf)
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">UF</label>
                                        <p class="mb-0">{{ $empresa->uf }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($empresa->cep)
                                <div class="col-md-4">
                                    <div>
                                        <label class="form-label text-muted d-block mb-2">CEP</label>
                                        <p class="mb-0">{{ $empresa->cep }}</p>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Planos Contratados -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Planos Contratados</h5>
                            <p class="text-muted mb-0">{{ $empresa->planosContratados->count() }} plano(s)</p>
                        </div>
                        <div class="card-body">
                            @if($empresa->planosContratados->isEmpty())
                                <div class="text-center py-4">
                                    <i class="ti ti-package-off ti-48 text-muted mb-3"></i>
                                    <h6 class="text-muted">Nenhum plano contratado</h6>
                                    <p class="text-muted small">Esta empresa não possui planos contratados</p>
                                </div>
                            @else
                                @php $planoAtual = $empresa->planosContratados->first(); @endphp
                                <div class="d-flex flex-column gap-3">
                                    <div class="border rounded p-3 border-primary bg-light-primary">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary">Plano Atual</span>
                                            <button type="button" class="btn btn-xs btn-primary py-1" style="font-size: 0.75rem; height: auto;" data-bs-toggle="modal" data-bs-target="#modalModulosPlano" data-plano-id="{{ $planoAtual->id }}">
                                                <i class="ti ti-apps me-1"></i>
                                                Ver Módulos
                                            </button>
                                        </div>
                                        <h6 class="mb-2">{{ $planoAtual->nome }}</h6>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">Valor:</small>
                                            <strong class="text-success">{{ $planoAtual->valor_formatado }}</strong>
                                        </div>
                                        @if($planoAtual->adesao_formatada && $planoAtual->adesao_formatada !== 'R$ 0,00')
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">Adesão:</small>
                                            <strong class="text-info">{{ $planoAtual->adesao_formatada }}</strong>
                                        </div>
                                        @endif
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">Contratado em:</small>
                                            <small>{{ $planoAtual->data_contratacao_formatada }}</small>
                                        </div>
                                        @if($planoAtual->observacoes)
                                        <div class="mt-2">
                                            <small class="text-muted">Observações:</small>
                                            <p class="small mb-0">{{ $planoAtual->observacoes }}</p>
                                        </div>
                                        @endif
                                    </div>
                                    @if($empresa->planosContratados->count() > 1)
                                    <div class="text-center mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalHistoricoPlanos">
                                            <i class="ti ti-history me-1"></i>
                                            Visualizar Histórico de Planos
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Estatísticas -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Estatísticas</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Plano:</span>
                                    <strong>{{ $empresa->planosContratados->first()->nome ?? 'Nenhum' }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Usuários ativos:</span>
                                    <strong>{{ $empresa->usuarioAtivo->count() }}</strong>
                                </div>
                                @if($empresa->isMatriz())
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Filiais:</span>
                                    <strong>{{ $empresa->filiais->count() }}</strong>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Histórico de Planos -->
<div class="modal fade" id="modalHistoricoPlanos" tabindex="-1" aria-labelledby="modalHistoricoPlanosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalHistoricoPlanosLabel">
                    <i class="ti ti-history me-2"></i>
                    Histórico de Planos - {{ $empresa->nome_empresa }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($empresa->planosContratados->isEmpty())
                    <div class="text-center py-4">
                        <i class="ti ti-package-off ti-48 text-muted mb-3"></i>
                        <h6 class="text-muted">Nenhum plano encontrado</h6>
                        <p class="text-muted small">Esta empresa não possui histórico de planos</p>
                    </div>
                @else
                    <div class="row">
                        @foreach($empresa->planosContratados->sortByDesc('created_at') as $plano)
                            <div class="col-md-6 mb-3">
                                <div class="card {{ $loop->first ? 'border-primary' : 'border-light' }}">
                                    <div class="card-body">
                                        @if($loop->first)
                                            <span class="badge bg-primary mb-2">Atual</span>
                                        @else
                                            <span class="badge bg-secondary mb-2">Histórico</span>
                                        @endif
                                        <h6 class="card-title">{{ $plano->nome }}</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">Valor:</small>
                                            <strong class="text-success">{{ $plano->valor_formatado }}</strong>
                                        </div>
                                        @if($plano->adesao_formatada && $plano->adesao_formatada !== 'R$ 0,00')
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">Adesão:</small>
                                            <strong class="text-info">{{ $plano->adesao_formatada }}</strong>
                                        </div>
                                        @endif
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">Contratado:</small>
                                            <small>{{ $plano->data_contratacao_formatada }}</small>
                                        </div>
                                        @if($plano->observacoes)
                                        <div class="mt-2">
                                            <small class="text-muted">Observações:</small>
                                            <p class="small text-muted mb-0">{{ $plano->observacoes }}</p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Módulos do Plano -->
<div class="modal fade" id="modalModulosPlano" tabindex="-1" aria-labelledby="modalModulosPlanosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalModulosPlanosLabel">
                    <i class="ti ti-apps me-2"></i>
                    Módulos Contratados
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <style>
                    .list-group-item {
                        border: 1px solid rgba(0, 0, 0, 0.125);
                        margin-bottom: 0.5rem;
                        border-radius: 0.375rem !important;
                    }
                    .list-group-item:last-child {
                        margin-bottom: 0;
                    }
                    .modulo-submenu {
                        background-color: rgba(0, 0, 0, 0.02);
                        border-left: 3px solid #696cff;
                        padding: 0.75rem;
                        border-radius: 0.25rem;
                    }
                </style>
                <div id="modulosContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-muted mt-2">Carregando módulos...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <a href="#" id="btnEditarPlanoContratado" class="btn btn-primary">
                    <i class="ti ti-edit me-1"></i>
                    Editar Plano Contratado
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalModulos = document.getElementById('modalModulosPlano');
    
    modalModulos.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const planoId = button.getAttribute('data-plano-id');
        const contentDiv = document.getElementById('modulosContent');
        
        // Atualizar link do botão editar
        const btnEditar = document.getElementById('btnEditarPlanoContratado');
        btnEditar.href = `/admin/planos-contratados/${planoId}/edit`;
        
        // Mostrar loading
        contentDiv.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="text-muted mt-2">Carregando módulos...</p>
            </div>
        `;
        
        // Buscar módulos via AJAX
        fetch(`/admin/planos-contratados/${planoId}/modulos`)
            .then(response => response.json())
            .then(data => {
                if (data.modulos && data.modulos.length > 0) {
                    let html = '<div class="list-group">';
                    
                    data.modulos.forEach(modulo => {
                        const icone = modulo.icone || 'ti ti-box';
                        const limite = modulo.limite ? `<span class="badge bg-label-info ms-2">Limite: ${modulo.limite}</span>` : '';
                        const temSubmodulos = modulo.submodulos && modulo.submodulos.length > 0;
                        const badgeSubs = temSubmodulos ? `<span class="badge bg-label-primary ms-2">${modulo.submodulos.length} Sub.</span>` : '';
                        
                        // Módulo principal
                        html += `
                            <div class="list-group-item">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-3">
                                            <span class="avatar-initial rounded bg-label-primary">
                                                <i class="${icone}"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">${modulo.nome}</h6>
                                        </div>
                                    </div>
                                    <div>
                                        ${limite}
                                        ${badgeSubs}
                                    </div>
                                </div>
                        `;
                        
                        // Submódulos
                        if (temSubmodulos) {
                            html += '<div class="mt-3 ps-4">';
                            modulo.submodulos.forEach(sub => {
                                const subIcone = sub.icone || 'ti ti-box';
                                const subLimite = sub.limite ? `<span class="badge bg-label-info ms-2">Limite: ${sub.limite}</span>` : '';
                                
                                html += `
                                    <div class="d-flex align-items-center justify-content-between py-2 border-start border-3 ps-3 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-xs me-2">
                                                <span class="avatar-initial rounded bg-label-secondary">
                                                    <i class="${subIcone} ti-xs"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <small class="text-muted fw-semibold">${sub.nome}</small>
                                                <span class="badge bg-label-info ms-2" style="font-size: 0.7rem;">Submódulo</span>
                                            </div>
                                        </div>
                                        <div>
                                            ${subLimite}
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }
                        
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `
                        <div class="text-center py-4">
                            <i class="ti ti-apps-off ti-48 text-muted mb-3"></i>
                            <h6 class="text-muted">Nenhum módulo encontrado</h6>
                            <p class="text-muted small">Este plano não possui módulos contratados</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                contentDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ti ti-alert-circle me-2"></i>
                        Erro ao carregar módulos. Tente novamente.
                    </div>
                `;
            });
    });
});
</script>

@endsection