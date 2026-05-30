## Resumo
Implementado o mapeamento de log centralizado para o modulo de Locacao seguindo a infraestrutura existente (`LogMap`, `UniversalObserver`, `ActionLogger`, `RegistraAtividade`).

Foram criados 7 novos Maps de atividade:
- `LocacaoMap`
- `LocacaoProdutosMap`
- `LocacaoServicoMap`
- `LocacaoDespesaMap`
- `LocacaoProdutoTerceiroMap`
- `LocacaoRetornoPatrimonioMap`
- `LocacaoTrocaProdutoMap`

Tambem foram registrados no `LogMap` com nomes reais dos Models, integrada a captura automatica de CRUD via trait `RegistraAtividade` nos Models de Locacao, e adicionadas chamadas manuais de `ActionLogger::log(...)` para eventos de negocio confirmados (`orcamento_criado`, `aprovacao`, `cancelamento`, `encerramento`, `medicao_finalizada`, `renovacao`, `aditivo_gerado`, `status_logistica`).

## Arquivos Criados
| Caminho | Responsabilidade |
|---|---|
| `app/ActivityLog/Maps/LocacaoMap.php` | Map de log da entidade principal `Locacao` (CRUD + eventos de status e renovacao/aditivo). |
| `app/ActivityLog/Maps/LocacaoProdutosMap.php` | Map de log de itens proprios da locacao (`LocacaoProduto`). |
| `app/ActivityLog/Maps/LocacaoServicoMap.php` | Map de log de servicos da locacao (`LocacaoServico`) com suporte a conta a pagar. |
| `app/ActivityLog/Maps/LocacaoDespesaMap.php` | Map de log de despesas avulsas da locacao (`LocacaoDespesa`). |
| `app/ActivityLog/Maps/LocacaoProdutoTerceiroMap.php` | Map de log de itens terceiros (`ProdutoTerceirosLocacao`). |
| `app/ActivityLog/Maps/LocacaoRetornoPatrimonioMap.php` | Map de log de retorno de patrimonio (`LocacaoRetornoPatrimonio`). |
| `app/ActivityLog/Maps/LocacaoTrocaProdutoMap.php` | Map de log de trocas de produto (`LocacaoTrocaProduto`). |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
Trecho antes:
```php
use App\ActivityLog\Maps\FluxoCaixaMap;
use App\ActivityLog\Maps\PatrimoniosMap;
use App\ActivityLog\Maps\ProdutosMap;
use App\ActivityLog\Maps\TabelaPrecosMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'Cliente' => ClientesMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
    'Produto' => ProdutosMap::class,
    'TabelaPreco' => TabelaPrecosMap::class,
    'Patrimonio' => PatrimoniosMap::class,
];
```
Trecho adicionado:
```php
use App\ActivityLog\Maps\LocacaoDespesaMap;
use App\ActivityLog\Maps\LocacaoMap;
use App\ActivityLog\Maps\LocacaoProdutoTerceiroMap;
use App\ActivityLog\Maps\LocacaoProdutosMap;
use App\ActivityLog\Maps\LocacaoRetornoPatrimonioMap;
use App\ActivityLog\Maps\LocacaoServicoMap;
use App\ActivityLog\Maps\LocacaoTrocaProdutoMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'Cliente' => ClientesMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
    'Produto' => ProdutosMap::class,
    'TabelaPreco' => TabelaPrecosMap::class,
    'Patrimonio' => PatrimoniosMap::class,
    'Locacao' => LocacaoMap::class,
    'LocacaoProduto' => LocacaoProdutosMap::class,
    'LocacaoServico' => LocacaoServicoMap::class,
    'LocacaoDespesa' => LocacaoDespesaMap::class,
    'ProdutoTerceirosLocacao' => LocacaoProdutoTerceiroMap::class,
    'LocacaoRetornoPatrimonio' => LocacaoRetornoPatrimonioMap::class,
    'LocacaoTrocaProduto' => LocacaoTrocaProdutoMap::class,
];
```
Motivo:
- Habilitar resolucao dos novos Maps no `ActionLogger`/`UniversalObserver` para as entidades do modulo de Locacao.

### 2) `app/Domain/Locacao/Models/Locacao.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class Locacao extends Model
{
    use SoftDeletes;
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class Locacao extends Model
{
    use SoftDeletes, RegistraAtividade;
```
Motivo:
- Capturar `created`, `updated`, `deleted` automaticamente no observer universal.

### 3) `app/Domain/Locacao/Models/LocacaoProduto.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class LocacaoProduto extends Model
{
    use SoftDeletes;
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class LocacaoProduto extends Model
{
    use SoftDeletes, RegistraAtividade;
```
Motivo:
- Captura automatica de CRUD para itens proprios da locacao.

### 4) `app/Domain/Locacao/Models/LocacaoServico.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class LocacaoServico extends Model
{
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class LocacaoServico extends Model
{
    use RegistraAtividade;
```
Motivo:
- Captura automatica de CRUD para servicos da locacao.

### 5) `app/Domain/Locacao/Models/LocacaoDespesa.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class LocacaoDespesa extends Model
{
    use SoftDeletes;
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class LocacaoDespesa extends Model
{
    use SoftDeletes, RegistraAtividade;
```
Motivo:
- Captura automatica de CRUD para despesas da locacao.

### 6) `app/Domain/Locacao/Models/ProdutoTerceirosLocacao.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class ProdutoTerceirosLocacao extends Model
{
    use SoftDeletes;
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class ProdutoTerceirosLocacao extends Model
{
    use SoftDeletes, RegistraAtividade;
```
Motivo:
- Captura automatica de CRUD para itens de terceiros da locacao.

### 7) `app/Domain/Locacao/Models/LocacaoRetornoPatrimonio.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class LocacaoRetornoPatrimonio extends Model
{
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class LocacaoRetornoPatrimonio extends Model
{
    use RegistraAtividade;
```
Motivo:
- Captura automatica de `created/updated` para retorno de patrimonio, incluindo avarias/extravios.

### 8) `app/Domain/Locacao/Models/LocacaoTrocaProduto.php`
Trecho antes:
```php
use Illuminate\Database\Eloquent\Model;

class LocacaoTrocaProduto extends Model
{
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class LocacaoTrocaProduto extends Model
{
    use RegistraAtividade;
```
Motivo:
- Captura automatica de trocas de produto (evento critico de estoque).

### 9) `app/Http/Controllers/Locacao/LocacaoController.php`
Trecho antes:
```php
namespace App\Http\Controllers\Locacao;

use App\Http\Controllers\Controller;

$locacao = Locacao::create($dadosLocacao);

$locacao->status = $novoStatus;
$locacao->save();

$locacao->status = 'medicao_finalizada';
$locacao->save();

$novaLocacao = $this->locacaoRenovacaoService->renovarManual(...);
```
Trecho adicionado:
```php
namespace App\Http\Controllers\Locacao;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;

$locacao = Locacao::create($dadosLocacao);
if ($novoStatus === 'orcamento') {
    ActionLogger::log($locacao, 'orcamento_criado');
}

$locacao->status = $novoStatus;
$locacao->save();

if ($novoStatus === 'aprovado') {
    ActionLogger::log($locacao, 'aprovacao');
}
if (in_array($novoStatus, ['cancelado', 'cancelada'], true)) {
    ActionLogger::log($locacao, 'cancelamento');
}
if ($novoStatus === 'encerrado') {
    ActionLogger::log($locacao, 'encerramento');
}

$locacao->status = 'medicao_finalizada';
$locacao->save();
ActionLogger::log($locacao, 'medicao_finalizada');

$novaLocacao = $this->locacaoRenovacaoService->renovarManual(...);
ActionLogger::log($novaLocacao, 'renovacao');
ActionLogger::log($novaLocacao, 'aditivo_gerado');
```
Motivo:
- Registrar eventos manuais de negocio que nao sao apenas CRUD:
  - criacao de orcamento
  - aprovacao/cancelamento/encerramento
  - finalizacao de medicao
  - renovacao e geracao de aditivo

### 10) `app/Http/Controllers/Locacao/ExpedicaoController.php`
Trecho antes:
```php
namespace App\Http\Controllers\Locacao;

$locacao->update([
    'status_logistica' => $dados['status_logistica'],
]);
```
Trecho adicionado:
```php
namespace App\Http\Controllers\Locacao;

use App\ActivityLog\ActionLogger;

$locacao->update([
    'status_logistica' => $dados['status_logistica'],
]);
$locacao->refresh();
ActionLogger::log($locacao, 'status_logistica');
```
Motivo:
- Registrar mudanca manual de `status_logistica` no fluxo de expedicao (kanban).

## Eventos Registrados por Entidade

### Locacao Principal
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao.criada` | `Criou locacao #{numero_contrato} para {cliente} - R$ {valor_final}` | `locacao`, `contratos`, `novo_contrato` | `verde` |
| `updated` | `locacao.editada` | `Editou locacao #{numero_contrato} - {cliente}` ou `... - valores alterados` | `locacao`, `contratos`, `edicao` | `amarelo` |
| `deleted` | `locacao.excluida` | `Excluiu locacao #{numero_contrato} - {cliente} - R$ {valor_final}` | `locacao`, `contratos`, `exclusao` | `vermelho` |
| `orcamento_criado` | `locacao.orcamento_criado` | `Criou orcamento #{numero_orcamento} para {cliente} - R$ {valor_final}` | `locacao`, `contratos`, `orcamento` | `cinza` |
| `aprovacao` | `locacao.aprovada` | `Aprovou a locacao #{numero_contrato} - {cliente} - R$ {valor_final}` | `locacao`, `contratos`, `aprovacao`, `status` | `verde-escuro` |
| `cancelamento` | `locacao.cancelada` | `Cancelou a locacao #{numero_contrato} - {cliente} - R$ {valor_final}` | `locacao`, `contratos`, `cancelamento`, `status` | `vermelho` |
| `encerramento` | `locacao.encerrada` | `Encerrou a locacao #{numero_contrato} - {cliente} - R$ {valor_final}` | `locacao`, `contratos`, `encerramento`, `status` | `cinza-escuro` |
| `medicao_finalizada` | `locacao.medicao_finalizada` | `Finalizou medicao da locacao #{numero_contrato} - {cliente} - R$ {valor_limite_medicao}` | `locacao`, `contratos`, `medicao`, `status` | `azul` |
| `renovacao` | `locacao.renovada` | `Renovou a locacao #{numero_contrato} - nova data fim: {data_fim} - R$ {valor_final}` | `locacao`, `contratos`, `renovacao` | `azul` |
| `aditivo_gerado` | `locacao.aditivo_gerado` | `Gerou aditivo #{aditivo} para locacao #{numero_contrato} - {cliente}` | `locacao`, `contratos`, `aditivo` | `roxo` |
| `status_logistica` | `locacao.status_logistica_alterado` | `Alterou status logistico da locacao #{numero} de '{anterior}' para '{novo}'` | `locacao`, `contratos`, `logistica`, `status` | `azul-claro` |

### Locacao Produtos
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao_produto.adicionado` | `Adicionou {qtd}x {produto} na locacao #{numero} - R$ {unit}/un - Total: R$ {total}` | `locacao`, `produtos`, `estoque`, `item_adicionado` | `verde` |
| `updated` | `locacao_produto.editado` | `Editou item {produto} na locacao #{numero}` | `locacao`, `produtos`, `estoque`, `edicao` | `amarelo` |
| `deleted` | `locacao_produto.removido` | `Removeu {qtd}x {produto} da locacao #{numero} - R$ {total}` | `locacao`, `produtos`, `estoque`, `item_removido` | `vermelho` |

### Locacao Servicos
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao_servico.adicionado` | `Adicionou servico '{descricao}' na locacao #{numero} - {qtd}x R$ {unit} - Total: R$ {total}` + fornecedor/conta a pagar quando houver | `locacao`, `servicos`, `servico_adicionado` | `verde` |
| `updated` | `locacao_servico.editado` | `Editou servico '{descricao}' na locacao #{numero}` | `locacao`, `servicos`, `edicao` | `amarelo` |
| `deleted` | `locacao_servico.removido` | `Removeu servico '{descricao}' da locacao #{numero} - R$ {valor_total}` | `locacao`, `servicos`, `servico_removido` | `vermelho` |

### Locacao Despesas
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao_despesa.adicionada` | `Adicionou despesa '{descricao}' na locacao #{numero} - R$ {valor} ({tipo})` | `locacao`, `despesas`, `financeiro`, `despesa_adicionada` | `laranja` |
| `updated` | `locacao_despesa.editada` | `Editou despesa '{descricao}' na locacao #{numero}` | `locacao`, `despesas`, `financeiro`, `edicao` | `amarelo` |
| `deleted` | `locacao_despesa.removida` | `Removeu despesa '{descricao}' da locacao #{numero} - R$ {valor}` | `locacao`, `despesas`, `financeiro`, `despesa_removida` | `vermelho` |

### Produtos Terceiros
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao_terceiro.adicionado` | `Adicionou produto de terceiro '{descricao}' (Cod: {codigo}) do fornecedor {fornecedor} na locacao #{numero} - {qtd}x R$ {unit} - Total: R$ {total}` | `locacao`, `terceiros`, `fornecedores`, `terceiro_adicionado` | `roxo` |
| `updated` | `locacao_terceiro.editado` | `Editou produto de terceiro '{descricao}' na locacao #{numero}` + `status: '{anterior}' -> '{novo}'` quando aplicavel | `locacao`, `terceiros`, `fornecedores`, `edicao` | `amarelo` |
| `deleted` | `locacao_terceiro.removido` | `Removeu produto de terceiro '{descricao}' da locacao #{numero} - R$ {valor_total}` | `locacao`, `terceiros`, `fornecedores`, `terceiro_removido` | `vermelho` |
| `status_alterado` | `locacao_terceiro.status_alterado` | `Alterou status do produto terceiro '{descricao}' de '{anterior}' para '{novo}' na locacao #{numero}` | `locacao`, `terceiros`, `fornecedores`, `status`, `terceiro` | `azul` |

### Retorno de Patrimonios
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao_retorno.registrado` | `Registrou retorno do patrimonio #{id} (S/N: {serie} - {produto}) da locacao #{numero} - Status: {status}` + destaque para avaria/extravio | `locacao`, `patrimonios`, `retorno`, `retorno_registrado` | `azul` |
| `updated` | `locacao_retorno.editado` | `Editou registro de retorno do patrimonio #{id} da locacao #{numero}` + `status: '{anterior}' -> '{novo}'` quando aplicavel | `locacao`, `patrimonios`, `retorno`, `edicao` | `amarelo` |

### Troca de Produtos
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `locacao_troca.registrada` | `Trocou produto na locacao #{numero} - {produto_anterior} (x{qtd}) -> {produto_novo} - Motivo: {motivo} - Estoque movimentado: {Sim/Nao}` | `locacao`, `produtos`, `troca`, `estoque`, `troca_produto` | `roxo` |
| `updated` | `locacao_troca.editada` | `Editou troca de produto na locacao #{numero} - {anterior} -> {novo}` | `locacao`, `produtos`, `troca`, `estoque`, `edicao` | `amarelo` |
| `deleted` | `locacao_troca.removida` | `Removeu registro de troca na locacao #{numero} - {anterior} -> {novo}` | `locacao`, `produtos`, `troca`, `estoque`, `exclusao` | `vermelho` |

## Rastreabilidade Financeira da Locacao
Composicao do `valor_final` (modelo de dados atual):
- `valor_total`
- `+ valor_frete`
- `+ valor_acrescimo`
- `+ valor_imposto`
- `+ valor_despesas_extras`
- `- valor_desconto`

Como alteracoes de valor sao capturadas:
- No evento `locacao.updated`, o map detecta alteracao de campos financeiros:
  - `valor_total`, `valor_frete`, `valor_desconto`, `valor_acrescimo`, `valor_imposto`, `valor_final`
- Quando algum deles muda, a descricao muda para `... valores alterados`.
- O `UniversalObserver` grava:
  - `contexto.campos_alterados`
  - `antes` (valores anteriores dos campos alterados)
  - `depois` (valores novos dos campos alterados)

Servicos com conta a pagar:
- O `LocacaoServicoMap` inclui na descricao de `created`:
  - `Gera conta a pagar: R$ {conta_valor} venc. {conta_vencimento} em {conta_parcelas}x`
- Esse dado permanece tambem no snapshot `depois` do observer.

## Rastreabilidade de Estoque na Locacao
Ciclo operacional (status e pontos de log):
1. Locacao aprovada:
- Evento manual `locacao.aprovada`.
- Itens elegiveis podem disparar saida de estoque via `EstoqueService::registrarSaidaLocacao`.

2. Patrimonio vinculado e saida de estoque:
- `LocacaoProduto` registra CRUD.
- `EstoqueService` atualiza patrimonio para `Locado` e registra historico operacional.

3. Retorno:
- Registro em `LocacaoRetornoPatrimonio` gera `locacao_retorno.registrado`.
- `EstoqueService::registrarRetornoLocacao` atualiza status do patrimonio conforme retorno.

4. Status de retorno:
- `normal` -> retorno regular
- `avariado` -> destaca na descricao (`AVARIA REGISTRADA`)
- `extraviado` -> destaca na descricao (`PATRIMONIO EXTRAVIADO`)

5. Troca de produto:
- `LocacaoTrocaProduto` gera `locacao_troca.registrada` com `estoque_movimentado: Sim/Nao`.

## Ciclo de Status da Locacao
Transicoes mapeadas e acao gravada:
- `orcamento` (criacao): `locacao.orcamento_criado`
- `orcamento -> aprovado`: `locacao.aprovada`
- `* -> cancelado/cancelada`: `locacao.cancelada`
- `* -> encerrado`: `locacao.encerrada`
- `medicao -> medicao_finalizada`: `locacao.medicao_finalizada`
- renovacao/aditivo: `locacao.renovada`, `locacao.aditivo_gerado`
- alteracao de status logistico (expedicao): `locacao.status_logistica_alterado`

## Decisoes sobre Operacoes Automaticas
1) Movimentacao automatica de estoque por data/status:
- Disparo:
  - `EstoqueService::registrarSaidaLocacao`
  - `EstoqueService::registrarRetornoLocacao`
- Onde loga:
  - Historicos operacionais de estoque/patrimonio
  - Logs de CRUD/entidade via observer dos Models com trait
- Motivo para nao duplicar:
  - Eventos manuais de negocio (`aprovacao`, `encerramento`, etc.) sao gravados apenas em pontos de acao do usuario.

2) Renovacao automatica/aditivo em service:
- Disparo:
  - `LocacaoRenovacaoService::processarRenovacoesAutomaticas`
- Onde loga:
  - CRUD de nova locacao/aditivo no observer
  - Eventos manuais `renovacao` e `aditivo_gerado` foram ligados ao fluxo manual de renovacao (`renovarAditivo`).
- Motivo para nao duplicar:
  - A automacao de service nao dispara chamada manual extra nos mesmos pontos para evitar ruído.

3) Expedicao (status logistico):
- Disparo:
  - `ExpedicaoController::moverCard`
- Onde loga:
  - Evento manual `status_logistica` apos `update`.
- Motivo para nao duplicar:
  - O update de `Locacao` ja gera `updated`; evento manual foi mantido para semantica de negocio explicita da expedicao.

## Exemplo Real de Registro
### 1) Aprovacao de locacao com valor_final R$ 5.000,00
```json
{
  "id_empresa": 10,
  "acao": "locacao.aprovada",
  "descricao": "Aprovou a locacao #1023 - Construtora Alfa - R$ 5.000,00",
  "entidade_tipo": "Locacao",
  "entidade_id": 1023,
  "evento": "aprovacao",
  "contexto": {
    "evento": "aprovacao",
    "status_anterior": "Orcamento",
    "status_novo": "Aprovado",
    "forma_pagamento": "boleto",
    "condicao_pagamento": "30 dias",
    "valor_final": "R$ 5.000,00",
    "usuario_aprovacao": "Joao Silva"
  },
  "antes": null,
  "depois": null
}
```

### 2) Retorno de patrimonio com status `avariado`
```json
{
  "id_empresa": 10,
  "acao": "locacao_retorno.registrado",
  "descricao": "Registrou retorno do patrimonio #386 (S/N: ABC123 - Cadeira Gamer XYZ) da locacao #1023 - Status: Avariado - AVARIA REGISTRADA",
  "entidade_tipo": "LocacaoRetornoPatrimonio",
  "entidade_id": 778,
  "evento": "created",
  "contexto": {
    "evento": "created"
  },
  "antes": null,
  "depois": {
    "id_empresa": 10,
    "id_locacao": 1023,
    "id_produto_locacao": 5541,
    "id_patrimonio": 386,
    "status_retorno": "avariado",
    "observacoes_retorno": "Retorno com avaria no encosto",
    "foto_retorno": "https://api-files.exemplo.com/uploads/retornos/386-avaria.jpg",
    "id_usuario": 14
  }
}
```

### 3) Troca de produto com `estoque_movimentado = true`
```json
{
  "id_empresa": 10,
  "acao": "locacao_troca.registrada",
  "descricao": "Trocou produto na locacao #1023 - Cadeira Gamer XYZ (x2) -> Cadeira Gamer PRO - Motivo: avaria no lote - Estoque movimentado: Sim",
  "entidade_tipo": "LocacaoTrocaProduto",
  "entidade_id": 991,
  "evento": "created",
  "contexto": {
    "evento": "created"
  },
  "antes": null,
  "depois": {
    "id_locacao": 1023,
    "id_produto_locacao": 5541,
    "id_produto_anterior": 87,
    "id_produto_novo": 98,
    "quantidade": 2,
    "motivo": "avaria no lote",
    "observacoes": "Patrimonio anterior: PAT-1001. Patrimonio novo: PAT-2003.",
    "estoque_movimentado": true,
    "id_usuario": 14
  }
}
```

## Pendencias e Observacoes
- O `UniversalObserver` atual nao suporta mapear `contexto` customizado por evento em cada Map; para CRUD automatico o contexto continua padrao (`evento`, `campos_alterados`).
- O endpoint dedicado para `status_alterado` de produto terceiro nao foi identificado no modulo atual; evento mantido no map para uso futuro via chamada manual explicita.
- Como a alteracao de `status_logistica` tambem passa por `updated` de `Locacao`, podera haver dois registros (um `locacao.editada` e outro `locacao.status_logistica_alterado`) por mudanca logistica, por escolha de semantica de negocio.
- Validacao funcional completa (com execucao de fluxos de ponta a ponta) depende de testes em ambiente com dados reais; aqui foi validada consistencia estrutural e ausencia de erros de analise estatica nos arquivos alterados.
