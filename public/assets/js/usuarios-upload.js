/**
 * Script para Upload de Foto e Anexos de Usuários
 * Integração com API Node.js
 */
(function() {
    'use strict';

    // Configuração da API
    // Para desenvolvimento local: 'http://localhost:3000'
    // Para produção: 'https://api.gestornow.com' ou o IP/subdomínio do servidor
    const API_BASE_URL = 'https://api.gestornow.com';
    const API_USUARIOS_FOTO = `${API_BASE_URL}/api/usuarios/imagens`;
    const API_USUARIOS_ANEXOS = `${API_BASE_URL}/api/usuarios/anexos`;
    
    // Dados do usuário (pegar do data attribute ou hidden field)
    let idUsuario = document.getElementById('userId').value;
    let idEmpresa = document.getElementById('empresaId').value;
    let picture = null;


    $(document).ready(function() {
        // Pegar IDs dos meta tags ou data attributes

        // Inicializar
        carregarFotoAtual();
        carregarAnexos();
        
        // ==================== UPLOAD DE FOTO ====================
        
        // Preview da foto ao selecionar
        $('#fotoUpload').on('change', function(e) {
            const file = e.target.files[0];
            picture = file;
            if (file) {
                // Validar tamanho (10MB)
                if (file.size > 10 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Arquivo muito grande',
                        text: 'A foto deve ter no máximo 10MB',
                        confirmButtonText: 'OK'
                    });
                    $(this).val('');
                    $('#btnUploadFoto').prop('disabled', true);
                    return;
                }

                // Validar tipo
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Formato inválido',
                        text: 'Use apenas JPG, PNG ou WEBP',
                        confirmButtonText: 'OK'
                    });
                    $(this).val('');
                    $('#btnUploadFoto').prop('disabled', true);
                    return;
                }

                // Preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#fotoPreview').html(`
                        <img src="${e.target.result}" alt="Preview" 
                             class="rounded-circle" 
                             style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    `);
                };
                reader.readAsDataURL(file);

                $('#btnUploadFoto').prop('disabled', false);
            } else {
                $('#btnUploadFoto').prop('disabled', true);
            }
        });

        // Upload da foto
        $('#btnUploadFoto').on('click', function() {
            const file = picture || $('#fotoUpload')[0].files[0];
            if (!file || !idUsuario || !idEmpresa) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Selecione uma foto e certifique-se de que os dados do usuário estão corretos',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Preparar FormData
            const formData = new FormData();
            formData.append('file', file);
            formData.append('idEmpresa', idEmpresa);
            formData.append('idUsuario', idUsuario);
            formData.append('nomeImagemUsuario', file.name.split('.')[0]);

            // Loading
            Swal.fire({
                title: 'Enviando foto...',
                html: 'Por favor aguarde',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Upload via AJAX
            $.ajax({
                url: API_USUARIOS_FOTO,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('✓ Foto enviada:', response);
                    
                    // Verificar se houve cleanup de arquivos antigos
                    if (response.data.cleanup && response.data.cleanup.deletedCount > 0) {
                        console.log('✓ Arquivos antigos removidos:', response.data.cleanup.deletedFiles);
                    }
                    
                    // Atualizar foto_url no banco via Laravel
                    // Usar a URL completa retornada pela API
                    atualizarFotoUsuario(response.data.file.url, response.data.file.filename);
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao enviar foto',
                        text: xhr.responseJSON?.message || 'Erro desconhecido',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });

        // Deletar foto
        $('#btnDeletarFoto').on('click', function() {
            Swal.fire({
                title: 'Tem certeza?',
                text: 'Deseja remover a foto do perfil?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    deletarFotoUsuario();
                }
            });
        });

        // ==================== UPLOAD DE ANEXOS ====================
        
        // Habilitar botão ao selecionar anexo
        $('#anexoUpload').on('change', function() {
            const file = $(this)[0].files[0];
            if (file) {
                // Validar tamanho (20MB)
                if (file.size > 20 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Arquivo muito grande',
                        text: 'O anexo deve ter no máximo 20MB',
                        confirmButtonText: 'OK'
                    });
                    $(this).val('');
                    $('#btnUploadAnexo').prop('disabled', true);
                    return;
                }

                // Auto-preencher nome se vazio
                if (!$('#nomeAnexo').val()) {
                    $('#nomeAnexo').val(file.name.split('.')[0]);
                }

                $('#btnUploadAnexo').prop('disabled', false);
            } else {
                $('#btnUploadAnexo').prop('disabled', true);
            }
        });

        // Upload de anexo
        $('#btnUploadAnexo').on('click', function() {
            const file = $('#anexoUpload')[0].files[0];
            const nomeAnexo = $('#nomeAnexo').val().trim();

            if (!file || !idUsuario || !idEmpresa) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Selecione um arquivo',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (!nomeAnexo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Nome obrigatório',
                    text: 'Digite um nome para o documento',
                    confirmButtonText: 'OK'
                });
                $('#nomeAnexo').focus();
                return;
            }

            // Preparar FormData
            const formData = new FormData();
            formData.append('file', file);
            formData.append('idEmpresa', idEmpresa);
            formData.append('idUsuario', idUsuario);
            formData.append('nomeAnexoUsuario', nomeAnexo);

            // Loading
            Swal.fire({
                title: 'Enviando anexo...',
                html: 'Por favor aguarde',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Upload via AJAX
            $.ajax({
                url: API_USUARIOS_ANEXOS,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('✓ Anexo enviado:', response);
                    
                    // Extrair informações do arquivo enviado
                    const arquivo = response.data.file;
                    console.log(`✓ Arquivo salvo como: ${arquivo.filename}`);
                    console.log(`✓ Tamanho: ${(arquivo.size / 1024 / 1024).toFixed(2)} MB`);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Anexo enviado!',
                        text: 'Documento adicionado com sucesso',
                        confirmButtonText: 'OK',
                        timer: 2000,
                        timerProgressBar: true
                    });

                    // Limpar campos
                    $('#anexoUpload').val('');
                    $('#nomeAnexo').val('');
                    $('#btnUploadAnexo').prop('disabled', true);

                    // Recarregar lista
                    carregarAnexos();
                },
                error: function(xhr, status, error) {
                    console.error('✗ Erro ao enviar anexo:', xhr);
                    
                    let mensagemErro = 'Erro desconhecido';
                    
                    if (xhr.status === 413) {
                        mensagemErro = 'Arquivo muito grande. O limite é 20MB.';
                    } else if (xhr.status === 0 || xhr.statusText === 'error') {
                        mensagemErro = 'Erro de conexão. Verifique se a API está acessível.';
                    } else if (xhr.responseJSON?.message) {
                        mensagemErro = xhr.responseJSON.message;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao enviar anexo',
                        text: mensagemErro,
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    });

    // ==================== FUNÇÕES AUXILIARES ====================

    /**
     * Carregar foto atual do usuário
     */
    let fotoUrl = '';
    function carregarFotoAtual() {
        if (!idEmpresa || !idUsuario) {
            console.warn('IDs não disponíveis para carregar foto');
            exibirFotoPadrao();
            return;
        }

        $.ajax({
            url: `${API_USUARIOS_FOTO}/${idEmpresa}?idUsuario=${idUsuario}`,
            method: 'GET',
            success: function(response) {
                console.log('✓ Fotos carregadas:', response);
                
                // A API já retorna filtrado pelo idUsuario através do query param
                if (response.data.files && response.data.files.length > 0) {
                    const fotoUsuario = response.data.files[0]; // Primeira foto (deve haver apenas uma)
                    
                    // Exibir foto existente usando a URL completa retornada pela API
                    $('#fotoPreview').html(`
                        <img src="${fotoUsuario.url}" 
                             alt="Foto do usuário" 
                             class="rounded-circle" 
                             style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    `);
                    
                    // Mostrar botão de deletar
                    $('#btnDeletarFoto').show();
                    
                    // Atualizar meta tag e hidden input
                    $('meta[name="foto-filename"]').attr('content', fotoUsuario.name);
                    $('#fotoFilename').val(fotoUsuario.name);
                } else {
                    // Exibir placeholder padrão
                    exibirFotoPadrao();
                }
            },
            error: function(xhr) {
                console.error('✗ Erro ao carregar foto:', xhr);
                exibirFotoPadrao();
            }
        });
    }

    /**
     * Exibir foto padrão (avatar com iniciais)
     */
    function exibirFotoPadrao() {
        const nomeUsuario = $('input[name="nome"]').val() || 'Usuario';
        const iniciais = nomeUsuario.split(' ')
            .map(n => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();

        $('#fotoPreview').html(`
            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                 style="width: 120px; height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 36px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                ${iniciais}
            </div>
        `);
        
        $('#btnDeletarFoto').hide();
    }

    /**
     * Atualizar foto_url no banco de dados via Laravel
     * @param {string} fotoUrl - URL completa da foto (ex: "/uploads/usuarios/imagens/1/usuarios_foto_151_1_abc.jpg")
     * @param {string} filename - Nome técnico do arquivo (ex: "usuarios_foto_151_1_abc.jpg")
     */
    function atualizarFotoUsuario(fotoUrl, filename) {
        console.log('Atualizando foto no banco:', { fotoUrl, filename });
        
        $.ajax({
            url: `/usuarios/${idUsuario}`,
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: {
                foto_url: fotoUrl,
                foto_filename: filename,
                _method: 'PUT'
            },
            success: function(response) {
                console.log('✓ Foto atualizada no banco:', response);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Foto atualizada!',
                    text: 'A foto do perfil foi atualizada com sucesso',
                    confirmButtonText: 'OK',
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    location.reload();
                });
            },
            error: function(xhr) {
                console.error('✗ Erro ao atualizar foto no banco:', xhr);
                Swal.fire({
                    icon: 'warning',
                    title: 'Foto enviada',
                    text: 'A foto foi enviada mas houve um erro ao salvar no banco de dados',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    /**
     * Deletar foto do usuário
     */
    function deletarFotoUsuario() {
        const fotoFilename = $('meta[name="foto-filename"]').attr('content') || $('#fotoFilename').val();
        
        if (!fotoFilename || !idEmpresa) {
            console.error('Dados insuficientes para deletar foto');
            return;
        }

        Swal.fire({
            title: 'Deletando foto...',
            html: 'Por favor aguarde',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Deletar da API Node.js
        $.ajax({
            url: `${API_USUARIOS_FOTO}/${idEmpresa}/${fotoFilename}`,
            method: 'DELETE',
            success: function() {
                // Atualizar banco de dados Laravel
                $.ajax({
                    url: `/usuarios/${idUsuario}`,
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        foto_url: null,
                        foto_filename: null,
                        _method: 'PUT'
                    },
                    success: function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Foto removida!',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            location.reload();
                        });
                    }
                });
            },
            error: function(xhr) {
                console.error('Erro ao deletar foto:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro ao deletar foto',
                    text: xhr.responseJSON?.message || 'Erro desconhecido',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    /**
     * Carregar lista de anexos
     */
    function carregarAnexos() {
        if (!idEmpresa || !idUsuario) {
            console.warn('IDs não disponíveis para carregar anexos');
            return;
        }

        $.ajax({
            url: `${API_USUARIOS_ANEXOS}/${idEmpresa}?idUsuario=${idUsuario}`,
            method: 'GET',
            success: function(response) {
                console.log('✓ Anexos carregados:', response);
                renderizarAnexos(response.data.files || []);
            },
            error: function(xhr) {
                console.error('✗ Erro ao carregar anexos:', xhr);
                
                if (xhr.status === 0) {
                    $('#listaAnexos').html('<div class="alert alert-warning small" role="alert"><i class="ti ti-alert-triangle me-2"></i>Não foi possível conectar à API. Verifique se o servidor está rodando.</div>');
                } else {
                    $('#listaAnexos').html('<p class="text-muted small">Erro ao carregar anexos</p>');
                }
            }
        });
    }

    /**
     * Renderizar lista de anexos
     */
    function renderizarAnexos(anexos) {
        const $lista = $('#listaAnexos');
        
        if (!anexos || anexos.length === 0) {
            $lista.html('<p class="text-muted small">Nenhum anexo encontrado</p>');
            return;
        }

        let html = '<div class="list-group">';
        anexos.forEach(anexo => {
            // Usar 'name' como filename técnico para operações de delete
            const filename = anexo.name;
            const extensao = filename.split('.').pop().toUpperCase();
            const tamanho = anexo.sizeInMB || (anexo.size / (1024 * 1024)).toFixed(2);
            
            // Extrair nome descritivo do filename (formato: usuarios_[nome]_[id]_[id]_[uuid].ext)
            let nomeExibicao = filename;
            const match = filename.match(/usuarios_(.+?)_\d+_\d+_[a-f0-9]+\./);
            if (match) {
                nomeExibicao = match[1].replace(/_/g, ' ');
            }
            
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-label-primary me-2">${extensao}</span>
                        <div>
                            <div class="fw-semibold">${nomeExibicao}</div>
                            <small class="text-muted">${tamanho} MB</small>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <a href="${anexo.url}" target="_blank" class="btn btn-outline-primary btn-sm" title="Visualizar">
                            <i class="ti ti-eye"></i>
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deletarAnexo('${filename}')" title="Deletar">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        $lista.html(html);
    }

    /**
     * Deletar anexo
     */
    window.deletarAnexo = function(filename) {
        Swal.fire({
            title: 'Tem certeza?',
            text: 'Deseja deletar este anexo?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, deletar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Deletando...',
                    html: 'Por favor aguarde',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: `${API_USUARIOS_ANEXOS}/${idEmpresa}/${filename}`,
                    method: 'DELETE',
                    success: function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Anexo deletado!',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            timerProgressBar: true
                        });
                        carregarAnexos();
                    },
                    error: function(xhr) {
                        console.error('Erro ao deletar anexo:', xhr);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro ao deletar',
                            text: xhr.responseJSON?.message || 'Erro desconhecido',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    };

})();
