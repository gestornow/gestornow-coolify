@extends('layouts.layoutMaster')

@section('title', 'Editar Produto - ' . ($produto->nome ?? 'Produto'))

@section('page-style')
<style>
    .produto-tabs-nav {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        gap: .4rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        padding-bottom: .2rem;
    }

    .produto-tabs-nav .nav-item {
        flex: 0 0 auto;
    }

    .produto-tabs-nav .nav-link {
        white-space: nowrap;
    }

    .produto-photo-card {
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border: 1px solid #667eea30;
    }

    @media (max-width: 767.98px) {
        .produto-tab-actions {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }

        .produto-tab-actions .btn,
        .produto-tab-actions a {
            width: 100%;
        }
    }

    html.dark-style .produto-photo-card {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
        border-color: #444b6e;
    }
</style>
@endsection

@section('content')
@php
    $podeExcluirProduto = \Perm::pode(auth()->user(), 'produtos.excluir');
    $podePatrimonio = \Perm::pode(auth()->user(), 'produtos.patrimonio');
    $podeTabelaPrecos = \Perm::pode(auth()->user(), 'produtos.tabela-precos');
    $podeManutencao = \Perm::pode(auth()->user(), 'produtos.manutencao');
    $podeMovimentacao = \Perm::pode(auth()->user(), 'produtos.movimentacao');
@endphp
<div class="container-xxl flex-grow-1 pt-1">
    <div class="row">
        <div class="col-md-12">
            <!-- Card das Tabs -->
            <div class="card mb-4">
                <div class="card-body py-3">
                    <ul class="nav nav-pills produto-tabs-nav" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-dados-gerais">
                                <i class="ti ti-file-description me-1"></i> Dados Gerais
                            </a>
                        </li>
                        @if($podePatrimonio)
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-patrimonios">
                                    <i class="ti ti-building-warehouse me-1"></i> Patrimônios
                                    <span class="badge bg-label-primary ms-1">{{ $produto->patrimonios->count() }}</span>
                                </a>
                            </li>
                        @endif
                        @if($podeTabelaPrecos)
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-precos">
                                    <i class="ti ti-currency-dollar me-1"></i> Tabela de Preços
                                    <span class="badge bg-label-success ms-1">{{ $produto->tabelasPreco->count() }}</span>
                                </a>
                            </li>
                        @endif
                        @if($podeManutencao)
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-manutencoes">
                                    <i class="ti ti-tool me-1"></i> Manutenções
                                    <span class="badge bg-label-warning ms-1">{{ $produto->manutencoes->count() }}</span>
                                </a>
                            </li>
                        @endif
                        @if($podeMovimentacao)
                            <li class="nav-item">
                                <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-estoque">
                                    <i class="ti ti-packages me-1"></i> Estoque
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="pill" data-bs-target="#tab-anexos">
                                <i class="ti ti-paperclip me-1"></i> Anexos
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Card do Conteúdo -->
            <div class="card">
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Tab: Dados Gerais -->
                        <div class="tab-pane fade show active" id="tab-dados-gerais" role="tabpanel">
                                @if($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach($errors->all() as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <!-- Hidden Fields para API -->
                                <input type="hidden" id="produtoId" value="{{ $produto->id_produto }}">
                                <input type="hidden" id="empresaId" value="{{ $produto->id_empresa ?? (session('id_empresa') ?? (auth()->user()->id_empresa ?? '')) }}">
                                <input type="hidden" id="fotoFilename" value="{{ $produto->foto_filename ?? '' }}">

                                <form id="produtoEditForm" action="{{ route('produtos.update', $produto->id_produto) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    
                                    <div class="row g-3">
                                        <!-- Card de Foto do Produto -->
                                        <div class="col-12">
                                            <div class="card produto-photo-card">
                                                <div class="card-body">
                                                    <h6 class="card-title mb-3">
                                                        <i class="ti ti-camera me-2"></i>
                                                        Foto do Produto
                                                    </h6>
                                                    <div class="row align-items-center">
                                                        <div class="col-md-2 text-center">
                                                            <div id="fotoPreview" class="mb-3 mb-md-0">
                                                                @if(!empty($produto->foto_url))
                                                                    <img src="{{ $produto->foto_url }}" alt="{{ $produto->nome }}" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                                                @else
                                                                    <div class="avatar avatar-xl">
                                                                        <span class="avatar-initial rounded bg-label-primary fs-1">
                                                                            <i class="ti ti-package"></i>
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-md-10">
                                                            <div class="mb-0">
                                                                <label for="fotoUpload" class="form-label small">Selecionar Foto</label>
                                                                <input type="file" class="form-control" id="fotoUpload" accept="image/jpeg,image/jpg,image/png,image/webp">
                                                                <small class="form-text text-muted">
                                                                    Formatos: JPG, PNG, WEBP. Tamanho máximo: 10MB
                                                                </small>
                                                            </div>
                                                            <div class="mt-2">
                                                                <button type="button" class="btn btn-sm btn-primary" id="btnUploadFoto" disabled>
                                                                    <i class="ti ti-upload me-1"></i> Enviar Foto
                                                                </button>
                                                                @if(!empty($produto->foto_url))
                                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="btnDeletarFoto">
                                                                    <i class="ti ti-trash me-1"></i> Remover
                                                                </button>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">Nome <span class="text-danger">*</span></label>
                                            <input type="text" name="nome" class="form-control" value="{{ old('nome', $produto->nome) }}" required>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Código</label>
                                            <input type="text" name="codigo" class="form-control" value="{{ old('codigo', $produto->codigo) }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Número de Série</label>
                                            <input type="text" name="numero_serie" class="form-control" value="{{ old('numero_serie', $produto->numero_serie) }}">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">Descrição</label>
                                            <textarea name="descricao" class="form-control" rows="3">{{ old('descricao', $produto->descricao) }}</textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">Detalhes</label>
                                            <textarea name="detalhes" class="form-control" rows="3">{{ old('detalhes', $produto->detalhes) }}</textarea>
                                        </div>

                                        <!-- Separador Preços -->
                                        <div class="col-12">
                                            <hr class="my-2">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Preço de Custo</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="preco_custo" class="form-control mask-money" value="{{ old('preco_custo', number_format($produto->preco_custo, 2, ',', '.')) }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Preço de Venda</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="preco_venda" class="form-control mask-money" value="{{ old('preco_venda', number_format($produto->preco_venda, 2, ',', '.')) }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Preço de Locação</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="preco_locacao" class="form-control mask-money" value="{{ old('preco_locacao', number_format($produto->preco_locacao, 2, ',', '.')) }}">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="ativo" {{ old('status', $produto->status) === 'ativo' ? 'selected' : '' }}>Ativo</option>
                                                <option value="inativo" {{ old('status', $produto->status) === 'inativo' ? 'selected' : '' }}>Inativo</option>
                                            </select>
                                        </div>

                                        <!-- Separador Dimensões -->
                                        <div class="col-12">
                                            <hr class="my-2">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Altura (cm)</label>
                                            <input type="text" name="altura" class="form-control mask-decimal" value="{{ old('altura', number_format($produto->altura, 2, ',', '.')) }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Largura (cm)</label>
                                            <input type="text" name="largura" class="form-control mask-decimal" value="{{ old('largura', number_format($produto->largura, 2, ',', '.')) }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Profundidade (cm)</label>
                                            <input type="text" name="profundidade" class="form-control mask-decimal" value="{{ old('profundidade', number_format($produto->profundidade, 2, ',', '.')) }}">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small">Peso (kg)</label>
                                            <input type="text" name="peso" class="form-control mask-decimal" value="{{ old('peso', number_format($produto->peso, 2, ',', '.')) }}">
                                        </div>

                                        <!-- Botões -->
                                        <div class="col-12 mt-4 d-flex justify-content-between produto-tab-actions">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="ti ti-check me-1"></i> Salvar
                                                </button>
                                                <a href="{{ route('produtos.index') }}" class="btn btn-secondary">Cancelar</a>
                                            </div>
                                            @if($podeExcluirProduto)
                                                <div>
                                                    <button type="button" class="btn btn-danger produto-action" data-action="delete" data-id="{{ $produto->id_produto }}" data-base-url="{{ url('produtos') }}">
                                                        <i class="ti ti-trash me-1"></i> Deletar
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </form>
                        </div>

                        <!-- Tab: Patrimônios -->
                        @if($podePatrimonio)
                        <div class="tab-pane fade" id="tab-patrimonios" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Patrimônios Vinculados</h6>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnExcluirSelecionados">
                                        <i class="ti ti-trash me-1"></i> Excluir Selecionados (<span id="contadorSelecionados">0</span>)
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBipagemPatrimonio">
                                        <i class="ti ti-scan me-1"></i> Bipagem
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPatrimoniosMassa">
                                        <i class="ti ti-list-numbers me-1"></i> Em Massa
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoPatrimonio">
                                        <i class="ti ti-plus me-1"></i> Novo Patrimônio
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Cards de Resumo -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-label-primary">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Total</span>
                                                <strong>{{ $produto->patrimonios->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-success">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Disponíveis</span>
                                                <strong>{{ $produto->patrimonios->where('status_locacao', 'Disponivel')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-info">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Locados</span>
                                                <strong>{{ $produto->patrimonios->where('status_locacao', 'Locado')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-warning">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Manutenção</span>
                                                <strong>{{ $produto->patrimonios->where('status_locacao', 'Em Manutencao')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" class="form-check-input" id="checkTodosPatrimonios">
                                            </th>
                                            <th>Nº Série</th>
                                            <th>Status</th>
                                            <th>Status Locação</th>
                                            <th>Valor Aquisição</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($produto->patrimonios as $patrimonio)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input check-patrimonio" value="{{ $patrimonio->id_patrimonio }}">
                                                </td>
                                                <td><strong>{{ $patrimonio->numero_serie }}</strong></td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'Ativo' => 'success',
                                                            'Inativo' => 'secondary',
                                                            'Descarte' => 'danger'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$patrimonio->status] ?? 'secondary' }}">
                                                        {{ $patrimonio->status }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @php
                                                        $statusLocacaoColors = [
                                                            'Disponivel' => 'success',
                                                            'Locado' => 'info',
                                                            'Em Manutencao' => 'warning'
                                                        ];
                                                        $statusLocacaoLabels = [
                                                            'Disponivel' => 'Disponível',
                                                            'Locado' => 'Locado',
                                                            'Em Manutencao' => 'Em Manutenção'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusLocacaoColors[$patrimonio->status_locacao] ?? 'secondary' }}">
                                                        {{ $statusLocacaoLabels[$patrimonio->status_locacao] ?? $patrimonio->status_locacao }}
                                                    </span>
                                                </td>
                                                <td>R$ {{ number_format($patrimonio->valor_aquisicao ?? 0, 2, ',', '.') }}</td>
                                                <td>
                                                    <a href="{{ route('locacoes.historico-patrimonio', $patrimonio->id_patrimonio) }}" 
                                                       class="btn btn-sm btn-icon btn-outline-info" 
                                                       title="Ver Histórico" target="_blank">
                                                        <i class="ti ti-history"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-icon btn-outline-primary btn-ver-contratos-patrimonio"
                                                            title="Ver Contratos"
                                                            data-id="{{ $patrimonio->id_patrimonio }}"
                                                            data-serie="{{ $patrimonio->numero_serie ?? ('PAT-' . $patrimonio->id_patrimonio) }}">
                                                        <i class="ti ti-file-text"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-outline-primary btn-editar-patrimonio" 
                                                            data-patrimonio="{{ json_encode($patrimonio) }}"
                                                            data-bs-toggle="modal" data-bs-target="#modalEditarPatrimonio">
                                                        <i class="ti ti-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-outline-danger btn-excluir-patrimonio" data-id="{{ $patrimonio->id_patrimonio }}">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="ti ti-building-off ti-lg mb-2"></i>
                                                    <p class="mb-0">Nenhum patrimônio cadastrado</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Tab: Tabela de Preços -->
                        @if($podeTabelaPrecos)
                        <div class="tab-pane fade" id="tab-precos" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Tabelas de Preço do Produto</h6>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovaTabela">
                                    <i class="ti ti-plus me-1"></i> Nova Tabela
                                </button>
                            </div>

                            @forelse($produto->tabelasPreco as $tabela)
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <div>
                                            <h6 class="mb-0">{{ $tabela->nome }}</h6>
                                            <small class="text-muted">{{ $tabela->descricao }}</small>
                                        </div>
                                        <div>
                                            @if($tabela->status === 'ativo')
                                                <span class="badge bg-label-success me-2">Ativa</span>
                                            @else
                                                <span class="badge bg-label-warning me-2">Inativa</span>
                                            @endif
                                            <button class="btn btn-sm btn-icon btn-outline-primary btn-editar-tabela" 
                                                    data-tabela="{{ json_encode($tabela) }}"
                                                    data-bs-toggle="modal" data-bs-target="#modalEditarTabela">
                                                <i class="ti ti-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-icon btn-outline-danger btn-excluir-tabela" data-id="{{ $tabela->id_tabela }}">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            @php
                                                $diasPreenchidos = [];
                                                for ($i = 1; $i <= 30; $i++) {
                                                    $campo = 'd' . $i;
                                                    if (($tabela->$campo ?? 0) > 0) {
                                                        $diasPreenchidos[$i] = $tabela->$campo;
                                                    }
                                                }
                                                // Períodos especiais
                                                if (($tabela->d60 ?? 0) > 0) $diasPreenchidos[60] = $tabela->d60;
                                                if (($tabela->d120 ?? 0) > 0) $diasPreenchidos[120] = $tabela->d120;
                                                if (($tabela->d360 ?? 0) > 0) $diasPreenchidos[360] = $tabela->d360;
                                            @endphp
                                            
                                            @if(count($diasPreenchidos) > 0)
                                                @foreach($diasPreenchidos as $dias => $valor)
                                                <div class="col-md-2 col-4 mb-2">
                                                    <small class="text-muted d-block">{{ $dias }} dia{{ $dias > 1 ? 's' : '' }}</small>
                                                    <strong>R$ {{ number_format($valor, 2, ',', '.') }}</strong>
                                                </div>
                                                @endforeach
                                            @else
                                                <div class="col-12">
                                                    <p class="text-muted mb-0">Nenhum preço configurado</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-muted">
                                    <i class="ti ti-table-off ti-lg mb-2"></i>
                                    <p class="mb-0">Nenhuma tabela de preços cadastrada</p>
                                </div>
                            @endforelse
                        </div>
                        @endif

                        <!-- Tab: Manutenções -->
                        @if($podeManutencao)
                        <div class="tab-pane fade" id="tab-manutencoes" role="tabpanel">
                            @php
                                $produtoTemPatrimonios = $produto->patrimonios->count() > 0;
                            @endphp
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Manutenções do Produto</h6>
                                <div class="d-flex gap-2">
                                    @if($produtoTemPatrimonios)
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalInfoMigracaoManutencao">
                                            <i class="ti ti-info-circle me-1"></i> Informações
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovaManutencao">
                                        <i class="ti ti-plus me-1"></i> Nova Manutenção
                                    </button>
                                </div>
                            </div>

                            <!-- Cards de Resumo -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-label-info">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Em Manutenção</span>
                                                <strong>{{ $produto->manutencoes->whereIn('status', ['em_andamento', 'pendente'])->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-success">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Concluídas</span>
                                                <strong>{{ $produto->manutencoes->where('status', 'concluida')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-primary">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Total</span>
                                                <strong>{{ $produto->manutencoes->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-secondary">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Custo Total</span>
                                                <strong>R$ {{ number_format($produto->manutencoes->sum('custo') + $produto->manutencoes->sum('valor'), 2, ',', '.') }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Vínculo</th>
                                            <th>Qtd</th>
                                            <th>Tipo</th>
                                            <th>Descrição</th>
                                            <th>Início</th>
                                            <th>Previsão</th>
                                            <th>Custo</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($produto->manutencoes as $manutencao)
                                            <tr>
                                                <td>
                                                    @if($manutencao->id_patrimonio)
                                                        <span class="badge bg-label-info">
                                                            <i class="ti ti-barcode me-1"></i>Patrimônio: {{ $manutencao->patrimonio->numero_serie ?? ('PAT-' . $manutencao->id_patrimonio) }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-label-primary">
                                                            <i class="ti ti-package me-1"></i>Produto
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>{{ $manutencao->id_patrimonio ? 1 : ($manutencao->quantidade ?? 1) }}</td>
                                                <td>
                                                    @php
                                                        $tipoColors = [
                                                            'preventiva' => 'info',
                                                            'corretiva' => 'warning',
                                                            'preditiva' => 'primary',
                                                            'emergencial' => 'danger'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $tipoColors[$manutencao->tipo] ?? 'secondary' }}">
                                                        {{ ucfirst($manutencao->tipo) }}
                                                    </span>
                                                </td>
                                                <td>{{ Str::limit($manutencao->descricao, 30) }}</td>
                                                <td>
                                                    {{ optional($manutencao->data_manutencao)->format('d/m/Y') ?? optional($manutencao->data_entrada)->format('d/m/Y') ?? '-' }}
                                                    @if(!empty($manutencao->hora_manutencao))
                                                        <small class="text-muted d-block">{{ substr($manutencao->hora_manutencao, 0, 5) }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ optional($manutencao->data_previsao)->format('d/m/Y') ?? '-' }}
                                                    @if(!empty($manutencao->hora_previsao))
                                                        <small class="text-muted d-block">{{ substr($manutencao->hora_previsao, 0, 5) }}</small>
                                                    @endif
                                                </td>
                                                <td>R$ {{ number_format($manutencao->valor ?? $manutencao->custo ?? 0, 2, ',', '.') }}</td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'pendente' => 'info',
                                                            'em_andamento' => 'info',
                                                            'concluida' => 'success',
                                                            'cancelada' => 'danger'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$manutencao->status] ?? 'secondary' }}">
                                                        {{ in_array($manutencao->status, ['em_andamento', 'pendente']) ? 'Em Manutenção' : (ucfirst(str_replace('_', ' ', $manutencao->status))) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-icon btn-outline-primary btn-editar-manutencao" 
                                                            data-manutencao="{{ json_encode($manutencao) }}"
                                                            data-bs-toggle="modal" data-bs-target="#modalEditarManutencao">
                                                        <i class="ti ti-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-outline-danger btn-excluir-manutencao" data-id="{{ $manutencao->id_manutencao }}">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center py-4 text-muted">
                                                    <i class="ti ti-tool-off ti-lg mb-2"></i>
                                                    <p class="mb-0">Nenhuma manutenção registrada</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Tab: Estoque -->
                        @if($podeMovimentacao)
                        <div class="tab-pane fade" id="tab-estoque" role="tabpanel">
                            @php
                                $temPatrimonios = $produto->patrimonios->count() > 0;
                                $patrimoniosDisponiveis = $produto->patrimonios->where('status', 'Ativo')->where('status_locacao', 'Disponivel')->count();
                            @endphp
                            
                            @if($temPatrimonios)
                            <div class="alert alert-info mb-4">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>Estoque baseado em Patrimônios</strong>
                                <p class="mb-0 mt-1">Este produto possui patrimônios cadastrados. O estoque é calculado automaticamente com base na quantidade de patrimônios ativos e disponíveis.</p>
                            </div>
                            @endif
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Controle de Estoque</h6>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalMovimentacoesProduto">
                                        <i class="ti ti-history me-1"></i> Movimentações
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnVerContratosProduto">
                                        <i class="ti ti-file-text me-1"></i> Ver Contratos
                                    </button>
                                    @if(!$temPatrimonios)
                                    <button type="button" class="btn btn-primary btn-sm" id="btnNovaMovimentacao" data-bs-toggle="modal" data-bs-target="#modalMovimentacaoEstoque">
                                        <i class="ti ti-plus me-1"></i> Nova Movimentação
                                    </button>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Cards de Resumo -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="card bg-label-primary mb-0">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="estoqueTotal">
                                                @if($temPatrimonios)
                                                    {{ $produto->patrimonios->where('status', 'Ativo')->count() }}
                                                @else
                                                    {{ $produto->estoque_total ?? 0 }}
                                                @endif
                                            </h4>
                                            <small>Estoque Total</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-label-success mb-0">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="estoqueDisponivel">
                                                @if($temPatrimonios)
                                                    {{ $patrimoniosDisponiveis }}
                                                @else
                                                    {{ $produto->quantidade ?? 0 }}
                                                @endif
                                            </h4>
                                            <small>Disponível</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-label-warning mb-0">
                                        <div class="card-body text-center py-3">
                                            <h4 class="mb-1" id="estoqueEmUso">
                                                @if($temPatrimonios)
                                                    {{ $produto->patrimonios->where('status', 'Ativo')->count() - $patrimoniosDisponiveis }}
                                                @else
                                                    {{ ($produto->estoque_total ?? 0) - ($produto->quantidade ?? 0) }}
                                                @endif
                                            </h4>
                                            <small>Em Uso/Locado</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            @if(!$temPatrimonios)
                            @else
                            <h6 class="mb-3">Detalhamento por Patrimônio</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nº Série</th>
                                            <th>Status</th>
                                            <th>Status Locação</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($produto->patrimonios->where('status', 'Ativo') as $pat)
                                        <tr>
                                            <td><code>{{ $pat->numero_serie }}</code></td>
                                            <td>
                                                <span class="badge bg-label-success">{{ $pat->status }}</span>
                                            </td>
                                            <td>
                                                @if($pat->status_locacao === 'Disponivel')
                                                    <span class="badge bg-label-success">Disponível</span>
                                                @elseif($pat->status_locacao === 'Locado')
                                                    <span class="badge bg-label-warning">Locado</span>
                                                @else
                                                    <span class="badge bg-label-info">{{ $pat->status_locacao }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('locacoes.historico-patrimonio', $pat->id_patrimonio) }}" class="btn btn-sm btn-outline-info" target="_blank">
                                                    <i class="ti ti-history me-1"></i> Histórico
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-ver-contratos-patrimonio" data-id="{{ $pat->id_patrimonio }}" data-serie="{{ $pat->numero_serie ?? ('PAT-' . $pat->id_patrimonio) }}">
                                                    <i class="ti ti-file-text me-1"></i> Contratos
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Nenhum patrimônio ativo</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>

                        <!-- Tab: Anexos -->
                        <div class="tab-pane fade" id="tab-anexos" role="tabpanel">
                            <h6 class="mb-3">Documentos e Arquivos</h6>
                            <div class="border border-dashed rounded p-4 text-center mb-4" style="border-color: #7367f0 !important;">
                                <i class="ti ti-cloud-upload ti-xl text-primary d-block mb-2"></i>
                                <h6>Arraste arquivos aqui ou clique para selecionar</h6>
                                <small class="text-muted d-block mb-3">PDF, DOC, DOCX, JPG, PNG (máx. 10MB)</small>
                                <input type="file" class="form-control" id="inputAnexo" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="max-width: 300px; margin: 0 auto;">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelaAnexos">
                                    <thead>
                                        <tr>
                                            <th>Arquivo</th>
                                            <th>Tipo</th>
                                            <th>Tamanho</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="ti ti-files-off ti-lg d-block mb-2"></i>
                                                    <p class="mb-0">Nenhum arquivo anexado</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInfoMigracaoManutencao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Informação sobre Manutenções</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Este produto agora é controlado por patrimônios.</p>
                <p class="mb-0 text-muted">Manutenções antigas baseadas apenas em quantidade foram removidas automaticamente na migração. A partir de agora, registre manutenção vinculando um patrimônio específico.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalContratosProduto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalContratosProdutoTitle">Contratos do Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContratosProdutoBody">
                <div class="text-center py-4 text-muted">
                    <i class="spinner-border spinner-border-sm me-1"></i> Carregando contratos...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Patrimônio -->
<div class="modal fade" id="modalNovoPatrimonio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Patrimônio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPatrimonio" method="POST" action="{{ route('patrimonios.store') }}">
                @csrf
                <input type="hidden" name="id_produto" value="{{ $produto->id_produto }}">
                <input type="hidden" name="redirect_to" value="produto_edit">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Número de Série <span class="text-danger">*</span></label>
                        <input type="text" name="numero_serie" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valor de Aquisição</label>
                            <input type="text" name="valor_aquisicao" class="form-control money">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valor Atual</label>
                            <input type="text" name="valor_atual" class="form-control money">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Ativo" selected>Ativo</option>
                                <option value="Inativo">Inativo</option>
                                <option value="Descarte">Descarte</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status de Locação</label>
                            <select name="status_locacao" class="form-select">
                                <option value="Disponivel" selected>Disponível</option>
                                <option value="Locado">Locado</option>
                                <option value="Em Manutencao">Em Manutenção</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Patrimônio -->
<div class="modal fade" id="modalEditarPatrimonio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Patrimônio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarPatrimonio" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id_produto" value="{{ $produto->id_produto }}">
                <input type="hidden" name="redirect_to" value="produto_edit">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Número de Série <span class="text-danger">*</span></label>
                        <input type="text" name="numero_serie" id="edit_numero_serie" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor de Aquisição</label>
                        <input type="text" name="valor_aquisicao" id="edit_valor_aquisicao" class="form-control money">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status_patrimonio" class="form-select">
                                <option value="Ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                                <option value="Descarte">Descarte</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status de Locação</label>
                            <select name="status_locacao" id="edit_status_locacao_patrimonio" class="form-select">
                                <option value="Disponivel">Disponível</option>
                                <option value="Locado">Locado</option>
                                <option value="Em Manutencao">Em Manutenção</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="edit_observacoes_patrimonio" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Patrimônios em Massa -->
<div class="modal fade" id="modalPatrimoniosMassa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cadastro de Patrimônios em Massa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPatrimoniosMassa">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ti ti-info-circle me-2"></i>
                        Informe a numeração inicial e final para criar múltiplos patrimônios com as mesmas configurações.
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Numeração Inicial <span class="text-danger">*</span></label>
                            <input type="number" name="numero_inicial" id="massa_numero_inicial" class="form-control" required min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Numeração Final <span class="text-danger">*</span></label>
                            <input type="number" name="numero_final" id="massa_numero_final" class="form-control" required min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prefixo (opcional)</label>
                        <input type="text" name="prefixo" id="massa_prefixo" class="form-control" placeholder="Ex: PAT-">
                        <small class="text-muted">O número de série será: Prefixo + Número (Ex: PAT-001, PAT-002...)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor de Aquisição (para todos)</label>
                        <input type="text" name="valor_aquisicao" id="massa_valor_aquisicao" class="form-control money">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="massa_status" class="form-select">
                                <option value="Ativo" selected>Ativo</option>
                                <option value="Inativo">Inativo</option>
                                <option value="Descarte">Descarte</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status de Locação</label>
                            <select name="status_locacao" id="massa_status_locacao" class="form-select">
                                <option value="Disponivel" selected>Disponível</option>
                                <option value="Locado">Locado</option>
                                <option value="Em Manutencao">Em Manutenção</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="massa_observacoes" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="previewMassa" class="d-none">
                        <label class="form-label">Preview:</label>
                        <div class="border rounded p-2 bg-light" style="max-height: 100px; overflow-y: auto;">
                            <small id="previewMassaContent"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Cadastrar Todos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Bipagem de Patrimônios -->
<div class="modal fade" id="modalBipagemPatrimonio" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bipagem de Patrimônios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formBipagemPatrimonio">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ti ti-scan me-2"></i>
                        Bipe ou digite os números de série. Pressione Enter após cada bipagem.
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Bipar/Digitar Número de Série</label>
                            <input type="text" id="bipagem_input" class="form-control form-control-lg" placeholder="Aguardando bipagem..." autofocus>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" id="btnAdicionarBipagem" class="btn btn-outline-primary w-100">
                                <i class="ti ti-plus me-1"></i> Adicionar
                            </button>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="bipagem_status" class="form-select">
                                <option value="Ativo" selected>Ativo</option>
                                <option value="Inativo">Inativo</option>
                                <option value="Descarte">Descarte</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor de Aquisição (para todos)</label>
                            <input type="text" name="valor_aquisicao" id="bipagem_valor" class="form-control money">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Patrimônios Bipados (<span id="contadorBipagem">0</span>)</label>
                        <button type="button" id="btnLimparBipagem" class="btn btn-outline-danger btn-sm">
                            <i class="ti ti-trash me-1"></i> Limpar Lista
                        </button>
                    </div>
                    <div class="table-responsive border rounded" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>Número de Série</th>
                                    <th width="50">Ação</th>
                                </tr>
                            </thead>
                            <tbody id="listaBipagem">
                                <tr id="bipagemVazio">
                                    <td colspan="3" class="text-center text-muted py-3">
                                        Nenhum patrimônio bipado ainda
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarBipagem" disabled>
                        <i class="ti ti-device-floppy me-1"></i> Salvar Todos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Tabela de Preços -->
<div class="modal fade" id="modalNovaTabela" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Tabela de Preços</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formTabela" method="POST" action="{{ route('tabela-precos.store') }}">
                @csrf
                <input type="hidden" name="id_produto" value="{{ $produto->id_produto }}">
                <input type="hidden" name="redirect_to" value="produto_edit">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nome da Tabela <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: Tabela Padrão">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control">
                    </div>
                    <hr>
                    <h6 class="mb-3">Preços por Período (Diárias)</h6>
                    <div class="row">
                        @for($i = 1; $i <= 30; $i++)
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">{{ $i }} dia{{ $i > 1 ? 's' : '' }}</label>
                            <input type="text" name="d{{ $i }}" class="form-control form-control-sm money">
                        </div>
                        @endfor
                    </div>
                    <h6 class="mb-3 mt-3">Períodos Especiais</h6>
                    <div class="row">
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">60 dias</label>
                            <input type="text" name="d60" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">120 dias</label>
                            <input type="text" name="d120" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">360 dias</label>
                            <input type="text" name="d360" class="form-control form-control-sm money">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tabela de Preços -->
<div class="modal fade" id="modalEditarTabela" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Tabela de Preços</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTabela" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id_produto" value="{{ $produto->id_produto }}">
                <input type="hidden" name="redirect_to" value="produto_edit">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nome da Tabela <span class="text-danger">*</span></label>
                            <input type="text" name="nome" id="edit_nome_tabela" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status_tabela" class="form-select">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" id="edit_descricao_tabela" class="form-control">
                    </div>
                    <hr>
                    <h6 class="mb-3">Preços por Período (Diárias)</h6>
                    <div class="row">
                        @for($i = 1; $i <= 30; $i++)
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">{{ $i }} dia{{ $i > 1 ? 's' : '' }}</label>
                            <input type="text" name="d{{ $i }}" id="edit_d{{ $i }}" class="form-control form-control-sm money">
                        </div>
                        @endfor
                    </div>
                    <h6 class="mb-3 mt-3">Períodos Especiais</h6>
                    <div class="row">
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">60 dias</label>
                            <input type="text" name="d60" id="edit_d60" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">120 dias</label>
                            <input type="text" name="d120" id="edit_d120" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 col-4 mb-2">
                            <label class="form-label small">360 dias</label>
                            <input type="text" name="d360" id="edit_d360" class="form-control form-control-sm money">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Movimentação de Estoque -->
<div class="modal fade" id="modalMovimentacaoEstoque" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Movimentação de Estoque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMovimentacaoEstoque">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" id="mov_tipo" class="form-select" required>
                                <option value="entrada">Entrada</option>
                                <option value="saida">Saída</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" id="mov_quantidade" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <select name="motivo" id="mov_motivo" class="form-select">
                            <option value="">Selecione...</option>
                            <option value="compra">Compra</option>
                            <option value="devolucao">Devolução</option>
                            <option value="ajuste_inventario">Ajuste de Inventário</option>
                            <option value="transferencia">Transferência</option>
                            <option value="perda">Perda/Avaria</option>
                            <option value="venda">Venda</option>
                            <option value="locacao">Locação</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="mov_observacoes" class="form-control" rows="2" placeholder="Observações adicionais..."></textarea>
                    </div>
                    <div class="alert alert-light border mb-0">
                        <div class="d-flex justify-content-between">
                            <span>Estoque Atual:</span>
                            <strong id="mov_estoque_atual">{{ $produto->quantidade ?? 0 }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span>Estoque Após:</span>
                            <strong id="mov_estoque_apos" class="text-primary">{{ $produto->quantidade ?? 0 }}</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMovimentacoesProduto" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Movimentações do Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($produto->patrimonios->count() === 0)
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabelaMovimentacoes">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Qtd</th>
                                    <th>Anterior</th>
                                    <th>Posterior</th>
                                    <th>Origem</th>
                                    <th>Referência</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyMovimentacoes">
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Carregando movimentações...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabelaMovimentacoesPatrimonio">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Qtd</th>
                                    <th>Origem</th>
                                    <th>Referência</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyMovimentacoesPatrimonio">
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Carregando movimentações...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Manutenção -->
<div class="modal fade" id="modalNovaManutencao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Manutenção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formManutencao" method="POST" action="{{ route('manutencoes.store') }}">
                @csrf
                <input type="hidden" name="id_produto" value="{{ $produto->id_produto }}">
                <input type="hidden" name="redirect_to" value="produto_edit">
                <div class="modal-body">
                    @if($produto->patrimonios->count() > 0)
                        <div class="mb-3">
                            <label class="form-label">Patrimônio</label>
                            <select name="id_patrimonio" class="form-select" required>
                                <option value="">Selecione o patrimônio</option>
                                @foreach($produto->patrimonios as $pat)
                                    @php
                                        $statusLocacaoPat = $pat->status_locacao ?? 'Disponivel';
                                        $bloquearPatrimonio = in_array($statusLocacaoPat, ['Locado', 'Em Manutencao'], true);
                                    @endphp
                                    <option value="{{ $pat->id_patrimonio }}" {{ $bloquearPatrimonio ? 'disabled' : '' }}>
                                        {{ $pat->numero_serie ?? ('PAT-' . $pat->id_patrimonio) }}
                                        @if($bloquearPatrimonio)
                                            (status: {{ $statusLocacaoPat }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Quantidade em Manutenção <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" class="form-control" min="1" value="1" required>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select" required>
                                <option value="preventiva">Preventiva</option>
                                <option value="corretiva">Corretiva</option>
                                <option value="preditiva">Preditiva</option>
                                <option value="emergencial">Emergencial</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="em_andamento">Em Manutenção</option>
                                <option value="concluida">Concluída</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                        <textarea name="descricao" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Manutenção <span class="text-danger">*</span></label>
                            <input type="date" name="data_manutencao" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Previsão</label>
                            <input type="date" name="data_previsao" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora de Manutenção</label>
                            <input type="time" name="hora_manutencao" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora da Previsão</label>
                            <input type="time" name="hora_previsao" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custo</label>
                            <input type="text" name="valor" class="form-control money">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsável</label>
                            <input type="text" name="responsavel" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Manutenção -->
<div class="modal fade" id="modalEditarManutencao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Manutenção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarManutencao" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="id_produto" value="{{ $produto->id_produto }}">
                <input type="hidden" name="redirect_to" value="produto_edit">
                <div class="modal-body">
                    @if($produto->patrimonios->count() > 0)
                        <div class="mb-3">
                            <label class="form-label">Patrimônio</label>
                            <select name="id_patrimonio" id="edit_id_patrimonio_manutencao" class="form-select" required>
                                <option value="">Selecione o patrimônio</option>
                                @foreach($produto->patrimonios as $pat)
                                    @php
                                        $statusLocacaoPat = $pat->status_locacao ?? 'Disponivel';
                                        $bloquearPatrimonio = in_array($statusLocacaoPat, ['Locado', 'Em Manutencao'], true);
                                    @endphp
                                    <option value="{{ $pat->id_patrimonio }}" {{ $bloquearPatrimonio ? 'disabled' : '' }}>
                                        {{ $pat->numero_serie ?? ('PAT-' . $pat->id_patrimonio) }}
                                        @if($bloquearPatrimonio)
                                            (status: {{ $statusLocacaoPat }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Quantidade em Manutenção <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" id="edit_quantidade_manutencao" class="form-control" min="1" value="1" required>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" id="edit_tipo_manutencao" class="form-select" required>
                                <option value="preventiva">Preventiva</option>
                                <option value="corretiva">Corretiva</option>
                                <option value="preditiva">Preditiva</option>
                                <option value="emergencial">Emergencial</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status_manutencao" class="form-select">
                                <option value="em_andamento">Em Manutenção</option>
                                <option value="concluida">Concluída</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                        <textarea name="descricao" id="edit_descricao_manutencao" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Manutenção <span class="text-danger">*</span></label>
                            <input type="date" name="data_manutencao" id="edit_data_manutencao" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Previsão</label>
                            <input type="date" name="data_previsao" id="edit_data_previsao_manutencao" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora de Manutenção</label>
                            <input type="time" name="hora_manutencao" id="edit_hora_manutencao" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora da Previsão</label>
                            <input type="time" name="hora_previsao" id="edit_hora_previsao_manutencao" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custo</label>
                            <input type="text" name="valor" id="edit_valor_manutencao" class="form-control money">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Responsável</label>
                            <input type="text" name="responsavel" id="edit_responsavel_manutencao" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/produtos-upload.js') }}"></script>
<script>
$(document).ready(function() {
    const abaInicial = new URLSearchParams(window.location.search).get('aba');
    const mapaAbas = {
        'estoque': '#tab-estoque',
        'manutencoes': '#tab-manutencoes',
        'patrimonios': '#tab-patrimonios',
        'precos': '#tab-precos',
        'anexos': '#tab-anexos',
    };

    if (abaInicial && mapaAbas[abaInicial]) {
        const alvo = document.querySelector('a[data-bs-target="' + mapaAbas[abaInicial] + '"]');
        if (alvo) {
            bootstrap.Tab.getOrCreateInstance(alvo).show();
        }
    }

    // Máscaras
    $('.mask-money').mask('#.##0,00', {reverse: true});
    $('.mask-decimal').mask('#.##0,00', {reverse: true});
    $('.money').mask('#.##0,00', {reverse: true});

    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val();
    }

    function updateCsrfToken(token) {
        if (!token) {
            return;
        }

        $('meta[name="csrf-token"]').attr('content', token);
        $('input[name="_token"]').val(token);
    }

    function refreshCsrfToken(callback, onFail, tentativa = 1) {
        $.ajax({
            url: '{{ route("csrf-token") }}',
            method: 'GET',
            cache: false,
            timeout: 5000,
            success: function(response) {
                var token = response.token || response._csrf_token;
                updateCsrfToken(token);
                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function() {
                if (tentativa < 3) {
                    setTimeout(function() {
                        refreshCsrfToken(callback, onFail, tentativa + 1);
                    }, 300);
                    return;
                }

                if (typeof onFail === 'function') {
                    onFail();
                }
            }
        });
    }

    function bindSubmitWithCsrfRefresh(selector, loadingText, originalHtml) {
        $(selector).on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const formEl = this;
            const botaoOriginal = originalHtml || $submitBtn.html();

            $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>' + (loadingText || 'Salvando...'));

            refreshCsrfToken(
                function() {
                    HTMLFormElement.prototype.submit.call(formEl);
                },
                function() {
                    $submitBtn.prop('disabled', false).html(botaoOriginal);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Não foi possível renovar a sessão automaticamente. Atualize a página e tente novamente.',
                        confirmButtonText: 'OK'
                    });
                }
            );

            return false;
        });
    }

    bindSubmitWithCsrfRefresh('#produtoEditForm', 'Salvando...', '<i class="ti ti-check me-1"></i> Salvar');
    bindSubmitWithCsrfRefresh('#formPatrimonio', 'Salvando...');
    bindSubmitWithCsrfRefresh('#formEditarPatrimonio', 'Salvando...');
    bindSubmitWithCsrfRefresh('#formManutencao', 'Salvando...');
    bindSubmitWithCsrfRefresh('#formEditarManutencao', 'Salvando...');
    bindSubmitWithCsrfRefresh('#formTabela', 'Salvando...');
    bindSubmitWithCsrfRefresh('#formEditarTabela', 'Salvando...');

    // Ação de deletar produto
    $(document).on('click', '.produto-action', function() {
        const action = $(this).data('action');
        const id = $(this).data('id');
        const baseUrl = $(this).data('base-url');

        if (action === 'delete') {
            Swal.fire({
                title: 'Confirmar exclusão?',
                text: 'Deseja realmente excluir este produto?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `${baseUrl}/${id}`,
                        method: 'DELETE',
                        data: {
                            _token: getCsrfToken()
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso!',
                                    text: response.message
                                }).then(() => {
                                    window.location.href = '{{ route('produtos.index') }}';
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: response.message
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: 'Erro ao excluir produto.'
                            });
                        }
                    });
                }
            });
        }
    });

    // === PATRIMÔNIOS ===
    // Editar patrimônio - preencher modal
    $(document).on('click', '.btn-editar-patrimonio', function() {
        var patrimonio = $(this).data('patrimonio');
        $('#formEditarPatrimonio').attr('action', '{{ url("patrimonios") }}/' + patrimonio.id_patrimonio);
        $('#edit_numero_serie').val(patrimonio.numero_serie);
        $('#edit_valor_aquisicao').val(formatMoney(patrimonio.valor_aquisicao));
        $('#edit_status_patrimonio').val(patrimonio.status);
        $('#edit_status_locacao_patrimonio').val(patrimonio.status_locacao);
        $('#edit_observacoes_patrimonio').val(patrimonio.observacoes);
    });

    // Excluir patrimônio
    $(document).on('click', '.btn-excluir-patrimonio', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir este patrimônio?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('patrimonios') }}/${id}`,
                    type: 'DELETE',
                    data: { _token: getCsrfToken() },
                    success: function(response) {
                        Swal.fire('Sucesso!', 'Patrimônio excluído.', 'success').then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    // === TABELA DE PREÇOS ===
    // Editar tabela - preencher modal
    $(document).on('click', '.btn-editar-tabela', function() {
        var tabela = $(this).data('tabela');
        $('#formEditarTabela').attr('action', '{{ url("tabela-precos") }}/' + tabela.id_tabela);
        $('#edit_nome_tabela').val(tabela.nome);
        $('#edit_descricao_tabela').val(tabela.descricao);
        $('#edit_status_tabela').val(tabela.status);
        
        // Preencher todos os dias d1 a d30
        for (var i = 1; i <= 30; i++) {
            $('#edit_d' + i).val(formatMoney(tabela['d' + i]));
        }
        // Períodos especiais
        $('#edit_d60').val(formatMoney(tabela.d60));
        $('#edit_d120').val(formatMoney(tabela.d120));
        $('#edit_d360').val(formatMoney(tabela.d360));
    });

    // Excluir tabela de preços
    $(document).on('click', '.btn-excluir-tabela', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir esta tabela de preços?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('tabela-precos') }}/${id}`,
                    type: 'DELETE',
                    data: { _token: getCsrfToken() },
                    success: function(response) {
                        Swal.fire('Sucesso!', 'Tabela excluída.', 'success').then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    // === MANUTENÇÕES ===
    // Editar manutenção - preencher modal
    $(document).on('click', '.btn-editar-manutencao', function() {
        var manutencao = $(this).data('manutencao');
        $('#formEditarManutencao').attr('action', '{{ url("manutencoes") }}/' + manutencao.id_manutencao);
        $('#edit_id_patrimonio_manutencao').val(manutencao.id_patrimonio);
        $('#edit_tipo_manutencao').val(manutencao.tipo);
        $('#edit_status_manutencao').val(manutencao.status === 'concluida' ? 'concluida' : 'em_andamento');
        $('#edit_descricao_manutencao').val(manutencao.descricao);
        $('#edit_data_manutencao').val(manutencao.data_manutencao ? manutencao.data_manutencao.substring(0, 10) : '');
        $('#edit_data_previsao_manutencao').val(manutencao.data_previsao ? manutencao.data_previsao.substring(0, 10) : '');
        $('#edit_hora_manutencao').val(manutencao.hora_manutencao ? manutencao.hora_manutencao.substring(0, 5) : '');
        $('#edit_hora_previsao_manutencao').val(manutencao.hora_previsao ? manutencao.hora_previsao.substring(0, 5) : '');
        if ($('#edit_quantidade_manutencao').length) {
            $('#edit_quantidade_manutencao').val(manutencao.quantidade || 1);
        }
        $('#edit_valor_manutencao').val(formatMoney(manutencao.valor || manutencao.custo));
        $('#edit_responsavel_manutencao').val(manutencao.responsavel);
    });

    // Excluir manutenção
    $(document).on('click', '.btn-excluir-manutencao', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir esta manutenção?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('manutencoes') }}/${id}`,
                    type: 'DELETE',
                    data: { _token: getCsrfToken() },
                    success: function(response) {
                        Swal.fire('Sucesso!', 'Manutenção excluída.', 'success').then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    // Função auxiliar para formatar dinheiro
    function formatMoney(value) {
        if (!value) return '';
        return parseFloat(value).toFixed(2).replace('.', ',');
    }

    // Limpar campos de preço ao focar (remover 0,00)
    $(document).on('focus', '.money', function() {
        if ($(this).val() === '0,00' || $(this).val() === '') {
            $(this).val('');
        }
    });

    // Reaplicar máscara nos modais quando abrirem
    $('.modal').on('shown.bs.modal', function() {
        $(this).find('.money').mask('#.##0,00', {reverse: true});
    });

    // === SELEÇÃO MÚLTIPLA DE PATRIMÔNIOS ===
    // Selecionar/Desselecionar todos
    $('#checkTodosPatrimonios').on('change', function() {
        $('.check-patrimonio').prop('checked', $(this).is(':checked'));
        atualizarContadorSelecionados();
    });

    // Atualizar contador ao marcar checkbox individual
    $(document).on('change', '.check-patrimonio', function() {
        atualizarContadorSelecionados();
        // Atualizar checkbox "todos"
        var total = $('.check-patrimonio').length;
        var marcados = $('.check-patrimonio:checked').length;
        $('#checkTodosPatrimonios').prop('checked', total === marcados);
    });

    function atualizarContadorSelecionados() {
        var qtd = $('.check-patrimonio:checked').length;
        $('#contadorSelecionados').text(qtd);
        if (qtd > 0) {
            $('#btnExcluirSelecionados').removeClass('d-none');
        } else {
            $('#btnExcluirSelecionados').addClass('d-none');
        }
    }

    // Excluir patrimônios selecionados
    $('#btnExcluirSelecionados').on('click', function() {
        var ids = [];
        $('.check-patrimonio:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        Swal.fire({
            title: 'Confirmar exclusão',
            text: `Deseja realmente excluir ${ids.length} patrimônio(s)?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Excluindo...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: '{{ route("patrimonios.destroyMassa") }}',
                    type: 'POST',
                    data: {
                        _token: getCsrfToken(),
                        ids: ids
                    },
                    success: function(response) {
                        Swal.fire('Sucesso!', response.message, 'success').then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });

    // === PATRIMÔNIOS EM MASSA ===
    // Preview da numeração em massa
    function atualizarPreviewMassa() {
        var inicial = parseInt($('#massa_numero_inicial').val()) || 0;
        var final = parseInt($('#massa_numero_final').val()) || 0;
        var prefixo = $('#massa_prefixo').val() || '';
        
        if (inicial > 0 && final > 0 && final >= inicial) {
            var preview = [];
            var limite = Math.min(final - inicial + 1, 10);
            for (var i = 0; i < limite; i++) {
                var num = inicial + i;
                preview.push(prefixo + String(num).padStart(3, '0'));
            }
            if (final - inicial + 1 > 10) {
                preview.push('...');
                preview.push(prefixo + String(final).padStart(3, '0'));
            }
            $('#previewMassaContent').text(preview.join(', '));
            $('#previewMassa').removeClass('d-none');
        } else {
            $('#previewMassa').addClass('d-none');
        }
    }

    $('#massa_numero_inicial, #massa_numero_final, #massa_prefixo').on('input', atualizarPreviewMassa);

    // Submeter patrimônios em massa
    $('#formPatrimoniosMassa').on('submit', function(e) {
        e.preventDefault();
        
        var inicial = parseInt($('#massa_numero_inicial').val());
        var final = parseInt($('#massa_numero_final').val());
        var prefixo = $('#massa_prefixo').val() || '';
        
        if (final < inicial) {
            Swal.fire('Erro', 'A numeração final deve ser maior ou igual à inicial.', 'error');
            return;
        }
        
        if (final - inicial > 500) {
            Swal.fire('Erro', 'Limite máximo de 500 patrimônios por vez.', 'error');
            return;
        }
        
        var patrimonios = [];
        for (var i = inicial; i <= final; i++) {
            patrimonios.push({
                numero_serie: prefixo + String(i).padStart(3, '0'),
                valor_aquisicao: $('#massa_valor_aquisicao').val(),
                status: $('#massa_status').val(),
                status_locacao: $('#massa_status_locacao').val(),
                observacoes: $('#massa_observacoes').val()
            });
        }
        
        Swal.fire({
            title: 'Cadastrando...',
            text: `Cadastrando ${patrimonios.length} patrimônios...`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        $.ajax({
            url: '{{ route("patrimonios.storeMassa") }}',
            type: 'POST',
            data: {
                _token: getCsrfToken(),
                id_produto: '{{ $produto->id_produto }}',
                patrimonios: patrimonios
            },
            success: function(response) {
                $('#modalPatrimoniosMassa').modal('hide');
                if (response.success) {
                    Swal.fire('Sucesso!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Erro ao cadastrar patrimônios.';
                if (xhr.responseJSON?.duplicados) {
                    msg += '\n\nDuplicados: ' + xhr.responseJSON.duplicados.join(', ');
                }
                Swal.fire('Erro', msg, 'error');
            }
        });
    });

    // === BIPAGEM DE PATRIMÔNIOS ===
    var patrimoniosBipados = [];

    function atualizarListaBipagem() {
        var tbody = $('#listaBipagem');
        tbody.empty();
        
        if (patrimoniosBipados.length === 0) {
            tbody.html('<tr id="bipagemVazio"><td colspan="3" class="text-center text-muted py-3">Nenhum patrimônio bipado ainda</td></tr>');
            $('#btnSalvarBipagem').prop('disabled', true);
        } else {
            patrimoniosBipados.forEach(function(serie, index) {
                tbody.append(`
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${serie}</strong></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-remover-bipagem" data-index="${index}">
                                <i class="ti ti-x"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
            $('#btnSalvarBipagem').prop('disabled', false);
        }
        $('#contadorBipagem').text(patrimoniosBipados.length);
    }

    function adicionarBipagem() {
        var serie = $('#bipagem_input').val().trim();
        if (!serie) return;
        
        if (patrimoniosBipados.includes(serie)) {
            Swal.fire('Atenção', 'Este número de série já foi bipado.', 'warning');
            $('#bipagem_input').val('').focus();
            return;
        }
        
        patrimoniosBipados.push(serie);
        atualizarListaBipagem();
        $('#bipagem_input').val('').focus();
    }

    $('#bipagem_input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            adicionarBipagem();
        }
    });

    $('#btnAdicionarBipagem').on('click', adicionarBipagem);

    $(document).on('click', '.btn-remover-bipagem', function() {
        var index = $(this).data('index');
        patrimoniosBipados.splice(index, 1);
        atualizarListaBipagem();
    });

    $('#btnLimparBipagem').on('click', function() {
        patrimoniosBipados = [];
        atualizarListaBipagem();
    });

    // Limpar lista ao fechar modal
    $('#modalBipagemPatrimonio').on('hidden.bs.modal', function() {
        patrimoniosBipados = [];
        atualizarListaBipagem();
        $('#bipagem_input').val('');
    });

    // Focar no input ao abrir modal
    $('#modalBipagemPatrimonio').on('shown.bs.modal', function() {
        $('#bipagem_input').focus();
    });

    // Salvar patrimônios bipados
    $('#formBipagemPatrimonio').on('submit', function(e) {
        e.preventDefault();
        
        if (patrimoniosBipados.length === 0) {
            Swal.fire('Atenção', 'Nenhum patrimônio bipado.', 'warning');
            return;
        }
        
        var patrimonios = patrimoniosBipados.map(function(serie) {
            return {
                numero_serie: serie,
                valor_aquisicao: $('#bipagem_valor').val(),
                status: $('#bipagem_status').val(),
                status_locacao: 'Disponivel',
                observacoes: ''
            };
        });
        
        Swal.fire({
            title: 'Cadastrando...',
            text: `Cadastrando ${patrimonios.length} patrimônios...`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        $.ajax({
            url: '{{ route("patrimonios.storeMassa") }}',
            type: 'POST',
            data: {
                _token: getCsrfToken(),
                id_produto: '{{ $produto->id_produto }}',
                patrimonios: patrimonios
            },
            success: function(response) {
                $('#modalBipagemPatrimonio').modal('hide');
                if (response.success) {
                    Swal.fire('Sucesso!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Erro ao cadastrar patrimônios.';
                if (xhr.responseJSON?.duplicados) {
                    msg += '\n\nDuplicados: ' + xhr.responseJSON.duplicados.join(', ');
                }
                Swal.fire('Erro', msg, 'error');
            }
        });
    });

    // === MOVIMENTAÇÃO DE ESTOQUE ===
    var estoqueAtual = {{ $produto->quantidade ?? 0 }};
    
    // Carregar movimentações ao abrir aba de estoque
    $('a[data-bs-target="#tab-estoque"]').on('shown.bs.tab', function() {
        carregarMovimentacoes();
    });

    $('#modalMovimentacoesProduto').on('shown.bs.modal', function() {
        carregarMovimentacoes();
    });
    
    function carregarMovimentacoes() {
        $.ajax({
            url: '{{ route("produtos.movimentacoes-estoque", $produto->id_produto) }}',
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    estoqueAtual = response.produto.quantidade;
                    $('#mov_estoque_atual').text(estoqueAtual);
                    atualizarPreviewEstoque();
                    
                    // Atualizar cards
                    $('#estoqueTotal').text(response.produto.estoque_total);
                    $('#estoqueDisponivel').text(response.produto.quantidade);
                    $('#estoqueEmUso').text(response.produto.em_uso);
                    
                    renderizarMovimentacoes(response.movimentacoes, '#tbodyMovimentacoes', false);
                    renderizarMovimentacoes(response.movimentacoes, '#tbodyMovimentacoesPatrimonio', true);
                }
            },
            error: function() {
                $('#tbodyMovimentacoes').html('<tr><td colspan="8" class="text-center text-danger py-3">Erro ao carregar movimentações</td></tr>');
                $('#tbodyMovimentacoesPatrimonio').html('<tr><td colspan="6" class="text-center text-danger py-3">Erro ao carregar movimentações</td></tr>');
            }
        });
    }
    
    function renderizarMovimentacoes(movimentacoes, tbodySelector, modoPatrimonio) {
        var tbody = $(tbodySelector);
        tbody.empty();
        
        if (movimentacoes.length === 0) {
            var colspanVazio = modoPatrimonio ? 6 : 8;
            tbody.html('<tr><td colspan="' + colspanVazio + '" class="text-center py-4"><div class="text-muted"><i class="ti ti-history ti-lg d-block mb-2"></i><p class="mb-0">Nenhuma movimentação registrada</p></div></td></tr>');
            return;
        }
        
        movimentacoes.forEach(function(mov) {
            var tipoBadge = mov.tipo === 'entrada' 
                ? '<span class="badge bg-label-success"><i class="ti ti-arrow-up me-1"></i>Entrada</span>'
                : '<span class="badge bg-label-danger"><i class="ti ti-arrow-down me-1"></i>Saída</span>';

            var origemLabel = {
                manual: 'Manual',
                locacao: 'Locação',
                manutencao: 'Manutenção'
            }[mov.origem] || '-';
            
            if (modoPatrimonio) {
                tbody.append(`
                    <tr>
                        <td>${mov.created_at}</td>
                        <td>${tipoBadge}</td>
                        <td><strong>${mov.tipo === 'entrada' ? '+' : '-'}${mov.quantidade}</strong></td>
                        <td>${origemLabel}</td>
                        <td>${mov.referencia || '-'}</td>
                        <td>${mov.motivo || '-'}</td>
                    </tr>
                `);
                return;
            }

            tbody.append(`
                <tr>
                    <td>${mov.created_at}</td>
                    <td>${tipoBadge}</td>
                    <td><strong>${mov.tipo === 'entrada' ? '+' : '-'}${mov.quantidade}</strong></td>
                    <td>${mov.estoque_anterior ?? '-'}</td>
                    <td>${mov.estoque_posterior ?? '-'}</td>
                    <td>${origemLabel}</td>
                    <td>${mov.referencia || '-'}</td>
                    <td>${mov.motivo || '-'}</td>
                </tr>
            `);
        });
    }
    
    // Calcular preview ao alterar tipo/quantidade
    $('#mov_tipo, #mov_quantidade').on('change input', function() {
        atualizarPreviewEstoque();
    });
    
    function atualizarPreviewEstoque() {
        var tipo = $('#mov_tipo').val();
        var qtd = parseInt($('#mov_quantidade').val()) || 0;
        var novoEstoque = tipo === 'entrada' ? estoqueAtual + qtd : estoqueAtual - qtd;
        
        $('#mov_estoque_apos').text(novoEstoque);
        
        if (tipo === 'saida' && novoEstoque < 0) {
            $('#mov_estoque_apos').addClass('text-danger').removeClass('text-primary');
        } else {
            $('#mov_estoque_apos').removeClass('text-danger').addClass('text-primary');
        }
    }
    
    // Submeter movimentação
    $('#formMovimentacaoEstoque').on('submit', function(e) {
        e.preventDefault();
        
        var tipo = $('#mov_tipo').val();
        var qtd = parseInt($('#mov_quantidade').val()) || 0;
        var novoEstoque = tipo === 'entrada' ? estoqueAtual + qtd : estoqueAtual - qtd;
        
        if (tipo === 'saida' && novoEstoque < 0) {
            Swal.fire('Atenção', 'Estoque insuficiente para esta operação.', 'warning');
            return;
        }
        
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Registrando...');
        
        $.ajax({
            url: '{{ route("produtos.registrar-movimentacao", $produto->id_produto) }}',
            type: 'POST',
            data: {
                _token: getCsrfToken(),
                tipo: tipo,
                quantidade: qtd,
                motivo: $('#mov_motivo').val(),
                observacoes: $('#mov_observacoes').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#modalMovimentacaoEstoque').modal('hide');
                    
                    // Limpar form
                    $('#mov_quantidade').val(1);
                    $('#mov_motivo').val('');
                    $('#mov_observacoes').val('');
                    
                    // Atualizar valores
                    estoqueAtual = response.produto.quantidade;
                    $('#estoqueTotal').text(response.produto.estoque_total);
                    $('#estoqueDisponivel').text(response.produto.quantidade);
                    $('#estoqueEmUso').text(response.produto.em_uso);
                    $('#mov_estoque_atual').text(estoqueAtual);
                    atualizarPreviewEstoque();
                    
                    // Recarregar tabela
                    carregarMovimentacoes();
                    
                    Swal.fire('Sucesso!', response.message, 'success');
                } else {
                    Swal.fire('Erro', response.message, 'error');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Erro ao registrar movimentação.';
                Swal.fire('Erro', msg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Registrar');
            }
        });
    });
    function abrirModalContratosProduto(idPatrimonio, seriePatrimonio) {
        $('#modalContratosProdutoTitle').text(idPatrimonio
            ? ('Contratos do Patrimônio ' + (seriePatrimonio || ('PAT-' + idPatrimonio)))
            : 'Contratos do Produto');

        $('#modalContratosProdutoBody').html('<div class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-1"></i> Carregando contratos...</div>');
        $('#modalContratosProduto').modal('show');

        $.ajax({
            url: '{{ route("produtos.historico-locacoes", $produto->id_produto) }}',
            type: 'GET',
            data: idPatrimonio ? { id_patrimonio: idPatrimonio } : {},
            success: function(response) {
                if (!response.success) {
                    $('#modalContratosProdutoBody').html('<div class="alert alert-danger mb-0">Erro ao carregar contratos.</div>');
                    return;
                }

                var locacoes = response.locacoes || [];
                if (locacoes.length === 0) {
                    $('#modalContratosProdutoBody').html('<div class="text-center py-4 text-muted">Nenhum contrato encontrado.</div>');
                    return;
                }

                var html = '<div class="table-responsive"><table class="table table-sm table-hover">';
                html += '<thead><tr><th>Contrato</th><th>Cliente</th><th>Período</th><th>Qtd</th><th>Status</th><th></th></tr></thead><tbody>';

                locacoes.forEach(function(loc) {
                    var statusBadge = {
                        'orcamento': '<span class="badge bg-secondary">Orçamento</span>',
                        'reserva': '<span class="badge bg-info">Reserva</span>',
                        'aprovado': '<span class="badge bg-primary">Aprovado</span>',
                        'em_andamento': '<span class="badge bg-primary">Em Andamento</span>',
                        'finalizada': '<span class="badge bg-success">Finalizada</span>',
                        'cancelada': '<span class="badge bg-danger">Cancelada</span>',
                        'atrasada': '<span class="badge bg-warning">Atrasada</span>'
                    };

                    html += '<tr>';
                    html += '<td><strong>' + (loc.numero_contrato || '-') + '</strong></td>';
                    html += '<td>' + (loc.cliente_nome || loc.cliente || '-') + '</td>';
                    html += '<td>' + (loc.data_inicio || '-') + ' - ' + (loc.data_fim || '-') + '</td>';
                    html += '<td>' + (loc.quantidade || 1) + '</td>';
                    html += '<td>' + (statusBadge[loc.status] || loc.status) + '</td>';
                    html += '<td><a href="/locacoes/' + loc.id_locacao + '" class="btn btn-xs btn-outline-primary" target="_blank"><i class="ti ti-eye ti-xs"></i></a></td>';
                    html += '</tr>';
                });

                html += '</tbody></table></div>';
                $('#modalContratosProdutoBody').html(html);
            },
            error: function() {
                $('#modalContratosProdutoBody').html('<div class="alert alert-danger mb-0">Erro ao carregar contratos.</div>');
            }
        });
    }

    $(document).on('click', '#btnVerContratosProduto', function() {
        abrirModalContratosProduto(null, null);
    });

    $(document).on('click', '.btn-ver-contratos-patrimonio', function() {
        abrirModalContratosProduto($(this).data('id'), $(this).data('serie'));
    });
    
});
</script>

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
