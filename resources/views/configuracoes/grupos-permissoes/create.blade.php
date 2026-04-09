@extends('layouts.layoutMaster')

@section('title', 'Novo Grupo de Permissoes')

@section('page-style')
<style>
    .perm-toolbar {
        background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), 0.05) 0%, rgba(var(--bs-primary-rgb), 0.01) 100%);
        border: 1px solid var(--bs-border-color);
        border-radius: 0.75rem;
        padding: 1rem;
        margin-top: 0.5rem;
        margin-bottom: 1rem;
    }

    .perm-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-top: 1rem;
        margin-bottom: 0.75rem;
    }

    .perm-module-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.75rem;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .perm-module-card:hover {
        border-color: rgba(var(--bs-primary-rgb), 0.45);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.06);
    }

    .perm-module-header {
        background: rgba(var(--bs-primary-rgb), 0.06);
        border-bottom: 1px solid var(--bs-border-color);
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
    }

    .perm-item {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.65rem;
        padding: 0.75rem;
        height: 100%;
        background: var(--bs-body-bg);
    }

    .perm-item small {
        word-break: break-all;
    }

    .perm-module-empty {
        display: none;
    }

    @media (max-width: 991.98px) {
        .perm-toolbar {
            padding: 0.85rem;
        }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="card mb-4">
        <div class="card-header border-bottom-0 pb-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Novo Grupo de Permissoes</h5>
                <a href="{{ route('configuracoes.grupos-permissoes.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('configuracoes.grupos-permissoes.store') }}" id="formGrupoPermissoes">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-5">
                        <label class="form-label">Descricao</label>
                        <input type="text" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}">
                        @error('descricao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                @php
                    $totalPermissoes = collect($catalogo)->flatten(1)->count();
                    $totalModulos = count($catalogo);
                    $ordemMenuModulos = [
                        'clientes',
                        'produtos',
                        'locacoes',
                        'expedicao',
                        'pdv',
                        'financeiro',
                        'faturas',
                        'admin',
                        'permissoes',
                        'configuracoes',
                    ];

                    $normalizarModulo = function ($valor) {
                        $valor = mb_strtolower(trim((string) $valor));
                        $valor = strtr($valor, [
                            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
                            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
                            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                            'ç' => 'c',
                        ]);

                        return preg_replace('/\s+/', ' ', $valor);
                    };

                    $catalogoOrdenado = collect($catalogo)
                        ->sortBy(function ($chaves, $modulo) use ($ordemMenuModulos, $normalizarModulo) {
                            $moduloNormalizado = $normalizarModulo($modulo);
                            $posicao = array_search($moduloNormalizado, $ordemMenuModulos, true);

                            return $posicao === false ? 999 : $posicao;
                        })
                        ->all();
                @endphp

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-primary border-opacity-25">
                            <div class="card-body">
                                <small class="text-muted d-block">Modulos</small>
                                <h4 class="mb-0">{{ $totalModulos }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-success border-opacity-25">
                            <div class="card-body">
                                <small class="text-muted d-block">Permissoes disponiveis</small>
                                <h4 class="mb-0" id="totalPermissoes">{{ $totalPermissoes }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-info border-opacity-25">
                            <div class="card-body">
                                <small class="text-muted d-block">Selecionadas</small>
                                <h4 class="mb-0" id="totalSelecionadas">{{ count(old('chaves', [])) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="perm-section-title">
                    <h6 class="mb-0">Permissoes por modulo</h6>
                    <small class="text-muted">Use a busca para localizar rapido</small>
                </div>

                <div class="perm-toolbar">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-lg-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="text" id="filtroPermissoes" class="form-control" placeholder="Buscar permissao por nome ou chave...">
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSelecionarTudo">
                                    <i class="ti ti-checks me-1"></i>Selecionar tudo
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparTudo">
                                    <i class="ti ti-square me-1"></i>Limpar tudo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                @error('chaves')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                @foreach($catalogoOrdenado as $modulo => $chaves)
                    <div class="perm-module-card mb-3" data-module-card>
                        <div class="perm-module-header px-3 py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <strong class="text-uppercase">{{ $modulo }}</strong>
                                <small class="text-muted ms-2"><span data-module-selected>0</span>/{{ count($chaves) }}</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-xs py-1 px-2" data-select-module>Selecionar modulo</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2" data-clear-module>Limpar</button>
                            </div>
                        </div>
                        <div class="p-3">
                            <div class="row g-2" data-module-items>
                            @foreach($chaves as $item)
                                <div class="col-12 col-md-6 col-xl-4" data-perm-item data-search="{{ strtolower($item->label . ' ' . $item->chave) }}">
                                    <label class="perm-item d-flex align-items-start gap-2" for="chk_{{ md5($item->chave) }}">
                                        <input class="form-check-input mt-1" type="checkbox" name="chaves[]" value="{{ $item->chave }}" id="chk_{{ md5($item->chave) }}" {{ in_array($item->chave, old('chaves', []), true) ? 'checked' : '' }}>
                                        <span>
                                            <span class="d-block fw-medium">{{ $item->label }}</span>
                                            <small class="text-muted">{{ $item->chave }}</small>
                                        </span>
                                    </label>
                                    </div>
                            @endforeach
                            </div>
                            <div class="perm-module-empty text-muted small mt-2" data-module-empty>
                                Nenhuma permissao deste modulo corresponde ao filtro.
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('configuracoes.grupos-permissoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    (function () {
        const filtro = document.getElementById('filtroPermissoes');
        const btnSelecionarTudo = document.getElementById('btnSelecionarTudo');
        const btnLimparTudo = document.getElementById('btnLimparTudo');
        const totalSelecionadas = document.getElementById('totalSelecionadas');
        const moduleCards = document.querySelectorAll('[data-module-card]');

        const allCheckboxes = () => Array.from(document.querySelectorAll('input[name="chaves[]"]'));

        function atualizarContadores() {
            const selecionadas = allCheckboxes().filter(chk => chk.checked).length;
            totalSelecionadas.textContent = selecionadas;

            moduleCards.forEach(card => {
                const checkboxes = Array.from(card.querySelectorAll('input[name="chaves[]"]'));
                const checked = checkboxes.filter(chk => chk.checked).length;
                const badge = card.querySelector('[data-module-selected]');
                if (badge) {
                    badge.textContent = checked;
                }
            });
        }

        function filtrarPermissoes() {
            const termo = (filtro.value || '').trim().toLowerCase();

            moduleCards.forEach(card => {
                const itens = Array.from(card.querySelectorAll('[data-perm-item]'));
                let visiveis = 0;

                itens.forEach(item => {
                    const texto = item.getAttribute('data-search') || '';
                    const mostrar = termo === '' || texto.includes(termo);
                    item.style.display = mostrar ? '' : 'none';
                    if (mostrar) {
                        visiveis++;
                    }
                });

                const vazio = card.querySelector('[data-module-empty]');
                if (vazio) {
                    vazio.style.display = visiveis === 0 ? 'block' : 'none';
                }

                card.style.display = visiveis === 0 ? 'none' : '';
            });
        }

        btnSelecionarTudo.addEventListener('click', function () {
            allCheckboxes().forEach(chk => {
                chk.checked = true;
            });
            atualizarContadores();
        });

        btnLimparTudo.addEventListener('click', function () {
            allCheckboxes().forEach(chk => {
                chk.checked = false;
            });
            atualizarContadores();
        });

        moduleCards.forEach(card => {
            const selectBtn = card.querySelector('[data-select-module]');
            const clearBtn = card.querySelector('[data-clear-module]');

            if (selectBtn) {
                selectBtn.addEventListener('click', function () {
                    card.querySelectorAll('input[name="chaves[]"]').forEach(chk => {
                        chk.checked = true;
                    });
                    atualizarContadores();
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    card.querySelectorAll('input[name="chaves[]"]').forEach(chk => {
                        chk.checked = false;
                    });
                    atualizarContadores();
                });
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target && event.target.matches('input[name="chaves[]"]')) {
                atualizarContadores();
            }
        });

        filtro.addEventListener('input', filtrarPermissoes);

        atualizarContadores();
    })();
</script>
@endsection
