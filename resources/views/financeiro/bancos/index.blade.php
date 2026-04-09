@extends('layouts.layoutMaster')

@section('title', 'Bancos')

@php
    $podeGerenciarBancos = \Perm::pode(auth()->user(), 'financeiro.bancos');
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Gestão de Bancos</h5>
                    @if($podeGerenciarBancos)
                        <button type="button" id="btnNovoBanco" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBanco">
                            <i class="ti ti-plus me-1"></i> Novo Banco
                        </button>
                    @endif
                </div>
            </div>

            <!-- Lista de Bancos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="table-bancos">
                            <thead>
                                <tr>
                                    <th>Banco</th>
                                    <th>Agência</th>
                                    <th>Conta</th>
                                    <th>Saldo Inicial</th>
                                    <th>Observações</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-bancos">
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo/Editar Banco -->
<div class="modal fade" id="modalBanco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBancoTitle">Nova Banco</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formBanco">
                <div class="modal-body">
                    <input type="hidden" id="banco_id">
                    
                    <div class="mb-3">
                        <label for="nome_banco" class="form-label">Nome do Banco <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome_banco" name="nome_banco" required placeholder="Ex: Banco do Brasil, Caixa, etc.">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="agencia" class="form-label">Agência</label>
                            <input type="text" class="form-control" id="agencia" name="agencia" placeholder="0000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="conta" class="form-label">Conta</label>
                            <input type="text" class="form-control" id="conta" name="conta" placeholder="00000-0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="saldo_inicial" class="form-label">Saldo Inicial</label>
                        <input type="number" step="0.01" class="form-control" id="saldo_inicial" name="saldo_inicial" value="0.00" placeholder="0.00">
                    </div>

                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre o banco"></textarea>
                    </div>

                    <!-- Toggle Gera Boleto -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="gera_boleto" name="gera_boleto" onchange="toggleConfigBoleto()">
                            <label class="form-check-label" for="gera_boleto">
                                <strong>Gera Boleto</strong>
                            </label>
                        </div>
                        <small class="text-muted">Ative para configurar a geração de boletos neste banco</small>
                        <div id="geraBoletoAviso" class="mt-2"></div>
                    </div>

                    <!-- Configurações de Boleto (mostrado quando gera_boleto está ativo) -->
                    <div id="configBoletoSection" style="display: none;">
                        <hr>
                        <h6 class="text-primary mb-3"><i class="ti ti-file-invoice me-1"></i>Configuração de Boleto</h6>
                        
                        <div class="mb-3">
                            <label for="id_banco_boleto" class="form-label">Banco para Integração <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_banco_boleto" name="id_banco_boleto" onchange="carregarCamposBanco()">
                                <option value="">Selecione o banco...</option>
                            </select>
                        </div>

                        <div id="camposBoleto">
                            <!-- Os campos serão carregados dinamicamente aqui -->
                        </div>

                        <div id="camposComuns" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="juros_mora" class="form-label">Juros de Mora (% mensal)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" id="juros_mora" name="juros_mora" value="0" placeholder="0.00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="multa_atraso" class="form-label">Multa por Atraso (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" id="multa_atraso" name="multa_atraso" value="0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="instrucao_1" class="form-label">Instrução do Boleto 1</label>
                                <input type="text" class="form-control" id="instrucao_1" name="instrucao_1" maxlength="255" placeholder="Ex: Não receber após vencimento">
                            </div>

                            <div class="mb-3">
                                <label for="instrucao_2" class="form-label">Instrução do Boleto 2</label>
                                <input type="text" class="form-control" id="instrucao_2" name="instrucao_2" maxlength="255" placeholder="Ex: Desconto de 5% até o vencimento">
                            </div>
                        </div>
                    </div>

                    <div id="bancoFormMsg"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
$(document).ready(function() {
    const podeGerenciarBancos = @json($podeGerenciarBancos);
    let editandoBanco = false;
    let bancosBoleto = [];
    let carregandoConfiguracaoBoleto = false;
    let controleBoleto = @json($controleBoleto ?? null);
    let bancoEmEdicaoGeraBoleto = false;

    function getMensagemControleBoleto() {
        if (controleBoleto && controleBoleto.mensagem) {
            return controleBoleto.mensagem;
        }

        return 'Seu plano não permite habilitar emissão de boleto neste momento.';
    }

    function podeHabilitarGeraBoletoNoContexto() {
        if (!controleBoleto) {
            return true;
        }

        if (controleBoleto.sem_aba_boletos) {
            return false;
        }

        // Banco que já estava habilitado pode permanecer habilitado na edição.
        if (bancoEmEdicaoGeraBoleto) {
            return true;
        }

        return !!controleBoleto.pode_habilitar;
    }

    function exibirAvisoControleBoleto(mensagem, tipo = 'warning') {
        $('#geraBoletoAviso').html(`
            <div class="alert alert-${tipo} py-2 px-3 mb-0">
                <i class="ti ti-alert-circle me-1"></i>${mensagem}
            </div>
        `);
    }

    function limparAvisoControleBoleto() {
        $('#geraBoletoAviso').html('');
    }

    function aplicarControleGeraBoleto() {
        const $toggle = $('#gera_boleto');

        if (!$toggle.length) {
            return;
        }

        if (!controleBoleto) {
            $toggle.prop('disabled', false);
            limparAvisoControleBoleto();
            return;
        }

        const semAbaBoletos = !!controleBoleto.sem_aba_boletos;
        const podeNoContexto = podeHabilitarGeraBoletoNoContexto();

        if (semAbaBoletos) {
            $toggle.prop('checked', false);
            $toggle.prop('disabled', true);
            $('#configBoletoSection').hide();
            exibirAvisoControleBoleto(getMensagemControleBoleto(), 'warning');
            return;
        }

        // Se não pode habilitar neste contexto, mantém desabilitado para evitar tentativa no segundo banco.
        if (!podeNoContexto) {
            if ($toggle.is(':checked')) {
                $toggle.prop('checked', false);
            }
            $toggle.prop('disabled', true);
            $('#configBoletoSection').hide();
            exibirAvisoControleBoleto(getMensagemControleBoleto(), 'warning');
            return;
        }

        $toggle.prop('disabled', false);
        limparAvisoControleBoleto();
    }

    // Carregar bancos ao iniciar
    carregarBancos();
    carregarBancosBoleto();
    aplicarControleGeraBoleto();

    // Limpar formulário ao fechar modal
    $('#modalBanco').on('hidden.bs.modal', function () {
        limparFormulario();
    });

    // Preparar formulário ao abrir modal para novo banco
    $('#btnNovoBanco').on('click', function () {
        limparFormulario();
        $('#modalBancoTitle').text('Novo Banco');
        editandoBanco = false;
        bancoEmEdicaoGeraBoleto = false;
        aplicarControleGeraBoleto();
    });

    // Submit do formulário
    $('#formBanco').on('submit', async function(e) {
        e.preventDefault();
        
        const geraBoleto = $('#gera_boleto').is(':checked');

        if (geraBoleto && !podeHabilitarGeraBoletoNoContexto()) {
            const mensagemBloqueio = getMensagemControleBoleto();
            mostrarMensagem(mensagemBloqueio, 'danger');
            exibirAvisoControleBoleto(mensagemBloqueio, 'danger');
            $('#gera_boleto').prop('checked', false);
            $('#configBoletoSection').hide();
            return;
        }
        
        const dados = {
            _token: '{{ csrf_token() }}',
            nome_banco: $('#nome_banco').val().trim(),
            agencia: $('#agencia').val().trim(),
            conta: $('#conta').val().trim(),
            saldo_inicial: $('#saldo_inicial').val() || 0,
            observacoes: $('#observacoes').val().trim(),
            gera_boleto: geraBoleto ? 1 : 0,
        };

        if (!dados.nome_banco) {
            mostrarMensagem('O nome do banco é obrigatório.', 'danger');
            return;
        }

        // Validar campos de boleto se ativado
        if (geraBoleto && !$('#id_banco_boleto').val()) {
            mostrarMensagem('Selecione o banco para integração de boleto.', 'danger');
            return;
        }

        const btnSubmit = $(this).find('button[type="submit"]');
        btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');

        const url = editandoBanco ? `/financeiro/bancos/${$('#banco_id').val()}` : '{{ route("financeiro.bancos.store") }}';
        const method = editandoBanco ? 'PUT' : 'POST';

        if (editandoBanco) {
            dados._method = 'PUT';
        }

        try {
            // Salvar banco
            const response = await $.ajax({
                url: url,
                method: 'POST',
                data: dados
            });
            
            if (response.success) {
                const bancoId = response.banco.id_bancos;
                
                // Salvar configuração de boleto se ativado
                if (geraBoleto) {
                    try {
                        await salvarConfiguracaoBoleto(bancoId);
                    } catch (erro) {
                        mostrarMensagem('Banco salvo, mas erro na configuração de boleto: ' + erro, 'warning');
                        btnSubmit.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Salvar');
                        return;
                    }
                }
                
                $('#modalBanco').modal('hide');
                Swal.fire({
                    title: 'Sucesso!',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                carregarBancos();
            } else {
                mostrarMensagem(response.message || 'Erro ao salvar banco.', 'danger');
            }
        } catch (xhr) {
            let errorMsg = 'Erro ao salvar banco.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            mostrarMensagem(errorMsg, 'danger');
        } finally {
            btnSubmit.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Salvar');
        }
    });

    // Função para carregar bancos
    function carregarBancos() {
        $('#tbody-bancos').html(`
            <tr>
                <td colspan="6" class="text-center">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: '{{ route("financeiro.bancos.list") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    controleBoleto = response.controle_boleto || controleBoleto;
                    aplicarControleGeraBoleto();

                    if (response.bancos && response.bancos.length > 0) {
                        renderizarBancos(response.bancos);
                    } else {
                        $('#tbody-bancos').html(`
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="ti ti-building-bank ti-lg mb-2"></i>
                                    <p>Nenhum banco cadastrado.</p>
                                </td>
                            </tr>
                        `);
                    }
                } else {
                    $('#tbody-bancos').html(`
                        <tr>
                            <td colspan="6" class="text-center text-danger">
                                <i class="ti ti-alert-circle me-1"></i> ${response.message || 'Erro ao carregar bancos.'}
                            </td>
                        </tr>
                    `);
                }
            },
            error: function(xhr) {
                const mensagem = xhr.responseJSON?.message || 'Erro ao carregar bancos.';
                $('#tbody-bancos').html(`
                    <tr>
                        <td colspan="6" class="text-center text-danger">
                            <i class="ti ti-alert-circle me-1"></i> ${mensagem}
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Renderizar lista de bancos
    function renderizarBancos(bancos) {
        let html = '';
        
        bancos.forEach(function(banco) {
            const saldoFormatado = parseFloat(banco.saldo_inicial || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });

            const badgeBoleto = banco.gera_boleto 
                ? '<span class="badge bg-label-success ms-1" title="Configurado para gerar boletos"><i class="ti ti-file-invoice ti-xs"></i></span>'
                : '';

            html += `
                <tr>
                    <td><strong>${banco.nome_banco}</strong>${badgeBoleto}</td>
                    <td>${banco.agencia || '-'}</td>
                    <td>${banco.conta || '-'}</td>
                    <td>${saldoFormatado}</td>
                    <td>${banco.observacoes ? banco.observacoes.substring(0, 50) + (banco.observacoes.length > 50 ? '...' : '') : '-'}</td>
                    <td>
                        ${podeGerenciarBancos ? `
                        <button class="btn btn-sm btn-icon btn-label-primary" onclick="editarBanco(${banco.id_bancos})" title="Editar">
                            <i class="ti ti-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-label-danger" onclick="excluirBanco(${banco.id_bancos}, '${banco.nome_banco}')" title="Excluir">
                            <i class="ti ti-trash"></i>
                        </button>
                        ` : '<span class="text-muted">Sem ações</span>'}
                    </td>
                </tr>
            `;
        });
        
        $('#tbody-bancos').html(html);
    }

    // Função para editar banco
    window.editarBanco = function(id) {
        $.ajax({
            url: `/financeiro/bancos/${id}`,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const banco = response.banco;

                    editandoBanco = true;
                    
                    $('#banco_id').val(banco.id_bancos);
                    $('#nome_banco').val(banco.nome_banco);
                    $('#agencia').val(banco.agencia);
                    $('#conta').val(banco.conta);
                    $('#saldo_inicial').val(banco.saldo_inicial);
                    $('#observacoes').val(banco.observacoes);

                    bancoEmEdicaoGeraBoleto = !!banco.gera_boleto;
                    
                    // Configuração de boleto
                    if (banco.gera_boleto) {
                        $('#gera_boleto').prop('checked', true);
                        $('#configBoletoSection').show();
                    } else {
                        $('#gera_boleto').prop('checked', false);
                        $('#configBoletoSection').hide();
                    }

                    aplicarControleGeraBoleto();

                    // Sempre tentar carregar a configuração existente para evitar tela vazia
                    // quando o flag gera_boleto estiver inconsistente.
                    carregarConfiguracaoBoleto(banco.id_bancos);
                    
                    $('#modalBancoTitle').text('Editar Banco');
                    $('#modalBanco').modal('show');
                }
            },
            error: function(xhr) {
                const mensagem = xhr.responseJSON?.message || 'Erro ao carregar dados do banco.';
                Swal.fire({
                    title: 'Erro!',
                    text: mensagem,
                    icon: 'error'
                });
            }
        });
    };

    // Função para excluir banco
    window.excluirBanco = function(id, nome) {
        Swal.fire({
            title: 'Confirmar exclusão',
            text: `Deseja realmente excluir o banco "${nome}"?`,
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: false,
            showCloseButton: false,
            confirmButtonColor: '#696cff',
            cancelButtonColor: '#8592a3',
            confirmButtonText: '<i class="ti ti-check me-1"></i>Sim, excluir!',
            cancelButtonText: '<i class="ti ti-x me-1"></i>Cancelar',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/financeiro/bancos/${id}`,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'DELETE'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Sucesso!',
                                text: response.message || 'Banco excluído com sucesso!',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            carregarBancos();
                        } else {
                            Swal.fire({
                                title: 'Erro!',
                                text: response.message || 'Erro ao excluir banco',
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Erro ao excluir banco.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            title: 'Erro!',
                            text: errorMsg,
                            icon: 'error'
                        });
                    }
                });
            }
        });
    };

    // Limpar formulário
    function limparFormulario() {
        $('#formBanco')[0].reset();
        $('#banco_id').val('');
        $('#saldo_inicial').val('0.00');
        $('#bancoFormMsg').html('');
        editandoBanco = false;
        bancoEmEdicaoGeraBoleto = false;
        
        // Limpar campos de boleto
        $('#gera_boleto').prop('checked', false);
        $('#configBoletoSection').hide();
        $('#id_banco_boleto').val('');
        $('#camposBoleto').html('');
        $('#camposComuns').hide();
        $('#juros_mora').val('0');
        $('#multa_atraso').val('0');
        $('#instrucao_1').val('');
        $('#instrucao_2').val('');
        limparAvisoControleBoleto();
        aplicarControleGeraBoleto();
    }

    // Mostrar mensagem no formulário
    function mostrarMensagem(mensagem, tipo) {
        $('#bancoFormMsg').html(`
            <div class="alert alert-${tipo} alert-dismissible fade show">
                <i class="ti ti-alert-circle me-1"></i>
                ${mensagem}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
    }

    // Carregar bancos de boleto disponíveis
    function carregarBancosBoleto() {
        $.ajax({
            url: '{{ route("financeiro.boletos.bancos-boleto") }}',
            method: 'GET',
            success: function(response) {
                let options = '<option value="">Selecione o banco...</option>';

                if (response.success && Array.isArray(response.bancos_boleto) && response.bancos_boleto.length > 0) {
                    bancosBoleto = response.bancos_boleto;
                    bancosBoleto.forEach(function(banco) {
                        options += `<option value="${banco.id_banco_boleto}" 
                            data-requer-certificado="${banco.requer_certificado}"
                            data-requer-chave="${banco.requer_chave}"
                            data-requer-client-id="${banco.requer_client_id}"
                            data-requer-client-secret="${banco.requer_client_secret}"
                            data-requer-api-key="${banco.requer_api_key}"
                            data-requer-token="${banco.requer_token}"
                            data-requer-convenio="${banco.requer_convenio}"
                            data-requer-carteira="${banco.requer_carteira}"
                            data-instrucoes="${banco.instrucoes || ''}"
                        >${banco.nome}</option>`;
                    });
                } else {
                    bancosBoleto = [];

                    if ($('#gera_boleto').is(':checked')) {
                        const mensagem = response.message || 'Nenhuma integração de boleto ativa foi encontrada.';
                        mostrarMensagem(mensagem, 'warning');
                    }
                }

                $('#id_banco_boleto').html(options);
            },
            error: function(xhr) {
                bancosBoleto = [];
                $('#id_banco_boleto').html('<option value="">Selecione o banco...</option>');

                const mensagem = xhr.responseJSON?.message || 'Não foi possível carregar os bancos de integração de boleto.';
                console.warn('Erro ao carregar bancos de boleto', {
                    status: xhr.status,
                    mensagem,
                });

                if ($('#gera_boleto').is(':checked')) {
                    mostrarMensagem(mensagem, 'warning');
                }
            }
        });
    }

    // Toggle visibilidade da configuração de boleto
    window.toggleConfigBoleto = function() {
        const checked = $('#gera_boleto').is(':checked');

        if (checked && !podeHabilitarGeraBoletoNoContexto()) {
            $('#gera_boleto').prop('checked', false);
            $('#configBoletoSection').slideUp();

            const mensagemBloqueio = getMensagemControleBoleto();
            mostrarMensagem(mensagemBloqueio, 'danger');
            exibirAvisoControleBoleto(mensagemBloqueio, 'danger');
            return;
        }

        if (checked) {
            $('#configBoletoSection').slideDown();
            limparAvisoControleBoleto();
        } else {
            $('#configBoletoSection').slideUp();
        }
    };

    // Carregar campos dinâmicos baseado no banco selecionado
    window.carregarCamposBanco = function() {
        const select = $('#id_banco_boleto');
        const selected = select.find('option:selected');
        const idBancoBoleto = select.val();

        if (!idBancoBoleto) {
            $('#camposBoleto').html('');
            $('#camposComuns').hide();
            return;
        }

        let html = '';
        const instrucoes = selected.data('instrucoes');
        const bancoNome = String(selected.text() || '').toLowerCase();
        const isCora = bancoNome.includes('cora');
        const idBancoSistema = $('#banco_id').val();

        // Mostrar instruções do banco
        if (instrucoes) {
            html += `<div class="alert alert-info mb-3">
                <i class="ti ti-info-circle me-1"></i>
                <strong>Instruções:</strong><br>
                ${instrucoes.replace(/\n/g, '<br>')}
            </div>`;
        }

        if (isCora) {
            const coraAuthorizeUrl = idBancoSistema ? `/financeiro/boletos/cora/authorize/${idBancoSistema}` : '';
            const redirectUriEstimado = `${window.location.origin}/financeiro/boletos/cora/callback`;
            const botaoDesabilitado = coraAuthorizeUrl ? '' : ' disabled';
            const hrefAutorizacao = coraAuthorizeUrl || '#';

            html += `<div class="alert alert-primary mb-3" id="coraAuthCard">
                <i class="ti ti-shield-lock me-1"></i>
                <strong>Conexão da conta Cora</strong><br>
                <span class="small">A emissão de boletos usa Authorization Code e exige autorização da conta.</span><br>
                <span class="small d-block mt-1">Redirect URI é a URL de retorno após a autorização na Cora.</span>
                <a id="coraAuthorizeBtn" href="${hrefAutorizacao}" class="btn btn-sm btn-primary mt-2${botaoDesabilitado}">Conectar conta Cora</a>
                <div id="coraSupportHint" class="small mt-1 text-muted">Se você não tem portal da Cora para cadastrar callback, envie o Redirect URI abaixo para suporteapi@cora.com.br.</div>
                <div id="coraAuthStatus" class="small mt-2 text-muted">Aguardando status da autorização.</div>
                <div id="coraRedirectUriHint" class="small mt-1 text-muted">Redirect URI esperado: <strong>${redirectUriEstimado}</strong></div>
            </div>`;
        }

        // Campos de arquivo
        if (selected.data('requer-certificado') == 1) {
            html += `
            <div class="mb-3">
                <label for="arquivo_certificado" class="form-label">Arquivo de Certificado (.crt) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="arquivo_certificado" name="arquivo_certificado" accept=".crt">
                <small class="text-muted" id="certificado_status"></small>
            </div>`;
        }

        if (selected.data('requer-chave') == 1) {
            html += `
            <div class="mb-3">
                <label for="arquivo_chave" class="form-label">Arquivo de Chave (.key) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="arquivo_chave" name="arquivo_chave" accept=".key">
                <small class="text-muted" id="chave_status"></small>
            </div>`;
        }

        // Campos de texto
        if (selected.data('requer-client-id') == 1) {
            html += `
            <div class="mb-3">
                <label for="client_id" class="form-label">Client ID <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="client_id" name="client_id" placeholder="Client ID da API">
            </div>`;
        }

        if (selected.data('requer-client-secret') == 1) {
            html += `
            <div class="mb-3">
                <label for="client_secret" class="form-label">Client Secret <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="client_secret" name="client_secret" placeholder="Client Secret da API">
                <small class="text-muted" id="client_secret_status"></small>
            </div>`;
        }

        if (selected.data('requer-api-key') == 1) {
            html += `
            <div class="mb-3">
                <label for="api_key" class="form-label">API Key <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="api_key" name="api_key" placeholder="API Key">
            </div>`;
        }

        if (selected.data('requer-token') == 1) {
            html += `
            <div class="mb-3">
                <label for="token" class="form-label">Token <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="token" name="token" placeholder="Token de acesso">
            </div>`;
        }

        if (selected.data('requer-convenio') == 1) {
            html += `
            <div class="mb-3">
                <label for="convenio" class="form-label">Número do Convênio <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="convenio" name="convenio" placeholder="Número do convênio">
            </div>`;
        }

        if (selected.data('requer-carteira') == 1) {
            html += `
            <div class="mb-3">
                <label for="carteira" class="form-label">Código da Carteira <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="carteira" name="carteira" placeholder="Código da carteira">
            </div>`;
        }

        $('#camposBoleto').html(html);
        $('#camposComuns').show();

        // Se estiver editando, carregar configuração existente
        if (editandoBanco && $('#banco_id').val() && !carregandoConfiguracaoBoleto) {
            carregarConfiguracaoBoleto($('#banco_id').val());
        }
    };

    // Carregar configuração de boleto existente
    function carregarConfiguracaoBoleto(idBancos) {
        carregandoConfiguracaoBoleto = true;

        $.ajax({
            url: `/financeiro/boletos/configuracao/${idBancos}`,
            method: 'GET',
            success: function(response) {
                if (response.success && response.config) {
                    const config = response.config;

                    // Se existe configuração no banco, garantir toggle ativo na edição.
                    if (!$('#gera_boleto').is(':checked') && podeHabilitarGeraBoletoNoContexto()) {
                        $('#gera_boleto').prop('checked', true);
                        $('#configBoletoSection').show();
                    }
                    
                    if (config.id_banco_boleto) {
                        $('#id_banco_boleto').val(config.id_banco_boleto);
                        carregarCamposBanco();
                        
                        // Preencher campos após carregar
                        setTimeout(function() {
                            const setCampo = function(selector, valor, fallback = '') {
                                if ($(selector).length) {
                                    $(selector).val(valor !== null && valor !== undefined ? valor : fallback);
                                }
                            };

                            setCampo('#client_id', config.client_id);
                            setCampo('#client_secret', config.client_secret);
                            setCampo('#api_key', config.api_key);
                            setCampo('#token', config.token);
                            setCampo('#convenio', config.convenio);
                            setCampo('#carteira', config.carteira);
                            setCampo('#juros_mora', config.juros_mora, 0);
                            setCampo('#multa_atraso', config.multa_atraso, 0);
                            setCampo('#instrucao_1', config.instrucao_1);
                            setCampo('#instrucao_2', config.instrucao_2);
                            
                            // Mostrar status dos arquivos
                            if (config.arquivo_certificado) {
                                $('#certificado_status').html('<span class="text-success"><i class="ti ti-check"></i> Arquivo já enviado</span>');
                            } else {
                                $('#certificado_status').html('');
                            }
                            if (config.arquivo_chave) {
                                $('#chave_status').html('<span class="text-success"><i class="ti ti-check"></i> Arquivo já enviado</span>');
                            } else {
                                $('#chave_status').html('');
                            }

                            if (config.tem_client_secret) {
                                $('#client_secret').attr('placeholder', 'Client Secret já configurado');
                                $('#client_secret_status').html('<span class="text-success"><i class="ti ti-check"></i> Client Secret já salvo</span>');
                            } else {
                                $('#client_secret').attr('placeholder', 'Client Secret da API');
                                $('#client_secret_status').html('');
                            }

                            if ($('#coraAuthStatus').length) {
                                if (config.tem_token) {
                                    const tokenModo = config.token_modo ? ` (modo: ${config.token_modo})` : '';
                                    $('#coraAuthStatus').html(`<span class="text-success"><i class="ti ti-check"></i> Conta Cora autorizada${tokenModo}.</span>`);
                                } else {
                                    $('#coraAuthStatus').html('<span class="text-warning"><i class="ti ti-alert-circle"></i> Conta Cora ainda não autorizada.</span>');
                                }

                                if (config.cora_authorize_url) {
                                    $('#coraAuthorizeBtn')
                                        .attr('href', config.cora_authorize_url)
                                        .removeClass('disabled');
                                }

                                if (config.cora_redirect_uri) {
                                    $('#coraRedirectUriHint').html(`Redirect URI: <strong>${config.cora_redirect_uri}</strong>`);
                                }
                            }
                        }, 100);
                    }
                }
            },
            complete: function() {
                carregandoConfiguracaoBoleto = false;
            }
        });
    }

    // Função para fazer upload de arquivo
    function uploadArquivoBoleto(idBancos, tipo, arquivo) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('id_bancos', idBancos);
            formData.append('tipo', tipo);
            formData.append('arquivo', arquivo);
            formData.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: '{{ route("financeiro.boletos.upload-arquivo") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(response.message);
                    }
                },
                error: function(xhr) {
                    reject(xhr.responseJSON?.message || 'Erro ao enviar arquivo');
                }
            });
        });
    }

    // Função para salvar configuração de boleto
    async function salvarConfiguracaoBoleto(idBancos) {
        const geraBoleto = $('#gera_boleto').is(':checked');
        
        if (!geraBoleto) {
            return Promise.resolve();
        }

        const idBancoBoleto = $('#id_banco_boleto').val();
        if (!idBancoBoleto) {
            return Promise.reject('Selecione o banco para integração de boleto');
        }

        // Salvar configuração primeiro para garantir que a linha existe
        const respostaConfig = await new Promise((resolve, reject) => {
            $.ajax({
                url: '{{ route("financeiro.boletos.salvar-configuracao") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id_bancos: idBancos,
                    id_banco_boleto: idBancoBoleto,
                    client_id: $('#client_id').val(),
                    client_secret: $('#client_secret').val(),
                    api_key: $('#api_key').val(),
                    token: $('#token').val(),
                    convenio: $('#convenio').val(),
                    carteira: $('#carteira').val(),
                    juros_mora: $('#juros_mora').val() || 0,
                    multa_atraso: $('#multa_atraso').val() || 0,
                    instrucao_1: $('#instrucao_1').val(),
                    instrucao_2: $('#instrucao_2').val()
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(response.message);
                    }
                },
                error: function(xhr) {
                    reject(xhr.responseJSON?.message || 'Erro ao salvar configuração de boleto');
                }
            });
        });

        // Com configuração criada/atualizada, enviar arquivos e vincular corretamente
        const arquivoCert = $('#arquivo_certificado')[0]?.files[0];
        const arquivoChave = $('#arquivo_chave')[0]?.files[0];

        if (arquivoCert) {
            await uploadArquivoBoleto(idBancos, 'certificado', arquivoCert);
        }
        if (arquivoChave) {
            await uploadArquivoBoleto(idBancos, 'chave', arquivoChave);
        }

        return respostaConfig;
    }
});
</script>
@endsection
