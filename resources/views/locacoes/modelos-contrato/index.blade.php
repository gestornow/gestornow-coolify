@extends('layouts.layoutMaster')

@section('title', 'Documentos de Locação')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="ti ti-file-text me-2"></i>
                            Documentos de Locação
                        </h5>
                        <small class="text-muted">Personalize contratos, orçamentos e medições da sua empresa</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('documentos.create') }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i> Novo Contrato
                        </a>
                        <a href="{{ route('documentos.create') }}?tipo=orcamento" class="btn btn-outline-primary">
                            <i class="ti ti-plus me-1"></i> Novo Orçamento
                        </a>
                        <a href="{{ route('documentos.create') }}?tipo=medicao" class="btn btn-outline-primary">
                            <i class="ti ti-plus me-1"></i> Nova Medição
                        </a>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-circle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Lista de Modelos -->
            <div class="row">
                @forelse($modelos as $modelo)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 {{ $modelo->padrao ? 'border-primary' : '' }}">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">{{ $modelo->nome }}</h6>
                                    @if($modelo->padrao)
                                        <span class="badge bg-primary"><i class="ti ti-star-filled ti-xs me-1"></i>Padrão</span>
                                    @endif
                                    @php
                                        $tipoModeloCard = method_exists($modelo, 'tipoModelo')
                                            ? $modelo->tipoModelo()
                                            : ((bool) ($modelo->usa_medicao ?? false) ? 'medicao' : 'contrato');
                                    @endphp
                                    @if($tipoModeloCard === 'medicao')
                                        <span class="badge bg-label-info">Medição</span>
                                    @elseif($tipoModeloCard === 'orcamento')
                                        <span class="badge bg-label-warning">Orçamento</span>
                                    @endif
                                    @if(!$modelo->ativo)
                                        <span class="badge bg-secondary">Inativo</span>
                                    @endif
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a href="{{ route('documentos.edit', $modelo->id_modelo) }}" class="dropdown-item">
                                                <i class="ti ti-pencil me-1"></i> Editar
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('documentos.preview', $modelo->id_modelo) }}" class="dropdown-item" target="_blank">
                                                <i class="ti ti-eye me-1"></i> Visualizar
                                            </a>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item btn-duplicar" data-id="{{ $modelo->id_modelo }}">
                                                <i class="ti ti-copy me-1"></i> Duplicar
                                            </button>
                                        </li>
                                        @if(!$modelo->padrao)
                                            <li>
                                                <button type="button" class="dropdown-item btn-definir-padrao" data-id="{{ $modelo->id_modelo }}">
                                                    <i class="ti ti-star me-1"></i> Definir como Padrão
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger btn-excluir" data-id="{{ $modelo->id_modelo }}">
                                                    <i class="ti ti-trash me-1"></i> Excluir
                                                </button>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    {{ $modelo->descricao ?? 'Sem descrição' }}
                                </p>
                                
                                <div class="d-flex justify-content-between text-muted small">
                                    <span><i class="ti ti-calendar me-1"></i>{{ $modelo->created_at->format('d/m/Y') }}</span>
                                    <span><i class="ti ti-refresh me-1"></i>{{ $modelo->updated_at->format('d/m/Y') }}</span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('documentos.edit', $modelo->id_modelo) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="ti ti-pencil me-1"></i> Editar
                                    </a>
                                    <a href="{{ route('documentos.preview', $modelo->id_modelo) }}" class="btn btn-sm btn-outline-info" target="_blank">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="ti ti-file-off ti-lg text-muted d-block mb-3"></i>
                                <h6 class="text-muted">Nenhum documento cadastrado</h6>
                                <p class="text-muted">Crie seu primeiro documento para personalizar contratos e orçamentos.</p>
                                <a href="{{ route('documentos.create') }}" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i> Criar Primeiro Documento
                                </a>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            <!-- Paginação -->
            @if($modelos->hasPages())
                <div class="d-flex justify-content-center">
                    {{ $modelos->links() }}
                </div>
            @endif
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
    // Excluir modelo
    $('.btn-excluir').on('click', function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir este modelo de contrato?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/documentos/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Excluído!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Erro', response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    // Definir como padrão
    $('.btn-definir-padrao').on('click', function() {
        var id = $(this).data('id');
        
        $.ajax({
            url: '/documentos/' + id + '/definir-padrao',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Erro', xhr.responseJSON?.message || 'Erro ao definir como padrão.', 'error');
            }
        });
    });

    // Duplicar modelo
    $('.btn-duplicar').on('click', function() {
        var id = $(this).data('id');
        
        $.ajax({
            url: '/documentos/' + id + '/duplicar',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Erro', xhr.responseJSON?.message || 'Erro ao duplicar.', 'error');
            }
        });
    });
});
</script>
@endsection
