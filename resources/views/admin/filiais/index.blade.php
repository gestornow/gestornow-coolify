@extends('layouts.layoutMaster')

@section('title', 'Lista de Filiais')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="row">
                <!-- Cards de Estatísticas -->
                <div class="col-12">
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['total'] ?? 0 }}</h4>
                                            </div>
                                            <span>Total de Filiais</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-building ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Filiais Ativas</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['ativas'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-success rounded p-2">
                                            <i class="ti ti-check ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Filiais Inativas</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['inativas'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-warning rounded p-2">
                                            <i class="ti ti-alert-circle ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Filiais Bloqueadas</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['bloqueadas'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-danger rounded p-2">
                                            <i class="ti ti-lock ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros e Busca -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Filtros de Busca</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('admin.filiais.index') }}" id="filter-form" class="row align-items-end w-100">
                                <div class="col-12 col-md-4 mb-3">
                                    <label class="form-label">Buscar</label>
                                    <input type="text" name="search" value="{{ request('search') }}" 
                                           class="form-control" placeholder="Nome, razão social ou CNPJ...">
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativo" {{ request('status') == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                        <option value="inativo" {{ request('status') == 'inativo' ? 'selected' : '' }}>Inativo</option>
                                        <option value="bloqueado" {{ request('status') == 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                                        <option value="validacao" {{ request('status') == 'validacao' ? 'selected' : '' }}>Em Validação</option>
                                        <option value="teste" {{ request('status') == 'teste' ? 'selected' : '' }}>Teste</option>
                                        <option value="cancelado" {{ request('status') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label class="form-label">Tipo</label>
                                    <select name="filial" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="Unica" {{ request('filial') == 'Unica' ? 'selected' : '' }}>Única</option>
                                        <option value="Matriz" {{ request('filial') == 'Matriz' ? 'selected' : '' }}>Matriz</option>
                                        <option value="Filial" {{ request('filial') == 'Filial' ? 'selected' : '' }}>Filial</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2 mb-3">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="ti ti-search me-1"></i>
                                            Filtrar
                                        </button>
                                        @if(request()->hasAny(['search', 'status', 'filial']))
                                            <a href="{{ route('admin.filiais.index') }}" class="btn btn-secondary">
                                                <i class="ti ti-x me-1"></i>
                                                Limpar
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Resultados -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <div>
                                <h5 class="mb-1">Lista de Filiais</h5>
                                <small class="text-muted">
                                    <span id="selection-info">{{ $empresas->total() }} resultado(s)</span>
                                </small>
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-2 ms-auto">
                                <button type="button" id="btn-delete-selected" class="btn btn-sm btn-danger" style="display: none;">
                                    <i class="ti ti-trash me-1"></i>
                                    <span class="d-none d-sm-inline">Excluir Selecionadas</span>
                                    <span class="d-inline d-sm-none">Excluir</span>
                                </button>
                                <a href="{{ route('admin.filiais.create') }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-plus me-1"></i>
                                    Nova Filial
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            @if($empresas->isEmpty())
                                <div class="text-center py-5">
                                    <i class="ti ti-building-off ti-48 text-muted mb-3"></i>
                                    <h6 class="text-muted">Nenhuma filial encontrada</h6>
                                    <p class="text-muted small mb-0">
                                        @if(request()->hasAny(['search', 'status', 'filial']))
                                            Tente alterar os filtros ou 
                                            <a href="{{ route('admin.filiais.index') }}">limpe os filtros</a>
                                            para ver todas as filiais.
                                        @else
                                            Não há filiais cadastradas no sistema.
                                        @endif
                                    </p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="30">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="select-all">
                                                    </div>
                                                </th>
                                                <th>Empresa</th>
                                                <th>Tipo</th>
                                                <th>Status</th>
                                                <th>Plano Atual</th>
                                                <th>Valor</th>
                                                <th>Contratação</th>
                                                <th width="100">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($empresas as $dados)
                                                @php
                                                    $empresa = $dados['empresa'];
                                                    $planoAtual = $dados['plano_atual'];
                                                    $totalPlanos = $dados['total_planos'];
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input filial-checkbox" type="checkbox" value="{{ $empresa->id_empresa }}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong>{{ $empresa->nome_empresa }}</strong>
                                                            <br><small class="text-muted">{{ $empresa->cnpj_formatado }}</small>
                                                            @if($empresa->codigo)
                                                                <br><small class="text-info">[{{ $empresa->codigo }}]</small>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($empresa->filial)
                                                            <span class="badge bg-label-{{ $empresa->filial == 'Matriz' ? 'primary' : ($empresa->filial == 'Filial' ? 'info' : 'secondary') }}">
                                                                {{ $empresa->filial }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
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
                                                    </td>
                                                    <td>
                                                        @if($planoAtual)
                                                            <div>
                                                                <strong>{{ $planoAtual->nome }}</strong>
                                                                @if($totalPlanos > 1)
                                                                    <br><small class="text-muted">{{ $totalPlanos }} contratos</small>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <span class="text-muted">Sem plano</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($planoAtual)
                                                            <strong class="text-success">{{ $planoAtual->valor_formatado }}</strong>
                                                            @if($planoAtual->adesao_formatada && $planoAtual->adesao_formatada !== 'R$ 0,00')
                                                                <br><small class="text-muted">+ {{ $planoAtual->adesao_formatada }} adesão</small>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($planoAtual)
                                                            <small>{{ $planoAtual->data_contratacao_formatada }}</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                <i class="ti ti-dots-vertical"></i>
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item" href="{{ route('admin.filiais.show', $empresa) }}">
                                                                    <i class="ti ti-eye me-2"></i>
                                                                    Visualizar
                                                                </a>
                                                                <a class="dropdown-item" href="{{ route('admin.filiais.edit', $empresa) }}">
                                                                    <i class="ti ti-edit me-2"></i>
                                                                    Editar
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
                                @if($empresas->hasPages())
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <div class="text-muted">
                                            Mostrando {{ $empresas->firstItem() }} até {{ $empresas->lastItem() }} de {{ $empresas->total() }} registros
                                        </div>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination mb-0">
                                                {{-- Primeira página --}}
                                                @if ($empresas->onFirstPage())
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="ti ti-chevrons-left ti-xs"></i></span>
                                                    </li>
                                                @else
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $empresas->url(1) }}"><i class="ti ti-chevrons-left ti-xs"></i></a>
                                                    </li>
                                                @endif

                                                {{-- Página anterior --}}
                                                @if ($empresas->onFirstPage())
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="ti ti-chevron-left ti-xs"></i></span>
                                                    </li>
                                                @else
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $empresas->previousPageUrl() }}"><i class="ti ti-chevron-left ti-xs"></i></a>
                                                    </li>
                                                @endif

                                                {{-- Páginas numeradas --}}
                                                @php
                                                    $start = max($empresas->currentPage() - 2, 1);
                                                    $end = min($start + 4, $empresas->lastPage());
                                                    $start = max($end - 4, 1);
                                                @endphp

                                                @for ($i = $start; $i <= $end; $i++)
                                                    @if ($i == $empresas->currentPage())
                                                        <li class="page-item active">
                                                            <span class="page-link">{{ $i }}</span>
                                                        </li>
                                                    @else
                                                        <li class="page-item">
                                                            <a class="page-link" href="{{ $empresas->url($i) }}">{{ $i }}</a>
                                                        </li>
                                                    @endif
                                                @endfor

                                                {{-- Próxima página --}}
                                                @if ($empresas->hasMorePages())
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $empresas->nextPageUrl() }}"><i class="ti ti-chevron-right ti-xs"></i></a>
                                                    </li>
                                                @else
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="ti ti-chevron-right ti-xs"></i></span>
                                                    </li>
                                                @endif

                                                {{-- Última página --}}
                                                @if ($empresas->hasMorePages())
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $empresas->url($empresas->lastPage()) }}"><i class="ti ti-chevrons-right ti-xs"></i></a>
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
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Variável para armazenar IDs selecionados
    let selectedIds = [];

    // Selecionar/Desselecionar todos
    $('#select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.filial-checkbox').prop('checked', isChecked);
        updateSelectedInfo();
    });

    // Selecionar individual
    $('.filial-checkbox').on('change', function() {
        updateSelectedInfo();
        
        // Atualizar checkbox "select-all"
        const totalCheckboxes = $('.filial-checkbox').length;
        const checkedCheckboxes = $('.filial-checkbox:checked').length;
        $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Atualizar informações de seleção
    function updateSelectedInfo() {
        selectedIds = [];
        $('.filial-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        const count = selectedIds.length;
        const $selectionInfo = $('#selection-info');
        const $btnDelete = $('#btn-delete-selected');

        if (count > 0) {
            $selectionInfo.html(`${count} filial(is) selecionada(s)`);
            $btnDelete.show();
        } else {
            $selectionInfo.html(`{{ $empresas->total() }} resultado(s)`);
            $btnDelete.hide();
        }
    }

    // Botão de exclusão em massa
    $('#btn-delete-selected').on('click', function() {
        if (selectedIds.length === 0) {
            return;
        }

        Swal.fire({
            title: 'Confirmar Exclusão',
            text: `Tem certeza que deseja excluir ${selectedIds.length} filial(is) selecionada(s)?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Criar formulário para enviar a requisição
                const form = $('<form>', {
                    method: 'POST',
                    action: '{{ route("admin.filiais.delete-multiple") }}'
                });

                form.append($('<input>', {
                    type: 'hidden',
                    name: '_token',
                    value: '{{ csrf_token() }}'
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: '_method',
                    value: 'DELETE'
                }));

                // Enviar cada ID como elemento do array
                selectedIds.forEach(function(id) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'ids[]',
                        value: id
                    }));
                });

                $('body').append(form);
                form.submit();
            }
        });
    });
});
</script>
@endsection

@endsection