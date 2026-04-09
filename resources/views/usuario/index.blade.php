@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Usuários')

@section('vendor-style')
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
                                            <span>Total de Usuários</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-users ti-sm"></i>
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
                                            <span>Usuários Ativos</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['ativos'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-success rounded p-2">
                                            <i class="ti ti-user-check ti-sm"></i>
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
                                            <span>Usuários Inativos</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['inativos'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-warning rounded p-2">
                                            <i class="ti ti-user-exclamation ti-sm"></i>
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
                                            <span>Usuários Bloqueados</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['bloqueados'] ?? 0 }}</h4>
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

                <!-- Filtros e Ações -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Filtros de Busca</h5>
                            <div>
                                <a href="{{ route('usuarios.criar') }}" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i>
                                    Novo Usuário
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label small mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativo" {{ (($filters['status'] ?? request('status')) == 'ativo') ? 'selected' : '' }}>Ativo</option>
                                        <option value="inativo" {{ (($filters['status'] ?? request('status')) == 'inativo') ? 'selected' : '' }}>Inativo</option>
                                        <option value="bloqueado" {{ (($filters['status'] ?? request('status')) == 'bloqueado') ? 'selected' : '' }}>Bloqueado</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="ti ti-search me-1"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Usuários -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Usuários</h5>
                            <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('usuarios.excluir-multiplos') }}" style="display: none;">
                                <i class="ti ti-trash me-1"></i>
                                Excluir Selecionados (<span id="countSelecionados">0</span>)
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="checkAll">
                                            </th>
                                            <th>Usuário</th>
                                            <th>Login</th>
                                            <th>Status</th>
                                            <th>Cadastro</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($users as $u)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input user-checkbox" value="{{ $u->id_usuario }}">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-3">
                                                            @if(!empty($u->foto_url))
                                                                <img 
                                                                    src="{{ $u->foto_url }}" 
                                                                    alt="{{ $u->nome }}" 
                                                                    class="rounded-circle user-avatar-img" 
                                                                    style="width: 38px; height: 38px; object-fit: cover;"
                                                                    onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                                <span class="avatar-initial rounded-circle bg-label-primary" style="display: none; width: 38px; height: 38px; align-items: center; justify-content: center;">
                                                                    {{ $u->inicial ?? strtoupper(substr($u->nome, 0, 2)) }}
                                                                </span>
                                                            @else
                                                                <span class="avatar-initial rounded-circle bg-label-primary" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                                                    {{ $u->inicial ?? strtoupper(substr($u->nome, 0, 2)) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="d-flex flex-column">
                                                            <strong>{{ $u->nome }}</strong>
                                                            <small class="text-muted">{{ $u->finalidade ?? 'Sem perfil global' }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $u->login }}</td>
                                                <td>
                                                    @if($u->status === 'ativo') <span class="badge bg-label-success">Ativo</span>
                                                    @elseif($u->status === 'inativo') <span class="badge bg-label-warning">Inativo</span>
                                                    @elseif($u->status === 'bloqueado') <span class="badge bg-label-danger">Bloqueado</span>
                                                    @else <span class="badge bg-label-secondary">Indefinido</span>
                                                    @endif
                                                </td>
                                                <td>{{ optional($u->created_at)->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                            <i class="ti ti-dots-vertical"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item" href="{{ url('usuarios/'.$u->id_usuario.'/edit') }}"><i class="ti ti-pencil me-2"></i>Editar</a>
                                                            <a class="dropdown-item" href="{{ url('usuarios/'.$u->id_usuario) }}"><i class="ti ti-eye me-2"></i>Visualizar</a>
                                                            <div class="dropdown-divider"></div>
                                                            
                                                            @if($u->status === 'bloqueado')
                                                                <a href="javascript:void(0)" class="dropdown-item text-success user-action" data-action="unlock" data-id="{{ $u->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                                                    <i class="ti ti-lock-open me-2"></i>Desbloquear
                                                                </a>
                                                            @elseif($u->status === 'inativo')
                                                                <a href="javascript:void(0)" class="dropdown-item text-success user-action" data-action="activate" data-id="{{ $u->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                                                    <i class="ti ti-check me-2"></i>Ativar
                                                                </a>
                                                            @else
                                                                {{-- Usuário está ATIVO - pode bloquear OU inativar --}}
                                                                <a href="javascript:void(0)" class="dropdown-item text-warning user-action" data-action="inactivate" data-id="{{ $u->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                                                    <i class="ti ti-user-minus me-2"></i>Inativar
                                                                </a>
                                                                <a href="javascript:void(0)" class="dropdown-item text-danger user-action" data-action="block" data-id="{{ $u->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                                                    <i class="ti ti-lock me-2"></i>Bloquear
                                                                </a>
                                                            @endif
                                                            
                                                            <div class="dropdown-divider"></div>
                                                            <a href="javascript:void(0)" class="dropdown-item text-danger user-action" data-action="delete" data-id="{{ $u->id_usuario }}" data-base-url="{{ url('usuarios') }}"><i class="ti ti-trash me-2"></i>Deletar</a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($users, 'links'))
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $users->firstItem() }} até {{ $users->lastItem() }} de {{ $users->total() }} registros
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination mb-0">
                                            {{-- Primeira página --}}
                                            @if ($users->onFirstPage())
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevrons-left ti-xs"></i></span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $users->appends(request()->query())->url(1) }}"><i class="ti ti-chevrons-left ti-xs"></i></a>
                                                </li>
                                            @endif

                                            {{-- Página anterior --}}
                                            @if ($users->onFirstPage())
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevron-left ti-xs"></i></span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $users->appends(request()->query())->previousPageUrl() }}"><i class="ti ti-chevron-left ti-xs"></i></a>
                                                </li>
                                            @endif

                                            {{-- Páginas numeradas --}}
                                            @php
                                                $start = max($users->currentPage() - 2, 1);
                                                $end = min($start + 4, $users->lastPage());
                                                $start = max($end - 4, 1);
                                            @endphp

                                            @for ($i = $start; $i <= $end; $i++)
                                                @if ($i == $users->currentPage())
                                                    <li class="page-item active">
                                                        <span class="page-link">{{ $i }}</span>
                                                    </li>
                                                @else
                                                    <li class="page-item">
                                                        <a class="page-link" href="{{ $users->appends(request()->query())->url($i) }}">{{ $i }}</a>
                                                    </li>
                                                @endif
                                            @endfor

                                            {{-- Próxima página --}}
                                            @if ($users->hasMorePages())
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $users->appends(request()->query())->nextPageUrl() }}"><i class="ti ti-chevron-right ti-xs"></i></a>
                                                </li>
                                            @else
                                                <li class="page-item disabled">
                                                    <span class="page-link"><i class="ti ti-chevron-right ti-xs"></i></span>
                                                </li>
                                            @endif

                                            {{-- Última página --}}
                                            @if ($users->hasMorePages())
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $users->appends(request()->query())->url($users->lastPage()) }}"><i class="ti ti-chevrons-right ti-xs"></i></a>
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
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/usuarios-actions.js') }}?v=20251118002"></script>
<script src="{{ asset('assets/js/usuarios-index.js') }}?v=20251118002"></script>

@if(session('success'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '{{ session('success') }}',
        confirmButtonText: 'OK',
        timer: 2000,
        timerProgressBar: true
    });
});
</script>
@endif

@if(session('error'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: '{{ session('error') }}',
        confirmButtonText: 'OK'
    });
});
</script>
@endif
@endsection

