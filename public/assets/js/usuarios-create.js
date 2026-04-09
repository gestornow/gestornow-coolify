/**
 * Script para Criação de Usuário com Upload de Foto e Anexos
 */
(function() {
    'use strict';

    const API_BASE_URL = 'https://api.gestornow.com';

    // Armazenar arquivos temporários
    let fotoSelecionada = null;
    let anexosSelecionados = [];

    /**
     * Comprimir imagem no lado do cliente antes de enviar
     */
    function comprimirImagem(file, maxWidth = 1920, maxHeight = 1920, quality = 0.85) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function(blob) {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Falha ao comprimir imagem'));
                        }
                    }, 'image/jpeg', quality);
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Realizar uploads após criar usuário
     */
    async function realizarUploads(usuarioId, idEmpresa) {
        const uploads = [];

        console.log('📤 Iniciando uploads...', { usuarioId, idEmpresa, foto: !!fotoSelecionada, anexos: anexosSelecionados.length });

        // Upload da foto se houver
        if (fotoSelecionada) {
            const fotoPromise = new Promise(async (resolve, reject) => {
                try {
                    console.log('📷 Comprimindo foto...');
                    const imagemComprimida = await comprimirImagem(fotoSelecionada, 1920, 1920, 0.85);
                    
                    const formData = new FormData();
                    const nomeArquivo = fotoSelecionada.name.split('.')[0];
                    const novoFile = new File([imagemComprimida], `${nomeArquivo}.jpg`, { type: 'image/jpeg' });
                    
                    formData.append('file', novoFile);
                    formData.append('idEmpresa', idEmpresa);
                    formData.append('idUsuario', usuarioId);
                    formData.append('nomeImagemUsuario', nomeArquivo);

                    console.log('📤 Enviando foto para API...');

                    $.ajax({
                        url: `${API_BASE_URL}/api/usuarios/imagens`,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhrFields: { withCredentials: false },
                        success: function(response) {
                            console.log('✓ Foto enviada:', response);
                            
                            // Atualizar foto_url no banco
                            $.ajax({
                                url: `/usuarios/${usuarioId}`,
                                method: 'PUT',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    foto_url: response.data.file.url,
                                    foto_filename: response.data.file.filename
                                },
                                success: () => {
                                    console.log('✓ Foto atualizada no banco');
                                    resolve();
                                },
                                error: () => {
                                    console.warn('⚠ Erro ao atualizar foto no banco');
                                    resolve();
                                }
                            });
                        },
                        error: (xhr) => {
                            console.error('✗ Erro ao enviar foto:', xhr);
                            reject(xhr);
                        }
                    });
                } catch (error) {
                    console.error('✗ Erro ao processar foto:', error);
                    reject(error);
                }
            });
            uploads.push(fotoPromise);
        }

        // Upload dos anexos se houver
        if (anexosSelecionados.length > 0) {
            console.log(`📎 Preparando ${anexosSelecionados.length} anexo(s)...`);
            
            anexosSelecionados.forEach((anexo, index) => {
                const anexoPromise = new Promise((resolve, reject) => {
                    console.log(`📤 Enviando anexo ${index + 1}/${anexosSelecionados.length}: ${anexo.nome}`);
                    
                    const formData = new FormData();
                    formData.append('file', anexo.file);
                    formData.append('idEmpresa', idEmpresa);
                    formData.append('idUsuario', usuarioId);
                    formData.append('nomeAnexoUsuario', anexo.nome);

                    $.ajax({
                        url: `${API_BASE_URL}/api/usuarios/anexos`,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhrFields: { withCredentials: false },
                        success: function(response) {
                            console.log(`✓ Anexo ${index + 1} enviado:`, response);
                            resolve();
                        },
                        error: function(xhr) {
                            console.error(`✗ Erro ao enviar anexo ${index + 1}:`, xhr);
                            reject(xhr);
                        }
                    });
                });
                uploads.push(anexoPromise);
            });
        }

        if (uploads.length === 0) {
            console.log('ℹ Nenhum arquivo para upload');
            return Promise.resolve();
        }

        return Promise.all(uploads);
    }

    // Inicialização
    $(document).ready(function() {
        console.log('🔧 Inicializando usuarios-create.js');

        // CEP autocomplete (logradouro + bairro) no cadastro de usuarios
        const $cep = $('#cep');
        if ($cep.length && !$cep.data('cepAutofillUserBound')) {
            $cep.data('cepAutofillUserBound', true);

            const aplicarDadosCep = (data) => {
                if (!data || data.erro) {
                    return;
                }

                const endereco = data.logradouro || data.endereco || '';
                const bairro = data.bairro || '';

                if ($('#endereco').length && endereco) {
                    $('#endereco').val(endereco);
                }

                if ($('#bairro').length && bairro) {
                    $('#bairro').val(bairro);
                }
            };

            const buscarCepUsuario = async (cep) => {
                try {
                    if (window.utils && typeof window.utils.lookupCEP === 'function') {
                        const dataUtils = await window.utils.lookupCEP(cep);
                        aplicarDadosCep(dataUtils);
                        return;
                    }
                } catch (e) {
                    // fallback para endpoint do ViaCEP
                }

                $.ajax({
                    url: `https://viacep.com.br/ws/${cep}/json/`,
                    dataType: 'json',
                    success: aplicarDadosCep,
                });
            };

            const consultarCepSeCompleto = () => {
                const cep = String($cep.val() || '').replace(/\D/g, '');
                if (cep.length !== 8) {
                    return;
                }

                if ($cep.data('ultimoCepConsultado') === cep) {
                    return;
                }

                $cep.data('ultimoCepConsultado', cep);
                buscarCepUsuario(cep);
            };

            $cep.on('blur', consultarCepSeCompleto);
            $cep.on('change', consultarCepSeCompleto);
            $cep.on('keyup', function() {
                if (String($(this).val() || '').replace(/\D/g, '').length === 8) {
                    consultarCepSeCompleto();
                }
            });
        }

        // Preview da foto ao selecionar
        $('#fotoUpload').on('change', function(e) {
            const file = e.target.files[0];
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
                    return;
                }

                // Armazenar arquivo
                fotoSelecionada = file;

                // Preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#fotoPreview').html(`
                        <img src="${e.target.result}" alt="Foto do Usuário" class="rounded-circle" 
                             style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    `);
                };
                reader.readAsDataURL(file);
            }
        });

        // Habilitar/desabilitar botão de adicionar anexo
        function verificarCamposAnexo() {
            const temArquivo = $('#anexoUpload')[0].files.length > 0;
            const temNome = $('#nomeAnexo').val().trim() !== '';
            $('#btnAdicionarAnexo').prop('disabled', !(temArquivo && temNome));
        }

        $('#anexoUpload').on('change', verificarCamposAnexo);
        $('#nomeAnexo').on('input', verificarCamposAnexo);

        // Adicionar anexo à lista ao clicar no botão
        $('#btnAdicionarAnexo').on('click', function() {
            const file = $('#anexoUpload')[0].files[0];
            const nomeDoc = $('#nomeAnexo').val().trim();
            
            if (!file || !nomeDoc) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos obrigatórios',
                    text: 'Selecione um arquivo e digite um nome para o documento',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Validar tamanho (20MB)
            if (file.size > 20 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Arquivo muito grande',
                    text: 'O anexo deve ter no máximo 20MB',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Validar tipo
            const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!validTypes.includes(file.type) && !file.name.match(/\.(pdf|doc|docx)$/i)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Formato inválido',
                    text: 'Use apenas PDF, DOC ou DOCX',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Adicionar à lista temporária
            anexosSelecionados.push({
                file: file,
                nome: nomeDoc
            });

            // Atualizar UI
            renderizarAnexosTemporarios();

            // Limpar campos
            $('#anexoUpload').val('');
            $('#nomeAnexo').val('');
            verificarCamposAnexo();

            // Feedback
            Swal.fire({
                icon: 'success',
                title: 'Anexo adicionado!',
                text: `"${nomeDoc}" foi adicionado à lista`,
                timer: 1500,
                showConfirmButton: false
            });
        });

        // Renderizar lista de anexos temporários
        function renderizarAnexosTemporarios() {
            const $lista = $('#listaAnexosTemp');
            
            if (anexosSelecionados.length === 0) {
                $lista.html('<p class="text-muted small mb-0">Nenhum anexo selecionado</p>');
                return;
            }

            let html = '<div class="list-group">';
            anexosSelecionados.forEach((anexo, index) => {
                const extensao = anexo.file.name.split('.').pop().toUpperCase();
                const tamanho = (anexo.file.size / (1024 * 1024)).toFixed(2);
                
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-label-primary me-2">${extensao}</span>
                            <div>
                                <div class="fw-semibold">${anexo.nome}</div>
                                <small class="text-muted">${tamanho} MB</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.removerAnexoTemp(${index})" title="Remover">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                `;
            });
            html += '</div>';

            $lista.html(html);
        }

        // Remover anexo temporário
        window.removerAnexoTemp = function(index) {
            anexosSelecionados.splice(index, 1);
            renderizarAnexosTemporarios();
        };

        // Interceptar submit do formulário para criar usuário via AJAX
        $('#userCreateForm').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('#userCreateSubmit');
            
            // Validação básica
            const login = $form.find('input[name="login"]').val();
            const nome = $form.find('input[name="nome"]').val();
            
            if (!login || !nome) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção!',
                    text: 'Login e Nome são obrigatórios.',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Desabilita botão
            $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Cadastrando...');
            
            // Enviar via AJAX
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(response) {
                    console.log('✓ Usuário criado:', response);
                    
                    // Pegar o ID do usuário criado
                    const usuarioId = response.usuario_id || response.id_usuario;
                    const idEmpresa = $('#empresaId').val();
                    
                    if (usuarioId && (fotoSelecionada || anexosSelecionados.length > 0)) {
                        // Atualizar campos hidden
                        $('#userId').val(usuarioId);
                        
                        // Fazer uploads
                        realizarUploads(usuarioId, idEmpresa).then(() => {
                            console.log('✓ Todos os uploads concluídos');
                            Swal.fire({
                                icon: 'success',
                                title: 'Sucesso!',
                                text: 'Usuário cadastrado com sucesso!',
                                confirmButtonText: 'OK',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                window.location.href = '/usuarios';
                            });
                        }).catch(error => {
                            console.error('✗ Erro em alguns uploads:', error);
                            Swal.fire({
                                icon: 'warning',
                                title: 'Usuário criado!',
                                html: 'Usuário cadastrado, mas houve erro ao enviar foto/anexos.<br>Você pode adicioná-los na edição.',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = '/usuarios';
                            });
                        });
                    } else {
                        // Sem uploads, redirecionar direto
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: 'Usuário cadastrado com sucesso!',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = '/usuarios';
                        });
                    }
                },
                error: function(xhr) {
                    console.error('✗ Erro ao criar usuário:', xhr);
                    
                    $submitBtn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Cadastrar Usuário');
                    
                    let errorMsg = 'Erro ao criar usuário';
                    
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            let errorsHtml = '<ul class="text-start mb-0">';
                            Object.keys(xhr.responseJSON.errors).forEach(function(key) {
                                xhr.responseJSON.errors[key].forEach(function(error) {
                                    errorsHtml += '<li>' + error + '</li>';
                                });
                            });
                            errorsHtml += '</ul>';
                            errorMsg = errorsHtml;
                        } else if (xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        html: errorMsg,
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    });

})();
