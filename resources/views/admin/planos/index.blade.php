@extends('layouts.layoutMaster')

@section('title', 'Gerenciar Planos')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
    /* Tabela responsiva */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Garantir que dropdown fique acima de outros elementos */
    .dropdown-menu {
        z-index: 1050;
        box-shadow: 0 0.25rem 1rem rgba(161, 172, 184, 0.45);
    }
    
    /* Posição do dropdown em desktop */
    @media (min-width: 992px) {
        .table .dropdown {
            position: static;
        }
        
        .card-body {
            overflow: visible !important;
        }
    }
    
    /* Ajustes para telas menores */
    @media (max-width: 991px) {
        /* Tornar a tabela scrollável horizontalmente */
        .table-responsive {
            margin: 0 -1rem;
            padding: 0 1rem;
        }
        
        /* Ajustar tamanho de fonte */
        .table {
            font-size: 0.875rem;
        }
        
        /* Compactar badges */
        .table .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Botões de ação mais compactos */
        .dropdown-toggle {
            font-size: 1.25rem;
        }
    }
    
    /* Ajustes para mobile */
    @media (max-width: 576px) {
        /* Cards estatísticos em coluna única */
        .stats-cards .col-md-4 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        
        /* Ocultar colunas menos importantes em mobile */
        .table th:nth-child(7),
        .table td:nth-child(7),
        .table th:nth-child(8),
        .table td:nth-child(8) {
            display: none;
        }
        
        /* Ajustar header de filtros */
        .filter-header {
            flex-direction: column;
            gap: 0.75rem !important;
        }
        
        .filter-header > div {
            width: 100%;
        }
        
        .filter-header form {
            width: 100%;
            flex-direction: column;
        }
        
        .filter-header .input-group,
        .filter-header .form-select {
            width: 100% !important;
        }
    }
    
    /* Estilo para módulos com submódulos */
    .modulo-com-subs:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .modulo-principal-cell {
        cursor: default;
    }
    
    .modulo-principal-cell[data-has-subs="true"] {
        cursor: pointer;
        user-select: none;
    }
    
    /* Ícone de expansão */
    .icon-expand {
        transition: transform 0.3s ease;
        display: inline-block;
    }
    
    .icon-expand.expanded {
        transform: rotate(90deg);
    }
    
    /* Submódulos */
    .submodulo-row {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .submodulo-row:hover {
        background-color: rgba(0, 0, 0, 0.04);
    }
    
    /* Animação suave */
    .submodulo-row {
        transition: all 0.3s ease;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // ========================================
    // CONFIGURAÇÃO GERAL
    // ========================================
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    // ========================================
    // GERENCIAMENTO DE PLANOS - CHECKBOXES
    // ========================================
    
    // Selecionar todos os checkboxes
    $('#select-all').on('change', function() {
        $('.plano-checkbox').prop('checked', $(this).prop('checked'));
        toggleDeleteButton();
    });

    // Verificar quando um checkbox individual é marcado
    $(document).on('change', '.plano-checkbox', function() {
        const totalCheckboxes = $('.plano-checkbox').length;
        const checkedCheckboxes = $('.plano-checkbox:checked').length;
        $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
        toggleDeleteButton();
    });

    // Mostrar/ocultar botão de excluir selecionados
    function toggleDeleteButton() {
        const checkedCount = $('.plano-checkbox:checked').length;
        $('#btn-delete-selected').toggle(checkedCount > 0);
    }

    // ========================================
    // GERENCIAMENTO DE PLANOS - EXCLUSÃO
    // ========================================
    
    // Excluir múltiplos planos selecionados
    $('#btn-delete-selected').on('click', function() {
        const selectedIds = [];
        const selectedNames = [];
        
        $('.plano-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
            selectedNames.push($(this).data('nome'));
        });

        if (selectedIds.length === 0) return;

        const planosList = selectedNames.length > 3 
            ? selectedNames.slice(0, 3).join(', ') + ` e mais ${selectedNames.length - 3}`
            : selectedNames.join(', ');

        swalWithBootstrapButtons.fire({
            title: 'Tem certeza?',
            html: `Deseja realmente excluir <strong>${selectedIds.length}</strong> plano(s)?<br><br><small>${planosList}</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Excluindo...',
                    text: 'Por favor, aguarde',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const deletePromises = selectedIds.map(id => {
                    return fetch(`/admin/planos/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    });
                });

                Promise.all(deletePromises)
                    .then(() => {
                        Swal.fire({
                            title: 'Sucesso!',
                            text: `${selectedIds.length} plano(s) excluído(s) com sucesso!`,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    })
                    .catch(() => {
                        Swal.fire({
                            title: 'Erro!',
                            text: 'Ocorreu um erro ao excluir os planos.',
                            icon: 'error'
                        });
                    });
            }
        });
    });

    // Excluir plano individual
    $(document).on('click', '.btn-delete', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        const nome = $(this).data('nome');
        const contratos = $(this).data('contratos') || 0;

        let mensagemAviso = `Deseja realmente excluir o plano "${nome}"?`;
        
        if (contratos > 0) {
            mensagemAviso += `<br><br>
                <div class="alert alert-warning mt-2 mb-0 text-start">
                    <i class="ti ti-alert-triangle me-1"></i>
                    <strong>Atenção:</strong> Existem <strong>${contratos}</strong> contrato(s) ativo(s) com este plano. 
                    Ao excluir o plano, os contratos também serão removidos.
                </div>`;
        }

        swalWithBootstrapButtons.fire({
            title: 'Tem certeza?',
            html: mensagemAviso,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                const form = $('<form>', {
                    'method': 'POST',
                    'action': url
                });
                form.append('@csrf');
                form.append('@method('DELETE')');
                $('body').append(form);
                form.submit();
            }
        });
    });
});
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Cards Estatísticos -->
            <div class="row mb-2 stats-cards">
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="ti ti-package ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total de Planos</p>
                                <h4 class="mb-0">{{ $planos->total() }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="ti ti-settings ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Módulos Cadastrados</p>
                                <h4 class="mb-0">{{ \App\Models\Modulo::count() }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-info">
                                    <i class="ti ti-users ti-28"></i>
                                </span>
                            </div>
                            <div>
                                <p class="mb-0 text-muted small">Total de Contratos</p>
                                <h4 class="mb-0">{{ \App\Models\PlanoContratado::count() }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Card -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between gap-3 filter-header">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.planos.create') }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-plus me-1"></i>
                                Novo Plano
                            </a>
                            <a href="{{ route('admin.modulos.index') }}" class="btn btn-sm btn-success">
                                <i class="menu-icon tf-icons ti ti-settings me-1"></i>
                                Gerenciar Módulos
                            </a>
                            <a href="{{ route('admin.categorias.index') }}" class="btn btn-sm btn-primary">
                                <i class="menu-icon tf-icons ti ti-category me-1"></i>
                                Gerenciar Categorias
                            </a>
                            <button type="button" class="btn btn-sm btn-danger" id="btn-delete-selected" style="display: none;">
                                <i class="ti ti-trash me-1"></i>
                                Excluir Selecionados
                            </button>
                        </div>
                        <form method="GET" action="{{ route('admin.planos.index') }}" id="filter-form" class="d-flex gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Buscar..." 
                                       value="{{ request('search') }}">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-search"></i>
                                </button>
                                @if(request('search'))
                                    <a href="{{ route('admin.planos.index') }}" class="btn btn-secondary">
                                        <i class="ti ti-x"></i>
                                    </a>
                                @endif
                            </div>
                            <select name="per_page" class="form-select form-select-sm" style="width: 70px;" onchange="this.form.submit()">
                                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                            <div class="text-nowrap">
                                <small class="text-muted">{{ $planos->total() }} resultado(s)</small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card de Planos -->
            <div class="card">
                <div class="card-body">
                    @if($planos->isEmpty())
                        <div class="text-center py-5">
                            <i class="ti ti-package-off ti-48 text-muted mb-3"></i>
                            <h6 class="text-muted">Nenhum plano {{ request('search') ? 'encontrado' : 'cadastrado' }}</h6>
                            <p class="text-muted small">
                                @if(request('search'))
                                    Tente uma pesquisa diferente ou 
                                    <a href="{{ route('admin.planos.index') }}">limpe os filtros</a>
                                @else
                                    Clique em "Novo Plano" para começar
                                @endif
                            </p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                            </div>
                                        </th>
                                        <th style="min-width: 180px;">Nome</th>
                                        <th style="min-width: 100px;">Valor</th>
                                        <th style="min-width: 100px;">Adesão</th>
                                        <th style="min-width: 90px;">Status</th>
                                        <th style="min-width: 120px;">Módulos</th>
                                        <th style="min-width: 120px;">Recursos</th>
                                        <th style="min-width: 120px;">Contratos</th>
                                        <th style="min-width: 150px;">Promoção</th>
                                        <th class="text-center" style="min-width: 90px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($planos as $plano)
                                        @php
                                            $promocaoVigente = $plano->obterPromocaoVigente();
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input plano-checkbox" type="checkbox" value="{{ $plano->id_plano }}" data-nome="{{ $plano->nome }}">
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>{{ $plano->nome }}</strong>
                                                    @if($plano->descricao)
                                                        <br><small class="text-muted">{{ Str::limit($plano->descricao, 50) }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-label-success">{{ $plano->valor_formatado }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-label-info">{{ $plano->adesao_formatada }}</span>
                                            </td>
                                            <td>
                                                @if($plano->ativo)
                                                    <span class="badge bg-label-success">
                                                        <i class="ti ti-circle-check ti-xs me-1"></i>
                                                        Ativo
                                                    </span>
                                                @else
                                                    <span class="badge bg-label-danger">
                                                        <i class="ti ti-circle-x ti-xs me-1"></i>
                                                        Inativo
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-label-primary">{{ $plano->modulos->count() }} módulo(s)</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-label-secondary">{{ $plano->contarRecursosAtivos() }} recurso(s)</span>
                                            </td>
                                            <td>
                                                @php
                                                    $contratosAtivos = $plano->contarContratosAtivos();
                                                @endphp
                                                @if($contratosAtivos > 0)
                                                    <span class="badge bg-label-warning" title="{{ $contratosAtivos }} contrato(s) ativo(s)">
                                                        <i class="ti ti-file-check ti-xs me-1"></i>
                                                        {{ $contratosAtivos }} ativo(s)
                                                    </span>
                                                @else
                                                    <span class="badge bg-label-secondary">Nenhum</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($promocaoVigente)
                                                    <span class="badge bg-label-success" title="{{ $promocaoVigente->nome }}">
                                                        <i class="ti ti-discount-2 ti-xs me-1"></i>
                                                        {{ Str::limit($promocaoVigente->nome, 20) }}
                                                    </span>
                                                    <div class="small text-muted mt-1">
                                                        @if($promocaoVigente->desconto_mensal > 0)
                                                            -R$ {{ number_format($promocaoVigente->desconto_mensal, 2, ',', '.') }}/mês
                                                        @endif
                                                        @if($promocaoVigente->desconto_adesao > 0)
                                                            | -R$ {{ number_format($promocaoVigente->desconto_adesao, 2, ',', '.') }} adesão
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="badge bg-label-secondary">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <a class="dropdown-item" href="{{ route('admin.planos.show', $plano) }}">
                                                            <i class="ti ti-eye me-1"></i> Visualizar
                                                        </a>
                                                        <a class="dropdown-item" href="{{ route('admin.planos.edit', $plano) }}">
                                                            <i class="ti ti-edit me-1"></i> Editar
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger btn-delete" 
                                                           href="{{ route('admin.planos.destroy', $plano) }}"
                                                           data-nome="{{ $plano->nome }}"
                                                           data-contratos="{{ $contratosAtivos }}">
                                                            <i class="ti ti-trash me-1"></i> Excluir
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                Mostrando {{ $planos->firstItem() }} até {{ $planos->lastItem() }} de {{ $planos->total() }} registros
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
                                    {{-- Primeira página --}}
                                    @if ($planos->onFirstPage())
                                        <li class="page-item disabled">
                                            <span class="page-link"><i class="ti ti-chevrons-left ti-xs"></i></span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $planos->url(1) }}"><i class="ti ti-chevrons-left ti-xs"></i></a>
                                        </li>
                                    @endif

                                    {{-- Página anterior --}}
                                    @if ($planos->onFirstPage())
                                        <li class="page-item disabled">
                                            <span class="page-link"><i class="ti ti-chevron-left ti-xs"></i></span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $planos->previousPageUrl() }}"><i class="ti ti-chevron-left ti-xs"></i></a>
                                        </li>
                                    @endif

                                    {{-- Páginas numeradas --}}
                                    @php
                                        $start = max($planos->currentPage() - 2, 1);
                                        $end = min($start + 4, $planos->lastPage());
                                        $start = max($end - 4, 1);
                                    @endphp

                                    @for ($i = $start; $i <= $end; $i++)
                                        @if ($i == $planos->currentPage())
                                            <li class="page-item active">
                                                <span class="page-link">{{ $i }}</span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $planos->url($i) }}">{{ $i }}</a>
                                            </li>
                                        @endif
                                    @endfor

                                    {{-- Próxima página --}}
                                    @if ($planos->hasMorePages())
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $planos->nextPageUrl() }}"><i class="ti ti-chevron-right ti-xs"></i></a>
                                        </li>
                                    @else
                                        <li class="page-item disabled">
                                            <span class="page-link"><i class="ti ti-chevron-right ti-xs"></i></span>
                                        </li>
                                    @endif

                                    {{-- Última página --}}
                                    @if ($planos->hasMorePages())
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $planos->url($planos->lastPage()) }}"><i class="ti ti-chevrons-right ti-xs"></i></a>
                                        </li>
                                    @else
                                        <li class="page-item disabled">
                                            <span class="page-link"><i class="ti ti-chevrons-right ti-xs"></i></span>
                                        </li>
                                    @endif
                                </ul>
                            </nav>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection