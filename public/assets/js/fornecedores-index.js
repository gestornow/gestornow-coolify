(function () {
  'use strict';

  $(document).ready(function () {
    var $checkAll = $('#checkAll');
    var $checks = $('.check-item');
    var $btnExcluirSelecionados = $('#btnExcluirSelecionados');
    var $countSelecionados = $('#countSelecionados');

    function getSelecionados() {
      var ids = [];
      $('.check-item:checked').each(function () {
        ids.push($(this).val());
      });
      return ids;
    }

    function atualizarEstadoSelecao() {
      var total = $checks.length;
      var marcados = $('.check-item:checked').length;

      if (total > 0 && marcados === total) {
        $checkAll.prop('checked', true);
      } else {
        $checkAll.prop('checked', false);
      }

      $countSelecionados.text(marcados);
      if (marcados > 0) {
        $btnExcluirSelecionados.show();
      } else {
        $btnExcluirSelecionados.hide();
      }
    }

    $checkAll.on('change', function () {
      var marcado = $(this).is(':checked');
      $('.check-item').prop('checked', marcado);
      atualizarEstadoSelecao();
    });

    $(document).on('change', '.check-item', function () {
      atualizarEstadoSelecao();
    });

    $(document).on('submit', '.form-delete-fornecedor', function (e) {
      e.preventDefault();
      var form = this;

      Swal.fire({
        title: 'Excluir fornecedor?',
        text: 'Esta acao nao pode ser desfeita.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });

    $btnExcluirSelecionados.on('click', function () {
      var ids = getSelecionados();
      if (!ids.length) {
        return;
      }

      Swal.fire({
        title: 'Excluir fornecedores selecionados?',
        text: 'Todos os registros selecionados serao removidos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (!result.isConfirmed) {
          return;
        }

        $.ajax({
          url: $btnExcluirSelecionados.data('url'),
          type: 'POST',
          dataType: 'json',
          data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            ids: ids
          },
          success: function (response) {
            if (response && response.success) {
              Swal.fire({
                icon: 'success',
                title: 'Registros excluidos',
                text: response.message || 'Fornecedores removidos com sucesso.',
                timer: 1800,
                showConfirmButton: false
              }).then(function () {
                window.location.reload();
              });
              return;
            }

            Swal.fire({
              icon: 'error',
              title: 'Erro',
              text: (response && response.message) ? response.message : 'Nao foi possivel excluir os fornecedores.'
            });
          },
          error: function () {
            Swal.fire({
              icon: 'error',
              title: 'Erro',
              text: 'Falha ao excluir os fornecedores selecionados.'
            });
          }
        });
      });
    });

    atualizarEstadoSelecao();
  });
})();
