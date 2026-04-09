(function () {
  'use strict';

  $(document).ready(function () {
    if (!$('#fornecedorForm').length) {
      return;
    }

    var $tipoPessoa = $('#id_tipo_pessoa');
    var $cpfCnpj = $('#cpf_cnpj');
    var $telefone = $('#telefone');
    var $cep = $('#cep');
    var $uf = $('#uf');
    var $btnConsultarCNPJ = $('#btnConsultarCNPJ');
    var $btnBuscarCEP = $('#btnBuscarCEP');
    var $labelDoc = $('#label-cpf-cnpj');
    var $labelRgIe = $('#label-rg-ie');
    var $labelDataNascimento = $('#label-data-nascimento');
    var $dataNascimento = $('#data_nascimento').closest('.col-md-4');

    function onlyDigits(value) {
      return (value || '').replace(/\D/g, '');
    }

    function notify(icon, title, text) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: icon,
          title: title,
          text: text,
          confirmButtonText: 'OK'
        });
        return;
      }

      window.alert(text || title);
    }

    function notifySuccess(text) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Sucesso!',
          text: text,
          timer: 1800,
          showConfirmButton: false
        });
        return;
      }

      window.alert(text);
    }

    function aplicarMascaraDocumento() {
      if (!$cpfCnpj.length || typeof $cpfCnpj.mask !== 'function') {
        return;
      }

      $cpfCnpj.unmask();
      if ($tipoPessoa.val() === '1') {
        $cpfCnpj.mask('000.000.000-00');
        $cpfCnpj.attr('placeholder', '000.000.000-00');
      } else {
        $cpfCnpj.mask('00.000.000/0000-00');
        $cpfCnpj.attr('placeholder', '00.000.000/0000-00');
      }
    }

    function atualizarTextosTipoPessoa() {
      var isFisica = $tipoPessoa.val() === '1';

      $labelDoc.text((isFisica ? 'CPF' : 'CNPJ') + ' *');
      $labelRgIe.text(isFisica ? 'RG' : 'Inscricao Estadual');
      $labelDataNascimento.text('Data de Nascimento');

      if ($btnConsultarCNPJ.length) {
        if (isFisica) {
          $btnConsultarCNPJ.addClass('d-none').prop('disabled', true);
        } else {
          $btnConsultarCNPJ.removeClass('d-none').prop('disabled', false);
        }
      }

      if (isFisica) {
        $dataNascimento.show();
      } else {
        $dataNascimento.hide();
        $('#data_nascimento').val('');
      }
    }

    function preencherEndereco(data) {
      $('#endereco').val(data.logradouro || '');
      $('#bairro').val(data.bairro || '');
      $('#municipio').val(data.localidade || data.municipio || '');
      $('#uf').val((data.uf || '').toUpperCase());

      if (!$('#numero').val()) {
        $('#numero').focus();
      }
    }

    function buscarCep(cep) {
      return $.ajax({
        url: 'https://viacep.com.br/ws/' + cep + '/json/',
        dataType: 'json',
        success: function (data) {
          if (!data || data.erro) {
            notify('warning', 'CEP nao encontrado', 'Nao foi possivel localizar o endereco para este CEP.');
            return;
          }

          preencherEndereco(data);
        },
        error: function () {
          notify('error', 'Erro', 'Nao foi possivel consultar o CEP.');
        }
      });
    }

    function preencherDadosCnpj(data) {
      var nome = data.nome || data.razao_social || '';
      var fantasia = data.fantasia || data.nome_fantasia || '';
      var email = data.email || '';
      var telefone = data.telefone || data.ddd_telefone_1 || '';
      var cep = onlyDigits(data.cep || '');

      if (nome) {
        $('#nome').val(nome);
      }

      if (fantasia) {
        $('#razao_social').val(fantasia);
      }

      if (email) {
        $('#email').val(email);
      }

      if (telefone) {
        $('#telefone').val(telefone);
      }

      if (cep.length === 8) {
        $('#cep').val(cep);
      }

      if (data.logradouro || data.bairro || data.municipio || data.uf) {
        preencherEndereco({
          logradouro: data.logradouro || '',
          bairro: data.bairro || '',
          municipio: data.municipio || data.localidade || '',
          uf: data.uf || ''
        });
      }

      $('#numero').val(data.numero || $('#numero').val());
      $('#complemento').val(data.complemento || $('#complemento').val());

      if (cep.length === 8 && !(data.logradouro && data.bairro && (data.municipio || data.localidade) && data.uf)) {
        buscarCep(cep);
      }
    }

    function consultarCnpj() {
      var tipoPessoa = $tipoPessoa.val();
      var cnpj = onlyDigits($cpfCnpj.val());

      if (tipoPessoa !== '2') {
        notify('warning', 'Atenção', 'Selecione Pessoa Juridica para consultar CNPJ.');
        return;
      }

      if (!cnpj || cnpj.length !== 14) {
        notify('warning', 'Atenção', 'Digite um CNPJ valido com 14 digitos.');
        return;
      }

      var botaoHtmlOriginal = $btnConsultarCNPJ.html();
      $btnConsultarCNPJ.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

      $.ajax({
        url: 'https://www.receitaws.com.br/v1/cnpj/' + cnpj,
        method: 'GET',
        dataType: 'jsonp',
        success: function (data) {
          if (!data || data.status === 'ERROR') {
            notify('error', 'Erro', (data && data.message) ? data.message : 'CNPJ nao encontrado.');
            return;
          }

          preencherDadosCnpj(data);
          notifySuccess('Dados do CNPJ carregados com sucesso!');
        },
        error: function () {
          notify('error', 'Erro', 'Nao foi possivel consultar o CNPJ. Tente novamente.');
        },
        complete: function () {
          $btnConsultarCNPJ.prop('disabled', false).html(botaoHtmlOriginal);
          atualizarTextosTipoPessoa();
        }
      });
    }

    function acaoBuscarCep() {
      var cep = onlyDigits($cep.val());

      if (cep.length !== 8) {
        notify('warning', 'Atenção', 'Digite um CEP valido com 8 digitos.');
        return;
      }

      if (!$btnBuscarCEP.length) {
        buscarCep(cep);
        return;
      }

      var botaoHtmlOriginal = $btnBuscarCEP.html();
      $btnBuscarCEP.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

      buscarCep(cep).always(function () {
        $btnBuscarCEP.prop('disabled', false).html(botaoHtmlOriginal);
      });
    }

    if (typeof $telefone.mask === 'function') {
      $telefone.mask('(00) 00000-0000');
      $cep.mask('00000-000');
    }

    $tipoPessoa.on('change', function () {
      atualizarTextosTipoPessoa();
      aplicarMascaraDocumento();
    });

    $btnConsultarCNPJ.on('click', function (e) {
      e.preventDefault();
      consultarCnpj();
    });

    $btnBuscarCEP.on('click', function (e) {
      e.preventDefault();
      acaoBuscarCep();
    });

    $cep.on('blur', function () {
      var valor = $(this).val().replace(/\D/g, '');
      if (valor.length === 8) {
        buscarCep(valor);
      }
    });

    $uf.on('input', function () {
      this.value = (this.value || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
    });

    atualizarTextosTipoPessoa();
    aplicarMascaraDocumento();
  });
})();
