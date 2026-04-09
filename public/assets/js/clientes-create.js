// ============================================
// CLIENTES CREATE - Formulário e Validação
// ============================================

$(document).ready(function() {
    const $form = $('#clienteCreateForm');
    const $submitBtn = $form.find('button[type="submit"]');

    function normalizarTexto(valor) {
        return (valor || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function preencherUfCidade(uf, cidade) {
        const ufNormalizada = (uf || '').toString().trim().toUpperCase();
        const cidadeNormalizada = (cidade || '').toString().trim();
        const cidadeChave = normalizarTexto(cidadeNormalizada);

        if (!ufNormalizada) {
            return;
        }

        const $uf = $('#uf');
        const $cidade = $('#cidade');

        $uf.val(ufNormalizada);

        if (!cidadeNormalizada) {
            $uf.trigger('change');
            return;
        }

        // A seleção da cidade depende do carregamento assíncrono da lista por UF.
        $cidade.attr('data-selected', cidadeNormalizada);
        $uf.trigger('change');

        let tentativas = 0;
        const maxTentativas = 20;
        const timer = setInterval(function() {
            tentativas += 1;

            const cidadeEncontrada = $cidade.find('option').filter(function() {
                return normalizarTexto($(this).val()) === cidadeChave;
            }).first();

            if (cidadeEncontrada.length) {
                $cidade.val(cidadeEncontrada.val()).trigger('change');
                clearInterval(timer);
                return;
            }

            if (tentativas >= maxTentativas) {
                clearInterval(timer);
            }
        }, 150);
    }

    // Alternar entre CPF e CNPJ
    $('#id_tipo_pessoa').on('change', function() {
        const tipoPessoa = $(this).val();
        if (tipoPessoa == '1') { // Pessoa Física
            $('#campo-cpf').show();
            $('#campo-cnpj').hide();
            $('#cpf_cnpj').attr('data-mask', '000.000.000-00').mask('000.000.000-00').val('').prop('required', true);
            $('#cnpj_input').val('').prop('required', false);
        } else { // Pessoa Jurídica
            $('#campo-cpf').hide();
            $('#campo-cnpj').show();
            $('#cnpj_input').attr('data-mask', '00.000.000/0000-00').mask('00.000.000/0000-00').val('').prop('required', true);
            $('#cpf_cnpj').val('').prop('required', false);
        }
    });

    // Consultar CNPJ na ReceitaWS
    $('#btnConsultarCNPJ').on('click', function() {
        const cnpj = $('#cnpj_input').val().replace(/[^\d]/g, '');
        
        if (!cnpj || cnpj.length !== 14) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'Digite um CNPJ válido.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Mostrar loading
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: `https://www.receitaws.com.br/v1/cnpj/${cnpj}`,
            method: 'GET',
            dataType: 'jsonp',
            success: function(data) {
                if (data.status === 'ERROR') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: data.message || 'CNPJ não encontrado.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    // Preencher campos
                    $('#cpf_cnpj').val($('#cnpj_input').val()); // Copiar CNPJ formatado para o campo hidden
                    $('#nome').val(data.nome || '');
                    $('#razao_social').val(data.fantasia || '');
                    $('#email').val(data.email || '');
                    $('#telefone').val(data.telefone || '');
                    $('#cep').val(data.cep ? data.cep.replace(/[^\d]/g, '') : '');
                    $('#endereco').val(data.logradouro || '');
                    $('#numero').val(data.numero || '');
                    $('#complemento').val(data.complemento || '');
                    $('#bairro').val(data.bairro || '');

                    const cidadeCnpj = data.municipio || data.cidade || data.localidade || '';
                    preencherUfCidade(data.uf || '', cidadeCnpj);

                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Dados do CNPJ carregados com sucesso!',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Não foi possível consultar o CNPJ. Tente novamente.',
                    confirmButtonText: 'OK'
                });
            },
            complete: function() {
                $('#btnConsultarCNPJ').prop('disabled', false).html('<i class="ti ti-search me-1"></i>Consultar');
            }
        });
    });

    // Validação básica antes de submeter
    $form.on('submit', function(e) {
        const nome = $form.find('input[name="nome"]').val();
        
        if (!nome) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'Nome é obrigatório.',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Desabilita botão para evitar múltiplos cliques
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Salvando...');
        
        // Permite submit normal do formulário
        return true;
    });

    // Validação de CPF
    function validarCPF(cpf) {
        cpf = cpf.replace(/[^\d]+/g, '');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        
        let soma = 0;
        let resto;
        
        for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
        resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.substring(9, 10))) return false;
        
        soma = 0;
        for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
        resto = (soma * 10) % 11;
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.substring(10, 11))) return false;
        
        return true;
    }

    // Validação de CNPJ
    function validarCNPJ(cnpj) {
        cnpj = cnpj.replace(/[^\d]+/g, '');
        if (cnpj.length !== 14) return false;
        
        if (/^(\d)\1{13}$/.test(cnpj)) return false;
        
        let tamanho = cnpj.length - 2;
        let numeros = cnpj.substring(0, tamanho);
        let digitos = cnpj.substring(tamanho);
        let soma = 0;
        let pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) pos = 9;
        }
        
        let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(0)) return false;
        
        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) pos = 9;
        }
        
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(1)) return false;
        
        return true;
    }

    // Validar CPF/CNPJ ao sair do campo
    $('#cpf_cnpj').on('blur', function() {
        const valor = $(this).val();
        const tipoPessoa = $('#id_tipo_pessoa').val();
        
        if (!valor) return;
        
        const valido = tipoPessoa == '1' ? validarCPF(valor) : validarCNPJ(valor);
        
        if (!valido) {
            Swal.fire({
                icon: 'error',
                title: 'Documento Inválido',
                text: `${tipoPessoa == '1' ? 'CPF' : 'CNPJ'} inválido!`,
                timer: 2000,
                showConfirmButton: false
            });
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
});
