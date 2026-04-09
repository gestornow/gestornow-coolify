@extends('layouts.layoutMaster')

@section('title', 'Gerenciar Módulos')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold py-3 mb-2">
                        <span class="text-muted fw-light">Admin /</span> Gerenciar Módulos
                    </h4>
                </div>
                <div>
                    <a href="{{ route('admin.modulos.create') }}" class="btn btn-success">
                        <i class="ti ti-plus me-1"></i>
                        Novo Módulo
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Lista de Módulos</h5>
                    <p class="text-muted mb-0">Gerenciamento completo de módulos e submódulos do sistema</p>
                </div>
                <div class="card-body">
                    @if($modulos->isEmpty())
                        <div class="text-center py-5">
                            <i class="ti ti-package ti-xl text-muted mb-3 d-block"></i>
                            <h5>Nenhum módulo cadastrado</h5>
                            <p class="text-muted">Comece criando o primeiro módulo do sistema</p>
                            <a href="{{ route('admin.modulos.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>
                                Criar Primeiro Módulo
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Módulo</th>
                                        <th>Descrição</th>
                                        <th>Ícone</th>
                                        <th>Rota</th>
                                        <th class="text-center">Ordem</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($modulos as $modulo)
                                        <!-- Módulo Principal -->
                                        <tr>
                                            <td>
                                                <strong>{{ $modulo->nome }}</strong>
                                                @if($modulo->temSubmodulos())
                                                    <span class="badge bg-label-primary ms-1">{{ $modulo->submodulos->count() }} submódulo(s)</span>
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($modulo->descricao, 50) ?? '-' }}</td>
                                            <td>
                                                @if($modulo->icone)
                                                    <i class="{{ $modulo->icone }} me-1"></i>
                                                    <small class="text-muted">{{ $modulo->icone }}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($modulo->rota)
                                                    <code class="text-primary">{{ $modulo->rota }}</code>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-label-secondary">{{ $modulo->ordem }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if($modulo->ativo)
                                                    <span class="badge bg-success">Ativo</span>
                                                @else
                                                    <span class="badge bg-secondary">Inativo</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('admin.modulos.edit', $modulo->id_modulo) }}">
                                                                <i class="ti ti-edit me-1"></i> Editar
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form action="{{ route('admin.modulos.destroy', $modulo->id_modulo) }}" 
                                                                  method="POST" 
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Tem certeza que deseja excluir este módulo?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="ti ti-trash me-1"></i> Excluir
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Submódulos -->
                                        @if($modulo->temSubmodulos())
                                            @foreach($modulo->submodulos as $submodulo)
                                                <tr class="bg-light">
                                                    <td class="ps-5">
                                                        <i class="ti ti-corner-down-right me-2 text-muted"></i>
                                                        {{ $submodulo->nome }}
                                                        <span class="badge bg-label-info ms-1">Submódulo</span>
                                                    </td>
                                                    <td>{{ Str::limit($submodulo->descricao, 50) ?? '-' }}</td>
                                                    <td>
                                                        @if($submodulo->icone)
                                                            <i class="{{ $submodulo->icone }} me-1"></i>
                                                            <small class="text-muted">{{ $submodulo->icone }}</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($submodulo->rota)
                                                            <code class="text-primary">{{ $submodulo->rota }}</code>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-label-secondary">{{ $submodulo->ordem }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($submodulo->ativo)
                                                            <span class="badge bg-success">Ativo</span>
                                                        @else
                                                            <span class="badge bg-secondary">Inativo</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="dropdown">
                                                            <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="dropdown">
                                                                <i class="ti ti-dots-vertical"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item" href="{{ route('admin.modulos.edit', $submodulo->id_modulo) }}">
                                                                        <i class="ti ti-edit me-1"></i> Editar
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <form action="{{ route('admin.modulos.destroy', $submodulo->id_modulo) }}" 
                                                                          method="POST" 
                                                                          class="d-inline"
                                                                          onsubmit="return confirm('Tem certeza que deseja excluir este submódulo?')">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="dropdown-item text-danger">
                                                                            <i class="ti ti-trash me-1"></i> Excluir
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
