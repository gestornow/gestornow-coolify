@extends('layouts.layoutMaster')

@section('title', 'Logs de Atividades')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<style>
.log-row-clickable { cursor: pointer; }
.log-row-clickable:hover { background-color: rgba(105, 108, 255, 0.04); }
.log-row-danger { background-color: rgba(255, 62, 29, 0.06); }
.log-row-warning { background-color: rgba(255, 171, 0, 0.08); }
.log-desc-truncate {
    max-width: 360px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
@endsection

@section('content')
@php
    $mapaCores = [
        'verde' => 'success',
        'amarelo' => 'warning',
        'vermelho' => 'danger',
        'azul' => 'primary',
        'laranja' => 'warning',
        'cinza' => 'secondary',
        'roxo' => 'primary',
        'verde-escuro' => 'success',
        'vermelho-escuro' => 'danger',
        'ciano' => 'info',
        'azul-claro' => 'info',
        'azul-escuro' => 'info',
        'cinza-escuro' => 'dark',
    ];

    $mapaEntidades = $entidadesMapeadas ?? [];

    $queryAtual = request()->query();
@endphp

<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <h4 class="fw-bold py-3 mb-2">
                    <span class="text-muted fw-light">Admin /</span> Logs de Atividades
                </h4>
                <p class="mb-0 text-muted">Auditoria completa de acoes do sistema</p>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $totais['total'] ?? 0 }}</h4>
                                    </div>
                                    <span>Total de Registros</span>
                                </div>
                                <span class="badge bg-label-primary rounded p-2">
                                    <i class="ti ti-list ti-sm"></i>
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
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $totais['hoje'] ?? 0 }}</h4>
                                    </div>
                                    <span>Registros de Hoje</span>
                                </div>
                                <span class="badge bg-label-success rounded p-2">
                                    <i class="ti ti-calendar-event ti-sm"></i>
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
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $totais['semana'] ?? 0 }}</h4>
                                    </div>
                                    <span>Registros da Semana</span>
                                </div>
                                <span class="badge bg-label-warning rounded p-2">
                                    <i class="ti ti-clock ti-sm"></i>
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
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $totais['mes'] ?? 0 }}</h4>
                                    </div>
                                    <span>Registros do Mes</span>
                                </div>
                                <span class="badge bg-label-info rounded p-2">
                                    <i class="ti ti-chart-bar ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.logs.index') }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Empresa</label>
                                <select name="id_empresa" class="form-select select2">
                                    <option value="">Todas</option>
                                    @foreach($empresas as $empresa)
                                        <option value="{{ $empresa->id_empresa }}" {{ (string)($filtros['id_empresa'] ?? '') === (string)$empresa->id_empresa ? 'selected' : '' }}>
                                            {{ $empresa->nome_empresa }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Usuario</label>
                                <select name="id_usuario" class="form-select select2">
                                    <option value="">Todos</option>
                                    @foreach($usuarios as $usuario)
                                        <option value="{{ $usuario->id_usuario }}" {{ (string)($filtros['id_usuario'] ?? '') === (string)$usuario->id_usuario ? 'selected' : '' }}>
                                            {{ $usuario->nome }} ({{ $usuario->login }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Modulo</label>
                                <select name="entidade_tipo" class="form-select select2">
                                    <option value="">Todos</option>
                                    @foreach($entidades as $entidade)
                                        <option value="{{ $entidade }}" {{ (string)($filtros['entidade_tipo'] ?? '') === (string)$entidade ? 'selected' : '' }}>
                                            {{ $mapaEntidades[$entidade] ?? $entidade }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tipo de Acao</label>
                                <select name="acao" class="form-select select2">
                                    <option value="">Todas</option>
                                    @foreach(($acoesAgrupadas ?? []) as $acaoLabel => $acoesGrupo)
                                        @php $valorAcao = '__label__:' . $acaoLabel; @endphp
                                        <option value="{{ $valorAcao }}" {{ (string)($filtros['acao'] ?? '') === (string)$valorAcao ? 'selected' : '' }}>
                                            {{ $acaoLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Origem</label>
                                <select name="origem" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach(['web', 'api', 'mobile', 'console', 'importacao'] as $origem)
                                        <option value="{{ $origem }}" {{ (string)($filtros['origem'] ?? '') === $origem ? 'selected' : '' }}>{{ ucfirst($origem) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Data inicio</label>
                                <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Data fim</label>
                                <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Busca na descricao</label>
                                <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}" placeholder="Digite parte da descricao...">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Valor min</label>
                                <input type="number" step="0.01" name="valor_min" class="form-control" value="{{ $filtros['valor_min'] ?? '' }}">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Valor max</label>
                                <input type="number" step="0.01" name="valor_max" class="form-control" value="{{ $filtros['valor_max'] ?? '' }}">
                            </div>

                            <div class="col-md-12 d-flex gap-2 justify-content-end mt-2">
                                <a href="{{ route('admin.logs.index') }}" class="btn btn-secondary">
                                    <i class="ti ti-x me-1"></i>Limpar filtros
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-search me-1"></i>Filtrar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Registros de Atividade</h5>
                    <small class="text-muted">{{ $logs->total() }} resultado(s)</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Empresa</th>
                                <th>Usuario</th>
                                <th>Modulo</th>
                                <th>Descricao</th>
                                <th>Valor</th>
                                <th>Origem</th>
                                <th class="text-center">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                @php
                                    $corOriginal = strtolower((string) ($log->cor ?? ''));
                                    $badgeCor = $mapaCores[$corOriginal] ?? 'primary';
                                    $moduloLabel = $log->entidade_tipo_label ?? ($mapaEntidades[$log->entidade_tipo] ?? ($log->entidade_tipo ?: '-'));
                                    $desc = (string) ($log->descricao ?? '-');
                                    $acaoTexto = strtolower((string) ($log->acao ?? ''));
                                    $rowClass = 'log-row-clickable';
                                    if ($corOriginal === 'vermelho' || $corOriginal === 'vermelho-escuro' || str_contains($acaoTexto, 'exclu') || str_contains($acaoTexto, 'cancel')) {
                                        $rowClass .= ' log-row-danger';
                                    } elseif ($corOriginal === 'laranja' || $corOriginal === 'amarelo' || str_contains($acaoTexto, 'avaria') || str_contains($acaoTexto, 'extravio')) {
                                        $rowClass .= ' log-row-warning';
                                    }
                                @endphp
                                <tr class="{{ $rowClass }}" data-log-id="{{ $log->id_registro }}">
                                    <td>{{ optional($log->ocorrido_em)->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $log->empresa->nome_empresa ?? '-' }}</td>
                                    <td>
                                        <div>{{ $log->nome_responsavel ?? ($log->usuario->nome ?? '-') }}</div>
                                        <small class="text-muted">{{ $log->email_responsavel ?? ($log->usuario->login ?? '-') }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-label-{{ $badgeCor }}">{{ $moduloLabel }}</span>
                                    </td>
                                    <td>
                                        <div class="log-desc-truncate" title="{{ $desc }}">{{ \Illuminate\Support\Str::limit($desc, 80) }}</div>
                                        <small class="text-muted">{{ $log->acao_label ?? ($acoesMapeadas[$log->acao] ?? $log->acao ?? '-') }} | {{ $log->entidade_referencia ?? ($log->entidade_label ?: '-') }}</small>
                                    </td>
                                    <td>
                                        {{ $log->valor !== null ? 'R$ ' . number_format((float)$log->valor, 2, ',', '.') : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-label-secondary">{{ $log->origem ?: '-' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-primary btn-log-detalhe" data-id="{{ $log->id_registro }}" title="Ver detalhes">
                                            <i class="ti ti-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="ti ti-notes-off ti-48 text-muted mb-2 d-block"></i>
                                        <h6 class="text-muted">Nenhum registro encontrado com os filtros aplicados</h6>
                                        <p class="text-muted mb-0">Tente ajustar os filtros ou limpe para visualizar todos os logs.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-pagination-info :paginator="$logs" />
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalheLog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhe do Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalDetalheLogBody">
                <div class="text-center py-4 text-muted">
                    <i class="spinner-border spinner-border-sm me-2"></i>Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script>
$(function () {
    $('.select2').select2({ width: '100%' });

    const modalEl = document.getElementById('modalDetalheLog');
    const modalBody = document.getElementById('modalDetalheLogBody');
    const modal = new bootstrap.Modal(modalEl);

    function formatValue(value) {
        if (value === null || value === undefined || value === '') return '-';
        if (typeof value === 'boolean') return value ? 'Sim' : 'Nao';
        if (typeof value === 'object') return renderObjectTable(value);
        return String(value);
    }

    function renderObjectTable(obj) {
        if (!obj || typeof obj !== 'object' || Object.keys(obj).length === 0) {
            return '<span class="text-muted">-</span>';
        }

        let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>';
        Object.keys(obj).forEach(function (key) {
            html += '<tr><th style="width: 35%;">' + escapeHtml(key) + '</th><td>' + formatValue(obj[key]) + '</td></tr>';
        });
        html += '</tbody></table></div>';

        return html;
    }

    function escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function badgeColor(cor) {
        const mapa = {
            'verde': 'success',
            'amarelo': 'warning',
            'vermelho': 'danger',
            'azul': 'primary',
            'laranja': 'warning',
            'cinza': 'secondary',
            'roxo': 'primary',
            'verde-escuro': 'success',
            'vermelho-escuro': 'danger',
            'ciano': 'info',
            'azul-claro': 'info'
        };

        return mapa[String(cor || '').toLowerCase()] || 'primary';
    }

    function openLogDetail(id) {
        modalBody.innerHTML = '<div class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Carregando...</div>';
        modal.show();

        $.ajax({
            url: '{{ route('admin.logs.show', ['id' => '__ID__']) }}'.replace('__ID__', id),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (!response.success || !response.log) {
                    modalBody.innerHTML = '<div class="alert alert-danger mb-0">Nao foi possivel carregar o detalhe do log.</div>';
                    return;
                }

                const log = response.log;
                const cor = badgeColor(log.cor);
                const valor = (log.valor !== null && log.valor !== undefined)
                    ? 'R$ ' + Number(log.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '-';
                const tags = Array.isArray(log.tags) && log.tags.length > 0
                    ? log.tags.map(t => '<span class="badge bg-label-secondary me-1">' + escapeHtml(t) + '</span>').join('')
                    : '<span class="text-muted">-</span>';

                modalBody.innerHTML = `
                    <div class="mb-3">
                        <span class="badge bg-label-${cor} mb-2">${escapeHtml(log.acao_label || log.acao || '-')}</span>
                        <h5 class="mb-1">${escapeHtml(log.descricao || '-')}</h5>
                        <small class="text-muted">${escapeHtml(log.ocorrido_em || '-')} | Origem: ${escapeHtml(log.origem || '-')}</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header"><strong>Quem fez</strong></div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>Nome:</strong> ${escapeHtml(log.nome_responsavel || log.usuario?.nome || '-')}</p>
                                    <p class="mb-1"><strong>Email:</strong> ${escapeHtml(log.email_responsavel || log.usuario?.login || '-')}</p>
                                    <p class="mb-1"><strong>Empresa:</strong> ${escapeHtml(log.empresa?.nome_empresa || '-')}</p>
                                    <p class="mb-0"><strong>IP:</strong> ${escapeHtml(log.ip || '-')}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header"><strong>O que foi feito</strong></div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>Acao:</strong> ${escapeHtml(log.acao_label || log.acao || '-')}</p>
                                    <p class="mb-1"><strong>Modulo:</strong> ${escapeHtml(log.entidade_tipo_label || log.entidade_tipo || '-')}</p>
                                    <p class="mb-1"><strong>Referencia:</strong> ${escapeHtml(log.entidade_referencia || log.entidade_label || (log.entidade_id ? ('Registro #' + log.entidade_id) : '-'))}</p>
                                    <p class="mb-1"><strong>Label:</strong> ${escapeHtml(log.entidade_label || '-')}</p>
                                    <p class="mb-1"><strong>Valor:</strong> ${valor}</p>
                                    <p class="mb-0"><strong>Tags:</strong> ${tags}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><strong>Contexto da Operacao</strong></div>
                        <div class="card-body">${renderObjectTable(log.contexto)}</div>
                    </div>

                    ${(log.antes && Object.keys(log.antes).length > 0) || (log.depois && Object.keys(log.depois).length > 0) ? `
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-danger border-opacity-25">
                                    <div class="card-header bg-label-danger"><strong>Antes</strong></div>
                                    <div class="card-body">${renderObjectTable(log.antes)}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-success border-opacity-25">
                                    <div class="card-header bg-label-success"><strong>Depois</strong></div>
                                    <div class="card-body">${renderObjectTable(log.depois)}</div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
            },
            error: function () {
                modalBody.innerHTML = '<div class="alert alert-danger mb-0">Erro ao carregar o detalhe do log.</div>';
            }
        });
    }

    $(document).on('click', '.btn-log-detalhe', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openLogDetail($(this).data('id'));
    });

    $(document).on('click', '.log-row-clickable', function (e) {
        if ($(e.target).closest('.btn-log-detalhe').length) {
            return;
        }
        openLogDetail($(this).data('log-id'));
    });
});
</script>
@endsection
