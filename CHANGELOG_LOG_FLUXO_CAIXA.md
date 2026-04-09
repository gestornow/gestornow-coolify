## Resumo
Implementado o map de log para Fluxo de Caixa com model dedicado (`FluxoCaixa`) e integração de eventos manuais da tela de fluxo.
A implementação evita logs duplicados para lançamentos automáticos gerados por baixas em Contas a Pagar/Receber, mantendo o log no módulo de origem.

## Arquivos Criados
| Caminho | Responsabilidade |
|---|---|
| app/Models/FluxoCaixa.php | Model Eloquent da tabela `fluxo_caixa` para viabilizar observer/ActionLogger no padrão centralizado |
| app/ActivityLog/Maps/FluxoCaixaMap.php | Mapeamento de eventos de atividade de Fluxo de Caixa |
| CHANGELOG_LOG_FLUXO_CAIXA.md | Documentação da implementação |

## Arquivos Modificados
### 1) app/ActivityLog/LogMap.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/ActivityLog/LogMap.php
- Trecho que existia antes:
```php
use App\ActivityLog\Maps\ContasPagarMap;
use App\ActivityLog\Maps\ContasReceberMap;

private static array $maps = [
  'ContaPagar' => ContasPagarMap::class,
  'ContaReceber' => ContasReceberMap::class,
];
```
- Trecho adicionado:
```php
use App\ActivityLog\Maps\FluxoCaixaMap;

private static array $maps = [
  'ContaPagar' => ContasPagarMap::class,
  'ContaReceber' => ContasReceberMap::class,
  'FluxoCaixa' => FluxoCaixaMap::class,
];
```
- Motivo da alteração:
Registrar o novo map de Fluxo de Caixa no catálogo central de maps.

### 2) app/Http/Controllers/Financeiro/ContasAPagarController.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/Http/Controllers/Financeiro/ContasAPagarController.php
- Trecho que existia antes:
```php
$this->contasAPagarService->registrarPagamento(
  conta: $conta,
  valorPago: $valorBaixa,
  idFormaPagamento: $validated['id_forma_pagamento'] ?? null,
  idBanco: $validated['id_bancos'] ?? null,
  dataPagamento: $validated['data_pagamento'],
  observacoes: $validated['observacoes'] ?? null
);
```
- Trecho adicionado:
```php
use App\Models\FluxoCaixa;

$this->contasAPagarService->registrarPagamento(...);

$lancamentoFluxo = FluxoCaixa::where('id_fluxo', function ($query) use ($conta) {
  $query->from('pagamentos_contas_pagar')
    ->select('id_fluxo_caixa')
    ->where('id_conta_pagar', $conta->id_contas)
    ->orderByDesc('id_pagamento')
    ->limit(1);
})
  ->where('id_empresa', $id_empresa)
  ->first();

if ($lancamentoFluxo) {
  ActionLogger::log($lancamentoFluxo, 'lancamento_saida');
}
```
- Motivo da alteração:
Registrar evento de saída manual no fluxo quando a criação ocorre pela tela de Fluxo de Caixa (`redirect_to = fluxo-caixa`), sem impactar baixas automáticas de outros fluxos.

### 3) app/Http/Controllers/Financeiro/ContasAReceberController.php
- Caminho completo: c:/Users/Eduardo/Desktop/NovoGestorNow/gestornow-2.0/app/Http/Controllers/Financeiro/ContasAReceberController.php
- Trecho que existia antes:
```php
$this->contasAReceberService->registrarRecebimento(
  conta: $conta,
  valorRecebido: $valorBaixa,
  idFormaPagamento: $validated['id_forma_pagamento'] ?? null,
  idBanco: $validated['id_bancos'] ?? null,
  dataRecebimento: $validated['data_pagamento'],
  observacoes: $validated['observacoes'] ?? null
);
```
- Trecho adicionado:
```php
use App\Models\FluxoCaixa;

$this->contasAReceberService->registrarRecebimento(...);

$lancamentoFluxo = FluxoCaixa::where('id_fluxo', function ($query) use ($conta) {
  $query->from('pagamentos_contas_receber')
    ->select('id_fluxo_caixa')
    ->where('id_conta_receber', $conta->id_contas)
    ->orderByDesc('id_pagamento')
    ->limit(1);
})
  ->where('id_empresa', $id_empresa)
  ->first();

if ($lancamentoFluxo) {
  ActionLogger::log($lancamentoFluxo, 'lancamento_entrada');
}
```
- Motivo da alteração:
Registrar evento de entrada manual no fluxo quando a criação ocorre pela tela de Fluxo de Caixa (`redirect_to = fluxo-caixa`), sem duplicar logs de baixas automáticas.

## Eventos Registrados
| Evento | Ação gravada | Descrição gerada | Tags | Cor |
|---|---|---|---|---|
| created | fluxo_caixa.criado | Criou lançamento de entrada/saída no fluxo de caixa com valor | fluxo_caixa, financeiro, cadastro | verde |
| updated | fluxo_caixa.editado | Editou o lançamento #ID do fluxo de caixa | fluxo_caixa, financeiro, edicao | amarelo |
| deleted | fluxo_caixa.excluido | Excluiu lançamento de entrada/saída com valor | fluxo_caixa, financeiro, exclusao | vermelho |
| lancamento_entrada | fluxo_caixa.entrada | Lançamento de entrada com valor, categoria e conta destino | fluxo_caixa, financeiro, entrada, lancamento_manual | verde |
| lancamento_saida | fluxo_caixa.saida | Lançamento de saída com valor, categoria e conta origem | fluxo_caixa, financeiro, saida, lancamento_manual | vermelho |
| estorno | fluxo_caixa.estorno | Estornou lançamento #ID do fluxo com valor | fluxo_caixa, financeiro, estorno | laranja |

## Decisão sobre Lançamentos Automáticos
- Lançamentos em `fluxo_caixa` podem ser gerados automaticamente por:
  - Baixa de Contas a Pagar (`ContasAPagarService::registrarPagamento`)
  - Baixa de Contas a Receber (`ContasAReceberService::registrarRecebimento`)
- Esses fluxos já possuem log no módulo de origem (`conta_pagar.*` e `conta_receber.*`).
- Decisão: **não gerar log duplicado de Fluxo de Caixa para esses lançamentos automáticos**.
- Justificativa: evitar duplicidade semântica e manter rastreabilidade centrada no ato de negócio principal (baixa da conta).

## Pendências e Observações
- Não foi adicionada integração para eventos inexistentes no fluxo atual (transferência, ajuste, abertura, fechamento, conciliação).
- Não foram adicionadas chamadas de log para lançamentos automáticos em `darBaixa` de Contas a Pagar/Receber, pois já existe log no módulo de origem.
- A validação de sintaxe/diagnóstico dos arquivos alterados foi executada e não apontou erros.
