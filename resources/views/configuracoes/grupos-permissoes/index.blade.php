@extends('layouts.layoutMaster')

@section('title', 'Grupos de Permissoes')

@section('page-style')
<style>
    .gp-summary-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.75rem;
    }

    .gp-table-wrap {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .gp-empty-state {
        border: 1px dashed var(--bs-border-color);
        border-radius: 0.75rem;
        padding: 2rem 1rem;
        text-align: center;
        color: var(--bs-secondary-color);
        background: rgba(var(--bs-primary-rgb), 0.02);
    }

    .gp-badge-date {
        font-size: 0.75rem;
        font-weight: 600;
    }

    .gp-row-hidden {
        display: none;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $gruposCollection = collect($grupos);
        $totalGrupos = $gruposCollection->count();
        $comDescricao = $gruposCollection->filter(function ($g) {
            return trim((string) ($g->descricao ?? '')) !== '';
        })->count();
        $semDescricao = max($totalGrupos - $comDescricao, 0);
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="gp-summary-card card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Total de grupos</small>
                    <h4 class="mb-0">{{ $totalGrupos }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="gp-summary-card card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Com descricao</small>
                    <h4 class="mb-0">{{ $comDescricao }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="gp-summary-card card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Sem descricao</small>
                    <h4 class="mb-0">{{ $semDescricao }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 pb-2">
            <h5 class="mb-0">Grupos de Permissoes</h5>
            <a href="{{ route('configuracoes.grupos-permissoes.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Novo Grupo
            </a>
        </div>

        <div class="card-body pt-2">
            <div class="row g-2 align-items-center mb-3">
                <div class="col-12 col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" id="filtroGrupos" placeholder="Buscar por nome ou descricao...">
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="text-muted text-lg-end small">
                        Exibindo <span id="gruposVisiveis">{{ $totalGrupos }}</span> de {{ $totalGrupos }} grupo(s)
                    </div>
                </div>
            </div>

            @if($totalGrupos === 0)
                <div class="gp-empty-state">
                    <i class="ti ti-users-group" style="font-size: 2rem;"></i>
                    <p class="mb-1 mt-2 fw-medium">Nenhum grupo cadastrado</p>
                    <small>Crie o primeiro grupo para organizar as permissoes dos usuarios.</small>
                </div>
            @else
                <div class="table-responsive gp-table-wrap">
                    <table class="table table-hover mb-0 align-middle" id="tabelaGrupos">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Descricao</th>
                                <th>Criado em</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grupos as $grupo)
                                @php
                                    $nomeGrupo = (string) ($grupo->nome ?? '');
                                    $descricaoGrupo = (string) ($grupo->descricao ?? '');
                                    $searchText = strtolower($nomeGrupo . ' ' . $descricaoGrupo);
                                @endphp
                                <tr data-row-search="{{ $searchText }}">
                                    <td>
                                        <div class="fw-semibold">{{ $grupo->nome }}</div>
                                    </td>
                                    <td>
                                        @if(trim($descricaoGrupo) !== '')
                                            <span>{{ $descricaoGrupo }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-label-secondary gp-badge-date">{{ \Carbon\Carbon::parse($grupo->created_at)->format('d/m/Y H:i') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('configuracoes.grupos-permissoes.edit', $grupo->id_grupo) }}" class="btn btn-sm btn-outline-warning">
                                                <i class="ti ti-edit me-1"></i>Editar
                                            </a>
                                            <form action="{{ route('configuracoes.grupos-permissoes.destroy', $grupo->id_grupo) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este grupo?')">
                                                    <i class="ti ti-trash me-1"></i>Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="gp-empty-state mt-3" id="resultadoVazioBusca" style="display:none;">
                    <i class="ti ti-search-off" style="font-size: 2rem;"></i>
                    <p class="mb-1 mt-2 fw-medium">Nenhum grupo encontrado</p>
                    <small>Tente outro termo de busca.</small>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    (function () {
        const input = document.getElementById('filtroGrupos');
        const tabela = document.getElementById('tabelaGrupos');
        const visiveisEl = document.getElementById('gruposVisiveis');
        const vazioBusca = document.getElementById('resultadoVazioBusca');

        if (!input || !tabela) {
            return;
        }

        const rows = Array.from(tabela.querySelectorAll('tbody tr'));

        function aplicarFiltro() {
            const termo = (input.value || '').trim().toLowerCase();
            let visiveis = 0;

            rows.forEach(row => {
                const texto = row.getAttribute('data-row-search') || '';
                const mostrar = termo === '' || texto.includes(termo);
                row.classList.toggle('gp-row-hidden', !mostrar);
                if (mostrar) {
                    visiveis++;
                }
            });

            if (visiveisEl) {
                visiveisEl.textContent = visiveis;
            }

            if (vazioBusca) {
                vazioBusca.style.display = visiveis === 0 ? 'block' : 'none';
            }
        }

        input.addEventListener('input', aplicarFiltro);
    })();
</script>
@endsection
