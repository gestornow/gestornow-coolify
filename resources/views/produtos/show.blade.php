@extends('layouts.layoutMaster')

@section('title', 'Visualizar Produto - ' . ($produto->nome ?? 'Produto'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif

            @php
                $podeEditarProduto = \Perm::pode(auth()->user(), 'produtos.editar');
                $podePatrimonio = \Perm::pode(auth()->user(), 'produtos.patrimonio');
                $podeTabelaPrecos = \Perm::pode(auth()->user(), 'produtos.tabela-precos');
                $podeManutencao = \Perm::pode(auth()->user(), 'produtos.manutencao');
                $podeAcessorios = \Perm::pode(auth()->user(), 'produtos.acessorios');
            @endphp
            
            <!-- Cabeçalho do Produto -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-lg me-3">
                            <span class="avatar-initial rounded-circle bg-label-primary fs-4">{{ $produto->inicial }}</span>
                        </div>
                        <div>
                            <h5 class="mb-0">{{ $produto->nome }}</h5>
                            <small class="text-muted">{{ $produto->codigo ?? 'Sem código' }}</small>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        @if($produto->status === 'ativo')
                            <span class="badge bg-label-success">Ativo</span>
                        @else
                            <span class="badge bg-label-warning">Inativo</span>
                        @endif
                        @if($podeEditarProduto)
                            <a href="{{ route('produtos.edit', $produto->id_produto) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-pencil me-1"></i> Editar
                            </a>
                        @endif
                        <a href="{{ route('produtos.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Navegação por Abas -->
            <div class="nav-align-top mb-4">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-info">
                            <i class="ti ti-info-circle me-1"></i> Informações
                        </button>
                    </li>
                    @if($podePatrimonio)
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-patrimonios">
                                <i class="ti ti-building me-1"></i> Patrimônios
                                <span class="badge bg-label-primary ms-1">{{ $produto->patrimonios->count() ?? 0 }}</span>
                            </button>
                        </li>
                    @endif
                    @if($podeTabelaPrecos)
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-precos">
                                <i class="ti ti-currency-dollar me-1"></i> Tabela de Preços
                                <span class="badge bg-label-success ms-1">{{ $produto->tabelasPreco->count() ?? 0 }}</span>
                            </button>
                        </li>
                    @endif
                    @if($podeManutencao)
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-manutencoes">
                                <i class="ti ti-tool me-1"></i> Manutenções
                                <span class="badge bg-label-warning ms-1">{{ $produto->manutencoes->count() ?? 0 }}</span>
                            </button>
                        </li>
                    @endif
                    @if($podeAcessorios)
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-acessorios">
                                <i class="ti ti-puzzle me-1"></i> Acessórios
                                <span class="badge bg-label-info ms-1">{{ $produto->acessorios->count() ?? 0 }}</span>
                            </button>
                        </li>
                    @endif
                </ul>

                <div class="tab-content">
                    <!-- Tab Informações -->
                    <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                        <div class="row p-3">
                            <!-- Coluna Principal -->
                            <div class="col-md-8">
                                <!-- Informações Básicas -->
                                <div class="card mb-4" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 1px solid #667eea30;">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-info-circle me-2"></i>
                                            Informações Básicas
                                        </h6>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label small text-muted">Nome</label>
                                                <p class="mb-0 fw-medium">{{ $produto->nome }}</p>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label small text-muted">Código</label>
                                                <p class="mb-0 fw-medium">{{ $produto->codigo ?? '-' }}</p>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label small text-muted">Número de Série</label>
                                                <p class="mb-0 fw-medium">{{ $produto->numero_serie ?? '-' }}</p>
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label small text-muted">Descrição</label>
                                                <p class="mb-0">{{ $produto->descricao ?? 'Sem descrição' }}</p>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small text-muted">Detalhes</label>
                                                <p class="mb-0">{{ $produto->detalhes ?? 'Sem detalhes' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preços -->
                                <div class="card mb-4" style="background: linear-gradient(135deg, #11998e15 0%, #38ef7d15 100%); border: 1px solid #11998e30;">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-currency-dollar me-2"></i>
                                            Preços
                                        </h6>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label small text-muted">Preço de Custo</label>
                                                <p class="mb-0 fw-medium text-danger">{{ $produto->preco_custo_formatado }}</p>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label small text-muted">Preço de Venda</label>
                                                <p class="mb-0 fw-medium text-success fs-5">{{ $produto->preco_formatado }}</p>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label small text-muted">Preço de Locação</label>
                                                <p class="mb-0 fw-medium text-info">R$ {{ number_format($produto->preco_locacao ?? 0, 2, ',', '.') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dimensões -->
                                <div class="card mb-4" style="background: linear-gradient(135deg, #ee097915 0%, #ff6a0015 100%); border: 1px solid #ee097930;">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-ruler-measure me-2"></i>
                                            Dimensões
                                        </h6>
                                        
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label small text-muted">Altura</label>
                                                <p class="mb-0 fw-medium">{{ number_format($produto->altura ?? 0, 2, ',', '.') }} cm</p>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label small text-muted">Largura</label>
                                                <p class="mb-0 fw-medium">{{ number_format($produto->largura ?? 0, 2, ',', '.') }} cm</p>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label small text-muted">Profundidade</label>
                                                <p class="mb-0 fw-medium">{{ number_format($produto->profundidade ?? 0, 2, ',', '.') }} cm</p>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label small text-muted">Peso</label>
                                                <p class="mb-0 fw-medium">{{ number_format($produto->peso ?? 0, 2, ',', '.') }} kg</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Coluna Lateral -->
                            <div class="col-md-4">
                                <!-- Estoque -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-box me-2"></i>
                                            Estoque
                                        </h6>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Estoque Total</span>
                                            <span class="fw-bold fs-5 {{ ($produto->estoque_total ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $produto->estoque_total ?? 0 }}
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">Quantidade</span>
                                            <span class="fw-bold fs-5">{{ $produto->quantidade ?? 0 }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Disponível</span>
                                            <span class="fw-bold fs-5 text-info">{{ $produto->quantidade_disponivel ?? 0 }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Datas -->
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-calendar me-2"></i>
                                            Datas
                                        </h6>
                                        
                                        <div class="mb-2">
                                            <span class="text-muted small">Cadastro:</span>
                                            <span class="d-block">{{ optional($produto->created_at)->format('d/m/Y H:i') ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted small">Última Atualização:</span>
                                            <span class="d-block">{{ optional($produto->updated_at)->format('d/m/Y H:i') ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Patrimônios -->
                    @if($podePatrimonio)
                    <div class="tab-pane fade" id="tab-patrimonios" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Patrimônios do Produto</h6>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoPatrimonio">
                                    <i class="ti ti-plus me-1"></i> Novo Patrimônio
                                </button>
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
                                                <strong>{{ $produto->patrimonios->where('status', 'disponivel')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-info">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Locados</span>
                                                <strong>{{ $produto->patrimonios->where('status', 'locado')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-warning">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Manutenção</span>
                                                <strong>{{ $produto->patrimonios->where('status', 'manutencao')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nº Série</th>
                                            <th>Localização</th>
                                            <th>Valor Atual</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($produto->patrimonios as $patrimonio)
                                            <tr>
                                                <td><strong>{{ $patrimonio->codigo_patrimonio }}</strong></td>
                                                <td>{{ $patrimonio->numero_serie ?? '-' }}</td>
                                                <td>{{ $patrimonio->localizacao ?? '-' }}</td>
                                                <td>{{ $patrimonio->valor_atual_formatado ?? 'R$ 0,00' }}</td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'disponivel' => 'success',
                                                            'em_uso' => 'primary',
                                                            'locado' => 'info',
                                                            'manutencao' => 'warning',
                                                            'baixado' => 'secondary',
                                                            'perdido' => 'danger'
                                                        ];
                                                        $statusLabels = [
                                                            'disponivel' => 'Disponível',
                                                            'em_uso' => 'Em Uso',
                                                            'locado' => 'Locado',
                                                            'manutencao' => 'Manutenção',
                                                            'baixado' => 'Baixado',
                                                            'perdido' => 'Perdido'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$patrimonio->status] ?? 'secondary' }}">
                                                        {{ $statusLabels[$patrimonio->status] ?? $patrimonio->status }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-icon btn-outline-primary btn-editar-patrimonio" data-patrimonio="{{ json_encode($patrimonio) }}">
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
                    </div>
                    @endif

                    <!-- Tab Tabela de Preços -->
                    @if($podeTabelaPrecos)
                    <div class="tab-pane fade" id="tab-precos" role="tabpanel">
                        <div class="p-3">
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
                                            <button class="btn btn-sm btn-icon btn-outline-primary btn-editar-tabela" data-tabela="{{ json_encode($tabela) }}">
                                                <i class="ti ti-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-icon btn-outline-danger btn-excluir-tabela" data-id="{{ $tabela->id_tabela }}">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-2 mb-2">
                                                <small class="text-muted d-block">Por Hora</small>
                                                <strong>R$ {{ number_format($tabela->preco_hora ?? 0, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <small class="text-muted d-block">Diária</small>
                                                <strong>R$ {{ number_format($tabela->d1 ?? 0, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <small class="text-muted d-block">Semanal</small>
                                                <strong>R$ {{ number_format($tabela->preco_semanal ?? $tabela->d7 ?? 0, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <small class="text-muted d-block">Quinzenal</small>
                                                <strong>R$ {{ number_format($tabela->preco_quinzenal ?? $tabela->d15 ?? 0, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <small class="text-muted d-block">Mensal</small>
                                                <strong>R$ {{ number_format($tabela->preco_mensal ?? $tabela->d30 ?? 0, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <small class="text-muted d-block">Anual</small>
                                                <strong>R$ {{ number_format($tabela->preco_anual ?? $tabela->d360 ?? 0, 2, ',', '.') }}</strong>
                                            </div>
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
                    </div>
                    @endif

                    <!-- Tab Manutenções -->
                    @if($podeManutencao)
                    <div class="tab-pane fade" id="tab-manutencoes" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Manutenções do Produto</h6>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovaManutencao">
                                    <i class="ti ti-plus me-1"></i> Nova Manutenção
                                </button>
                            </div>

                            <!-- Cards de Resumo -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-label-warning">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Pendentes</span>
                                                <strong>{{ $produto->manutencoes->where('status', 'pendente')->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-label-info">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>Em Andamento</span>
                                                <strong>{{ $produto->manutencoes->where('status', 'em_andamento')->count() }}</strong>
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
                                                <span>Custo Total</span>
                                                <strong>R$ {{ number_format($produto->manutencoes->sum('custo'), 2, ',', '.') }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Descrição</th>
                                            <th>Data Entrada</th>
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
                                                <td>{{ Str::limit($manutencao->descricao, 40) }}</td>
                                                <td>{{ optional($manutencao->data_entrada)->format('d/m/Y') ?? '-' }}</td>
                                                <td>{{ optional($manutencao->data_previsao)->format('d/m/Y') ?? '-' }}</td>
                                                <td>{{ $manutencao->custo_formatado ?? 'R$ 0,00' }}</td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'pendente' => 'warning',
                                                            'em_andamento' => 'info',
                                                            'concluida' => 'success',
                                                            'cancelada' => 'danger'
                                                        ];
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$manutencao->status] ?? 'secondary' }}">
                                                        {{ ucfirst(str_replace('_', ' ', $manutencao->status)) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-icon btn-outline-primary btn-editar-manutencao" data-manutencao="{{ json_encode($manutencao) }}">
                                                        <i class="ti ti-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-icon btn-outline-danger btn-excluir-manutencao" data-id="{{ $manutencao->id_manutencao }}">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-muted">
                                                    <i class="ti ti-tool-off ti-lg mb-2"></i>
                                                    <p class="mb-0">Nenhuma manutenção registrada</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Tab Acessórios -->
                    @if($podeAcessorios)
                    <div class="tab-pane fade" id="tab-acessorios" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Acessórios Vinculados</h6>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalVincularAcessorio">
                                    <i class="ti ti-link me-1"></i> Vincular Acessório
                                </button>
                            </div>

                            <div class="row">
                                @forelse($produto->acessorios as $acessorio)
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">{{ $acessorio->nome }}</h6>
                                                        <small class="text-muted d-block">{{ $acessorio->codigo ?? 'Sem código' }}</small>
                                                    </div>
                                                    <button class="btn btn-sm btn-icon btn-outline-danger btn-desvincular-acessorio" data-id="{{ $acessorio->id_acessorio }}">
                                                        <i class="ti ti-unlink"></i>
                                                    </button>
                                                </div>
                                                <p class="small text-muted mt-2 mb-2">{{ Str::limit($acessorio->descricao, 60) }}</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-success fw-medium">R$ {{ number_format($acessorio->valor ?? 0, 2, ',', '.') }}</span>
                                                    <span class="badge bg-label-info">Qtd: {{ $acessorio->pivot->quantidade ?? 1 }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="text-center py-5 text-muted">
                                            <i class="ti ti-puzzle-off ti-lg mb-2"></i>
                                            <p class="mb-0">Nenhum acessório vinculado</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Patrimônio -->
@if($podePatrimonio)
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
                <input type="hidden" name="redirect_to" value="produto">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código do Patrimônio <span class="text-danger">*</span></label>
                        <input type="text" name="codigo_patrimonio" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número de Série</label>
                        <input type="text" name="numero_serie" class="form-control">
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
                    <div class="mb-3">
                        <label class="form-label">Localização</label>
                        <input type="text" name="localizacao" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status de Locação</label>
                        <select name="status_locacao" class="form-select">
                            <option value="Disponivel">Disponível</option>
                            <option value="Locado">Locado</option>
                        </select>
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
@endif

<!-- Modal Nova Manutenção -->
@if($podeManutencao)
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
                <input type="hidden" name="redirect_to" value="produto">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Patrimônio</label>
                        <select name="id_patrimonio" class="form-select">
                            <option value="">Nenhum (geral)</option>
                            @foreach($produto->patrimonios as $pat)
                                <option value="{{ $pat->id_patrimonio }}">{{ $pat->numero_serie ?: $pat->id_patrimonio }}</option>
                            @endforeach
                        </select>
                    </div>
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
                                <option value="pendente">Pendente</option>
                                <option value="em_andamento">Em Andamento</option>
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
@endif

<!-- Modal Nova Tabela de Preços -->
@if($podeTabelaPrecos)
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
                <input type="hidden" name="redirect_to" value="produto">
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
                    <h6 class="mb-3">Preços por Período</h6>
                    <div class="row">
                        <div class="col-md-2 mb-2">
                            <label class="form-label small">Por Hora</label>
                            <input type="text" name="preco_hora" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label small">Diária (d1)</label>
                            <input type="text" name="d1" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label small">Semanal</label>
                            <input type="text" name="preco_semanal" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label small">Quinzenal</label>
                            <input type="text" name="preco_quinzenal" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label small">Mensal</label>
                            <input type="text" name="preco_mensal" class="form-control form-control-sm money">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="form-label small">Anual</label>
                            <input type="text" name="preco_anual" class="form-control form-control-sm money">
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
@endif

<!-- Modal Vincular Acessório -->
@if($podeAcessorios)
<div class="modal fade" id="modalVincularAcessorio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vincular Acessório</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVincularAcessorio" method="POST" action="{{ route('produtos.acessorios.store', $produto->id_produto) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Acessório <span class="text-danger">*</span></label>
                        <select name="id_acessorio" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach($acessoriosDisponiveis ?? [] as $acessorio)
                                <option value="{{ $acessorio->id_acessorio }}">{{ $acessorio->nome }} - R$ {{ number_format($acessorio->valor ?? 0, 2, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantidade</label>
                        <input type="number" name="quantidade" class="form-control" value="1" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observação</label>
                        <textarea name="observacao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Vincular</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Máscara de dinheiro
    $('.money').mask('#.##0,00', {reverse: true});

    // Excluir patrimônio
    $('.btn-excluir-patrimonio').on('click', function() {
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
                    data: { _token: '{{ csrf_token() }}' },
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

    // Excluir manutenção
    $('.btn-excluir-manutencao').on('click', function() {
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
                    data: { _token: '{{ csrf_token() }}' },
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

    // Excluir tabela de preços
    $('.btn-excluir-tabela').on('click', function() {
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
                    data: { _token: '{{ csrf_token() }}' },
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

    // Desvincular acessório
    $('.btn-desvincular-acessorio').on('click', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Confirmar',
            text: 'Deseja realmente desvincular este acessório?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, desvincular!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('produtos/' . $produto->id_produto . '/acessorios') }}/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        Swal.fire('Sucesso!', 'Acessório desvinculado.', 'success').then(() => location.reload());
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao desvincular.', 'error');
                    }
                });
            }
        });
    });
});
</script>
@endsection
