/**
 * Script para página de listagem de usuários
 * Gerencia checkboxes, exclusão múltipla e feedback visual
 */
(function() {
    'use strict';

    $(document).ready(function() {
        // Elementos
        const $checkAll = $('#checkAll');
        const $userCheckboxes = $('.user-checkbox');
        const $btnExcluir = $('#btnExcluirSelecionados');
        const $countSelecionados = $('#countSelecionados');

        // Função para atualizar o botão
        function atualizarBotao() {
            const selecionados = $('.user-checkbox:checked').length;
            
            if (selecionados > 0) {
                $btnExcluir.show();
                $countSelecionados.text(selecionados);
            } else {
                $btnExcluir.hide();
                $countSelecionados.text('0');
            }
        }

        // Evento: Selecionar todos
        $checkAll.on('change', function() {
            $userCheckboxes.prop('checked', this.checked);
            atualizarBotao();
        });

        // Evento: Checkbox individual
        $userCheckboxes.on('change', function() {
            const total = $userCheckboxes.length;
            const marcados = $userCheckboxes.filter(':checked').length;
            
            // Atualizar checkbox "selecionar todos"
            $checkAll.prop('checked', marcados === total && total > 0);
            
            // Atualizar botão
            atualizarBotao();
        });

        // Evento: Botão de excluir
        $btnExcluir.on('click', function() {
            const ids = [];
            $userCheckboxes.filter(':checked').each(function() {
                ids.push(parseInt($(this).val()));
            });
            
            if (ids.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Aviso',
                    text: 'Nenhum usuário selecionado',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Confirmação
            Swal.fire({
                title: 'Tem certeza?',
                html: `Você vai excluir <strong>${ids.length}</strong> usuário(s). Isso não pode ser desfeito!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Obter URL e CSRF token dos meta tags ou data attributes
                    const url = $btnExcluir.data('url') || '/usuarios/excluir-multiplos';
                    const csrfToken = $('meta[name="csrf-token"]').attr('content');
                    
                    // Loading
                    Swal.fire({
                        title: 'Processando...',
                        html: 'Excluindo usuários...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // AJAX
                    $.ajax({
                        url: url,
                        method: 'POST',
                        contentType: 'application/json',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        data: JSON.stringify({ ids: ids }),
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: response.message || 'Usuários excluídos com sucesso',
                                confirmButtonText: 'OK',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                window.location.reload();
                            });
                        },
                        error: function(xhr, status, error) {
                            let msg = 'Erro ao excluir usuários';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                msg = response.message || msg;
                            } catch(e) {
                                // Ignora erro de parse
                            }
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro!',
                                text: msg,
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        });

        // Inicializar
        atualizarBotao();
    });

})();
