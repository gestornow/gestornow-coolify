@extends('layouts.layoutMaster')

@section('title', 'Editar Módulos do Plano Contratado')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <form action="{{ route('admin.planos.contratados.update', $planoContratado) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <!-- Formulário de Módulos -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">Editar Módulos do Plano Contratado</h5>
                                    <p class="text-muted mb-0 small">{{ $planoContratado->empresa->nome_empresa ?? 'Empresa' }} - {{ $planoContratado->nome }}</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('admin.filiais.show', $planoContratado->id_empresa) }}" class="btn btn-secondary btn-sm">
                                        <i class="ti ti-x me-1"></i>
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        Salvar
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Selecione os módulos</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select-all-modulos">
                                        <label class="form-check-label" for="select-all-modulos">
                                            <strong>Selecionar Todos</strong>
                                        </label>
                                    </div>
                                </div>

                                @foreach($modulos as $modulo)
                                    <!-- Módulo Principal -->
                                    <div class="mb-3">
                                        <div class="card {{ in_array($modulo->id_modulo, $modulosContratados) ? 'border-primary' : '' }}">
                                            <div class="card-body">
                                                <div class="form-check">
                                                    <input 
                                                        class="form-check-input modulo-checkbox modulo-principal" 
                                                        type="checkbox" 
                                                        name="modulos[]" 
                                                        value="{{ $modulo->id_modulo }}" 
                                                        id="modulo_{{ $modulo->id_modulo }}"
                                                        data-modulo-id="{{ $modulo->id_modulo }}"
                                                        {{ in_array($modulo->id_modulo, $modulosContratados) ? 'checked' : '' }}>
                                                    <label class="form-check-label w-100" for="modulo_{{ $modulo->id_modulo }}">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                @if($modulo->icone)
                                                                    <i class="{{ $modulo->icone }} me-1"></i>
                                                                @endif
                                                                <strong>{{ $modulo->nome }}</strong>
                                                                @if($modulo->temSubmodulos())
                                                                    <span class="badge bg-label-primary ms-1">{{ $modulo->submodulos->count() }} Sub.</span>
                                                                @endif
                                                                @if($modulo->descricao)
                                                                    <br><small class="text-muted">{{ $modulo->descricao }}</small>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submódulos -->
                                    @if($modulo->temSubmodulos())
                                        @foreach($modulo->submodulos as $submodulo)
                                            <div class="mb-3 ms-4">
                                                <div class="card {{ in_array($submodulo->id_modulo, $modulosContratados) ? 'border-info' : 'border-light' }} bg-light">
                                                    <div class="card-body py-2">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-8">
                                                                <div class="form-check">
                                                                    <input 
                                                                        class="form-check-input modulo-checkbox submodulo-checkbox" 
                                                                        type="checkbox" 
                                                                        name="modulos[]" 
                                                                        value="{{ $submodulo->id_modulo }}" 
                                                                        id="modulo_{{ $submodulo->id_modulo }}"
                                                                        data-parent-id="{{ $modulo->id_modulo }}"
                                                                        {{ in_array($submodulo->id_modulo, $modulosContratados) ? 'checked' : '' }}>
                                                                    <label class="form-check-label" for="modulo_{{ $submodulo->id_modulo }}">
                                                                        <i class="ti ti-corner-down-right me-1 text-muted"></i>
                                                                        @if($submodulo->icone)
                                                                            <i class="{{ $submodulo->icone }} me-1"></i>
                                                                        @endif
                                                                        {{ $submodulo->nome }}
                                                                        <span class="badge bg-label-info ms-1">Submódulo</span>
                                                                        @if($submodulo->descricao)
                                                                            <br><small class="text-muted ps-4">{{ $submodulo->descricao }}</small>
                                                                        @endif
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="limite-input" style="display: {{ in_array($submodulo->id_modulo, $modulosContratados) ? 'block' : 'none' }};">
                                                                    <label class="form-label small mb-1">Limite (opcional)</label>
                                                                    <input 
                                                                        type="number" 
                                                                        class="form-control form-control-sm" 
                                                                        name="limites[{{ $submodulo->id_modulo }}]" 
                                                                        placeholder="Ex: 5"
                                                                        min="0"
                                                                        value="{{ $limites[$submodulo->id_modulo] ?? '' }}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Resumo -->
                    <div class="col-lg-4">
                        <div class="card position-sticky" style="top: 20px;">
                            <div class="card-header">
                                <h5 class="mb-0">Resumo</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="text-muted">Plano</h6>
                                    <p class="fw-bold mb-1">{{ $planoContratado->nome }}</p>
                                    <small class="text-muted">{{ $planoContratado->valor_formatado }}</small>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted">Empresa</h6>
                                    <p class="mb-1">{{ $planoContratado->empresa->nome_empresa ?? 'N/A' }}</p>
                                    <small class="text-muted">{{ $planoContratado->empresa->cnpj_formatado ?? '' }}</small>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted">Módulos Selecionados</h6>
                                    <h4 id="contador-modulos" class="mb-0">{{ count($modulosContratados) }}</h4>
                                </div>

                                <hr>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        Salvar Módulos
                                    </button>
                                    <a href="{{ route('admin.filiais.show', $planoContratado->id_empresa) }}" class="btn btn-secondary">
                                        <i class="ti ti-x me-1"></i>
                                        Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.modulo-checkbox');
    const selectAllCheckbox = document.getElementById('select-all-modulos');
    const contador = document.getElementById('contador-modulos');

    // Selecionar todos
    selectAllCheckbox.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
            triggerCheckboxChange(checkbox);
        });
        atualizarContador();
    });

    // Função para atualizar visual do checkbox
    function triggerCheckboxChange(checkbox) {
        const card = checkbox.closest('.card');
        const limiteDiv = card.querySelector('.limite-input');
        
        if (checkbox.checked) {
            if (checkbox.classList.contains('modulo-principal')) {
                card.classList.add('border-primary');
            } else {
                card.classList.add('border-info');
            }
            if (limiteDiv && checkbox.classList.contains('submodulo-checkbox')) {
                limiteDiv.style.display = 'block';
            }
        } else {
            card.classList.remove('border-primary', 'border-info');
            if (limiteDiv) {
                limiteDiv.style.display = 'none';
                const input = limiteDiv.querySelector('input');
                if (input) input.value = '';
            }
        }
    }

    // Atualizar contador
    function atualizarContador() {
        const total = document.querySelectorAll('.modulo-checkbox:checked').length;
        contador.textContent = total;
        
        // Atualizar estado do "selecionar todos"
        const totalCheckboxes = checkboxes.length;
        const checkedCheckboxes = document.querySelectorAll('.modulo-checkbox:checked').length;
        selectAllCheckbox.checked = totalCheckboxes === checkedCheckboxes;
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            triggerCheckboxChange(this);
            atualizarContador();
        });
    });

    // Inicializar contador
    atualizarContador();
});
</script>
@endsection

@endsection
