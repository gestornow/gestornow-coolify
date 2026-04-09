@extends('layouts.layoutMaster')

@section('title', 'Detalhes do Plano')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="row">
                <!-- Informações do Plano -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Informações do Plano</h5>
                            <a href="{{ route('admin.planos.edit', $plano) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-edit me-1"></i>
                                Editar
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">Nome do Plano</label>
                                    <p class="fw-bold">{{ $plano->nome }}</p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-muted">Valor Mensal</label>
                                    <p class="fw-bold text-success">{{ $plano->valor_formatado }}</p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label text-muted">Taxa de Adesão</label>
                                    <p class="fw-bold text-info">{{ $plano->adesao_formatada }}</p>
                                </div>
                                @if($plano->descricao)
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label text-muted">Descrição</label>
                                        <p>{{ $plano->descricao }}</p>
                                    </div>
                                @endif
                                <div class="col-md-12">
                                    <label class="form-label text-muted">Data de Criação</label>
                                    <p>{{ $plano->criado_em ? $plano->criado_em->format('d/m/Y H:i') : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Módulos do Plano -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Módulos Inclusos</h5>
                            <p class="text-muted mb-0">{{ $plano->modulos->count() }} módulo(s) ativo(s)</p>
                        </div>
                        <div class="card-body">
                            @if($plano->modulos->isEmpty())
                                <div class="text-center py-4">
                                    <i class="ti ti-package-off ti-48 text-muted mb-3"></i>
                                    <h6 class="text-muted">Nenhum módulo configurado</h6>
                                    <p class="text-muted small">Este plano não possui módulos configurados</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-borderless">
                                        <thead>
                                            <tr>
                                                <th>Módulo</th>
                                                <th>Limite</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($plano->modulos as $planoModulo)
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong>{{ $planoModulo->modulo->nome ?? 'Módulo não encontrado' }}</strong>
                                                            @if($planoModulo->modulo && $planoModulo->modulo->descricao)
                                                                <br><small class="text-muted">{{ $planoModulo->modulo->descricao }}</small>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-label-secondary">{{ $planoModulo->getLimiteFormatado() }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recursos do Sistema -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recursos do Sistema</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Relatórios</span>
                                    @if($plano->temRelatorios())
                                        <span class="badge bg-success">Incluído</span>
                                    @else
                                        <span class="badge bg-danger">Não incluído</span>
                                    @endif
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Bancos</span>
                                    @if($plano->temBancos())
                                        <span class="badge bg-success">Incluído</span>
                                    @else
                                        <span class="badge bg-danger">Não incluído</span>
                                    @endif
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Assinatura Digital</span>
                                    @if($plano->temAssinaturaDigital())
                                        <span class="badge bg-success">Incluído</span>
                                    @else
                                        <span class="badge bg-danger">Não incluído</span>
                                    @endif
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Contratos</span>
                                    @if($plano->temContratos())
                                        <span class="badge bg-success">Incluído</span>
                                    @else
                                        <span class="badge bg-danger">Não incluído</span>
                                    @endif
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Faturas</span>
                                    @if($plano->temFaturas())
                                        <span class="badge bg-success">Incluído</span>
                                    @else
                                        <span class="badge bg-danger">Não incluído</span>
                                    @endif
                                </div>
                            </div>
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
                                    <span class="text-muted">Total de módulos:</span>
                                    <strong>{{ $plano->modulos->count() }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Recursos ativos:</span>
                                    <strong>{{ count($plano->getRecursosAtivos()) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Contratos ativos:</span>
                                    <strong>{{ \App\Models\PlanoContratado::where('nome', $plano->nome)->count() }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection