// Geração de PDF usando PDFKit
// Importar PDFKit e blobstream

async function gerarPDFContasReceber(dados) {
    try {
        // Verificar se PDFKit está disponível
        if (typeof window.PDFDocument === 'undefined') {
            console.error('PDFKit não está carregado');
            alert('Erro: Biblioteca PDFKit não encontrada');
            return;
        }

        // Criar documento PDF
        const doc = new window.PDFDocument({
            size: 'A4',
            layout: 'portrait',
            margin: 30
        });

        // Criar array para armazenar os buffers
        const buffers = [];
        doc.on('data', buffers.push.bind(buffers));

        // Cores - Padrão de contratos
        const corPrimaria = '#333333';
        const corSecundaria = '#666666';
        const corFundo = '#f5f5f5';
        const corBorda = '#dddddd';
        const verde = '#198754';
        const amarelo = '#ffc107';
        const vermelho = '#dc3545';

        // Cabeçalho - Estilo de contratos
        doc.rect(30, 30, 535, 2).fillAndStroke(corPrimaria, corPrimaria);
        
        doc.moveDown(0.5);
        doc.fontSize(18)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('RELATÓRIO - CONTAS A RECEBER', { align: 'center' });

        doc.font('Helvetica');
        doc.fontSize(10)
           .fillColor(corSecundaria)
           .text('Gerado em: ' + dados.data_geracao, { align: 'center' });

        doc.rect(30, doc.y + 10, 535, 2).fillAndStroke(corPrimaria, corPrimaria);
        
        doc.moveDown(2);

        // Filtros aplicados - Seção com estilo de contrato
        if (dados.filtros && Object.keys(dados.filtros).length > 0) {
            const secaoY = doc.y;
            
            // Bordinha lateral e fundo da seção
            doc.rect(30, secaoY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
            doc.rect(34, secaoY, 531, 20).fillAndStroke(corFundo, corFundo);
            
            doc.fontSize(11)
               .fillColor(corPrimaria)
               .font('Helvetica-Bold')
               .text('FILTROS APLICADOS', 44, secaoY + 6);
            
            doc.font('Helvetica');
            doc.moveDown(0.8);
            
            if (dados.filtros.data_inicio || dados.filtros.data_fim) {
                const inicio = dados.filtros.data_inicio || '...';
                const fim = dados.filtros.data_fim || '...';
                doc.fontSize(10)
                   .fillColor(corPrimaria)
                   .text(`Período: ${inicio} até ${fim}`, 44);
            }
            
            if (dados.filtros.status) {
                doc.fontSize(10)
                   .fillColor(corPrimaria)
                   .text(`Status: ${dados.filtros.status}`, 44);
            }
            
            doc.moveDown(1.5);
        }

        // Seção de totais - Estilo de contrato
        const secaoTotaisY = doc.y;
        doc.rect(30, secaoTotaisY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
        doc.rect(34, secaoTotaisY, 531, 20).fillAndStroke(corFundo, corFundo);
        doc.fontSize(11)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('RESUMO FINANCEIRO', 44, secaoTotaisY + 6);
        
        doc.font('Helvetica');
        doc.moveDown(1.2);

        // Caixas de totais com novo layout
        const larguraCaixa = 128;
        const alturaCaixa = 45;
        const espacamento = 6;
        let x = 30;
        const y = doc.y;

        // Total Geral
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor(corPrimaria)
           .fontSize(9)
           .text('Total Geral', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_geral), x + 10, y + 22, { width: larguraCaixa - 20 });

        // Total Pago
        x += larguraCaixa + espacamento;
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor(verde)
           .font('Helvetica')
           .fontSize(9)
           .text('Total Pago', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_pago), x + 10, y + 22, { width: larguraCaixa - 20 });

        // Total Pendente
        x += larguraCaixa + espacamento;
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor('#856404')
           .font('Helvetica')
           .fontSize(9)
           .text('Total Pendente', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_pendente), x + 10, y + 22, { width: larguraCaixa - 20 });

        // Total Vencido
        x += larguraCaixa + espacamento;
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor(vermelho)
           .font('Helvetica')
           .fontSize(9)
           .text('Total Vencido', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_vencido), x + 10, y + 22, { width: larguraCaixa - 20 });

        doc.moveDown(3);

        // Seção de detalhamento
        const secaoDetalhesY = doc.y;
        doc.rect(30, secaoDetalhesY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
        doc.rect(34, secaoDetalhesY, 531, 20).fillAndStroke(corFundo, corFundo);
        doc.fontSize(11)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('DETALHAMENTO DAS CONTAS', 44, secaoDetalhesY + 6);

        doc.font('Helvetica');
        doc.moveDown(1.5);

        // Tabela de contas - Estilo de contrato
        const tabelaY = doc.y;
        doc.fillColor(corPrimaria);

        // Cabeçalho da tabela
        const colunas = [
            { titulo: 'Vencimento', largura: 52, x: 30 },
            { titulo: 'Descrição', largura: 150, x: 82 },
            { titulo: 'Cliente', largura: 70, x: 232 },
            { titulo: 'Categoria', largura: 50, x: 302 },
            { titulo: 'Valor', largura: 55, x: 352 },
            { titulo: 'Pago', largura: 55, x: 407 },
            { titulo: 'Restante', largura: 55, x: 462 },
            { titulo: 'Status', largura: 58, x: 517 }
        ];

        // Linha do cabeçalho - Estilo tabela de contrato
        // Desenhar fundo preto
        doc.save();
        doc.rect(30, tabelaY, 545, 20).fillAndStroke('#333333', '#333333');
        doc.restore();
        
        // Escrever títulos em branco
        doc.fontSize(7).fillColor('#FFFFFF').font('Helvetica-Bold');
        colunas.forEach(col => {
            doc.text(col.titulo, col.x + 2, tabelaY + 6, { 
                width: col.largura, 
                align: 'left',
                continued: false
            });
        });

        let linhaY = tabelaY + 20;
        doc.fillColor(corPrimaria).fontSize(7).font('Helvetica');

        // Dados da tabela
        dados.contas.forEach((conta, index) => {
            const textoDescricao = conta.descricao || '-';
            const textoCliente = conta.cliente || '-';
            const textoCategoria = conta.categoria || '-';
            const textoStatus = conta.status || '-';

            const alturaDescricao = doc.heightOfString(textoDescricao, { width: colunas[1].largura - 4, align: 'left' });
            const alturaCliente = doc.heightOfString(textoCliente, { width: colunas[2].largura - 4, align: 'left' });
            const alturaCategoria = doc.heightOfString(textoCategoria, { width: colunas[3].largura - 4, align: 'left' });
            const alturaStatus = doc.heightOfString(textoStatus, { width: colunas[7].largura - 4, align: 'left' });

            const alturaMinima = 14;
            const paddingVertical = 6;
            const alturaLinha = Math.max(
                alturaMinima,
                alturaDescricao + paddingVertical,
                alturaCliente + paddingVertical,
                alturaCategoria + paddingVertical,
                alturaStatus + paddingVertical
            );

            // Verificar se precisa de nova página
            if (linhaY + alturaLinha > 750) {
                doc.addPage({ size: 'A4', layout: 'portrait', margin: 30 });
                linhaY = 30;
                
                // Repetir cabeçalho
                // Desenhar fundo preto
                doc.save();
                doc.rect(30, linhaY, 545, 20).fillAndStroke('#333333', '#333333');
                doc.restore();
                
                // Escrever títulos em branco
                doc.fontSize(9).fillColor('#FFFFFF').font('Helvetica-Bold');
                colunas.forEach(col => {
                    doc.text(col.titulo, col.x + 2, linhaY + 6, { 
                        width: col.largura, 
                        align: 'left',
                        continued: false
                    });
                });
                linhaY += 20;
                doc.fillColor(corPrimaria).fontSize(7).font('Helvetica');
            }

            // Linha com borda - altura dinâmica conforme conteúdo
            doc.rect(30, linhaY, 545, alturaLinha).stroke(corBorda);

            doc.fillColor(corPrimaria).font('Helvetica');
            doc.text(conta.data_vencimento, colunas[0].x + 2, linhaY + 3, { width: colunas[0].largura - 4 });
            doc.text(textoDescricao, colunas[1].x + 2, linhaY + 3, { width: colunas[1].largura - 4, align: 'left' });
            doc.text(textoCliente, colunas[2].x + 2, linhaY + 3, { width: colunas[2].largura - 4, align: 'left' });
            doc.text(textoCategoria, colunas[3].x + 2, linhaY + 3, { width: colunas[3].largura - 4, align: 'left' });
            doc.text('R$ ' + formatarMoeda(conta.valor_total), colunas[4].x + 2, linhaY + 3, { width: colunas[4].largura - 4 });
            doc.text('R$ ' + formatarMoeda(conta.valor_pago), colunas[5].x + 2, linhaY + 3, { width: colunas[5].largura - 4 });
            doc.text('R$ ' + formatarMoeda(conta.valor_restante), colunas[6].x + 2, linhaY + 3, { width: colunas[6].largura - 4 });
            doc.text(textoStatus, colunas[7].x + 2, linhaY + 3, { width: colunas[7].largura - 4, align: 'left' });

            linhaY += alturaLinha;
        });

        // Rodapé - Estilo de contrato
        const rodapeY = 780;
        doc.rect(30, rodapeY, 535, 1).fillAndStroke(corBorda, corBorda);
        doc.fontSize(8).fillColor(corSecundaria);
        doc.text('Relatório gerado pelo Sistema GestorNow', 30, rodapeY + 10, { align: 'center', width: 535 });

        // Finalizar o PDF
        doc.end();

        // Aguardar conclusão e abrir modal de preview
        doc.on('end', function() {
            const blob = new Blob(buffers, { type: 'application/pdf' });
            abrirModalPreviewPDF(blob, 'relatorio-contas-receber-' + getDataAtual() + '.pdf');
        });

    } catch (error) {
        console.error('Erro ao gerar PDF:', error);
        alert('Erro ao gerar PDF: ' + error.message);
    }
}

async function gerarPDFContasPagar(dados) {
    try {
        if (typeof window.PDFDocument === 'undefined') {
            console.error('PDFKit não está carregado');
            alert('Erro: Biblioteca PDFKit não encontrada');
            return;
        }

        const doc = new window.PDFDocument({
            size: 'A4',
            layout: 'portrait',
            margin: 30
        });

        // Criar array para armazenar os buffers
        const buffers = [];
        doc.on('data', buffers.push.bind(buffers));

        // Cores - Padrão de contratos
        const corPrimaria = '#333333';
        const corSecundaria = '#666666';
        const corFundo = '#f5f5f5';
        const corBorda = '#dddddd';
        const verde = '#198754';
        const amarelo = '#ffc107';
        const vermelho = '#dc3545';

        // Cabeçalho - Estilo de contratos
        doc.rect(30, 30, 535, 2).fillAndStroke(corPrimaria, corPrimaria);
        
        doc.moveDown(0.5);
        doc.fontSize(18)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('RELATÓRIO - CONTAS A PAGAR', { align: 'center' });

        doc.font('Helvetica');
        doc.fontSize(10)
           .fillColor(corSecundaria)
           .text('Gerado em: ' + dados.data_geracao, { align: 'center' });

        doc.rect(30, doc.y + 10, 535, 2).fillAndStroke(corPrimaria, corPrimaria);
        
        doc.moveDown(2);

        // Filtros aplicados - Seção com estilo de contrato
        if (dados.filtros && Object.keys(dados.filtros).length > 0) {
            const secaoY = doc.y;
            
            // Bordinha lateral e fundo da seção
            doc.rect(30, secaoY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
            doc.rect(34, secaoY, 531, 20).fillAndStroke(corFundo, corFundo);
            
            doc.fontSize(11)
               .fillColor(corPrimaria)
               .font('Helvetica-Bold')
               .text('FILTROS APLICADOS', 44, secaoY + 6);
            
            doc.font('Helvetica');
            doc.moveDown(0.8);
            
            if (dados.filtros.data_inicio || dados.filtros.data_fim) {
                const inicio = dados.filtros.data_inicio || '...';
                const fim = dados.filtros.data_fim || '...';
                doc.fontSize(10)
                   .fillColor(corPrimaria)
                   .text(`Período: ${inicio} até ${fim}`, 44);
            }
            
            if (dados.filtros.status) {
                doc.fontSize(10)
                   .fillColor(corPrimaria)
                   .text(`Status: ${dados.filtros.status}`, 44);
            }
            
            doc.moveDown(1.5);
        }

        // Seção de totais - Estilo de contrato
        const secaoTotaisY = doc.y;
        doc.rect(30, secaoTotaisY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
        doc.rect(34, secaoTotaisY, 531, 20).fillAndStroke(corFundo, corFundo);
        doc.fontSize(11)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('RESUMO FINANCEIRO', 44, secaoTotaisY + 6);
        
        doc.font('Helvetica');
        doc.moveDown(1.2);

        // Caixas de totais com novo layout
        const larguraCaixa = 128;
        const alturaCaixa = 45;
        const espacamento = 6;
        let x = 30;
        const y = doc.y;

        // Total Geral
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor(corPrimaria)
           .fontSize(9)
           .text('Total Geral', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_geral), x + 10, y + 22, { width: larguraCaixa - 20 });

        // Total Pago
        x += larguraCaixa + espacamento;
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor(verde)
           .font('Helvetica')
           .fontSize(9)
           .text('Total Pago', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_pago), x + 10, y + 22, { width: larguraCaixa - 20 });

        // Total Pendente
        x += larguraCaixa + espacamento;
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor('#856404')
           .font('Helvetica')
           .fontSize(9)
           .text('Total Pendente', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_pendente), x + 10, y + 22, { width: larguraCaixa - 20 });

        // Total Vencido
        x += larguraCaixa + espacamento;
        doc.rect(x, y, larguraCaixa, alturaCaixa).fillAndStroke(corFundo, corBorda);
        doc.fillColor(vermelho)
           .font('Helvetica')
           .fontSize(9)
           .text('Total Vencido', x + 10, y + 8, { width: larguraCaixa - 20 });
        doc.fontSize(14)
           .font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.totais.total_vencido), x + 10, y + 22, { width: larguraCaixa - 20 });

        doc.moveDown(3);

        // Seção de detalhamento
        const secaoDetalhesY = doc.y;
        doc.rect(30, secaoDetalhesY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
        doc.rect(34, secaoDetalhesY, 531, 20).fillAndStroke(corFundo, corFundo);
        doc.fontSize(11)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('DETALHAMENTO DAS CONTAS', 44, secaoDetalhesY + 6);

        doc.font('Helvetica');
        doc.moveDown(1.5);

        // Tabela de contas - Estilo de contrato
        const tabelaY = doc.y;
        doc.fillColor(corPrimaria);

        const colunas = [
            { titulo: 'Vencimento', largura: 52, x: 30 },
            { titulo: 'Descrição', largura: 150, x: 82 },
            { titulo: 'Fornecedor', largura: 70, x: 232 },
            { titulo: 'Categoria', largura: 50, x: 302 },
            { titulo: 'Valor', largura: 55, x: 352 },
            { titulo: 'Pago', largura: 55, x: 407 },
            { titulo: 'Restante', largura: 55, x: 462 },
            { titulo: 'Status', largura: 58, x: 517 }
        ];

        // Linha do cabeçalho - Estilo tabela de contrato
        // Desenhar fundo preto
        doc.save();
        doc.rect(30, tabelaY, 545, 20).fillAndStroke('#333333', '#333333');
        doc.restore();
        
        // Escrever títulos em branco
        doc.fontSize(9).fillColor('#FFFFFF').font('Helvetica-Bold');
        colunas.forEach(col => {
            doc.text(col.titulo, col.x + 2, tabelaY + 6, { 
                width: col.largura, 
                align: 'left',
                continued: false
            });
        });

        let linhaY = tabelaY + 20;
        doc.fillColor(corPrimaria).fontSize(7).font('Helvetica');

        // Dados da tabela
        dados.contas.forEach((conta, index) => {
            const textoDescricao = conta.descricao || '-';
            const textoFornecedor = conta.fornecedor || '-';
            const textoCategoria = conta.categoria || '-';
            const textoStatus = conta.status || '-';

            const alturaDescricao = doc.heightOfString(textoDescricao, { width: colunas[1].largura - 4, align: 'left' });
            const alturaFornecedor = doc.heightOfString(textoFornecedor, { width: colunas[2].largura - 4, align: 'left' });
            const alturaCategoria = doc.heightOfString(textoCategoria, { width: colunas[3].largura - 4, align: 'left' });
            const alturaStatus = doc.heightOfString(textoStatus, { width: colunas[7].largura - 4, align: 'left' });

            const alturaMinima = 14;
            const paddingVertical = 6;
            const alturaLinha = Math.max(
                alturaMinima,
                alturaDescricao + paddingVertical,
                alturaFornecedor + paddingVertical,
                alturaCategoria + paddingVertical,
                alturaStatus + paddingVertical
            );

            if (linhaY + alturaLinha > 750) {
                doc.addPage({ size: 'A4', layout: 'portrait', margin: 30 });
                linhaY = 30;
                
                // Repetir cabeçalho
                // Desenhar fundo preto
                doc.save();
                doc.rect(30, linhaY, 545, 20).fillAndStroke('#333333', '#333333');
                doc.restore();
                
                // Escrever títulos em branco
                doc.fontSize(9).fillColor('#FFFFFF').font('Helvetica-Bold');
                colunas.forEach(col => {
                    doc.text(col.titulo, col.x + 2, linhaY + 6, { 
                        width: col.largura, 
                        align: 'left',
                        continued: false
                    });
                });
                linhaY += 20;
                doc.fillColor(corPrimaria).fontSize(7).font('Helvetica');
            }

            // Linha com borda - altura dinâmica conforme conteúdo
            doc.rect(30, linhaY, 545, alturaLinha).stroke(corBorda);

            doc.fillColor(corPrimaria).font('Helvetica');
            doc.text(conta.data_vencimento, colunas[0].x + 2, linhaY + 3, { width: colunas[0].largura - 4 });
            doc.text(textoDescricao, colunas[1].x + 2, linhaY + 3, { width: colunas[1].largura - 4, align: 'left' });
            doc.text(textoFornecedor, colunas[2].x + 2, linhaY + 3, { width: colunas[2].largura - 4, align: 'left' });
            doc.text(textoCategoria, colunas[3].x + 2, linhaY + 3, { width: colunas[3].largura - 4, align: 'left' });
            doc.text('R$ ' + formatarMoeda(conta.valor_total), colunas[4].x + 2, linhaY + 3, { width: colunas[4].largura - 4 });
            doc.text('R$ ' + formatarMoeda(conta.valor_pago), colunas[5].x + 2, linhaY + 3, { width: colunas[5].largura - 4 });
            doc.text('R$ ' + formatarMoeda(conta.valor_restante), colunas[6].x + 2, linhaY + 3, { width: colunas[6].largura - 4 });
            doc.text(textoStatus, colunas[7].x + 2, linhaY + 3, { width: colunas[7].largura - 4, align: 'left' });

            linhaY += alturaLinha;
        });

        // Rodapé - Estilo de contrato
        const rodapeY = 780;
        doc.rect(30, rodapeY, 535, 1).fillAndStroke(corBorda, corBorda);
        doc.fontSize(8).fillColor(corSecundaria);
        doc.text('Relatório gerado pelo Sistema GestorNow', 30, rodapeY + 10, { align: 'center', width: 535 });

        doc.end();

        doc.on('end', function() {
            const blob = new Blob(buffers, { type: 'application/pdf' });
            abrirModalPreviewPDF(blob, 'relatorio-contas-pagar-' + getDataAtual() + '.pdf');
        });

    } catch (error) {
        console.error('Erro ao gerar PDF:', error);
        alert('Erro ao gerar PDF: ' + error.message);
    }
}

// Funções auxiliares
function formatarMoeda(valor) {
    if (!valor || isNaN(valor)) return '0,00';
    return parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function truncarTexto(texto, tamanho) {
    if (!texto) return '-';
    return texto.length > tamanho ? texto.substring(0, tamanho) + '...' : texto;
}

function getDataAtual() {
    const data = new Date();
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    const hora = String(data.getHours()).padStart(2, '0');
    const minuto = String(data.getMinutes()).padStart(2, '0');
    const segundo = String(data.getSeconds()).padStart(2, '0');
    return `${ano}-${mes}-${dia}_${hora}-${minuto}-${segundo}`;
}

// Abrir modal de pré-visualização do PDF
function abrirModalPreviewPDF(blob, nomeArquivo) {
    // Criar URL do blob
    const url = URL.createObjectURL(blob);

    // Verifica se já existe um modal de preview aberto e remove
    const modalExistente = document.getElementById('modal-preview-pdf');
    if (modalExistente) {
        modalExistente.remove();
    }

    // Criar modal
    const modal = document.createElement('div');
    modal.id = 'modal-preview-pdf';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
    `;

    // Container do conteúdo
    const container = document.createElement('div');
    container.style.cssText = `
        background: white;
        border-radius: 8px;
        width: 95%;
        max-width: 1200px;
        height: 90%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    `;

    // Cabeçalho do modal
    const header = document.createElement('div');
    header.style.cssText = `
        padding: 15px 20px;
        background-color: #f5f5f5;
        border-bottom: 1px solid #ddd;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    `;

    const titulo = document.createElement('h3');
    titulo.textContent = 'Pré-visualização do Relatório';
    titulo.style.cssText = `
        margin: 0;
        font-size: 18px;
        color: #333;
    `;

    const btnFecharHeader = document.createElement('button');
    btnFecharHeader.innerHTML = '✕';
    btnFecharHeader.style.cssText = `
        background: none;
        border: none;
        font-size: 24px;
        color: #666;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    btnFecharHeader.onmouseover = () => btnFecharHeader.style.color = '#333';
    btnFecharHeader.onmouseout = () => btnFecharHeader.style.color = '#666';
    btnFecharHeader.onclick = () => fecharModal();

    header.appendChild(titulo);
    header.appendChild(btnFecharHeader);

    // Iframe para o PDF
    const iframe = document.createElement('iframe');
    iframe.src = url;
    iframe.style.cssText = `
        width: 100%;
        height: 100%;
        border: none;
        flex: 1;
    `;

    // Rodapé com botões
    const footer = document.createElement('div');
    footer.style.cssText = `
        padding: 15px 20px;
        background-color: #f5f5f5;
        border-top: 1px solid #ddd;
        border-radius: 0 0 8px 8px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    `;

    // Botão Baixar
    const btnBaixar = document.createElement('button');
    btnBaixar.innerHTML = 'Baixar PDF';
    btnBaixar.style.cssText = `
        padding: 10px 20px;
        background-color: #198754;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s;
    `;
    btnBaixar.onmouseover = () => btnBaixar.style.backgroundColor = '#146c43';
    btnBaixar.onmouseout = () => btnBaixar.style.backgroundColor = '#198754';
    btnBaixar.onclick = () => {
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivo;
        link.click();
    };

    // Botão Fechar
    const btnFechar = document.createElement('button');
    btnFechar.innerHTML = 'Fechar';
    btnFechar.style.cssText = `
        padding: 10px 20px;
        background-color: #6c757d;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s;
    `;
    btnFechar.onmouseover = () => btnFechar.style.backgroundColor = '#5a6268';
    btnFechar.onmouseout = () => btnFechar.style.backgroundColor = '#6c757d';
    btnFechar.onclick = () => fecharModal();

    footer.appendChild(btnBaixar);
    footer.appendChild(btnFechar);

    // Montar o modal
    container.appendChild(header);
    container.appendChild(iframe);
    container.appendChild(footer);
    modal.appendChild(container);
    document.body.appendChild(modal);

    // Função para fechar o modal
    function fecharModal() {
        modal.remove();
        URL.revokeObjectURL(url);
    }

    // Fechar ao clicar fora do container
    modal.onclick = (e) => {
        if (e.target === modal) {
            fecharModal();
        }
    };

    // Fechar com ESC
    const handleEsc = (e) => {
        if (e.key === 'Escape') {
            fecharModal();
            document.removeEventListener('keydown', handleEsc);
        }
    };
    document.addEventListener('keydown', handleEsc);
}
