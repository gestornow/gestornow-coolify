## Resumo
Foi implementado o map centralizado de log para o modulo de Faturamento de Locacoes, com eventos de geracao, edicao, cancelamento e faturamento em lote. Tambem foi feita a integracao no fluxo financeiro para registrar cancelamentos e lote via ActionLogger, mantendo a arquitetura ja existente de observer + maps.

## Arquivos Criados
| Caminho | Responsabilidade |
| --- | --- |
| `app/ActivityLog/Maps/FaturamentoLocacaoMap.php` | Define entidade, tags, label, valor e eventos de log do faturamento de locacao. |

## Arquivos Modificados
- Caminho completo: `app/ActivityLog/LogMap.php`
- Trecho que existia antes:
```php
use App\ActivityLog\Maps\FluxoCaixaMap;
use App\ActivityLog\Maps\LocacaoDespesaMap;
```
```php
'LocacaoTrocaProduto' => LocacaoTrocaProdutoMap::class,
```
- Trecho adicionado:
```php
use App\ActivityLog\Maps\FaturamentoLocacaoMap;
```
```php
'LocacaoTrocaProduto' => LocacaoTrocaProdutoMap::class,
'FaturamentoLocacao' => FaturamentoLocacaoMap::class,
```
- Motivo da alteracao: Registrar o novo map no roteador central de logs para habilitar resolucao automatica por model.

- Caminho completo: `app/Models/FaturamentoLocacao.php`
- Trecho que existia antes:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
```
```php
use HasFactory, SoftDeletes;
```
- Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
```
```php
use HasFactory, SoftDeletes, RegistraAtividade;
```
- Motivo da alteracao: Habilitar captura automatica de `created` e `updated` pelo `UniversalObserver`.

- Caminho completo: `app/Http/Controllers/Financeiro/FaturamentoController.php`
- Trecho que existia antes:
```php
use App\Http\Controllers\Controller;
```
```php
foreach ($faturamentos as $faturamento) {
    $faturamento->update([
        'id_conta_receber' => $contaReceber->id_contas,
    ]);
}

DB::commit();
```
```php
// Excluir o faturamento
$faturamento->delete();
```
```php
foreach ($faturamentos as $faturamento) {
    $faturamento->delete();
}
```
- Trecho adicionado:
```php
use App\ActivityLog\ActionLogger;
```
```php
foreach ($faturamentos as $faturamento) {
    $faturamento->update([
        'id_conta_receber' => $contaReceber->id_contas,
    ]);
}

if (!empty($faturamentos)) {
    ActionLogger::log($faturamentos[0], 'faturamento_lote');
}

DB::commit();
```
```php
ActionLogger::log($faturamento, 'cancelamento');

// Excluir o faturamento
$faturamento->delete();
```
```php
foreach ($faturamentos as $faturamento) {
    ActionLogger::log($faturamento, 'cancelamento');
    $faturamento->delete();
}
```
- Motivo da alteracao: Registrar eventos customizados de negocio (cancelamento e lote) no ponto em que a operacao ja foi aplicada com sucesso no fluxo transacional.

## Eventos Registrados
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
| --- | --- | --- | --- | --- |
| `created` | `fatura_locacao.gerada` | Gerou fatura #{numero_fatura} para locacao #{numero_contrato} - {cliente} - R$ {valor} - Venc.: {data_vencimento} | `fatura_gerada`, `financeiro` + tags padrao | verde |
| `updated` | `fatura_locacao.editada` | Editou fatura... e inclui sufixo de alteracao de valor e/ou vencimento quando detectado | `edicao` + tags padrao | amarelo |
| `cancelamento` | `fatura_locacao.cancelada` | Cancelou fatura... e, se existir `id_conta_receber`, menciona cancelamento automatico da conta | `cancelamento`, `financeiro` + tags padrao | vermelho |
| `faturamento_lote` | `fatura_locacao.lote_gerado` | Gerou lote de faturamento #{id_grupo_faturamento}... | `lote`, `faturamento`, `financeiro` + tags padrao | verde-escuro |

## Cadeia de Rastreabilidade Financeira
Locacao encerrada
  -> [LocacaoMap: `locacao.encerrada` - `app/ActivityLog/Maps/LocacaoMap.php`]
  -> Fatura gerada
  -> [FaturamentoLocacaoMap: `fatura_locacao.gerada` - `app/ActivityLog/Maps/FaturamentoLocacaoMap.php`]
  -> Conta a receber criada
  -> [ContasReceberMap: `conta_receber.criada` - `app/ActivityLog/Maps/ContasReceberMap.php`]
  -> Baixa da conta a receber
  -> [ContasReceberMap: `conta_receber.baixa` - `app/ActivityLog/Maps/ContasReceberMap.php`]

## Comportamento no Cancelamento
- O cancelamento de fatura no controller financeiro remove (soft delete) a conta a receber vinculada quando individual ou ultimo item do lote.
- Em lote com mais de uma fatura, recalcula o valor da conta consolidada.
- O cancelamento e automatico no mesmo fluxo do endpoint (`cancelar` e `cancelarLote`), sem job assincrono.
- Logs gerados:
  - `fatura_locacao.cancelada` (manual via `ActionLogger::log(..., 'cancelamento')`)
  - Nao ha log `deleted` no map de faturamento para evitar duplicidade no fluxo de cancelamento.
- Rastreio recomendado:
  1. Buscar `acao = fatura_locacao.cancelada` na entidade `FaturamentoLocacao`.
  2. Ler `id_conta_receber` no snapshot (`depois` do created/updated e no estado do model no momento do cancelamento).
  3. Conferir eventos da `ContaReceber` pelo id relacionado.

## Comportamento no Faturamento em Lote
- O lote e processado no endpoint `faturarLote`, com criacao de multiplas faturas e uma conta consolidada (ou parcelamento).
- Quantidade de logs por lote:
  - 1 log customizado de lote (`fatura_locacao.lote_gerado`) por chamada de lote.
  - Logs de `created` podem ocorrer por cada fatura (observer do model).
- Rastreio do lote:
  - Usar `id_grupo_faturamento` nas faturas do grupo.
  - Correlacionar com o registro de `fatura_locacao.lote_gerado`.

## Exemplo Real de Registro
1) Geracao de fatura com conta a receber vinculada
```json
{
  "id_empresa": 1,
  "id_usuario": 153,
  "acao": "fatura_locacao.gerada",
  "descricao": "Gerou fatura #19 para locacao #009 - Cliente Exemplo - R$ 1.250,00 - Venc.: 25/03/2026",
  "entidade_tipo": "FaturamentoLocacao",
  "entidade_id": 114,
  "entidade_label": "Fatura #19 - Contrato #009 - Cliente Exemplo - R$ 1.250,00",
  "valor": 1250,
  "contexto": {
    "evento": "created"
  },
  "antes": null,
  "depois": {
    "id_faturamento_locacao": 114,
    "id_empresa": 1,
    "id_locacao": 114,
    "id_usuario": 153,
    "numero_fatura": 19,
    "id_cliente": 1054,
    "id_conta_receber": 8821,
    "id_grupo_faturamento": null,
    "descricao": "Faturamento Locacao #009",
    "valor_total": "1250.00",
    "data_faturamento": "2026-03-08",
    "data_vencimento": "2026-03-25",
    "status": "faturado",
    "origem": "encerramento_locacao",
    "observacoes": "Gerado automaticamente"
  }
}
```

2) Cancelamento de fatura com conta a receber cancelada
```json
{
  "id_empresa": 1,
  "id_usuario": 153,
  "acao": "fatura_locacao.cancelada",
  "descricao": "Cancelou fatura #19 da locacao #009 - Cliente Exemplo - R$ 1.250,00 - Conta a receber #8821 tambem cancelada automaticamente",
  "entidade_tipo": "FaturamentoLocacao",
  "entidade_id": 114,
  "entidade_label": "Fatura #19 - Contrato #009 - Cliente Exemplo - R$ 1.250,00",
  "valor": 1250,
  "contexto": {
    "evento": "cancelamento"
  },
  "antes": null,
  "depois": null
}
```

## Pendencias e Observacoes
- A infraestrutura atual do `ActivityMap` nao possui campo nativo para injetar `contexto` rico por evento no observer automatico (`created`, `updated`).
- O cancelamento e lote foram integrados via `ActionLogger::log` conforme arquitetura existente; o `contexto` desses eventos segue o padrao atual (`{"evento": "..."}`).
- Se for necessario contexto financeiro consolidado detalhado (lista de faturas no lote, CPF/CNPJ expandido, etc.) em um unico registro, o ajuste recomendado e usar `ActionLogger::logDireto` nesses pontos, mantendo a mesma infraestrutura atual.
