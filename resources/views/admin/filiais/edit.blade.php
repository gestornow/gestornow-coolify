@extends('layouts.layoutMaster')

@section('title', 'Editar Filial - ' . $empresa->nome_empresa)

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
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
            <form action="{{ route('admin.filiais.update', $empresa) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <!-- Informações da Empresa -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Editar Informações da Empresa</h5>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('admin.filiais.show', $empresa) }}" class="btn btn-secondary btn-sm">
                                        <i class="ti ti-x me-1"></i>
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        Salvar
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="nome_empresa">Nome da Empresa <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('nome_empresa') is-invalid @enderror" 
                                               id="nome_empresa" name="nome_empresa" 
                                               value="{{ old('nome_empresa', $empresa->nome_empresa) }}" required>
                                        @error('nome_empresa')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="razao_social">Razão Social <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('razao_social') is-invalid @enderror" 
                                               id="razao_social" name="razao_social" 
                                               value="{{ old('razao_social', $empresa->razao_social) }}" required>
                                        @error('razao_social')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="cnpj">CNPJ <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('cnpj') is-invalid @enderror" 
                                               id="cnpj" name="cnpj" 
                                               value="{{ old('cnpj', $empresa->cnpj_formatado) }}" required>
                                        @error('cnpj')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="cpf">CPF</label>
                                        <input type="text" class="form-control @error('cpf') is-invalid @enderror" 
                                               id="cpf" name="cpf" 
                                               value="{{ old('cpf', $empresa->cpf_formatado) }}">
                                        @error('cpf')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="codigo">Código</label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror" 
                                               id="codigo" name="codigo" 
                                               value="{{ old('codigo', $empresa->codigo) }}">
                                        @error('codigo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="filial">Tipo <span class="text-danger">*</span></label>
                                        <select class="form-select @error('filial') is-invalid @enderror" id="filial" name="filial" required>
                                            <option value="">Selecione...</option>
                                            <option value="Unica" {{ old('filial', $empresa->filial) == 'Unica' ? 'selected' : '' }}>Única</option>
                                            <option value="Matriz" {{ old('filial', $empresa->filial) == 'Matriz' ? 'selected' : '' }}>Matriz</option>
                                            <option value="Filial" {{ old('filial', $empresa->filial) == 'Filial' ? 'selected' : '' }}>Filial</option>
                                        </select>
                                        @error('filial')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                            <option value="">Selecione...</option>
                                            <option value="ativo" {{ old('status', $empresa->status) == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                            <option value="inativo" {{ old('status', $empresa->status) == 'inativo' ? 'selected' : '' }}>Inativo</option>
                                            <option value="bloqueado" {{ old('status', $empresa->status) == 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                                            <option value="validacao" {{ old('status', $empresa->status) == 'validacao' ? 'selected' : '' }}>Em Validação</option>
                                            <option value="teste" {{ old('status', $empresa->status) == 'teste' ? 'selected' : '' }}>Teste</option>
                                            <option value="cancelado" {{ old('status', $empresa->status) == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                            <option value="teste bloqueado" {{ old('status', $empresa->status) == 'teste bloqueado' ? 'selected' : '' }}>Teste Bloqueado</option>
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="dados_cadastrais">Dados Cadastrais <span class="text-danger">*</span></label>
                                        <select class="form-select @error('dados_cadastrais') is-invalid @enderror" id="dados_cadastrais" name="dados_cadastrais" required>
                                            <option value="">Selecione...</option>
                                            <option value="incompleto" {{ old('dados_cadastrais', $empresa->dados_cadastrais) == 'incompleto' ? 'selected' : '' }}>Incompleto</option>
                                            <option value="completo" {{ old('dados_cadastrais', $empresa->dados_cadastrais) == 'completo' ? 'selected' : '' }}>Completo</option>
                                        </select>
                                        @error('dados_cadastrais')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="email">E-mail</label>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                               id="email" name="email" 
                                               value="{{ old('email', $empresa->email) }}">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="telefone">Telefone</label>
                                        <input type="text" class="form-control @error('telefone') is-invalid @enderror" 
                                               id="telefone" name="telefone" 
                                               value="{{ old('telefone', $empresa->telefone_formatado) }}">
                                        @error('telefone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="ie">Inscrição Estadual</label>
                                        <input type="text" class="form-control @error('ie') is-invalid @enderror" 
                                               id="ie" name="ie" 
                                               value="{{ old('ie', $empresa->ie) }}">
                                        @error('ie')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="im">Inscrição Municipal</label>
                                        <input type="text" class="form-control @error('im') is-invalid @enderror" 
                                               id="im" name="im" 
                                               value="{{ old('im', $empresa->im) }}">
                                        @error('im')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Endereço -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Endereço</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label" for="endereco">Endereço</label>
                                        <input type="text" class="form-control @error('endereco') is-invalid @enderror" 
                                               id="endereco" name="endereco" 
                                               value="{{ old('endereco', $empresa->endereco) }}">
                                        @error('endereco')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="numero">Número</label>
                                        <input type="text" class="form-control @error('numero') is-invalid @enderror" 
                                               id="numero" name="numero" 
                                               value="{{ old('numero', $empresa->numero) }}">
                                        @error('numero')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="bairro">Bairro</label>
                                        <input type="text" class="form-control @error('bairro') is-invalid @enderror" 
                                               id="bairro" name="bairro" 
                                               value="{{ old('bairro', $empresa->bairro) }}">
                                        @error('bairro')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="complemento">Complemento</label>
                                        <input type="text" class="form-control @error('complemento') is-invalid @enderror" 
                                               id="complemento" name="complemento" 
                                               value="{{ old('complemento', $empresa->complemento) }}">
                                        @error('complemento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="uf">UF</label>
                                        <select class="form-select @error('uf') is-invalid @enderror" id="uf" name="uf">
                                            <option value="">Selecione...</option>
                                            <option value="AC" {{ old('uf', $empresa->uf) == 'AC' ? 'selected' : '' }}>AC</option>
                                            <option value="AL" {{ old('uf', $empresa->uf) == 'AL' ? 'selected' : '' }}>AL</option>
                                            <option value="AP" {{ old('uf', $empresa->uf) == 'AP' ? 'selected' : '' }}>AP</option>
                                            <option value="AM" {{ old('uf', $empresa->uf) == 'AM' ? 'selected' : '' }}>AM</option>
                                            <option value="BA" {{ old('uf', $empresa->uf) == 'BA' ? 'selected' : '' }}>BA</option>
                                            <option value="CE" {{ old('uf', $empresa->uf) == 'CE' ? 'selected' : '' }}>CE</option>
                                            <option value="DF" {{ old('uf', $empresa->uf) == 'DF' ? 'selected' : '' }}>DF</option>
                                            <option value="ES" {{ old('uf', $empresa->uf) == 'ES' ? 'selected' : '' }}>ES</option>
                                            <option value="GO" {{ old('uf', $empresa->uf) == 'GO' ? 'selected' : '' }}>GO</option>
                                            <option value="MA" {{ old('uf', $empresa->uf) == 'MA' ? 'selected' : '' }}>MA</option>
                                            <option value="MT" {{ old('uf', $empresa->uf) == 'MT' ? 'selected' : '' }}>MT</option>
                                            <option value="MS" {{ old('uf', $empresa->uf) == 'MS' ? 'selected' : '' }}>MS</option>
                                            <option value="MG" {{ old('uf', $empresa->uf) == 'MG' ? 'selected' : '' }}>MG</option>
                                            <option value="PA" {{ old('uf', $empresa->uf) == 'PA' ? 'selected' : '' }}>PA</option>
                                            <option value="PB" {{ old('uf', $empresa->uf) == 'PB' ? 'selected' : '' }}>PB</option>
                                            <option value="PR" {{ old('uf', $empresa->uf) == 'PR' ? 'selected' : '' }}>PR</option>
                                            <option value="PE" {{ old('uf', $empresa->uf) == 'PE' ? 'selected' : '' }}>PE</option>
                                            <option value="PI" {{ old('uf', $empresa->uf) == 'PI' ? 'selected' : '' }}>PI</option>
                                            <option value="RJ" {{ old('uf', $empresa->uf) == 'RJ' ? 'selected' : '' }}>RJ</option>
                                            <option value="RN" {{ old('uf', $empresa->uf) == 'RN' ? 'selected' : '' }}>RN</option>
                                            <option value="RS" {{ old('uf', $empresa->uf) == 'RS' ? 'selected' : '' }}>RS</option>
                                            <option value="RO" {{ old('uf', $empresa->uf) == 'RO' ? 'selected' : '' }}>RO</option>
                                            <option value="RR" {{ old('uf', $empresa->uf) == 'RR' ? 'selected' : '' }}>RR</option>
                                            <option value="SC" {{ old('uf', $empresa->uf) == 'SC' ? 'selected' : '' }}>SC</option>
                                            <option value="SP" {{ old('uf', $empresa->uf) == 'SP' ? 'selected' : '' }}>SP</option>
                                            <option value="SE" {{ old('uf', $empresa->uf) == 'SE' ? 'selected' : '' }}>SE</option>
                                            <option value="TO" {{ old('uf', $empresa->uf) == 'TO' ? 'selected' : '' }}>TO</option>
                                        </select>
                                        @error('uf')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="cep">CEP</label>
                                        <input type="text" class="form-control @error('cep') is-invalid @enderror" 
                                               id="cep" name="cep" 
                                               value="{{ old('cep', $empresa->cep) }}">
                                        @error('cep')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo e Ações -->
                    <div class="col-lg-4">
                        <!-- Plano Contratado -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Plano Contratado</h5>
                                <p class="text-muted mb-0">{{ $empresa->planosContratados->count() }} plano(s)</p>
                            </div>
                            <div class="card-body">
                                @if($empresa->planosContratados->isEmpty())
                                    <div class="text-center py-4">
                                        <i class="ti ti-package-off ti-48 text-muted mb-3"></i>
                                        <h6 class="text-muted">Nenhum plano contratado</h6>
                                        <p class="text-muted small">Esta empresa não possui planos contratados</p>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalContratarPlano">
                                            <i class="ti ti-plus me-1"></i>
                                            Contratar Plano
                                        </button>
                                    </div>
                                @else
                                    @php $planoAtual = $empresa->planosContratados->sortByDesc('created_at')->first(); @endphp
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
                                        <div class="text-center mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalContratarPlano">
                                                <i class="ti ti-edit me-1"></i>
                                                Alterar Plano
                                            </button>
                                        </div>
                                        @if($empresa->planosContratados->count() > 1)
                                        <div class="text-center">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalHistoricoPlanos">
                                                <i class="ti ti-history me-1"></i>
                                                Ver Histórico
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card position-sticky" style="top: 20px;">
                            <div class="card-header">
                                <h5 class="mb-0">Resumo</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="text-muted">Empresa Atual</h6>
                                    <p class="fw-bold">{{ $empresa->nome_empresa }}</p>
                                    <small class="text-muted">{{ $empresa->cnpj_formatado }}</small>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="text-muted">Status Atual</h6>
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
                                    
                                    @if(in_array($empresa->status, ['bloqueado', 'inativo']) && $empresa->data_bloqueio)
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                {{ $empresa->status === 'bloqueado' ? 'Bloqueado' : 'Inativado' }} em: {{ $empresa->data_bloqueio->format('d/m/Y H:i') }}
                                            </small>
                                        </div>
                                    @endif
                                    
                                    @if($empresa->status === 'cancelado' && $empresa->data_cancelamento)
                                        <div class="mt-2">
                                            <small class="text-muted">Cancelado em: {{ $empresa->data_cancelamento->format('d/m/Y H:i') }}</small>
                                        </div>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted">Tipo Atual</h6>
                                    @if($empresa->filial)
                                        <span class="badge bg-label-{{ $empresa->filial == 'Matriz' ? 'primary' : ($empresa->filial == 'Filial' ? 'info' : 'secondary') }}">
                                            {{ $empresa->filial }}
                                        </span>
                                    @else
                                        <span class="text-muted">Não definido</span>
                                    @endif
                                </div>

                                <hr>

                                <!-- Botões de Ação de Status -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-3">Ações Rápidas</h6>
                                    <div class="d-flex flex-column gap-2">
                                        @php
                                            $currentStatus = $empresa->status;
                                        @endphp

                                        <!-- Botão Ativar: mostrar se está inativo, bloqueado, teste bloqueado ou cancelado -->
                                        @if(in_array($currentStatus, ['inativo', 'bloqueado', 'teste bloqueado', 'cancelado', 'validacao', 'teste']))
                                            <button type="button" class="btn btn-success btn-status-action" data-status="ativo" data-message="Tem certeza que deseja ativar esta filial?">
                                                <i class="ti ti-circle-check me-2"></i>
                                                Ativar Filial
                                            </button>
                                        @endif

                                        <!-- Botão Inativar: mostrar se está ativo -->
                                        @if($currentStatus === 'ativo')
                                            <button type="button" class="btn btn-warning btn-status-action" data-status="inativo" data-message="Tem certeza que deseja inativar esta filial?">
                                                <i class="ti ti-circle-x me-2"></i>
                                                Inativar Filial
                                            </button>
                                        @endif

                                        <!-- Botão Bloquear: mostrar se está ativo -->
                                        @if($currentStatus === 'ativo')
                                            <button type="button" class="btn btn-danger btn-status-action" data-status="bloqueado" data-message="Tem certeza que deseja bloquear esta filial?">
                                                <i class="ti ti-lock me-2"></i>
                                                Bloquear Filial
                                            </button>
                                        @endif

                                        <!-- Botão Cancelar: mostrar se está ativo, bloqueado ou inativo -->
                                        @if(in_array($currentStatus, ['ativo', 'bloqueado', 'inativo']))
                                            <button type="button" class="btn btn-dark btn-status-action" data-status="cancelado" data-message="Tem certeza que deseja cancelar esta filial? Esta ação não pode ser desfeita." data-confirm-text="Sim, cancelar">
                                                <i class="ti ti-ban me-2"></i>
                                                Cancelar Filial
                                            </button>
                                        @endif

                                        <!-- Botão Desbloquear: mostrar se está bloqueado -->
                                        @if($currentStatus === 'bloqueado')
                                            <button type="button" class="btn btn-info btn-status-action" data-status="ativo" data-message="Tem certeza que deseja desbloquear esta filial?">
                                                <i class="ti ti-lock-open me-2"></i>
                                                Desbloquear Filial
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Formulário oculto para ações de status -->
            <form id="statusForm" method="POST" style="display: none;">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" id="statusInput">
            </form>
        </div>
    </div>
</div>

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

<!-- Modal Contratar Plano -->
<div class="modal fade" id="modalContratarPlano" tabindex="-1" aria-labelledby="modalContratarPlanoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalContratarPlanoLabel">Contratar Plano</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="formContratarPlano">
                    @csrf
                    <input type="hidden" name="id_empresa" id="modal_id_empresa" value="{{ $empresa->id_empresa }}">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Plano</label>
                            <select class="form-select" id="modal_id_plano" name="id_plano" required>
                                <option value="">Selecione um plano...</option>
                                @if(isset($planos))
                                    @foreach($planos as $plano)
                                        <option value="{{ $plano->id_plano }}" data-valor="{{ $plano->valor }}" data-adesao="{{ $plano->adesao }}">{{ $plano->nome }} - {{ $plano->valor_formatado }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Valor (R$)</label>
                            <input type="text" class="form-control money-mask" id="modal_valor" name="valor" placeholder="R$ 0,00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Adesão (R$)</label>
                            <input type="text" class="form-control money-mask" id="modal_adesao" name="adesao" placeholder="R$ 0,00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Método da Adesão</label>
                            <select class="form-select" id="modal_metodo_adesao" name="metodo_adesao" required>
                                <option value="PIX">PIX</option>
                                <option value="BOLETO">Boleto</option>
                                <option value="CREDIT_CARD">Cartão de Crédito</option>
                                <option value="DEBIT_CARD">Cartão de Débito</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Método das Mensalidades</label>
                            <select class="form-select" id="modal_metodo_mensal" name="metodo_mensal" required>
                                <option value="BOLETO">Boleto</option>
                                <option value="CREDIT_CARD">Cartão de Crédito</option>
                                <option value="DEBIT_CARD">Cartão de Débito</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Observações</label>
                            <input type="text" class="form-control" id="modal_observacoes" name="observacoes" placeholder="Observações (opcional)">
                        </div>
                        <div class="col-md-12 mb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_gerar_link_pagamento" name="gerar_link_pagamento" checked>
                                <label class="form-check-label" for="modal_gerar_link_pagamento">
                                    Gerar cobranças automáticas no Asaas (adesão agora e mensalidade D+30)
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnContratarPlanoConfirm">Contratar</button>
            </div>
        </div>
    </div>
</div>

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== TRATAMENTO DOS BOTÕES DE AÇÕES RÁPIDAS (STATUS) =====
    const statusButtons = document.querySelectorAll('.btn-status-action');
    const statusForm = document.getElementById('statusForm');
    const statusInput = document.getElementById('statusInput');

    statusButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            const message = this.getAttribute('data-message');
            const confirmText = this.getAttribute('data-confirm-text') || 'Sim, alterar';

            Swal.fire({
                title: 'Confirmar Alteração',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Não',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Preencher o formulário e submeter
                    statusInput.value = status;
                    statusForm.action = '{{ route("admin.filiais.update-status", $empresa) }}';
                    statusForm.submit();
                }
            });
        });
    });

    // ===== MÁSCARAS E OUTROS SCRIPTS =====
    // Máscaras para os campos usando Cleave.js
    if (document.getElementById('cnpj')) {
        new Cleave('#cnpj', {
            delimiters: ['.', '.', '/', '-'],
            blocks: [2, 3, 3, 4, 2],
            uppercase: false
        });
    }
    
    if (document.getElementById('cpf')) {
        new Cleave('#cpf', {
            delimiters: ['.', '.', '-'],
            blocks: [3, 3, 3, 2],
            uppercase: false
        });
    }
    
    if (document.getElementById('telefone')) {
        new Cleave('#telefone', {
            phone: true,
            phoneRegionCode: 'BR'
        });
    }
    
    if (document.getElementById('cep')) {
        new Cleave('#cep', {
            delimiters: ['-'],
            blocks: [5, 3]
        });
    }

    // Máscara monetária para inputs do modal - REMOVIDO Cleave.js
    // Vamos usar formatação manual em vez de Cleave para evitar conflitos
    
    // Função para formatar valor monetário enquanto digita
    function formatarMoeda(input) {
        let valor = input.value.replace(/\D/g, ''); // Remove tudo que não é número
        if (valor === '') {
            input.value = '';
            return;
        }
        
        // Converte para número com centavos
        valor = (parseInt(valor) / 100).toFixed(2);
        
        // Formata com separadores
        const partes = valor.split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        
        input.value = 'R$ ' + partes.join(',');
    }
    
    // Adicionar evento de input nos campos monetários
    const modalValorInput = document.getElementById('modal_valor');
    const modalAdesaoInput = document.getElementById('modal_adesao');
    
    if (modalValorInput) {
        modalValorInput.addEventListener('input', function() {
            formatarMoeda(this);
        });
    }
    
    if (modalAdesaoInput) {
        modalAdesaoInput.addEventListener('input', function() {
            formatarMoeda(this);
        });
    }
    
    // Auto-preenchimento de valores do plano
    const planoSelect = document.getElementById('id_plano');
    if (planoSelect) {
        planoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const valor = selectedOption.getAttribute('data-valor');
            const adesao = selectedOption.getAttribute('data-adesao');
            
            const valorInput = document.getElementById('valor_plano');
            const adesaoInput = document.getElementById('adesao_plano');
            
            if (valorInput) {
                valorInput.value = valor || '';
            }
            
            if (adesaoInput) {
                adesaoInput.value = adesao || '';
            }
        });
    }

    // Modal contratar plano: preencher valores ao selecionar e submeter via AJAX
    const modalPlanoSelect = document.getElementById('modal_id_plano');
    if (modalPlanoSelect) {
        modalPlanoSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const v = opt ? opt.getAttribute('data-valor') : '';
            const a = opt ? opt.getAttribute('data-adesao') : '';
            
            // Preencher valores formatados diretamente
            if (v) {
                const valorFormatado = parseFloat(v).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('modal_valor').value = 'R$ ' + valorFormatado;
            } else {
                document.getElementById('modal_valor').value = '';
            }
            
            if (a) {
                const adesaoFormatada = parseFloat(a).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('modal_adesao').value = 'R$ ' + adesaoFormatada;
            } else {
                document.getElementById('modal_adesao').value = '';
            }
        });
    }

    // Função para converter valor formatado para número
    function parseCurrencyValue(value) {
        if (!value) return null;
        // Remove R$, espaços, pontos (separador de milhar) e converte vírgula para ponto
        const cleaned = value.replace(/R\$\s?/g, '').replace(/\./g, '').replace(',', '.');
        const parsed = parseFloat(cleaned);
        return isNaN(parsed) ? null : parsed;
    }

    // Função para atualizar o card de plano dinamicamente
    function atualizarCardPlano(planoData) {
        // Busca por todas as divs com classe card
        const cards = document.querySelectorAll('.card');
        let cardBody = null;
        let cardHeader = null;
        
        // Procura pelo card que contém o título "Plano Contratado"
        cards.forEach(card => {
            const h5Elements = card.querySelectorAll('h5');
            h5Elements.forEach(h5 => {
                if (h5.textContent.includes('Plano Contratado')) {
                    cardBody = card.querySelector('.card-body');
                    cardHeader = card.querySelector('.card-header');
                }
            });
        });
        
        if (cardBody && cardHeader) {
            // Atualiza o contador no header
            const pElement = cardHeader.querySelector('p.text-muted');
            if (pElement) {
                pElement.textContent = '1 plano(s)';
            }
            
            // Atualiza o conteúdo do body
            cardBody.innerHTML = `
                <div class="d-flex flex-column gap-3">
                    <div class="border rounded p-3 border-primary bg-light-primary">
                        <span class="badge bg-primary mb-2">Plano Atual</span>
                        <h6 class="mb-2">${planoData.nome}</h6>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Valor:</small>
                            <strong class="text-success">${planoData.valor_formatado}</strong>
                        </div>
                        ${planoData.adesao_formatada && planoData.adesao_formatada !== 'R$ 0,00' ? `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Adesão:</small>
                            <strong class="text-info">${planoData.adesao_formatada}</strong>
                        </div>
                        ` : ''}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Contratado em:</small>
                            <small>${planoData.data_contratacao_formatada}</small>
                        </div>
                        ${planoData.observacoes ? `
                        <div class="mt-2">
                            <small class="text-muted">Observações:</small>
                            <p class="small mb-0">${planoData.observacoes}</p>
                        </div>
                        ` : ''}
                    </div>
                    <div class="text-center mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalContratarPlano">
                            <i class="ti ti-edit me-1"></i>
                            Alterar Plano
                        </button>
                    </div>
                </div>
            `;
        }
    }

    const btnContratar = document.getElementById('btnContratarPlanoConfirm');
    if (btnContratar) {
        btnContratar.addEventListener('click', function() {
            const form = document.getElementById('formContratarPlano');
            const idEmpresa = document.getElementById('modal_id_empresa').value;
            const idPlano = document.getElementById('modal_id_plano').value;
            const valorRaw = document.getElementById('modal_valor').value;
            const adesaoRaw = document.getElementById('modal_adesao').value;
            const metodoAdesao = document.getElementById('modal_metodo_adesao').value;
            const metodoMensal = document.getElementById('modal_metodo_mensal').value;
            const gerarLinkPagamento = document.getElementById('modal_gerar_link_pagamento').checked;
            const observacoes = document.getElementById('modal_observacoes').value;

            if (!idPlano) {
                alert('Selecione um plano.');
                return;
            }

            // Converter valores formatados para números
            const valor = parseCurrencyValue(valorRaw);
            const adesao = parseCurrencyValue(adesaoRaw);

            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            btnContratar.disabled = true;
            btnContratar.innerHTML = 'Aguarde...';

            const rotaContratar = '/admin/assinaturas/contratar-plano';

            fetch(rotaContratar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id_empresa: idEmpresa,
                    id_plano: idPlano,
                    metodo_adesao: metodoAdesao,
                    metodo_mensal: metodoMensal,
                    gerar_link_pagamento: gerarLinkPagamento,
                    valor: valor,
                    adesao: adesao,
                    observacoes: observacoes || null
                })
            })
            .then(response => response.json().then(data => ({status: response.status, body: data})))
            .then(({status, body}) => {
                btnContratar.disabled = false;
                btnContratar.innerHTML = 'Contratar';
                if (status >= 200 && status < 300 && body.success) {
                    // fechar modal
                    const modalEl = document.getElementById('modalContratarPlano');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    
                    // atualizar card dinamicamente quando o plano já for ativado no ato
                    if (body.plano_contratado) {
                        atualizarCardPlano(body.plano_contratado);
                    }
                    
                    // limpar formulário do modal
                    form.reset();
                    document.getElementById('modal_gerar_link_pagamento').checked = true;

                    const links = [];

                    if (body.pagamento_adesao) {
                        if (body.pagamento_adesao.url) {
                            links.push(`<a href="${body.pagamento_adesao.url}" target="_blank" rel="noopener">Pagamento da Adesão</a>`);
                        }

                        if (body.pagamento_adesao.boleto) {
                            links.push(`<a href="${body.pagamento_adesao.boleto}" target="_blank" rel="noopener">Boleto da Adesão</a>`);
                        }

                        if (body.pagamento_adesao.pix_copy_paste) {
                            links.push(`<button type="button" class="btn btn-sm btn-label-info ms-2 btn-copy-pix-inline" data-pix="${body.pagamento_adesao.pix_copy_paste}">Copiar PIX da Adesão</button>`);
                        }
                    }

                    if (body.pagamento_mensal) {
                        if (body.pagamento_mensal.url) {
                            links.push(`<a href="${body.pagamento_mensal.url}" target="_blank" rel="noopener">Pagamento da Mensalidade</a>`);
                        }

                        if (body.pagamento_mensal.boleto) {
                            links.push(`<a href="${body.pagamento_mensal.boleto}" target="_blank" rel="noopener">Boleto da Mensalidade</a>`);
                        }
                    }

                    if (body.mensal_assinatura && body.mensal_assinatura.id) {
                        const statusAssinatura = body.mensal_assinatura.status || 'PENDING';
                        const vencimentoAssinatura = body.mensal_assinatura.next_due_date
                            ? ` | Próx. vencimento: ${body.mensal_assinatura.next_due_date}`
                            : '';

                        links.push(`<span class="badge bg-label-primary">Assinatura recorrente Asaas: ${body.mensal_assinatura.id} (${statusAssinatura})${vencimentoAssinatura}</span>`);
                    }
                    
                    // mostrar notificação de sucesso
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="ti ti-check me-2"></i>
                        ${body.message}
                        ${links.length ? `<div class="mt-2 d-flex flex-wrap gap-2">${links.join('')}</div>` : ''}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;

                    alertDiv.querySelectorAll('.btn-copy-pix-inline').forEach(btnPix => {
                        btnPix.addEventListener('click', function () {
                            const pix = this.getAttribute('data-pix') || '';
                            if (!pix) {
                                return;
                            }

                            navigator.clipboard.writeText(pix).then(() => {
                                this.textContent = 'PIX Copiado';
                            });
                        });
                    });
                    
                    // Inserir antes do primeiro card
                    const firstCard = document.querySelector('.card');
                    if (firstCard) {
                        firstCard.parentNode.insertBefore(alertDiv, firstCard);
                        // Remover após 5 segundos
                        setTimeout(() => {
                            if (alertDiv && alertDiv.parentNode) {
                                alertDiv.remove();
                            }
                        }, 5000);
                    }
                } else {
                    let msg = body.message || 'Erro ao contratar plano.';
                    if (body.errors) {
                        // mostrar primeiro erro
                        const firstKey = Object.keys(body.errors)[0];
                        msg = body.errors[firstKey][0];
                    }
                    alert(msg);
                }
            })
            .catch(err => {
                btnContratar.disabled = false;
                btnContratar.innerHTML = 'Contratar';
                alert('Erro ao contratar plano: ' + err.message);
            });
        });
    }

    // ===== MODAL DE MÓDULOS DO PLANO =====
    const modalModulos = document.getElementById('modalModulosPlano');
    
    if (modalModulos) {
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
    }
});
</script>
@endsection

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

@endsection