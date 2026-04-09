/**
 * Script para Upload de Foto e Anexos de Produtos
 * Integração com API Node.js
 */
(function() {
    'use strict';

    // Configuração da API
    const API_BASE_URL = 'https://api.gestornow.com';
    const API_PRODUTOS_FOTO = `${API_BASE_URL}/api/produtos/imagens`;
    const API_PRODUTOS_ANEXOS = `${API_BASE_URL}/api/produtos/anexos`;
    const API_PRODUTOS_ANEXOS_UPLOAD_CANDIDATES = [
        `${API_BASE_URL}/api/produtos/anexos`,
        `${API_BASE_URL}/uploads/produtos/anexos`
    ];

    function normalizarUrlFotoProduto(url, filename, empresaId) {
        if (!url && filename) {
            return `${API_BASE_URL}/uploads/produtos/imagens/${empresaId}/${filename}`;
        }

        if (!url) {
            return null;
        }

        let finalUrl = String(url).trim();
        if (!finalUrl.startsWith('http')) {
            finalUrl = API_BASE_URL + '/' + finalUrl.replace(/^\//, '');
        }

        finalUrl = finalUrl.replace('/api/produtos/imagens/', '/uploads/produtos/imagens/');
        finalUrl = finalUrl.replace('/produtos/imagens/', '/uploads/produtos/imagens/');

        return finalUrl;
    }

    function normalizarUrlAnexoProduto(url, filename, empresaId) {
        if (!url && filename) {
            return `${API_BASE_URL}/uploads/produtos/anexos/${empresaId}/${filename}`;
        }

        if (!url) {
            return null;
        }

        let finalUrl = String(url).trim();
        if (!finalUrl.startsWith('http')) {
            finalUrl = API_BASE_URL + '/' + finalUrl.replace(/^\//, '');
        }

        finalUrl = finalUrl.replace('/api/produtos/anexos/', '/uploads/produtos/anexos/');
        finalUrl = finalUrl.replace('/produtos/anexos/', '/uploads/produtos/anexos/');

        return finalUrl;
    }
    
    // Dados do produto (pegar do hidden field)
    let idProduto = null;
    let idEmpresa = null;
    let picture = null;

    $(document).ready(function() {
        // Pegar IDs dos hidden fields
        const produtoIdEl = document.getElementById('produtoId');
        const empresaIdEl = document.getElementById('empresaId');
        
        if (produtoIdEl) idProduto = produtoIdEl.value;
        if (empresaIdEl) idEmpresa = empresaIdEl.value;

        if ((!idEmpresa || String(idEmpresa).trim() === '') && document.querySelector('input[name="id_empresa"]')) {
            idEmpresa = document.querySelector('input[name="id_empresa"]').value;
        }

        if ((!idEmpresa || String(idEmpresa).trim() === '') && document.body && document.body.dataset) {
            idEmpresa = document.body.dataset.idEmpresa || idEmpresa;
        }

        idProduto = idProduto ? String(idProduto).trim() : null;
        idEmpresa = idEmpresa ? String(idEmpresa).trim() : null;

        // Se temos um produto existente, carregar anexos
        if (idProduto && idEmpresa) {
            carregarAnexos();
        }
        
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
                             class="rounded" 
                             style="width: 80px; height: 80px; object-fit: cover;">
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
            if (!file) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Selecione uma foto',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (!idProduto || !idEmpresa) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Salve o produto primeiro',
                    text: 'Para adicionar uma foto, você precisa salvar o produto primeiro.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Preparar FormData
            const formData = new FormData();
            formData.append('file', file);
            formData.append('idEmpresa', idEmpresa);
            formData.append('idProduto', idProduto);
            formData.append('nomeImagemProduto', file.name.split('.')[0]);

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
                url: API_PRODUTOS_FOTO,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('✓ Foto enviada - Resposta completa:', response);
                    
                    // Pegar dados da resposta
                    let fotoUrl = response.data?.file?.url;
                    let filename = response.data?.file?.filename;

                    fotoUrl = normalizarUrlFotoProduto(fotoUrl, filename, idEmpresa);
                    
                    console.log('✓ URL final:', fotoUrl);
                    console.log('✓ Filename:', filename);
                    
                    // Atualizar foto_url no banco via Laravel
                    atualizarFotoProduto(fotoUrl, filename);
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
                text: 'Deseja remover a foto do produto?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, remover',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    deletarFotoProduto();
                }
            });
        });

        // ==================== UPLOAD DE ANEXOS ====================
        
        // Upload de anexo ao selecionar
        $('#inputAnexo').on('change', function() {
            const file = $(this)[0].files[0];
            if (file) {
                const extensao = (file.name.split('.').pop() || '').toLowerCase();

                if (extensao === 'xls' || extensao === 'xlsx') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tipo de arquivo não permitido',
                        text: 'Arquivos XLS/XLSX não são permitidos para anexos de produtos.',
                        confirmButtonText: 'OK'
                    });
                    $(this).val('');
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
                    $(this).val('');
                    return;
                }

                if (!idProduto || !idEmpresa) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Salve o produto primeiro',
                        text: 'Para adicionar anexos, você precisa salvar o produto primeiro.',
                        confirmButtonText: 'OK'
                    });
                    $(this).val('');
                    return;
                }

                // Upload direto
                uploadAnexo(file);
            }
        });
    });

    // ==================== FUNÇÕES AUXILIARES ====================

    /**
     * Atualizar foto_url no banco de dados via Laravel
     */
    function atualizarFotoProduto(fotoUrl, filename) {
        console.log('Atualizando foto no banco:', { fotoUrl, filename });
        
        $.ajax({
            url: `/produtos/${idProduto}`,
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            data: {
                foto_url: fotoUrl,
                foto_filename: filename
            },
            success: function(response) {
                console.log('✓ Foto atualizada no banco:', response);
                
                // Atualizar hidden field com filename
                $('#fotoFilename').val(filename);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Foto atualizada!',
                    text: 'A foto do produto foi atualizada com sucesso',
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
                    text: 'A foto foi enviada mas houve um erro ao salvar no banco de dados. Erro: ' + (xhr.responseJSON?.message || xhr.statusText),
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            }
        });
    }

    /**
     * Deletar foto do produto
     */
    function deletarFotoProduto() {
        const fotoFilename = $('#fotoFilename').val();
        
        if (!fotoFilename || !idEmpresa) {
            console.error('Dados insuficientes para deletar foto');
            Swal.close();
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
            url: `${API_PRODUTOS_FOTO}/${idEmpresa}/${fotoFilename}`,
            method: 'DELETE',
            success: function() {
                // Atualizar banco de dados Laravel
                $.ajax({
                    url: `/produtos/${idProduto}`,
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: {
                        foto_url: '',
                        foto_filename: ''
                    },
                    complete: function() {
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
     * Upload de anexo
     */
    function uploadAnexo(file) {
        const nomeAnexo = file.name.split('.')[0];
        const extensaoArquivo = (file.name.split('.').pop() || '').toLowerCase();
        const isExcel = extensaoArquivo === 'xls' || extensaoArquivo === 'xlsx';

        function montarFormDataAnexo() {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('idEmpresa', idEmpresa);
            formData.append('idProduto', idProduto);
            formData.append('nomeAnexoProduto', nomeAnexo);
            return formData;
        }

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

        function obterMensagemErroUpload(xhr) {
            let mensagemErro = 'Erro desconhecido';

            if (xhr.status === 413) {
                mensagemErro = 'Arquivo muito grande. O limite é 20MB.';
            } else if (xhr.status === 0 || xhr.statusText === 'error') {
                mensagemErro = 'Erro de conexão. Verifique se a API está acessível.';
            } else if (xhr.responseJSON?.message) {
                mensagemErro = xhr.responseJSON.message;
            } else if (xhr.responseJSON?.error) {
                mensagemErro = typeof xhr.responseJSON.error === 'string'
                    ? xhr.responseJSON.error
                    : (xhr.responseJSON.error.message || JSON.stringify(xhr.responseJSON.error));
            } else if (xhr.responseText) {
                mensagemErro = xhr.responseText.length > 180
                    ? `${xhr.responseText.substring(0, 180)}...`
                    : xhr.responseText;
            }

            if (isExcel && xhr.status === 400 && /Tipo de arquivo não permitido/i.test(mensagemErro)) {
                mensagemErro = 'A API atual rejeitou XLS/XLSX neste endpoint. É necessário liberar os MIME types application/vnd.ms-excel e application/vnd.openxmlformats-officedocument.spreadsheetml.sheet na API de anexos.';
            }

            return mensagemErro;
        }

        function enviarTentativa(indice) {
            const endpoint = API_PRODUTOS_ANEXOS_UPLOAD_CANDIDATES[indice];
            if (!endpoint) {
                return;
            }

            $.ajax({
                url: endpoint,
                method: 'POST',
                data: montarFormDataAnexo(),
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('✓ Anexo enviado:', response);

                    Swal.fire({
                        icon: 'success',
                        title: 'Anexo enviado!',
                        text: 'Documento adicionado com sucesso',
                        confirmButtonText: 'OK',
                        timer: 2000,
                        timerProgressBar: true
                    });

                    $('#inputAnexo').val('');
                    carregarAnexos();
                },
                error: function(xhr) {
                    console.error(`✗ Erro ao enviar anexo (${endpoint}):`, xhr);

                    const deveTentarProximo = !isExcel && (xhr.status === 404 || xhr.status === 405) && indice < API_PRODUTOS_ANEXOS_UPLOAD_CANDIDATES.length - 1;
                    if (deveTentarProximo) {
                        enviarTentativa(indice + 1);
                        return;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao enviar anexo',
                        text: `${obterMensagemErroUpload(xhr)} (HTTP ${xhr.status || '-'} em ${endpoint})`,
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        enviarTentativa(0);
    }

    /**
     * Carregar lista de anexos
     */
    function carregarAnexos() {
        if (!idEmpresa || !idProduto) {
            console.warn('IDs não disponíveis para carregar anexos');
            return;
        }

        $.ajax({
            url: `${API_PRODUTOS_ANEXOS}/${idEmpresa}?idProduto=${idProduto}`,
            method: 'GET',
            success: function(response) {
                console.log('✓ Anexos carregados:', response);
                renderizarAnexos(response.data.files || []);
            },
            error: function(xhr) {
                console.error('✗ Erro ao carregar anexos:', xhr);
                
                if (xhr.status === 0) {
                    $('#tabelaAnexos tbody').html('<tr><td colspan="5" class="text-center py-4"><div class="text-warning"><i class="ti ti-alert-triangle ti-lg d-block mb-2"></i><p class="mb-0">Não foi possível conectar à API</p></div></td></tr>');
                }
            }
        });
    }

    /**
     * Renderizar lista de anexos na tabela
     */
    function renderizarAnexos(anexos) {
        const $tbody = $('#tabelaAnexos tbody');
        
        if (!anexos || anexos.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="text-muted">
                            <i class="ti ti-files-off ti-lg d-block mb-2"></i>
                            <p class="mb-0">Nenhum arquivo anexado</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        anexos.forEach(anexo => {
            const filename = anexo.name;
            const extensao = filename.split('.').pop().toUpperCase();
            const tamanho = anexo.sizeInMB ? `${anexo.sizeInMB} MB` : (anexo.size ? `${(anexo.size / (1024 * 1024)).toFixed(2)} MB` : '-');
            const anexoUrl = normalizarUrlAnexoProduto(anexo.url, filename, idEmpresa);
            const extensoesSemPreview = ['XLS', 'XLSX'];
            const podeVisualizar = !extensoesSemPreview.includes(extensao);
            
            // Extrair nome descritivo
            let nomeExibicao = filename;
            const match = filename.match(/produto_(.+?)_\d+_\d+_[a-f0-9]+\./);
            if (match) {
                nomeExibicao = match[1].replace(/_/g, ' ');
            }
            
            // Data de modificação
            const dataUpload = anexo.modified ? new Date(anexo.modified).toLocaleDateString('pt-BR') : '-';
            
            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-label-primary me-2">${extensao}</span>
                            <span>${nomeExibicao}</span>
                        </div>
                    </td>
                    <td>${extensao}</td>
                    <td>${tamanho}</td>
                    <td>${dataUpload}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            ${podeVisualizar ? `<a href="${anexoUrl || '#'}" target="_blank" class="btn btn-outline-primary ${anexoUrl ? '' : 'disabled'}" title="Visualizar"><i class="ti ti-eye"></i></a>` : ''}
                            <button type="button" class="btn btn-outline-success ${anexoUrl ? '' : 'disabled'}" onclick="baixarAnexoProduto('${anexoUrl || ''}', '${filename.replace(/'/g, "\\'")}')" title="Download">
                                <i class="ti ti-download"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="deletarAnexoProduto('${filename}')" title="Deletar">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    window.baixarAnexoProduto = function(url, filename) {
        if (!url) {
            Swal.fire({
                icon: 'error',
                title: 'Erro no download',
                text: 'URL do arquivo não encontrada.',
                confirmButtonText: 'OK'
            });
            return;
        }

        fetch(url, { method: 'GET' })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error(`Falha no download (${response.status})`);
                }
                return response.blob();
            })
            .then(function(blob) {
                const blobUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = filename || 'arquivo';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(function(err) {
                console.error('Erro ao baixar anexo:', err);
                // Fallback: abre link direto caso fetch falhe por CORS/política do servidor
                const a = document.createElement('a');
                a.href = url;
                a.setAttribute('download', filename || 'arquivo');
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
    };

    /**
     * Deletar anexo do produto
     */
    window.deletarAnexoProduto = function(filename) {
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
                    url: `${API_PRODUTOS_ANEXOS}/${idEmpresa}/${filename}`,
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
