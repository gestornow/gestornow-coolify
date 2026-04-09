@extends('layouts.layoutMaster')

@section('title', isset($modelo) ? 'Editar Modelo de Contrato' : 'Novo Modelo de Contrato')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('content')
@php
    $tipoModeloAtual = strtolower((string) ($tipoModelo ?? ((isset($modelo) && ($modelo->usa_medicao ?? false)) ? 'medicao' : 'contrato')));
    if (!in_array($tipoModeloAtual, ['contrato', 'orcamento', 'medicao'], true)) {
        $tipoModeloAtual = 'contrato';
    }
    $isModeloContrato = $tipoModeloAtual === 'contrato';
    $isModeloMedicao = $tipoModeloAtual === 'medicao';
    $isModeloOrcamento = $tipoModeloAtual === 'orcamento';

    $labelTipoDocumento = $isModeloMedicao
        ? 'Medição'
        : ($isModeloOrcamento ? 'Orçamento' : 'Contrato');
@endphp
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <form action="{{ isset($modelo) ? route('documentos.update', $modelo->id_modelo) : route('documentos.store') }}" 
                  method="POST" enctype="multipart/form-data" id="formModelo">
                @csrf
                @if(isset($modelo))
                    @method('PUT')
                @endif
                <input type="hidden" name="tipo_modelo" value="{{ $tipoModeloAtual }}">

                <!-- Cabeçalho -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="ti ti-file-text me-2"></i>
                                {{ isset($modelo) ? ('Editar Documento de ' . $labelTipoDocumento) : ('Novo Documento de ' . $labelTipoDocumento) }}
                            </h5>
                        </div>
                        <a href="{{ route('documentos.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger mb-4">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif


                <!-- Informações Básicas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informações Básicas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome do Modelo <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control" required
                                       value="{{ old('nome', $modelo->nome ?? '') }}"
                                        placeholder="Ex: {{ $isModeloOrcamento ? 'Orçamento Padrão' : ($isModeloMedicao ? 'Medição Padrão' : 'Contrato Padrão') }}">
                            </div>
                            @if(!$isModeloMedicao)
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" name="ativo" class="form-check-input" value="1"
                                            {{ old('ativo', $modelo->ativo ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Ativo</label>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Modelo Padrão</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" name="padrao" class="form-check-input" value="1"
                                            {{ old('padrao', $modelo->padrao ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label">Usar como padrão</label>
                                    </div>
                                </div>
                                @if($isModeloContrato)
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Cabeçalho no PDF</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="hidden" name="exibir_cabecalho" value="0">
                                            <input type="checkbox" name="exibir_cabecalho" class="form-check-input" value="1"
                                                {{ old('exibir_cabecalho', $modelo->exibir_cabecalho ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label">Exibir cabeçalho</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Logo no PDF</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="hidden" name="exibir_logo" value="0">
                                            <input type="checkbox" name="exibir_logo" class="form-check-input" value="1"
                                                {{ old('exibir_logo', $modelo->exibir_logo ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label">Exibir logo</label>
                                        </div>
                                    </div>
                                @else
                                    <input type="hidden" name="exibir_cabecalho" value="1">
                                    <input type="hidden" name="exibir_logo" value="1">
                                @endif
                            @else
                                <input type="hidden" name="ativo" value="{{ old('ativo', $modelo->ativo ?? 1) }}">
                                <input type="hidden" name="padrao" value="{{ old('padrao', $modelo->padrao ?? 0) }}">
                                <input type="hidden" name="exibir_cabecalho" value="0">
                                <input type="hidden" name="exibir_logo" value="0">
                            @endif
                            @if($isModeloOrcamento)
                                <input type="hidden" name="exibir_assinatura_locadora" value="1">
                                <input type="hidden" name="exibir_assinatura_cliente" value="1">
                            @else
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Assinatura Locadora</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="hidden" name="exibir_assinatura_locadora" value="0">
                                        <input type="checkbox" name="exibir_assinatura_locadora" class="form-check-input" value="1"
                                               {{ old('exibir_assinatura_locadora', $modelo->exibir_assinatura_locadora ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Exibir assinatura</label>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Assinatura Cliente</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="hidden" name="exibir_assinatura_cliente" value="0">
                                        <input type="checkbox" name="exibir_assinatura_cliente" class="form-check-input" value="1"
                                               {{ old('exibir_assinatura_cliente', $modelo->exibir_assinatura_cliente ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label">Exibir assinatura</label>
                                    </div>
                                </div>
                            @endif
                            @if(!$isModeloMedicao)
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2"
                                          placeholder="Descrição breve do modelo">{{ old('descricao', $modelo->descricao ?? '') }}</textarea>
                            </div>
                                @if($isModeloContrato)
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Título do Documento</label>
                                        <input type="text" name="titulo_documento" class="form-control"
                                               value="{{ old('titulo_documento', $modelo->titulo_documento ?? 'Contrato') }}"
                                               placeholder="Ex: CONTRATO">
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Subtítulo do Documento</label>
                                        <input type="text" name="subtitulo_documento" class="form-control"
                                               value="{{ old('subtitulo_documento', $modelo->subtitulo_documento ?? 'Locação de Bens Móveis') }}"
                                               placeholder="Ex: Locação de Bens Móveis">
                                    </div>
                                @else
                                    <input type="hidden" name="titulo_documento" value="Orçamento">
                                    <input type="hidden" name="subtitulo_documento" value="Orçamento de Locação">
                                    <input type="hidden" name="cor_borda" value="#2f4858">
                                @endif
                            @else
                                <input type="hidden" name="descricao" value="{{ old('descricao', $modelo->descricao ?? '') }}">
                                <input type="hidden" name="titulo_documento" value="{{ old('titulo_documento', $modelo->titulo_documento ?? 'Contrato de Medição') }}">
                                <input type="hidden" name="subtitulo_documento" value="{{ old('subtitulo_documento', $modelo->subtitulo_documento ?? 'Medição') }}">
                                <input type="hidden" name="cor_borda" value="{{ old('cor_borda', $modelo->cor_borda ?? '#2f4858') }}">
                            @endif
                        </div>
                    </div>
                </div>

                @if($isModeloContrato)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Tema Visual</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Cor das Bordas (faixa superior)</label>
                                <input type="color" name="cor_borda" class="form-control form-control-color w-100"
                                       value="{{ old('cor_borda', $modelo->cor_borda ?? '#2f4858') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Tabela de Produtos (Fixa com Colunas Selecionáveis)</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $colunasSelecionadas = old('colunas_tabela_produtos', $modelo->colunas_tabela_produtos ?? ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal']);
                        @endphp
                        <div class="row">
                            @foreach(($colunasTabelaDisponiveis ?? []) as $codigoColuna => $labelColuna)
                                <div class="col-md-3 mb-2">
                                    <label class="form-check d-flex align-items-center gap-2">
                                        <input class="form-check-input" type="checkbox" name="colunas_tabela_produtos[]"
                                               value="{{ $codigoColuna }}" {{ in_array($codigoColuna, $colunasSelecionadas, true) ? 'checked' : '' }}>
                                        <span class="form-check-label">{{ $labelColuna }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">A tabela sempre será impressa; aqui você escolhe quais colunas aparecem.</small>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Padrão do Documento</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            O layout do documento é fixo no padrão oficial. Aqui você personaliza apenas campos permitidos: nome, status, padrão, cabeçalho/logo, título/subtítulo, cores e colunas da tabela.
                        </div>
                    </div>
                </div>
                @else
                    <input type="hidden" name="colunas_tabela_produtos[]" value="produto">
                    <input type="hidden" name="colunas_tabela_produtos[]" value="quantidade">
                    <input type="hidden" name="colunas_tabela_produtos[]" value="dias">
                    <input type="hidden" name="colunas_tabela_produtos[]" value="valor_unitario">
                    <input type="hidden" name="colunas_tabela_produtos[]" value="subtotal">
                @endif

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Cláusulas (Banco de Dados)</h5>
                    </div>
                    <div class="card-body">
                        <label class="form-label">Texto das cláusulas</label>
                        <input type="hidden" name="clausulas_html" id="clausulasHtmlInput"
                               value="{{ old('clausulas_html', $modelo->conteudo_html ?? '') }}">
                        <div id="clausulas-editor" class="border rounded" style="min-height: 240px;"></div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-primary" id="btnAutomatizarClausulaSelecionada">
                                Automatizar cláusula selecionada
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                            <small class="text-muted">Selecione o trecho da cláusula. A primeira linha vira o head e o restante vira o body.</small>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalVariaveisContrato">
                                Ver variáveis
                            </button>
                        </div>
                        <div id="resumoBlocosAutomatizados" class="alert alert-primary mt-3 mb-0 py-2 px-3 d-none" role="status">
                            <div class="small fw-semibold mb-1">Blocos já automatizados neste modelo</div>
                            <div id="resumoBlocosAutomatizadosLista" class="small"></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Assinatura da Locadora</h5>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="assinatura_locadora_base64" id="assinaturaLocadoraBase64">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-7">
                                <div class="border rounded p-2 bg-white">
                                    <canvas id="assinatura-locadora" width="520" height="160" style="width:100%; height:160px; display:block;"></canvas>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <div class="input-group input-group-sm" style="max-width: 320px;">
                                        <span class="input-group-text">Texto</span>
                                        <input type="text" class="form-control" id="assinaturaTexto" placeholder="Digite a assinatura">
                                    </div>
                                    <div class="input-group input-group-sm" style="max-width: 220px;">
                                        <span class="input-group-text">Fonte</span>
                                        <select class="form-select" id="assinaturaFonte">
                                            <option value="'Pacifico', cursive">Pacifico</option>
                                            <option value="'Dancing Script', cursive">Dancing Script</option>
                                            <option value="'Great Vibes', cursive">Great Vibes</option>
                                            <option value="'Allura', cursive">Allura</option>
                                            <option value="'Brush Script MT', cursive">Brush Script</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnGerarAssinatura">Gerar</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimparAssinatura">Limpar</button>
                                    <small class="text-muted">Desenhe ou gere por texto.</small>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Ou envie uma imagem</label>
                                <input type="file" name="assinatura_locadora_arquivo" class="form-control" accept="image/*">
                                @if(isset($modelo) && $modelo->assinatura_locadora_url)
                                    <div class="mt-3">
                                        <div class="text-muted small mb-1">Assinatura atual:</div>
                                        <img src="{{ $modelo->assinatura_locadora_url }}" alt="Assinatura atual" style="max-height: 90px; max-width: 220px;" loading="lazy">
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="card">
                    <div class="card-body d-flex justify-content-between">
                        <a href="{{ route('documentos.index') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-x me-1"></i> Cancelar
                        </a>
                        <div class="d-flex gap-2">
                            @if(isset($modelo))
                                <a href="{{ route('documentos.preview', $modelo->id_modelo) }}" class="btn btn-outline-info" target="_blank">
                                    <i class="ti ti-eye me-1"></i> Visualizar
                                </a>
                            @endif
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i> {{ isset($modelo) ? 'Salvar Alterações' : 'Criar Modelo' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('clausulasHtmlInput');
    var editorEl = document.getElementById('clausulas-editor');
    var resumoBlocosAutomatizadosEl = document.getElementById('resumoBlocosAutomatizados');
    var resumoBlocosAutomatizadosListaEl = document.getElementById('resumoBlocosAutomatizadosLista');

    if (!editorEl) {
        return;
    }

    var toolbar = [
        [{ font: [] }, { size: [] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ script: 'super' }, { script: 'sub' }],
        [{ header: '1' }, { header: '2' }, 'blockquote', 'code-block'],
        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
        [{ direction: 'rtl' }],
        ['link'],
        ['clean']
    ];

    var quill = new Quill('#clausulas-editor', {
        bounds: '#clausulas-editor',
        modules: { toolbar: toolbar },
        theme: 'snow'
    });

    var clausulasForamEditadas = false;
    quill.on('text-change', function (delta, oldDelta, source) {
        if (source === 'user') {
            clausulasForamEditadas = true;
        }
    });

    function escaparHtml(texto) {
        return String(texto || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizarTextoLinha(texto) {
        return String(texto || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function extrairBlocosAutomatizadosDoHtml(html) {
        var htmlAtual = String(html || '').trim();
        if (htmlAtual === '' || !/class\s*=\s*["'][^"']*clausula-box/i.test(htmlAtual)) {
            return [];
        }

        var temp = document.createElement('div');
        temp.innerHTML = htmlAtual;

        return Array.prototype.slice.call(temp.querySelectorAll('.clausula-box'))
            .map(function (box, index) {
                var head = box.querySelector('.clausula-head');
                var titulo = normalizarTextoLinha(head ? head.textContent : '');

                if (titulo !== '') {
                    return titulo;
                }

                var textoFallback = normalizarTextoLinha(box.textContent || '');
                if (textoFallback !== '') {
                    return textoFallback.substring(0, 90);
                }

                return 'Bloco ' + (index + 1);
            })
            .filter(function (titulo) {
                return titulo !== '';
            });
    }

    function atualizarResumoBlocosAutomatizados(blocos) {
        if (!resumoBlocosAutomatizadosEl || !resumoBlocosAutomatizadosListaEl) {
            return;
        }

        var blocosUnicos = Array.from(new Set((blocos || []).map(normalizarTextoLinha).filter(function (titulo) {
            return titulo !== '';
        })));

        if (!blocosUnicos.length) {
            resumoBlocosAutomatizadosEl.classList.add('d-none');
            resumoBlocosAutomatizadosListaEl.innerHTML = '';
            return;
        }

        resumoBlocosAutomatizadosListaEl.innerHTML = blocosUnicos.map(function (titulo, index) {
            return '<div>'
                + '<span class="badge bg-label-primary me-1">AUTO</span>'
                + '<strong>Bloco ' + (index + 1) + ':</strong> '
                + escaparHtml(titulo)
                + '</div>';
        }).join('');

        resumoBlocosAutomatizadosEl.classList.remove('d-none');
    }

    var blocosAutomatizadosDetectados = extrairBlocosAutomatizadosDoHtml(input ? input.value : '');
    atualizarResumoBlocosAutomatizados(blocosAutomatizadosDetectados);

    function inserirHtmlNoEditor(html) {
        var range = quill.getSelection(true);
        var index = range ? range.index : quill.getLength();
        quill.clipboard.dangerouslyPasteHTML(index, html, 'user');
        quill.setSelection(Math.min(index + 1, quill.getLength()), 0, 'silent');
        quill.focus();
    }

    function extrairLinhasTexto(texto) {
        return String(texto || '')
            .replace(/\r\n?/g, '\n')
            .split('\n')
            .map(function (linha) {
                return linha.trim();
            })
            .filter(function (linha) {
                return linha !== '';
            });
    }

    function montarClausulaTexto(titulo, corpo) {
        var tituloFinal = (titulo || '').trim();
        var corpoFinal = (corpo || '').trim();

        if (tituloFinal === '') {
            tituloFinal = 'TÍTULO';
        }

        if (corpoFinal === '') {
            corpoFinal = 'Descreva aqui o conteúdo da cláusula.';
        }

        return '<p>' + escaparHtml(tituloFinal) + '</p>'
            + '<p>' + escaparHtml(corpoFinal).replace(/\r?\n/g, '<br>') + '</p>'
            + '<p><br></p>';
    }

    function htmlParaTextoComQuebras(html) {
        var temp = document.createElement('div');
        temp.innerHTML = String(html || '');

        temp.querySelectorAll('br').forEach(function (el) {
            el.replaceWith('\n');
        });

        temp.querySelectorAll('p,div,li,h1,h2,h3,h4,h5,h6').forEach(function (el) {
            el.appendChild(document.createTextNode('\n'));
        });

        return temp.textContent || '';
    }

    function quebrarEmBlocosClausulas(texto) {
        return String(texto || '')
            .replace(/\r\n?/g, '\n')
            .split(/\n{2,}/)
            .map(function (bloco) {
                return bloco.trim();
            })
            .filter(function (bloco) {
                return bloco !== '';
            });
    }

    function montarClausulasBoxAPartirTexto(texto) {
        var blocos = quebrarEmBlocosClausulas(texto);
        if (!blocos.length) {
            return '';
        }

        return blocos.map(function (bloco) {
            var linhas = extrairLinhasTexto(bloco);
            if (!linhas.length) {
                return '';
            }

            var titulo = (linhas[0] || '').trim();
            if (titulo === '') {
                titulo = 'TÍTULO';
            }

            var corpoLinhas = linhas.slice(1);
            var corpo = corpoLinhas.length
                ? corpoLinhas.map(escaparHtml).join('<br>')
                : '&nbsp;';

            return '<div class="clausula-box">'
                + '<div class="clausula-head">' + escaparHtml(titulo) + '</div>'
                + '<div class="clausula-body">' + corpo + '</div>'
                + '</div>';
        }).join('');
    }

    function normalizarHtmlClausulas(html) {
        var htmlAtual = String(html || '').trim();
        if (htmlAtual === '') {
            return '';
        }

        if (/class\s*=\s*["'][^"']*clausula-box/i.test(htmlAtual)) {
            return htmlAtual;
        }

        var texto = htmlParaTextoComQuebras(htmlAtual);
        var clausulasBox = montarClausulasBoxAPartirTexto(texto);

        if (clausulasBox === '') {
            return htmlAtual;
        }

        return clausulasBox + '<p><br></p>';
    }

    var btnAutomatizarClausulaSelecionada = document.getElementById('btnAutomatizarClausulaSelecionada');

    if (btnAutomatizarClausulaSelecionada) {
        btnAutomatizarClausulaSelecionada.addEventListener('click', function () {
            var range = quill.getSelection();

            if (!range || !range.length) {
                window.alert('Selecione primeiro o texto de uma cláusula no editor.');
                return;
            }

            var textoSelecionado = quill.getText(range.index, range.length).trim();
            if (!textoSelecionado) {
                window.alert('A seleção está vazia.');
                return;
            }

            var linhas = extrairLinhasTexto(textoSelecionado);
            if (!linhas.length) {
                window.alert('Não foi possível identificar texto na seleção.');
                return;
            }

            var titulo = linhas[0];
            var corpo = linhas.slice(1).join('\n').trim();

            if (corpo === '') {
                corpo = 'Descreva aqui o conteúdo da cláusula.';
            }

            quill.deleteText(range.index, range.length, 'user');
            quill.setSelection(range.index, 0, 'silent');
            inserirHtmlNoEditor(montarClausulaTexto(titulo, corpo));
            clausulasForamEditadas = true;

            blocosAutomatizadosDetectados.push(titulo);
            atualizarResumoBlocosAutomatizados(blocosAutomatizadosDetectados);
        });
    }

    if (input && input.value) {
        quill.clipboard.dangerouslyPasteHTML(input.value, 'silent');
    }

    var form = document.getElementById('formModelo');
    if (form) {
        form.addEventListener('submit', function () {
            if (!input) {
                return;
            }

            // Se o usuário não mexeu no editor nesta edição, preserva o HTML salvo.
            if (!clausulasForamEditadas) {
                return;
            }

            var htmlNormalizado = normalizarHtmlClausulas(quill.root.innerHTML);
            var textoLimpo = String(htmlNormalizado || '')
                .replace(/<br\s*\/?>(\s*)/gi, '\n')
                .replace(/&nbsp;/gi, ' ')
                .replace(/<[^>]+>/g, '')
                .trim();

            input.value = textoLimpo === '' ? '' : htmlNormalizado;
        });
    }

    var canvas = document.getElementById('assinatura-locadora');
    var hidden = document.getElementById('assinaturaLocadoraBase64');
    var btnLimpar = document.getElementById('btnLimparAssinatura');
    var btnGerar = document.getElementById('btnGerarAssinatura');
    var inputTexto = document.getElementById('assinaturaTexto');
    var selectFonte = document.getElementById('assinaturaFonte');
    if (canvas && hidden) {
        var ctx = canvas.getContext('2d');
        var desenhando = false;
        var ultimoX = 0;
        var ultimoY = 0;

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#1f2937';

        function getPos(evt) {
            var rect = canvas.getBoundingClientRect();
            var x = (evt.touches ? evt.touches[0].clientX : evt.clientX) - rect.left;
            var y = (evt.touches ? evt.touches[0].clientY : evt.clientY) - rect.top;
            return { x: x, y: y };
        }

        function iniciar(evt) {
            desenhando = true;
            var pos = getPos(evt);
            ultimoX = pos.x;
            ultimoY = pos.y;
        }

        function desenhar(evt) {
            if (!desenhando) return;
            var pos = getPos(evt);
            ctx.beginPath();
            ctx.moveTo(ultimoX, ultimoY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            ultimoX = pos.x;
            ultimoY = pos.y;
            evt.preventDefault();
        }

        function finalizar() {
            if (!desenhando) return;
            desenhando = false;
            hidden.value = canvas.toDataURL('image/png');
        }

        function gerarAssinaturaTexto() {
            var texto = (inputTexto && inputTexto.value) ? inputTexto.value.trim() : '';
            if (!texto) return;

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var fonte = selectFonte ? selectFonte.value : 'cursive';
            ctx.font = '48px ' + fonte;
            ctx.fillStyle = '#111827';
            ctx.textBaseline = 'middle';

            var x = 16;
            var y = canvas.height / 2;
            ctx.fillText(texto, x, y);

            hidden.value = canvas.toDataURL('image/png');
        }

        canvas.addEventListener('mousedown', iniciar);
        canvas.addEventListener('mousemove', desenhar);
        canvas.addEventListener('mouseup', finalizar);
        canvas.addEventListener('mouseleave', finalizar);
        canvas.addEventListener('touchstart', iniciar, { passive: false });
        canvas.addEventListener('touchmove', desenhar, { passive: false });
        canvas.addEventListener('touchend', finalizar);

        if (btnLimpar) {
            btnLimpar.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hidden.value = '';
            });
        }

        if (btnGerar) {
            btnGerar.addEventListener('click', gerarAssinaturaTexto);
        }
    }
});
</script>
@endsection

@section('page-modals')
<div class="modal fade" id="modalVariaveisContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Variáveis do Contrato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @foreach(($variaveisDisponiveis ?? []) as $grupo => $variaveis)
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">{{ $grupo }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($variaveis as $variavel => $descricao)
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="{{ $descricao }}">{{ $variavel }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
