// Geração de Excel usando ExcelJS

async function gerarExcelContasReceber(dados) {
    try {
        // Verificar se ExcelJS está disponível
        if (typeof ExcelJS === 'undefined') {
            console.error('ExcelJS não está carregado');
            alert('Erro: Biblioteca ExcelJS não encontrada');
            return;
        }

        // Criar workbook
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'GestorNow';
        workbook.created = new Date();
        
        // Adicionar worksheet
        const worksheet = workbook.addWorksheet('Contas a Receber', {
            pageSetup: { 
                paperSize: 9, 
                orientation: 'landscape',
                fitToPage: true,
                fitToWidth: 1,
                fitToHeight: 0
            }
        });

        // Título com bordas (estilo PDF)
        worksheet.mergeCells('A1:L1');
        const tituloCell = worksheet.getCell('A1');
        tituloCell.value = 'RELATÓRIO - CONTAS A RECEBER';
        tituloCell.font = { size: 18, bold: true, color: { argb: 'FF333333' } };
        tituloCell.alignment = { vertical: 'middle', horizontal: 'center' };
        tituloCell.border = {
            top: { style: 'thick', color: { argb: 'FF333333' } },
            bottom: { style: 'thick', color: { argb: 'FF333333' } }
        };
        worksheet.getRow(1).height = 35;

        // Data de geração
        worksheet.mergeCells('A2:L2');
        const dataCell = worksheet.getCell('A2');
        dataCell.value = 'Gerado em: ' + dados.data_geracao;
        dataCell.font = { size: 10, color: { argb: 'FF666666' } };
        dataCell.alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getRow(2).height = 20;

        let linhaAtual = 4;

        // Filtros aplicados (com fundo cinza)
        if (dados.filtros && Object.keys(dados.filtros).length > 0) {
            worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
            const filtrosCell = worksheet.getCell(`A${linhaAtual}`);
            filtrosCell.value = 'FILTROS APLICADOS';
            filtrosCell.font = { size: 11, bold: true, color: { argb: 'FF333333' } };
            filtrosCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
            filtrosCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            filtrosCell.border = {
                left: { style: 'thick', color: { argb: 'FF333333' } },
                top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
            };
            linhaAtual++;
            
            worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
            const filtrosDetalhesCell = worksheet.getCell(`A${linhaAtual}`);
            let textoFiltros = '';
            
            if (dados.filtros.data_inicio || dados.filtros.data_fim) {
                textoFiltros += `Período: ${dados.filtros.data_inicio || '...'} até ${dados.filtros.data_fim || '...'}  `;
            }
            if (dados.filtros.status) {
                textoFiltros += `| Status: ${dados.filtros.status}`;
            }
            
            filtrosDetalhesCell.value = textoFiltros;
            filtrosDetalhesCell.font = { size: 9, color: { argb: 'FF333333' } };
            filtrosDetalhesCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            linhaAtual += 2;
        }

        // Seção de totais - Layout horizontal (4 colunas lado a lado)
        worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
        const secaoTotaisCell = worksheet.getCell(`A${linhaAtual}`);
        secaoTotaisCell.value = 'RESUMO FINANCEIRO';
        secaoTotaisCell.font = { size: 11, bold: true, color: { argb: 'FF333333' } };
        secaoTotaisCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        secaoTotaisCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
        secaoTotaisCell.border = {
            left: { style: 'thick', color: { argb: 'FF333333' } },
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        worksheet.getRow(linhaAtual).height = 20;
        linhaAtual++;

        // Totais em linha horizontal (4 colunas)
        // Total Geral
        worksheet.getCell(`A${linhaAtual}`).value = 'Total Geral';
        worksheet.getCell(`A${linhaAtual}`).font = { size: 9, color: { argb: 'FF333333' } };
        worksheet.getCell(`A${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`A${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`A${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.getCell(`A${linhaAtual + 1}`).value = dados.totais.total_geral;
        worksheet.getCell(`A${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`A${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FF333333' } };
        worksheet.getCell(`A${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`A${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`A${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Total Pago
        worksheet.mergeCells(`B${linhaAtual}:D${linhaAtual}`);
        worksheet.getCell(`B${linhaAtual}`).value = 'Total Pago';
        worksheet.getCell(`B${linhaAtual}`).font = { size: 9, color: { argb: 'FF198754' } };
        worksheet.getCell(`B${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`B${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`B${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.mergeCells(`B${linhaAtual + 1}:D${linhaAtual + 1}`);
        worksheet.getCell(`B${linhaAtual + 1}`).value = dados.totais.total_pago;
        worksheet.getCell(`B${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`B${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FF198754' } };
        worksheet.getCell(`B${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`B${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`B${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Total Pendente
        worksheet.mergeCells(`E${linhaAtual}:H${linhaAtual}`);
        worksheet.getCell(`E${linhaAtual}`).value = 'Total Pendente';
        worksheet.getCell(`E${linhaAtual}`).font = { size: 9, color: { argb: 'FF856404' } };
        worksheet.getCell(`E${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`E${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`E${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.mergeCells(`E${linhaAtual + 1}:H${linhaAtual + 1}`);
        worksheet.getCell(`E${linhaAtual + 1}`).value = dados.totais.total_pendente;
        worksheet.getCell(`E${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`E${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FF856404' } };
        worksheet.getCell(`E${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`E${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`E${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Total Vencido
        worksheet.mergeCells(`I${linhaAtual}:L${linhaAtual}`);
        worksheet.getCell(`I${linhaAtual}`).value = 'Total Vencido';
        worksheet.getCell(`I${linhaAtual}`).font = { size: 9, color: { argb: 'FFDC3545' } };
        worksheet.getCell(`I${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`I${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`I${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.mergeCells(`I${linhaAtual + 1}:L${linhaAtual + 1}`);
        worksheet.getCell(`I${linhaAtual + 1}`).value = dados.totais.total_vencido;
        worksheet.getCell(`I${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`I${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FFDC3545' } };
        worksheet.getCell(`I${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`I${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`I${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        worksheet.getRow(linhaAtual).height = 18;
        worksheet.getRow(linhaAtual + 1).height = 30;
        linhaAtual += 3;

        // Seção de detalhamento
        worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
        const secaoDetalhesCell = worksheet.getCell(`A${linhaAtual}`);
        secaoDetalhesCell.value = 'DETALHAMENTO DAS CONTAS';
        secaoDetalhesCell.font = { size: 11, bold: true, color: { argb: 'FF333333' } };
        secaoDetalhesCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        secaoDetalhesCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
        secaoDetalhesCell.border = {
            left: { style: 'thick', color: { argb: 'FF333333' } },
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        worksheet.getRow(linhaAtual).height = 20;
        linhaAtual++;

        // Cabeçalho da tabela
        const cabecalho = [
            'Data Vencimento',
            'Descrição',
            'Cliente',
            'Documento',
            'Categoria',
            'Banco',
            'Forma Pagamento',
            'Valor Total',
            'Valor Pago',
            'Valor Restante',
            'Status',
            'Observações'
        ];

        const headerRow = worksheet.getRow(linhaAtual);
        headerRow.values = cabecalho;
        headerRow.font = { bold: true, size: 10, color: { argb: 'FFFFFFFF' } };
        headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
        headerRow.alignment = { vertical: 'middle', horizontal: 'center' };
        headerRow.height = 22;
        
        // Aplicar bordas no cabeçalho
        headerRow.eachCell((cell) => {
            cell.border = {
                top: { style: 'thin', color: { argb: 'FF333333' } },
                left: { style: 'thin', color: { argb: 'FF333333' } },
                bottom: { style: 'thin', color: { argb: 'FF333333' } },
                right: { style: 'thin', color: { argb: 'FF333333' } }
            };
        });

        linhaAtual++;

        // Dados
        dados.contas.forEach((conta, index) => {
            const row = worksheet.getRow(linhaAtual);
            row.values = [
                conta.data_vencimento,
                conta.descricao,
                conta.cliente,
                conta.documento,
                conta.categoria,
                conta.banco,
                conta.forma_pagamento,
                conta.valor_total,
                conta.valor_pago,
                conta.valor_restante,
                conta.status,
                conta.observacoes
            ];

            // Formatar valores monetários
            row.getCell(8).numFmt = 'R$ #,##0.00';
            row.getCell(9).numFmt = 'R$ #,##0.00';
            row.getCell(10).numFmt = 'R$ #,##0.00';

            // Alinhamento dos valores
            row.getCell(1).alignment = { vertical: 'middle', horizontal: 'center' }; // Data
            row.getCell(8).alignment = { vertical: 'middle', horizontal: 'right' };  // Valor Total
            row.getCell(9).alignment = { vertical: 'middle', horizontal: 'right' };  // Valor Pago
            row.getCell(10).alignment = { vertical: 'middle', horizontal: 'right' }; // Valor Restante
            row.getCell(11).alignment = { vertical: 'middle', horizontal: 'center' }; // Status

            // Linhas alternadas
            if (index % 2 === 0) {
                row.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF8F9FA' } };
            }

            // Bordas
            row.eachCell((cell) => {
                cell.border = {
                    top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                    left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                    bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                    right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
                };
            });

            row.height = 18;
            linhaAtual++;
        });

        // Ajustar largura das colunas
        worksheet.columns = [
            { key: 'vencimento', width: 15 },
            { key: 'descricao', width: 30 },
            { key: 'cliente', width: 25 },
            { key: 'documento', width: 15 },
            { key: 'categoria', width: 20 },
            { key: 'banco', width: 20 },
            { key: 'forma', width: 20 },
            { key: 'valor_total', width: 15 },
            { key: 'valor_pago', width: 15 },
            { key: 'valor_restante', width: 15 },
            { key: 'status', width: 20 },
            { key: 'obs', width: 30 }
        ];

        // Rodapé
        linhaAtual += 2;
        worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
        const rodapeCell = worksheet.getCell(`A${linhaAtual}`);
        rodapeCell.value = 'Relatório gerado pelo Sistema GestorNow';
        rodapeCell.font = { size: 9, color: { argb: 'FF666666' }, italic: true };
        rodapeCell.alignment = { vertical: 'middle', horizontal: 'center' };
        rodapeCell.border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Gerar o arquivo
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { 
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
        });
        
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'relatorio-contas-receber-' + getDataAtualExcel() + '.xlsx';
        link.click();
        URL.revokeObjectURL(url);

    } catch (error) {
        console.error('Erro ao gerar Excel:', error);
        alert('Erro ao gerar Excel: ' + error.message);
    }
}

async function gerarExcelContasPagar(dados) {
    try {
        if (typeof ExcelJS === 'undefined') {
            console.error('ExcelJS não está carregado');
            alert('Erro: Biblioteca ExcelJS não encontrada');
            return;
        }

        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'GestorNow';
        workbook.created = new Date();
        
        const worksheet = workbook.addWorksheet('Contas a Pagar', {
            pageSetup: { 
                paperSize: 9, 
                orientation: 'landscape',
                fitToPage: true,
                fitToWidth: 1,
                fitToHeight: 0
            }
        });

        // Título com bordas (estilo PDF)
        worksheet.mergeCells('A1:L1');
        const tituloCell = worksheet.getCell('A1');
        tituloCell.value = 'RELATÓRIO - CONTAS A PAGAR';
        tituloCell.font = { size: 18, bold: true, color: { argb: 'FF333333' } };
        tituloCell.alignment = { vertical: 'middle', horizontal: 'center' };
        tituloCell.border = {
            top: { style: 'thick', color: { argb: 'FF333333' } },
            bottom: { style: 'thick', color: { argb: 'FF333333' } }
        };
        worksheet.getRow(1).height = 35;

        // Data de geração
        worksheet.mergeCells('A2:L2');
        const dataCell = worksheet.getCell('A2');
        dataCell.value = 'Gerado em: ' + dados.data_geracao;
        dataCell.font = { size: 10, color: { argb: 'FF666666' } };
        dataCell.alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getRow(2).height = 20;

        let linhaAtual = 4;

        // Filtros aplicados (com fundo cinza)
        if (dados.filtros && Object.keys(dados.filtros).length > 0) {
            worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
            const filtrosCell = worksheet.getCell(`A${linhaAtual}`);
            filtrosCell.value = 'FILTROS APLICADOS';
            filtrosCell.font = { size: 11, bold: true, color: { argb: 'FF333333' } };
            filtrosCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
            filtrosCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            filtrosCell.border = {
                left: { style: 'thick', color: { argb: 'FF333333' } },
                top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
                right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
            };
            linhaAtual++;
            
            worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
            const filtrosDetalhesCell = worksheet.getCell(`A${linhaAtual}`);
            let textoFiltros = '';
            
            if (dados.filtros.data_inicio || dados.filtros.data_fim) {
                textoFiltros += `Período: ${dados.filtros.data_inicio || '...'} até ${dados.filtros.data_fim || '...'}  `;
            }
            if (dados.filtros.status) {
                textoFiltros += `| Status: ${dados.filtros.status}`;
            }
            
            filtrosDetalhesCell.value = textoFiltros;
            filtrosDetalhesCell.font = { size: 9, color: { argb: 'FF333333' } };
            filtrosDetalhesCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            linhaAtual += 2;
        }

        // Seção de totais - Layout horizontal (4 colunas lado a lado)
        worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
        const secaoTotaisCell = worksheet.getCell(`A${linhaAtual}`);
        secaoTotaisCell.value = 'RESUMO FINANCEIRO';
        secaoTotaisCell.font = { size: 11, bold: true, color: { argb: 'FF333333' } };
        secaoTotaisCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        secaoTotaisCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
        secaoTotaisCell.border = {
            left: { style: 'thick', color: { argb: 'FF333333' } },
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        worksheet.getRow(linhaAtual).height = 20;
        linhaAtual++;

        // Totais em linha horizontal (4 colunas)
        // Total Geral
        worksheet.getCell(`A${linhaAtual}`).value = 'Total Geral';
        worksheet.getCell(`A${linhaAtual}`).font = { size: 9, color: { argb: 'FF333333' } };
        worksheet.getCell(`A${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`A${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`A${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.getCell(`A${linhaAtual + 1}`).value = dados.totais.total_geral;
        worksheet.getCell(`A${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`A${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FF333333' } };
        worksheet.getCell(`A${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`A${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`A${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Total Pago
        worksheet.mergeCells(`B${linhaAtual}:D${linhaAtual}`);
        worksheet.getCell(`B${linhaAtual}`).value = 'Total Pago';
        worksheet.getCell(`B${linhaAtual}`).font = { size: 9, color: { argb: 'FF198754' } };
        worksheet.getCell(`B${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`B${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`B${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.mergeCells(`B${linhaAtual + 1}:D${linhaAtual + 1}`);
        worksheet.getCell(`B${linhaAtual + 1}`).value = dados.totais.total_pago;
        worksheet.getCell(`B${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`B${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FF198754' } };
        worksheet.getCell(`B${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`B${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`B${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Total Pendente
        worksheet.mergeCells(`E${linhaAtual}:H${linhaAtual}`);
        worksheet.getCell(`E${linhaAtual}`).value = 'Total Pendente';
        worksheet.getCell(`E${linhaAtual}`).font = { size: 9, color: { argb: 'FF856404' } };
        worksheet.getCell(`E${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`E${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`E${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.mergeCells(`E${linhaAtual + 1}:H${linhaAtual + 1}`);
        worksheet.getCell(`E${linhaAtual + 1}`).value = dados.totais.total_pendente;
        worksheet.getCell(`E${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`E${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FF856404' } };
        worksheet.getCell(`E${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`E${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`E${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        // Total Vencido
        worksheet.mergeCells(`I${linhaAtual}:L${linhaAtual}`);
        worksheet.getCell(`I${linhaAtual}`).value = 'Total Vencido';
        worksheet.getCell(`I${linhaAtual}`).font = { size: 9, color: { argb: 'FFDC3545' } };
        worksheet.getCell(`I${linhaAtual}`).alignment = { vertical: 'top', horizontal: 'center' };
        worksheet.getCell(`I${linhaAtual}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`I${linhaAtual}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        
        worksheet.mergeCells(`I${linhaAtual + 1}:L${linhaAtual + 1}`);
        worksheet.getCell(`I${linhaAtual + 1}`).value = dados.totais.total_vencido;
        worksheet.getCell(`I${linhaAtual + 1}`).numFmt = 'R$ #,##0.00';
        worksheet.getCell(`I${linhaAtual + 1}`).font = { size: 14, bold: true, color: { argb: 'FFDC3545' } };
        worksheet.getCell(`I${linhaAtual + 1}`).alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getCell(`I${linhaAtual + 1}`).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        worksheet.getCell(`I${linhaAtual + 1}`).border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        worksheet.getRow(linhaAtual).height = 18;
        worksheet.getRow(linhaAtual + 1).height = 30;
        linhaAtual += 3;

        // Seção de detalhamento
        worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
        const secaoDetalhesCell = worksheet.getCell(`A${linhaAtual}`);
        secaoDetalhesCell.value = 'DETALHAMENTO DAS CONTAS';
        secaoDetalhesCell.font = { size: 11, bold: true, color: { argb: 'FF333333' } };
        secaoDetalhesCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
        secaoDetalhesCell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
        secaoDetalhesCell.border = {
            left: { style: 'thick', color: { argb: 'FF333333' } },
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
            right: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };
        worksheet.getRow(linhaAtual).height = 20;
        linhaAtual++;

        const cabecalho = [
            'Data Vencimento',
            'Descrição',
            'Fornecedor',
            'Documento',
            'Categoria',
            'Banco',
            'Forma Pagamento',
            'Valor Total',
            'Valor Pago',
            'Valor Restante',
            'Status',
            'Observações'
        ];

        const headerRow = worksheet.getRow(linhaAtual);
        headerRow.values = cabecalho;
        headerRow.font = { bold: true, size: 10, color: { argb: 'FFFFFFFF' } };
        headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
        headerRow.alignment = { vertical: 'middle', horizontal: 'center' };
        headerRow.height = 22;
        
        headerRow.eachCell((cell) => {
            cell.border = {
                top: { style: 'thin', color: { argb: 'FF333333' } },
                left: { style: 'thin', color: { argb: 'FF333333' } },
                bottom: { style: 'thin', color: { argb: 'FF333333' } },
                right: { style: 'thin', color: { argb: 'FF333333' } }
            };
        });

        linhaAtual++;

        dados.contas.forEach((conta, index) => {
            const row = worksheet.getRow(linhaAtual);
            row.values = [
                conta.data_vencimento,
                conta.descricao,
                conta.fornecedor,
                conta.documento,
                conta.categoria,
                conta.banco,
                conta.forma_pagamento,
                conta.valor_total,
                conta.valor_pago,
                conta.valor_restante,
                conta.status,
                conta.observacoes
            ];

            row.getCell(8).numFmt = 'R$ #,##0.00';
            row.getCell(9).numFmt = 'R$ #,##0.00';
            row.getCell(10).numFmt = 'R$ #,##0.00';

            // Alinhamento dos valores
            row.getCell(1).alignment = { vertical: 'middle', horizontal: 'center' }; // Data
            row.getCell(8).alignment = { vertical: 'middle', horizontal: 'right' };  // Valor Total
            row.getCell(9).alignment = { vertical: 'middle', horizontal: 'right' };  // Valor Pago
            row.getCell(10).alignment = { vertical: 'middle', horizontal: 'right' }; // Valor Restante
            row.getCell(11).alignment = { vertical: 'middle', horizontal: 'center' }; // Status

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

            row.height = 18;
            linhaAtual++;
        });

        worksheet.columns = [
            { key: 'vencimento', width: 15 },
            { key: 'descricao', width: 30 },
            { key: 'fornecedor', width: 25 },
            { key: 'documento', width: 15 },
            { key: 'categoria', width: 20 },
            { key: 'banco', width: 20 },
            { key: 'forma', width: 20 },
            { key: 'valor_total', width: 15 },
            { key: 'valor_pago', width: 15 },
            { key: 'valor_restante', width: 15 },
            { key: 'status', width: 20 },
            { key: 'obs', width: 30 }
        ];

        // Rodapé
        linhaAtual += 2;
        worksheet.mergeCells(`A${linhaAtual}:L${linhaAtual}`);
        const rodapeCell = worksheet.getCell(`A${linhaAtual}`);
        rodapeCell.value = 'Relatório gerado pelo Sistema GestorNow';
        rodapeCell.font = { size: 9, color: { argb: 'FF666666' }, italic: true };
        rodapeCell.alignment = { vertical: 'middle', horizontal: 'center' };
        rodapeCell.border = {
            top: { style: 'thin', color: { argb: 'FFDDDDDD' } }
        };

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { 
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
        });
        
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'relatorio-contas-pagar-' + getDataAtualExcel() + '.xlsx';
        link.click();
        URL.revokeObjectURL(url);

    } catch (error) {
        console.error('Erro ao gerar Excel:', error);
        alert('Erro ao gerar Excel: ' + error.message);
    }
}

function getDataAtualExcel() {
    const data = new Date();
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}
