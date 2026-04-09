@extends('layouts.layoutMaster')

@section('title', 'Editar Plano')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css')}}" />
<style>
    /* Estilo para módulos com submódulos */
    .modulo-principal-row:hover {
        background-color: rgba(0, 0, 0, 0.02);
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
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Máscara para valores monetários
    $('.money-input').on('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            value = (parseFloat(value) / 100).toFixed(2);
            this.value = value;
        }
    });

    // Selecionar todos os módulos
    $('#select-all-modulos').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.modulo-checkbox').prop('checked', isChecked).trigger('change');
    });

    // Toggle de módulos
    $('.modulo-checkbox').on('change', function() {
        const row = $(this).closest('.modulo-row');
        const limiteInput = row.find('.limite-input');
        
        if ($(this).is(':checked')) {
            row.removeClass('opacity-50');
            limiteInput.prop('disabled', false);
        } else {
            row.addClass('opacity-50');
            limiteInput.prop('disabled', true).val('');
        }

        // Atualizar estado do "selecionar todos"
        atualizarSelectAll();
    });

    // Função para atualizar o checkbox "selecionar todos"
    function atualizarSelectAll() {
        const total = $('.modulo-checkbox').length;
        const checked = $('.modulo-checkbox:checked').length;
        $('#select-all-modulos').prop('checked', total === checked);
    }

    // Inicializar estado dos módulos
    $('.modulo-checkbox').trigger('change');
    atualizarSelectAll();

    // Toggle de submódulos - expandir/recolher ao clicar no módulo pai
    $(document).on('click', '.modulo-principal-cell[data-has-subs="true"]', function(e) {
        // Não expandir se clicar no checkbox ou input
        if ($(e.target).is('input') || $(e.target).closest('.form-check').length > 0) {
            return;
        }
        
        const moduloId = $(this).data('modulo-id');
        const submodulos = $(`.submodulo-pai-${moduloId}`);
        const iconExpand = $(this).find('.icon-expand');
        
        if (submodulos.first().is(':visible')) {
            // Recolher submódulos
            submodulos.fadeOut(200);
            iconExpand.removeClass('expanded');
        } else {
            // Expandir submódulos
            submodulos.fadeIn(200);
            iconExpand.addClass('expanded');
        }
    });

    // Também permitir clicar no ícone diretamente
    $(document).on('click', '.icon-expand', function(e) {
        e.stopPropagation();
        e.preventDefault();
        $(this).closest('.modulo-principal-cell').trigger('click');
    });
});
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
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

            <form action="{{ route('admin.planos.update', $plano) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <!-- Informações Básicas -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Informações do Plano</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="nome" class="form-label">Nome do Plano <span class="text-danger">*</span></label>
                                        <input type="text" id="nome" name="nome" class="form-control" 
                                               value="{{ old('nome', $plano->nome) }}" placeholder="Ex: Plano Básico" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="valor" class="form-label">Valor Mensal (R$) <span class="text-danger">*</span></label>
                                        <input type="number" id="valor" name="valor" class="form-control money-input" 
                                               value="{{ old('valor', $plano->valor) }}" step="0.01" min="0" placeholder="0.00" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="adesao" class="form-label">Taxa de Adesão (R$) <span class="text-danger">*</span></label>
                                        <input type="number" id="adesao" name="adesao" class="form-control money-input" 
                                               value="{{ old('adesao', $plano->adesao) }}" step="0.01" min="0" placeholder="0.00" required>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="descricao" class="form-label">Descrição</label>
                                        <textarea id="descricao" name="descricao" class="form-control" rows="3"
                                                  placeholder="Descrição opcional do plano">{{ old('descricao', $plano->descricao) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Módulos do Plano -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Módulos do Plano</h5>
                                <p class="text-muted mb-0">Selecione os módulos que farão parte deste plano</p>
                            </div>
                            <div class="card-body">
                                @if($modulos->isEmpty())
                                    <div class="text-center py-4">
                                        <p class="text-muted">Nenhum módulo disponível. Configure os módulos primeiro.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <thead>
                                                <tr>
                                                    <th width="50">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="select-all-modulos">
                                                            <label class="form-check-label" for="select-all-modulos" title="Selecionar todos">
                                                                <small>Todos</small>
                                                            </label>
                                                        </div>
                                                    </th>
                                                    <th>Módulo</th>
                                                    <th width="150">Limite</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $index = 0;
                                                @endphp
                                                @foreach($modulos as $modulo)
                                                    @if($modulo->isPrincipal())
                                                        @php
                                                            $planoModulo = $plano->todosModulos->where('id_modulo', $modulo->id_modulo)->first();
                                                            $isChecked = $planoModulo && $planoModulo->ativo;
                                                            $limite = $planoModulo ? $planoModulo->limite : null;
                                                            $qtdSubs = $modulo->submodulos->count();
                                                        @endphp
                                                        <!-- Módulo Principal -->
                                                        <tr class="modulo-row modulo-principal-row">
                                                            <td>
                                                                <input type="hidden" name="modulos[{{ $index }}][id_modulo]" value="{{ $modulo->id_modulo }}">
                                                                <input type="hidden" name="modulos[{{ $index }}][ativo]" value="0">
                                                                <div class="form-check">
                                                                    <input class="form-check-input modulo-checkbox" type="checkbox" 
                                                                           name="modulos[{{ $index }}][ativo]" 
                                                                           id="modulo_{{ $modulo->id_modulo }}" value="1"
                                                                           {{ old("modulos.{$index}.ativo", $isChecked) ? 'checked' : '' }}>
                                                                </div>
                                                            </td>
                                                            <td class="modulo-principal-cell" data-modulo-id="{{ $modulo->id_modulo }}" data-has-subs="{{ $qtdSubs > 0 ? 'true' : 'false' }}">
                                                                @if($qtdSubs > 0)
                                                                    <i class="ti ti-chevron-right me-2 text-muted icon-expand" title="Expandir submódulos"></i>
                                                                @endif
                                                                <span class="form-label mb-0">
                                                                    @if($modulo->icone)
                                                                        <i class="{{ $modulo->icone }} me-1"></i>
                                                                    @endif
                                                                    <strong>{{ $modulo->nome }}</strong>
                                                                    <span class="badge bg-label-dark ms-1" title="Ordem de exibição">#{{ $modulo->ordem ?? 0 }}</span>
                                                                    @if($qtdSubs > 0)
                                                                        <span class="badge bg-label-primary ms-1">{{ $qtdSubs }} Sub.</span>
                                                                    @endif
                                                                    @if($modulo->descricao)
                                                                        <br><small class="text-muted">{{ $modulo->descricao }}</small>
                                                                    @endif
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <!-- Sem limite para módulos principais -->
                                                            </td>
                                                        </tr>
                                                        @php $index++; @endphp

                                                        <!-- Submódulos -->
                                                        @foreach($modulo->submodulos as $submodulo)
                                                            @php
                                                                $planoSubmodulo = $plano->todosModulos->where('id_modulo', $submodulo->id_modulo)->first();
                                                                $isSubChecked = $planoSubmodulo && $planoSubmodulo->ativo;
                                                                $subLimite = $planoSubmodulo ? $planoSubmodulo->limite : null;
                                                            @endphp
                                                            <tr class="modulo-row submodulo-row submodulo-pai-{{ $modulo->id_modulo }}" style="display: none;">
                                                                <td>
                                                                    <input type="hidden" name="modulos[{{ $index }}][id_modulo]" value="{{ $submodulo->id_modulo }}">
                                                                    <input type="hidden" name="modulos[{{ $index }}][ativo]" value="0">
                                                                    <div class="form-check ps-4">
                                                                        <input class="form-check-input modulo-checkbox submodulo-checkbox" type="checkbox" 
                                                                               name="modulos[{{ $index }}][ativo]" 
                                                                               id="modulo_{{ $submodulo->id_modulo }}" value="1"
                                                                               {{ old("modulos.{$index}.ativo", $isSubChecked) ? 'checked' : '' }}>
                                                                    </div>
                                                                </td>
                                                                <td class="ps-5">
                                                                    <label for="modulo_{{ $submodulo->id_modulo }}" class="form-label mb-0">
                                                                        <i class="ti ti-corner-down-right me-1 text-muted"></i>
                                                                        @if($submodulo->icone)
                                                                            <i class="{{ $submodulo->icone }} me-1"></i>
                                                                        @endif
                                                                        {{ $submodulo->nome }}
                                                                        <span class="badge bg-label-dark ms-1" title="Ordem de exibição">#{{ $submodulo->ordem ?? 0 }}</span>
                                                                        <span class="badge bg-label-info ms-1">Submódulo</span>
                                                                        @if($submodulo->descricao)
                                                                            <br><small class="text-muted ps-4">{{ $submodulo->descricao }}</small>
                                                                        @endif
                                                                    </label>
                                                                </td>
                                                                <td>
                                                                    <input type="number" name="modulos[{{ $index }}][limite]" 
                                                                           class="form-control limite-input" 
                                                                           value="{{ old("modulos.{$index}.limite", $subLimite) }}"
                                                                           min="0" placeholder="Ilimitado" {{ !$isSubChecked ? 'disabled' : '' }}>
                                                                </td>
                                                            </tr>
                                                            @php $index++; @endphp
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

                    <!-- Recursos do Sistema -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recursos do Sistema</h5>
                                <p class="text-muted mb-0">Configure os recursos inclusos</p>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Relatórios <span class="text-danger">*</span></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="relatorios" id="relatorios_s" value="S" 
                                                   {{ old('relatorios', $plano->relatorios) === 'S' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="relatorios_s">Sim</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="relatorios" id="relatorios_n" value="N" 
                                                   {{ old('relatorios', $plano->relatorios) === 'N' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="relatorios_n">Não</label>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Bancos <span class="text-danger">*</span></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="bancos" id="bancos_s" value="S" 
                                                   {{ old('bancos', $plano->bancos) === 'S' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="bancos_s">Sim</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="bancos" id="bancos_n" value="N" 
                                                   {{ old('bancos', $plano->bancos) === 'N' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="bancos_n">Não</label>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Assinatura Digital <span class="text-danger">*</span></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="assinatura_digital" id="assinatura_s" value="S" 
                                                   {{ old('assinatura_digital', $plano->assinatura_digital) === 'S' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="assinatura_s">Sim</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="assinatura_digital" id="assinatura_n" value="N" 
                                                   {{ old('assinatura_digital', $plano->assinatura_digital) === 'N' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="assinatura_n">Não</label>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Contratos <span class="text-danger">*</span></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="contratos" id="contratos_s" value="S" 
                                                   {{ old('contratos', $plano->contratos) === 'S' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="contratos_s">Sim</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="contratos" id="contratos_n" value="N" 
                                                   {{ old('contratos', $plano->contratos) === 'N' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="contratos_n">Não</label>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Faturas <span class="text-danger">*</span></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="faturas" id="faturas_s" value="S" 
                                                   {{ old('faturas', $plano->faturas) === 'S' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="faturas_s">Sim</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="faturas" id="faturas_n" value="N" 
                                                   {{ old('faturas', $plano->faturas) === 'N' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="faturas_n">Não</label>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Status do Plano <span class="text-danger">*</span></label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="ativo" id="plano_ativo" value="1" 
                                                   {{ old('ativo', $plano->ativo) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="plano_ativo">
                                                <span class="status-text">{{ $plano->ativo ? 'Ativo' : 'Inativo' }}</span>
                                            </label>
                                        </div>
                                        <small class="text-muted">Planos inativos não aparecem para contratação</small>
                                    </div>
                                </div>

                                <hr>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        Atualizar Plano
                                    </button>
                                    <a href="{{ route('admin.planos.index') }}" class="btn btn-secondary">
                                        Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Promoções do Plano</h5>
                        <small class="text-muted">Configure promoções por período de datas.</small>
                    </div>
                </div>
                <div class="card-body">
                    @if($promocoes->isEmpty())
                        <div class="alert alert-info mb-4" role="alert">
                            Nenhuma promoção cadastrada para este plano.
                        </div>
                    @else
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Data Início</th>
                                        <th>Data Fim</th>
                                        <th>Desconto Mensal</th>
                                        <th>Desconto Adesão</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($promocoes as $promocao)
                                    @php
                                        $estaVigente = $promocao->estaVigente();
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $promocao->nome }}</strong>
                                            @if($promocao->observacoes)
                                            <br><small class="text-muted">{{ $promocao->observacoes }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $promocao->data_inicio ? $promocao->data_inicio->format('d/m/Y') : '-' }}</td>
                                        <td>{{ $promocao->data_fim ? $promocao->data_fim->format('d/m/Y') : '-' }}</td>
                                        <td>R$ {{ number_format((float) $promocao->desconto_mensal, 2, ',', '.') }}</td>
                                        <td>R$ {{ number_format((float) $promocao->desconto_adesao, 2, ',', '.') }}</td>
                                        <td>
                                            @if($estaVigente)
                                                <span class="badge bg-label-success">
                                                    <i class="ti ti-circle-check ti-xs me-1"></i>
                                                    Vigente
                                                </span>
                                            @elseif($promocao->ativo)
                                                <span class="badge bg-label-warning">Ativa (fora do período)</span>
                                            @else
                                                <span class="badge bg-label-secondary">Inativa</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.planos.promocoes.destroy', ['plano' => $plano, 'idPromocao' => $promocao->id]) }}" onsubmit="return confirm('Remover esta promoção?');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.planos.promocoes.store', $plano) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nome da promoção <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control" maxlength="120" required placeholder="Ex: Promoção de Março">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data início</label>
                                <input type="date" name="data_inicio" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data fim</label>
                                <input type="date" name="data_fim" class="form-control">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="promocao_ativa" name="ativo" value="1" checked>
                                    <label class="form-check-label" for="promocao_ativa">Ativa</label>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Desconto mensal (R$)</label>
                                <input type="number" step="0.01" min="0" name="desconto_mensal" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Desconto adesão (R$)</label>
                                <input type="number" step="0.01" min="0" name="desconto_adesao" class="form-control" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Observações</label>
                                <input type="text" name="observacoes" class="form-control" maxlength="1000" placeholder="Observações sobre a promoção">
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>
                                Adicionar Promoção
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection