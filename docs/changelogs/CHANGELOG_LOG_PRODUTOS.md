## Resumo
Foi implementado o map de log centralizado para o modulo de Produtos seguindo o padrao existente do projeto.
O map `ProdutosMap` foi criado, registrado no `LogMap`, e o model `Produto` foi integrado ao Observer via trait `RegistraAtividade` para eventos CRUD automaticos.
Tambem foi integrada chamada manual de log apenas para movimentacao manual de estoque (entrada/saida), sem duplicar logs de movimentacoes automaticas de locacao/manutencao.

## Arquivos Criados
| Caminho | Responsabilidade |
| --- | --- |
| `app/ActivityLog/Maps/ProdutosMap.php` | Definir configuracao de eventos de log para Produto (CRUD + entrada/saida de estoque), label, valor e campos de diff ignorados |
| `CHANGELOG_LOG_PRODUTOS.md` | Documentar implementacao, diffs, decisoes e limitacoes |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
- Trecho que existia antes:
```php
use App\ActivityLog\Maps\ContasReceberMap;
use App\ActivityLog\Maps\ClientesMap;
use App\ActivityLog\Maps\FluxoCaixaMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'Cliente' => ClientesMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
];
```
- Trecho adicionado para visualizar:
```php
use App\ActivityLog\Maps\ProdutosMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'Cliente' => ClientesMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
    'Produto' => ProdutosMap::class,
];
```
- Motivo da alteracao:
Registrar o novo map no resolvedor central para habilitar logs da entidade Produto.

### 2) `app/Domain/Produto/Models/Produto.php`
- Trecho que existia antes:
```php
namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes;
```
- Trecho adicionado:
```php
namespace App\Domain\Produto\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes, RegistraAtividade;
```
- Motivo da alteracao:
Conectar o model Produto ao `UniversalObserver` para captura automatica de `created`, `updated` e `deleted` sem alterar regra de negocio.

### 3) `app/Http/Controllers/Produto/ProdutoController.php`
- Trecho que existia antes:
```php
namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
```
- Trecho adicionado:
```php
namespace App\Http\Controllers\Produto;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
```
- Trecho que existia antes:
```php
$movimentacao = MovimentacaoEstoque::registrar(
    $id,
    $validated['tipo'],
    $validated['quantidade'],
    $validated['motivo'],
    $validated['observacoes']
);

// Recarregar produto para obter valores atualizados
$produto->refresh();
```
- Trecho adicionado:
```php
$movimentacao = MovimentacaoEstoque::registrar(
    $id,
    $validated['tipo'],
    $validated['quantidade'],
    $validated['motivo'],
    $validated['observacoes']
);

// Disponibiliza contexto para descricao detalhada do map sem alterar regra de negocio.
$produto->setAttribute('audit_quantidade_movimentada', (int) $movimentacao->quantidade);
$produto->setAttribute('audit_estoque_anterior', (int) $movimentacao->estoque_anterior);
$produto->setAttribute('audit_estoque_posterior', (int) $movimentacao->estoque_posterior);
ActionLogger::log($produto, $movimentacao->tipo === 'entrada' ? 'entrada_estoque' : 'saida_estoque');

// Recarregar produto para obter valores atualizados
$produto->refresh();
```
- Motivo da alteracao:
Registrar evento customizado apenas para movimentacao manual de estoque (entrada/saida) apos operacao bem-sucedida, evitando duplicidade de logs automaticos vindos de outros modulos.

## Eventos Registrados
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
| --- | --- | --- | --- | --- |
| `created` | `produto.criado` | `Cadastrou o produto {nome} (Cod: {codigo})` | `produtos`, `estoque`, `novo_cadastro` | `verde` |
| `updated` | `produto.editado` | `Editou o produto {nome}` ou `Editou o produto {nome} - precos alterados` | `produtos`, `estoque`, `edicao` | `amarelo` |
| `deleted` | `produto.excluido` | `Excluiu o produto {nome} (Cod: {codigo})` | `produtos`, `estoque`, `exclusao` | `vermelho` |
| `entrada_estoque` | `produto.entrada_estoque` | `Entrada de {qtd} unidades no estoque de {nome} - De: {antes} -> Para: {depois}` | `produtos`, `estoque`, `entrada` | `verde` |
| `saida_estoque` | `produto.saida_estoque` | `Saida de {qtd} unidades do estoque de {nome} - De: {antes} -> Para: {depois}` | `produtos`, `estoque`, `saida` | `laranja` |

## Rastreamento de Precos
Como alteracoes de preco sao capturadas:
- Via Observer automatico no evento `updated`.
- Nao existe no modulo atual um metodo dedicado de reajuste de preco para `Produto` (tabela `produtos`), entao nao foi criada chamada manual `reajuste_preco`.

O que aparece no campo descricao:
- Se algum campo de preco foi alterado (`preco`, `preco_reposicao`, `preco_custo`, `preco_venda`, `preco_locacao`), a descricao vira:
  - `Editou o produto {nome} - precos alterados`
- Caso contrario:
  - `Editou o produto {nome}`

O que aparece no campo contexto:
- Contexto padrao da infraestrutura para `updated` (`evento` e `campos_alterados`).
- Os pares antes/depois ficam nos campos JSON `antes` e `depois` do registro (gerados pelo Observer).

Exemplo de registro gerado:
- `acao`: `produto.editado`
- `descricao`: `Editou o produto Furadeira X - precos alterados`
- `antes.preco_venda`: `100.00`
- `depois.preco_venda`: `120.00`

## Rastreamento de Estoque
Como movimentacoes sao capturadas:
- Manuais (modulo Produtos):
  - endpoint `POST /produtos/{produto}/movimentacao-estoque`
  - gera `ActionLogger::log($produto, 'entrada_estoque'|'saida_estoque')`
- Automaticas (outros modulos):
  - locacao/devolucao/manutencao registram em `ProdutoHistorico` por servicos/controladores de origem.

O que aparece no campo descricao:
- Para manuais: quantidade, estoque anterior e estoque posterior, conforme exigido.

O que aparece nos campos antes/depois:
- Eventos manuais `entrada_estoque`/`saida_estoque` usam `ActionLogger::log` (antes/depois nulos por padrao da infraestrutura).
- Alteracao de `quantidade`/`estoque_total` tambem gera `updated` automatico via Observer com `antes`/`depois` desses campos.

Exemplo de registro gerado:
- `acao`: `produto.entrada_estoque`
- `descricao`: `Entrada de 10 unidades no estoque de Cadeira X - De: 5 -> Para: 15`
- `contexto.evento`: `entrada_estoque`

## Decisao sobre Movimentacoes Automaticas
Quais movimentacoes sao geradas por outros modulos:
- Locacoes/devolucoes e manutencoes registram no `ProdutoHistorico` e alteram quantidade via `EstoqueService`, `ManutencaoEstoqueService` e fluxos de `LocacaoController`.

Se geram log proprio aqui ou sao cobertas na origem:
- Foram tratadas como cobertas na origem; nao foi adicionada chamada de log manual nessas rotas/servicos para evitar duplicidade.

Justificativa da decisao:
- Ha rastreabilidade ja existente no modulo de origem para esses movimentos automaticos.
- Repetir log no modulo Produtos criaria eventos duplicados para a mesma operacao de negocio.

## Pendencias e Observacoes
- Nao foi identificado endpoint dedicado para `inativacao`, `reativacao`, `restauracao` ou `reajuste_preco` em `ProdutoController`; essas alteracoes seguem cobertas por `updated` quando ocorrem via edicao generica.
- O Observer atual mascara campos de `camposSensiveis()` com padrao interno do projeto (`[OCULTO]`), nao com `***`.
- O contexto detalhado de `created`/`updated` e controlado pela infraestrutura atual; nao foi alterado.
- Validacao funcional completa em runtime (requisoes HTTP + persistencia em `registro_atividade`) nao foi executada nesta etapa; foram validadas integridade de codigo e ausencia de erros de analise.
