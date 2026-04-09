@extends('layouts.layoutMaster')

@section('title', 'Criar Novo Módulo')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold py-3 mb-2">
                        <span class="text-muted fw-light">Admin / Módulos /</span> Criar Novo Módulo
                    </h4>
                </div>
                <div>
                    <a href="{{ route('admin.planos.index') }}" class="btn btn-secondary">
                        <i class="ti ti-arrow-left me-1"></i>
                        Voltar aos Planos
                    </a>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Oops!</strong> Corrija os erros abaixo:
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informações do Módulo</h5>
                            <p class="text-muted mb-0">Preencha os dados do novo módulo</p>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.modulos.store') }}" method="POST">
                                @csrf
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="nome" class="form-label">Nome do Módulo <span class="text-danger">*</span></label>
                                        <input type="text" id="nome" name="nome" class="form-control" 
                                               value="{{ old('nome') }}" placeholder="Ex: Produtos, Financeiro" required>
                                        <div class="form-text">Nome único para identificar o módulo</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="id_modulo_pai" class="form-label">Módulo Pai</label>
                                        <select id="id_modulo_pai" name="id_modulo_pai" class="form-select">
                                            <option value="">Nenhum (Módulo Principal)</option>
                                            @foreach($modulosPrincipais as $moduloPai)
                                                <option value="{{ $moduloPai->id_modulo }}" {{ old('id_modulo_pai') == $moduloPai->id_modulo ? 'selected' : '' }}>
                                                    {{ $moduloPai->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Deixe em branco para criar um módulo principal, ou selecione um módulo para criar um submódulo</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="descricao" class="form-label">Descrição</label>
                                        <textarea id="descricao" name="descricao" class="form-control" rows="3"
                                                  placeholder="Descrição opcional do módulo">{{ old('descricao') }}</textarea>
                                        <div class="form-text">Descrição que aparecerá nos formulários de planos</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="icone" class="form-label">Ícone</label>
                                        <input type="text" id="icone" name="icone" class="form-control" 
                                               value="{{ old('icone') }}" placeholder="Ex: ti ti-box, ti ti-chart-bar">
                                        <div class="form-text">Classe do ícone (Tabler Icons). Veja: <a href="https://tabler-icons.io/" target="_blank">tabler-icons.io</a></div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="rota" class="form-label">Rota</label>
                                        <input type="text" id="rota" name="rota" class="form-control" 
                                               value="{{ old('rota') }}" placeholder="Ex: admin.produtos.index">
                                        <div class="form-text">Nome da rota Laravel (opcional)</div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label for="ordem" class="form-label">Ordem de Exibição</label>
                                        <input type="number" id="ordem" name="ordem" class="form-control" 
                                               value="{{ old('ordem', 0) }}" min="0" placeholder="0">
                                        <div class="form-text">Ordem em que o módulo aparecerá no menu (0 = primeiro)</div>
                                    </div>
                                </div>

                                <hr>

                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('admin.planos.index') }}" class="btn btn-secondary">
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        Salvar Módulo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection