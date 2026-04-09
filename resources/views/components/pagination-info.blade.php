@props(['paginator', 'perPageOptions' => [10, 20, 50, 100]])

@if($paginator->hasPages() || $paginator->total() > 0)
<div class="card-footer">
    <div class="row align-items-center">
        <div class="col-md-4 col-sm-12 mb-3 mb-md-0">
            <!-- Informações de registros -->
            <div class="text-muted">
                Mostrando 
                <strong>{{ $paginator->firstItem() ?? 0 }}</strong> 
                até 
                <strong>{{ $paginator->lastItem() ?? 0 }}</strong> 
                de 
                <strong>{{ $paginator->total() }}</strong> 
                {{ Str::plural('registro', $paginator->total()) }}
            </div>
        </div>

        <div class="col-md-4 col-sm-12 mb-3 mb-md-0 text-center">
            <!-- Navegação de páginas -->
            @if($paginator->hasPages())
                <nav aria-label="Navegação de página">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        {{-- Link Anterior --}}
                        @if ($paginator->onFirstPage())
                            <li class="page-item disabled" aria-disabled="true">
                                <span class="page-link">
                                    <i class="ti ti-chevron-left"></i>
                                </span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                                    <i class="ti ti-chevron-left"></i>
                                </a>
                            </li>
                        @endif

                        {{-- Links de Páginas --}}
                        @php
                            $start = max($paginator->currentPage() - 2, 1);
                            $end = min($start + 4, $paginator->lastPage());
                            $start = max($end - 4, 1);
                        @endphp

                        @if($start > 1)
                            <li class="page-item">
                                <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
                            </li>
                            @if($start > 2)
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            @endif
                        @endif

                        @for ($i = $start; $i <= $end; $i++)
                            @if ($i == $paginator->currentPage())
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link">{{ $i }}</span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $paginator->url($i) }}">{{ $i }}</a>
                                </li>
                            @endif
                        @endfor

                        @if($end < $paginator->lastPage())
                            @if($end < $paginator->lastPage() - 1)
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            @endif
                            <li class="page-item">
                                <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}">
                                    {{ $paginator->lastPage() }}
                                </a>
                            </li>
                        @endif

                        {{-- Link Próximo --}}
                        @if ($paginator->hasMorePages())
                            <li class="page-item">
                                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                                    <i class="ti ti-chevron-right"></i>
                                </a>
                            </li>
                        @else
                            <li class="page-item disabled" aria-disabled="true">
                                <span class="page-link">
                                    <i class="ti ti-chevron-right"></i>
                                </span>
                            </li>
                        @endif
                    </ul>
                </nav>
            @endif
        </div>

        <div class="col-md-4 col-sm-12">
            <!-- Seletor de itens por página -->
            <div class="d-flex align-items-center justify-content-md-end justify-content-center">
                <label for="perPageSelect" class="form-label mb-0 me-2 text-nowrap">
                    Itens por página:
                </label>
                <select 
                    id="perPageSelect" 
                    class="form-select form-select-sm" 
                    style="width: auto;"
                    onchange="changePerPage(this.value)">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" {{ request('per_page', 20) == $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<script>
function changePerPage(perPage) {
    // Obter URL atual e parâmetros
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    
    // Atualizar parâmetro per_page
    params.set('per_page', perPage);
    
    // Remover parâmetro page para voltar à primeira página
    params.delete('page');
    
    // Redirecionar com novos parâmetros
    window.location.href = url.pathname + '?' + params.toString();
}
</script>
@endif
