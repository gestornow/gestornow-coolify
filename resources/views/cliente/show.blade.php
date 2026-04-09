@extends('layouts.layoutMaster')

@section('title', 'Detalhes do Cliente - ' . ($cliente->nome ?? 'Cliente'))

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Cliente: {{ $cliente->nome }}</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-arrow-left me-1"></i>Voltar
                        </a>
                        @pode('clientes.editar')
                            <a href="{{ route('clientes.editar', $cliente->id_clientes) }}" class="btn btn-primary">
                                <i class="ti ti-edit me-1"></i>Editar
                            </a>
                        @endpode
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Foto do Cliente -->
                        @if($cliente->foto_url)
                        <div class="col-12 text-center mb-3">
                            <img src="{{ $cliente->foto_url }}" alt="Foto do Cliente" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                        @endif

                        <!-- Dados Básicos -->
                        <div class="col-12">
                            <h6 class="text-muted mb-3">Dados Básicos</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted">Nome</label>
                            <div class="fw-bold">{{ $cliente->nome }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted">Razão Social / Nome Fantasia</label>
                            <div>{{ $cliente->razao_social ?? '-' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Tipo de Pessoa</label>
                            <div>
                                @if($cliente->id_tipo_pessoa == 1)
                                    <span class="badge bg-label-info">Pessoa Física</span>
                                @else
                                    <span class="badge bg-label-warning">Pessoa Jurídica</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">CPF/CNPJ</label>
                            <div>{{ $cliente->cpf_cnpj_formatado }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">RG/IE</label>
                            <div>{{ $cliente->rg_ie ?? '-' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Email</label>
                            <div>{{ $cliente->email ?? '-' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Telefone</label>
                            <div>{{ $cliente->telefone_formatado }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Data de Nascimento</label>
                            <div>{{ $cliente->data_nascimento ? $cliente->data_nascimento->format('d/m/Y') : '-' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Status</label>
                            <div>
                                @if($cliente->status === 'ativo')
                                    <span class="badge bg-label-success">Ativo</span>
                                @elseif($cliente->status === 'inativo')
                                    <span class="badge bg-label-warning">Inativo</span>
                                @elseif($cliente->status === 'bloqueado')
                                    <span class="badge bg-label-danger">Bloqueado</span>
                                @else
                                    <span class="badge bg-label-secondary">Indefinido</span>
                                @endif
                            </div>
                        </div>

                        <!-- Endereço -->
                        <div class="col-12 mt-4">
                            <h6 class="text-muted mb-3">Endereço</h6>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted">CEP</label>
                            <div>{{ $cliente->cep_formatado }}</div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label small text-muted">Endereço</label>
                            <div>{{ $cliente->endereco ?? '-' }}</div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small text-muted">Número</label>
                            <div>{{ $cliente->numero ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted">Complemento</label>
                            <div>{{ $cliente->complemento ?? '-' }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted">Bairro</label>
                            <div>{{ $cliente->bairro ?? '-' }}</div>
                        </div>

                        <!-- Endereço de Entrega -->
                        @if($cliente->endereco_entrega || $cliente->cep_entrega)
                        <div class="col-12 mt-4">
                            <h6 class="text-muted mb-3">Endereço de Entrega</h6>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted">CEP</label>
                            <div>{{ $cliente->cep_entrega ? preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/[^0-9]/', '', $cliente->cep_entrega)) : '-' }}</div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label small text-muted">Endereço</label>
                            <div>{{ $cliente->endereco_entrega ?? '-' }}</div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small text-muted">Número</label>
                            <div>{{ $cliente->numero_entrega ?? '-' }}</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small text-muted">Complemento</label>
                            <div>{{ $cliente->complemento_entrega ?? '-' }}</div>
                        </div>
                        @endif

                        <!-- Informações do Sistema -->
                        <div class="col-12 mt-4">
                            <h6 class="text-muted mb-3">Informações do Sistema</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Cadastrado em</label>
                            <div>{{ $cliente->created_at ? $cliente->created_at->format('d/m/Y H:i') : '-' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Última Atualização</label>
                            <div>{{ $cliente->updated_at ? $cliente->updated_at->format('d/m/Y H:i') : '-' }}</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Empresa</label>
                            <div>{{ $cliente->empresa->nome_empresa ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
