/**
 * Helper para Financeiro - Contas a Pagar
 * Permite criar categorias e bancos inline na página de criação de contas
 * 
 * Endpoints utilizados:
 * POST /financeiro/categorias - Criar categoria
 * POST /financeiro/bancos - Criar banco
 * GET /financeiro/categorias - Listar categorias
 * GET /financeiro/bancos - Listar bancos
 */

class FinanceiroHelper {
    /**
     * Cria uma nova categoria via AJAX
     * @param {Object} data - Dados da categoria {nome, tipo, descricao}
     * @param {Function} callback - Callback de sucesso
     * @param {Function} errorCallback - Callback de erro
     */
    static criarCategoria(data, callback, errorCallback) {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch('/financeiro/categorias', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (callback) callback(result.data);
            } else {
                if (errorCallback) errorCallback(result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao criar categoria:', error);
            if (errorCallback) errorCallback('Erro na requisição');
        });
    }

    /**
     * Cria um novo banco via AJAX
     * @param {Object} data - Dados do banco {nome_banco, agencia, conta, saldo_inicial, observacoes}
     * @param {Function} callback - Callback de sucesso
     * @param {Function} errorCallback - Callback de erro
     */
    static criarBanco(data, callback, errorCallback) {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch('/financeiro/bancos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (callback) callback(result.data);
            } else {
                if (errorCallback) errorCallback(result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao criar banco:', error);
            if (errorCallback) errorCallback('Erro na requisição');
        });
    }

    /**
     * Lista todas as categorias
     * @param {Function} callback - Callback de sucesso
     * @param {Function} errorCallback - Callback de erro
     */
    static listarCategorias(callback, errorCallback) {
        fetch('/financeiro/categorias', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (callback) callback(result.data);
            } else {
                if (errorCallback) errorCallback(result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao listar categorias:', error);
            if (errorCallback) errorCallback('Erro na requisição');
        });
    }

    /**
     * Lista todas os bancos
     * @param {Function} callback - Callback de sucesso
     * @param {Function} errorCallback - Callback de erro
     */
    static listarBancos(callback, errorCallback) {
        fetch('/financeiro/bancos', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (callback) callback(result.data);
            } else {
                if (errorCallback) errorCallback(result.message);
            }
        })
        .catch(error => {
            console.error('Erro ao listar bancos:', error);
            if (errorCallback) errorCallback('Erro na requisição');
        });
    }

    /**
     * Abre um modal para criar categoria
     */
    static abrirModalCategoria() {
        const modal = document.getElementById('modalCategoria');
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'block';
        }
    }

    /**
     * Fecha o modal de criar categoria
     */
    static fecharModalCategoria() {
        const modal = document.getElementById('modalCategoria');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
    }

    /**
     * Abre um modal para criar banco
     */
    static abrirModalBanco() {
        const modal = document.getElementById('modalBanco');
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'block';
        }
    }

    /**
     * Fecha o modal de criar banco
     */
    static fecharModalBanco() {
        const modal = document.getElementById('modalBanco');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
    }

    /**
     * Adiciona uma nova opção ao select de categorias
     */
    static adicionarOpcaoCategoria(id, nome) {
        const select = document.getElementById('id_categoria_contas');
        if (select) {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = nome;
            option.selected = true;
            select.appendChild(option);
            
            // Se usando Select2, atualizar
            if ($.fn.select2) {
                $(select).trigger('change');
            }
        }
    }

    /**
     * Adiciona uma nova opção ao select de bancos
     */
    static adicionarOpcaoBanco(id, nomeBanco, agencia = '', conta = '') {
        const select = document.getElementById('id_bancos');
        if (select) {
            const label = agencia || conta ? `${nomeBanco} (${agencia}/${conta})` : nomeBanco;
            const option = document.createElement('option');
            option.value = id;
            option.textContent = label;
            option.selected = true;
            select.appendChild(option);
            
            // Se usando Select2, atualizar
            if ($.fn.select2) {
                $(select).trigger('change');
            }
        }
    }
}

// Event listeners para formulários de criação
document.addEventListener('DOMContentLoaded', function() {
    // Formulário para criar categoria
    const formCategoria = document.getElementById('formNovaCategoria');
    if (formCategoria) {
        formCategoria.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nome = document.getElementById('nomeCategoria').value;
            const tipo = document.getElementById('tipoCategoria').value;
            const descricao = document.getElementById('descricaoCategoria').value;

            if (!nome.trim()) {
                alert('Por favor, insira o nome da categoria');
                return;
            }

            FinanceiroHelper.criarCategoria(
                { nome, tipo, descricao },
                function(data) {
                    alert('Categoria criada com sucesso!');
                    FinanceiroHelper.adicionarOpcaoCategoria(data.id_categoria_contas, data.nome);
                    FinanceiroHelper.fecharModalCategoria();
                    formCategoria.reset();
                },
                function(erro) {
                    alert('Erro: ' + erro);
                }
            );
        });
    }

    // Formulário para criar banco
    const formBanco = document.getElementById('formNovoBanco');
    if (formBanco) {
        formBanco.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nomeBanco = document.getElementById('nomeBanco').value;
            const agencia = document.getElementById('agenciaBanco').value;
            const conta = document.getElementById('contaBanco').value;
            const saldoInicial = document.getElementById('saldoInicial').value;
            const observacoes = document.getElementById('observacoesBanco').value;

            if (!nomeBanco.trim()) {
                alert('Por favor, insira o nome do banco');
                return;
            }

            FinanceiroHelper.criarBanco(
                { 
                    nome_banco: nomeBanco, 
                    agencia, 
                    conta, 
                    saldo_inicial: saldoInicial,
                    observacoes
                },
                function(data) {
                    alert('Banco criado com sucesso!');
                    FinanceiroHelper.adicionarOpcaoBanco(
                        data.id_bancos, 
                        data.nome_banco, 
                        data.agencia,
                        data.conta
                    );
                    FinanceiroHelper.fecharModalBanco();
                    formBanco.reset();
                },
                function(erro) {
                    alert('Erro: ' + erro);
                }
            );
        });
    }
});
