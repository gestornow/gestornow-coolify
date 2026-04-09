// Funções auxiliares para geração de PDFs e Excel dos novos relatórios
// Adicionar no final de relatorios-pdf.js e relatorios-excel.js

// ============================================
// FLUXO DE CAIXA - PDF
// ============================================
async function gerarPDFFluxoCaixa(dados) {
    try {
        if (typeof window.PDFDocument === 'undefined') {
            console.error('PDFKit não está carregado');
            return;
        }

        const doc = new window.PDFDocument({
            size: 'A4',
            layout: 'portrait',
            margin: 30
        });

        const buffers = [];
        doc.on('data', buffers.push.bind(buffers));

        // Cores
        const corPrimaria = '#333333';
        const corSecundaria = '#666666';
        const corFundo = '#f5f5f5';

        // Título
        doc.fontSize(18)
           .fillColor(corPrimaria)
           .font('Helvetica-Bold')
           .text('RELATÓRIO - FLUXO DE CAIXA CONSOLIDADO', { align: 'center' });

        doc.fontSize(10)
           .fillColor(corSecundaria)
           .font('Helvetica')
           .text('Gerado em: ' + new Date().toLocaleString('pt-BR'), { align: 'center' });

        doc.moveDown(2);

        // Cards de Resumo
        const y = doc.y;
        const cardWidth = 255;
        const cardHeight = 60;

        // Saldo Inicial
        doc.rect(30, y, cardWidth, cardHeight).fillAndStroke(corFundo, '#dddddd');
        doc.fillColor(corSecundaria).fontSize(9).text('Saldo Inicial', 40, y + 10);
        doc.fillColor(corPrimaria).fontSize(14).font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.resumo.saldo_inicial), 40, y + 30);

        // Total Entradas
        doc.rect(295, y, cardWidth, cardHeight).fillAndStroke(corFundo, '#dddddd');
        doc.fillColor('#28a745').fontSize(9).font('Helvetica').text('Total Entradas', 305, y + 10);
        doc.fillColor('#28a745').fontSize(14).font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.resumo.total_entradas), 305, y + 30);

        // Total Saídas
        doc.rect(30, y + 70, cardWidth, cardHeight).fillAndStroke(corFundo, '#dddddd');
        doc.fillColor('#dc3545').fontSize(9).font('Helvetica').text('Total Saídas', 40, y + 80);
        doc.fillColor('#dc3545').fontSize(14).font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.resumo.total_saidas), 40, y + 110);

        // Saldo Final
        doc.rect(295, y + 70, cardWidth, cardHeight).fillAndStroke(corFundo, '#dddddd');
        doc.fillColor('#0d6efd').fontSize(9).font('Helvetica').text('Saldo Final', 305, y + 80);
        doc.fillColor('#0d6efd').fontSize(14).font('Helvetica-Bold')
           .text('R$ ' + formatarMoeda(dados.resumo.saldo_final), 305, y + 110);

        doc.y = y + cardHeight * 2 + 30;

        // Seção de lançamentos detalhados
        const secaoLancamentosY = doc.y;
        doc.rect(30, secaoLancamentosY, 4, 20).fillAndStroke(corPrimaria, corPrimaria);
        doc.rect(34, secaoLancamentosY, 516, 20).fillAndStroke(corFundo, '#dddddd');
        doc.fontSize(11).fillColor(corPrimaria).font('Helvetica-Bold')
           .text('LANÇAMENTOS DETALHADOS', 44, secaoLancamentosY + 6);
        doc.moveDown(1.5);

        const tableY = doc.y;
        // Desenhar fundo preto
        doc.save();
        doc.rect(30, tableY, 520, 22).fillAndStroke('#333333', '#333333');
        doc.restore();
        
        // Escrever títulos em branco
        doc.fontSize(7).fillColor('#FFFFFF').font('Helvetica-Bold');
        doc.text('Data', 35, tableY + 7, { width: 50, continued: false });
        doc.text('Descrição', 90, tableY + 7, { width: 130, continued: false });
        doc.text('Categoria', 225, tableY + 7, { width: 80, continued: false });
        doc.text('Entradas', 310, tableY + 7, { width: 70, continued: false });
        doc.text('Saídas', 385, tableY + 7, { width: 70, continued: false });
        doc.text('Saldo', 460, tableY + 7, { width: 85, continued: false });

        let rowY = tableY + 22;
        doc.font('Helvetica').fillColor(corPrimaria);

        dados.lancamentos.forEach((lanc, index) => {
            if (rowY > 700) {
                doc.addPage({ size: 'A4', layout: 'portrait', margin: 30 });
                rowY = 30;
                
                // Repetir cabeçalho na nova página
                // Desenhar fundo preto
                doc.save();
                doc.rect(30, rowY, 520, 22).fillAndStroke('#333333', '#333333');
                doc.restore();
                
                // Escrever títulos em branco
                doc.fontSize(7).fillColor('#FFFFFF').font('Helvetica-Bold');
                doc.text('Data', 35, rowY + 7, { width: 50, continued: false });
                doc.text('Descrição', 90, rowY + 7, { width: 130, continued: false });
                doc.text('Categoria', 225, rowY + 7, { width: 80, continued: false });
                doc.text('Entradas', 310, rowY + 7, { width: 70, continued: false });
                doc.text('Saídas', 385, rowY + 7, { width: 70, continued: false });
                doc.text('Saldo', 460, rowY + 7, { width: 85, continued: false });
                rowY += 22;
            }

            const entradaText = lanc.tipo === 'entrada' ? 'R$ ' + formatarMoeda(lanc.valor) : '-';
            const saidaText = lanc.tipo === 'saida' ? 'R$ ' + formatarMoeda(lanc.valor) : '-';

            doc.fontSize(7);
            doc.fillColor(corPrimaria).font('Helvetica');
            doc.text(lanc.data, 35, rowY + 4, { width: 50 });
            doc.text(lanc.descricao || '-', 90, rowY + 4, { width: 130, ellipsis: true });
            doc.text(lanc.categoria || '-', 225, rowY + 4, { width: 80, ellipsis: true });
            doc.fillColor('#28a745').text(entradaText, 310, rowY + 4, { width: 70 });
            doc.fillColor('#dc3545').text(saidaText, 385, rowY + 4, { width: 70 });
            doc.fillColor(corPrimaria).text('R$ ' + formatarMoeda(lanc.saldo || 0), 460, rowY + 4, { width: 85 });

            doc.rect(30, rowY, 520, 16).stroke('#dddddd');
            rowY += 16;
        });

        // Finalizar
        doc.end();

        doc.on('end', function() {
            const blob = new Blob(buffers, { type: 'application/pdf' });
            abrirModalPreviewPDF(blob, 'fluxo-caixa-' + getDataAtual() + '.pdf');
        });

    } catch (error) {
        console.error('Erro ao gerar PDF:', error);
        alert('Erro ao gerar PDF: ' + error.message);
    }
}

// ============================================
// FLUXO DE CAIXA - EXCEL
// ============================================
async function gerarExcelFluxoCaixa(dados) {
    try {
        if (typeof ExcelJS === 'undefined') {
            console.error('ExcelJS não está carregado');
            return;
        }

        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'GestorNow';
        workbook.created = new Date();

        const worksheet = workbook.addWorksheet('Fluxo de Caixa', {
            pageSetup: { 
                paperSize: 9, 
                orientation: 'portrait'
            }
        });

        // Título
        worksheet.mergeCells('A1:G1');
        const tituloCell = worksheet.getCell('A1');
        tituloCell.value = 'RELATÓRIO - FLUXO DE CAIXA CONSOLIDADO';
        tituloCell.font = { size: 18, bold: true, color: { argb: 'FF333333' } };
        tituloCell.alignment = { vertical: 'middle', horizontal: 'center' };
        tituloCell.border = {
            top: { style: 'thick', color: { argb: 'FF333333' } },
            bottom: { style: 'thick', color: { argb: 'FF333333' } }
        };
        worksheet.getRow(1).height = 35;

        let linhaAtual = 3;

        // Resumo
        worksheet.mergeCells(`A${linhaAtual}:G${linhaAtual}`);
        const secaoResumo = worksheet.getCell(`A${linhaAtual}`);
        secaoResumo.value = 'RESUMO FINANCEIRO';
        secaoResumo.font = { size: 12, bold: true, color: { argb: 'FF333333' } };
        secaoResumo.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        linhaAtual++;

        // Cards de resumo horizontalmente
        worksheet.getCell(`A${linhaAtual}`).value = 'Saldo Inicial';
        worksheet.getCell(`A${linhaAtual}`).font = { bold: true };
        worksheet.getCell(`A${linhaAtual + 1}`).value = dados.resumo.saldo_inicial;
        worksheet.getCell(`A${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';

        worksheet.getCell(`C${linhaAtual}`).value = 'Total Entradas';
        worksheet.getCell(`C${linhaAtual}`).font = { bold: true, color: { argb: 'FF28a745' } };
        worksheet.getCell(`C${linhaAtual + 1}`).value = dados.resumo.total_entradas;
        worksheet.getCell(`C${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`C${linhaAtual + 1}`).font = { color: { argb: 'FF28a745' } };

        worksheet.getCell(`E${linhaAtual}`).value = 'Total Saídas';
        worksheet.getCell(`E${linhaAtual}`).font = { bold: true, color: { argb: 'FFDC3545' } };
        worksheet.getCell(`E${linhaAtual + 1}`).value = dados.resumo.total_saidas;
        worksheet.getCell(`E${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`E${linhaAtual + 1}`).font = { color: { argb: 'FFDC3545' } };

        worksheet.getCell(`G${linhaAtual}`).value = 'Saldo Final';
        worksheet.getCell(`G${linhaAtual}`).font = { bold: true, color: { argb: 'FF0D6EFD' } };
        worksheet.getCell(`G${linhaAtual + 1}`).value = dados.resumo.saldo_final;
        worksheet.getCell(`G${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`G${linhaAtual + 1}`).font = { color: { argb: 'FF0D6EFD' } };

        linhaAtual += 4;

        // Tabela de lançamentos
        worksheet.mergeCells(`A${linhaAtual}:G${linhaAtual}`);
        const secaoLancamentos = worksheet.getCell(`A${linhaAtual}`);
        secaoLancamentos.value = 'LANÇAMENTOS DETALHADOS';
        secaoLancamentos.font = { size: 12, bold: true, color: { argb: 'FF333333' } };
        secaoLancamentos.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        linhaAtual++;

        // Cabeçalho da tabela
        const cabecalho = ['Data', 'Descrição', 'Categoria', 'Tipo', 'Entradas', 'Saídas', 'Saldo'];
        const headerRow = worksheet.getRow(linhaAtual);
        headerRow.values = cabecalho;
        headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } };
        headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
        headerRow.alignment = { vertical: 'middle', horizontal: 'center' };
        headerRow.height = 22;
        linhaAtual++;

        // Dados
        dados.lancamentos.forEach((lanc, index) => {
            const row = worksheet.getRow(linhaAtual);
            row.values = [
                lanc.data,
                lanc.descricao,
                lanc.categoria,
                lanc.tipo,
                lanc.tipo === 'entrada' ? lanc.valor : null,
                lanc.tipo === 'saida' ? lanc.valor : null,
                lanc.saldo || 0
            ];

            row.getCell(5).numFmt = 'R$ #,##0.00';
            row.getCell(6).numFmt = 'R$ #,##0.00';
            row.getCell(7).numFmt = 'R$ #,##0.00';

            if (index % 2 === 0) {
                row.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8F9FA' } };
            }

            row.eachCell((cell) => {
                cell.border = {
                    top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                    left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                    bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                    right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
                };
            });

            linhaAtual++;
        });

        // Ajustar largura das colunas
        worksheet.columns = [
            { key: 'data', width: 12 },
            { key: 'descricao', width: 35 },
            { key: 'categoria', width: 20 },
            { key: 'tipo', width: 10 },
            { key: 'entradas', width: 15 },
            { key: 'saidas', width: 15 },
            { key: 'saldo', width: 15 }
        ];

        // Rodapé
        linhaAtual += 2;
        worksheet.mergeCells(`A${linhaAtual}:G${linhaAtual}`);
        const rodapeCell = worksheet.getCell(`A${linhaAtual}`);
        rodapeCell.value = 'Relatório gerado pelo Sistema GestorNow';
        rodapeCell.font = { size: 9, color: { argb: 'FF666666' }, italic: true };
        rodapeCell.alignment = { vertical: 'middle', horizontal: 'center' };

        // Gerar arquivo
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { 
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
        });
        
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'fluxo-caixa-' + getDataAtualExcel() + '.xlsx';
        link.click();
        URL.revokeObjectURL(url);

    } catch (error) {
        console.error('Erro ao gerar Excel:', error);
        alert('Erro ao gerar Excel: ' + error.message);
    }
}

// Funções auxiliares já existentes no projeto
function formatarMoeda(valor) {
    return parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function truncarTexto(texto, tamanho) {
    return texto.length > tamanho ? texto.substring(0, tamanho) + '...' : texto;
}

function getDataAtual() {
    const data = new Date();
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function getDataAtualExcel() {
    return getDataAtual();
}
