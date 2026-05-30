## Resumo
Foram implementados os Maps de log para `TabelaPreco` e `Patrimonio`, com registro no `LogMap` e integracao com `RegistraAtividade` para eventos CRUD.
Tambem foi adicionada a integracao de eventos customizados manuais de patrimonio (ativacao, inativacao, descarte e disponibilizado) no fluxo de `update` do `PatrimonioController`.
A decisao sobre mudancas automaticas de `status_locacao` foi documentada para evitar log customizado duplicado no modulo de Patrimonios.

## Arquivos Criados
| Caminho | Responsabilidade |
|---|---|
| `app/ActivityLog/Maps/TabelaPrecosMap.php` | Map de log da entidade `TabelaPreco` (CRUD, label, tags e contagem de faixas d* alteradas na descricao de edicao). |
| `app/ActivityLog/Maps/PatrimoniosMap.php` | Map de log da entidade `Patrimonio` (CRUD + eventos manuais confirmados: ativacao, inativacao, descarte, disponibilizado). |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
Trecho que existia antes:
```php
use App\ActivityLog\Maps\FluxoCaixaMap;
use App\ActivityLog\Maps\ProdutosMap;

private static array $maps = [
    'ContaPagar' => ContasPagarMap::class,
    'ContaReceber' => ContasReceberMap::class,
    'Cliente' => ClientesMap::class,
    'FluxoCaixa' => FluxoCaixaMap::class,
    'Produto' => ProdutosMap::class,
];
```
Trecho adicionado:
```php
use App\ActivityLog\Maps\PatrimoniosMap;
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
Motivo da alteracao:
- Registrar os novos Maps para que `ActionLogger`/`UniversalObserver` consigam resolver configuracoes de log para as duas entidades.

### 2) `app/Domain/Produto/Models/TabelaPreco.php`
Trecho que existia antes:
```php
use Illuminate\Database\Eloquent\Model;

class TabelaPreco extends Model
{
    use SoftDeletes;
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class TabelaPreco extends Model
{
    use SoftDeletes, RegistraAtividade;
```
Motivo da alteracao:
- Habilitar captura automatica de `created`, `updated` e `deleted` via `UniversalObserver`.

### 3) `app/Domain/Produto/Models/Patrimonio.php`
Trecho que existia antes:
```php
use Illuminate\Database\Eloquent\Model;

class Patrimonio extends Model
{
    use SoftDeletes;
```
Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class Patrimonio extends Model
{
    use SoftDeletes, RegistraAtividade;
```
Motivo da alteracao:
- Habilitar captura automatica de `created`, `updated` e `deleted` via `UniversalObserver`.

### 4) `app/Http/Controllers/Produto/PatrimonioController.php`
Trecho que existia antes:
```php
use App\Http\Controllers\Controller;

$patrimonio->update($data);
```
Trecho adicionado:
```php
use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;

$statusAnterior = (string) ($patrimonio->status ?? '');
$statusLocacaoAnterior = (string) ($patrimonio->status_locacao ?? '');

$patrimonio->update($data);
$patrimonio->refresh();

$statusNovo = (string) ($patrimonio->status ?? '');
$statusLocacaoNovo = (string) ($patrimonio->status_locacao ?? '');

if ($statusAnterior !== $statusNovo) {
    if ($statusNovo === 'Ativo') {
        ActionLogger::log($patrimonio, 'ativacao');
    }
    if ($statusNovo === 'Inativo') {
        ActionLogger::log($patrimonio, 'inativacao');
    }
    if ($statusNovo === 'Descarte') {
        ActionLogger::log($patrimonio, 'descarte');
    }
}

if ($statusLocacaoAnterior !== $statusLocacaoNovo && $statusLocacaoNovo === 'Disponivel') {
    ActionLogger::log($patrimonio, 'disponibilizado');
}
```
Motivo da alteracao:
- Registrar eventos customizados manuais confirmados durante atualizacao de patrimonio, sem alterar regra de negocio existente.

## Eventos Registrados - Tabela de Precos
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `tabela_precos.criada` | `Criou tabela de precos '{nome}' para o produto {produto}` | `tabela_precos`, `produtos`, `financeiro`, `novo_cadastro` | `verde` |
| `updated` | `tabela_precos.editada` | `Editou tabela de precos '{nome}' do produto {produto} - {X} faixas de preco alteradas` | `tabela_precos`, `produtos`, `financeiro`, `edicao` | `amarelo` |
| `deleted` | `tabela_precos.excluida` | `Excluiu tabela de precos '{nome}' do produto {produto}` | `tabela_precos`, `produtos`, `financeiro`, `exclusao` | `vermelho` |

## Eventos Registrados - Patrimonios
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
|---|---|---|---|---|
| `created` | `patrimonio.criado` | `Registrou patrimonio #{id} (S/N: {serie}) do produto {produto}` | `patrimonios`, `estoque`, `novo_cadastro` | `verde` |
| `updated` | `patrimonio.editado` | `Editou patrimonio #{id} (S/N: {serie}) do produto {produto}` | `patrimonios`, `estoque`, `edicao` | `amarelo` |
| `deleted` | `patrimonio.excluido` | `Excluiu patrimonio #{id} (S/N: {serie}) do produto {produto} - valor de aquisicao: R$ {valor}` | `patrimonios`, `estoque`, `exclusao` | `vermelho` |
| `ativacao` | `patrimonio.ativado` | `Ativou o patrimonio #{id} (S/N: {serie}) do produto {produto}` | `patrimonios`, `estoque`, `status` | `verde` |
| `inativacao` | `patrimonio.inativado` | `Inativou o patrimonio #{id} (S/N: {serie}) do produto {produto}` | `patrimonios`, `estoque`, `status` | `cinza` |
| `descarte` | `patrimonio.descartado` | `Descartou o patrimonio #{id} (S/N: {serie}) do produto {produto} - valor de aquisicao: R$ {valor}` | `patrimonios`, `estoque`, `status`, `descarte` | `vermelho-escuro` |
| `disponibilizado` | `patrimonio.disponibilizado` | `Patrimonio #{id} (S/N: {serie}) marcado como disponivel` | `patrimonios`, `estoque`, `locacao`, `disponivel` | `azul` |

## Logica de Contagem de Faixas Alteradas
No evento `tabela_precos.updated`, a contagem e feita em `TabelaPrecosMap::contarFaixasAlteradas()`:
- Le os campos alterados com `array_keys($tabela->getChanges())`.
- Cruza esse conjunto com os campos de faixa `d1..d30,d60,d120,d360`.
- Usa o total desse cruzamento na descricao (`{X} faixas de preco alteradas`).

Observacao de arquitetura atual:
- No modo aprovado (opcao A), `antes/depois/contexto` do CRUD continuam sendo gerados pelo `UniversalObserver` padrao.
- Portanto, a formatacao de `antes/depois` em R$ por faixa e enriquecimento total de contexto por evento nao foi customizada no observer/infraestrutura.

## Ciclo de Vida do Patrimonio
Sequencia operacional possivel e onde cada log fica:
- Criado: `patrimonio.criado` (CRUD observer via `PatrimoniosMap`).
- Disponivel: `patrimonio.disponibilizado` (manual no `PatrimonioController@update` quando `status_locacao` vira `Disponivel`).
- Locado: mudanca automatica no modulo de locacao/estoque (`EstoqueService`), sem evento customizado de Patrimonio neste escopo.
- Devolvido: mudanca automatica via locacao/estoque (`EstoqueService`), sem evento customizado de Patrimonio neste escopo.
- Manutencao: mudancas automaticas em `ManutencaoEstoqueService`, sem evento customizado de Patrimonio neste escopo.
- Retorno: mudanca automatica de manutencao/locacao, sem evento customizado de Patrimonio neste escopo.
- Avaria: tratada no modulo de locacao/retorno (status de retorno), sem evento customizado dedicado em Patrimonio neste escopo.
- Descarte: `patrimonio.descartado` (manual no `PatrimonioController@update` quando `status` vira `Descarte`).

## Decisao sobre Status Automatico de Patrimonios
Mudancas automaticas (origem locacao/manutencao):
- `Disponivel -> Locado` em `EstoqueService::registrarSaidaLocacao`.
- `Locado -> Disponivel/Em Manutencao/Extraviado` em `EstoqueService::registrarRetornoLocacao`.
- `Disponivel -> Em Manutencao` e `Em Manutencao -> Disponivel` em `ManutencaoEstoqueService`.

Mudancas manuais (origem patrimonio):
- Edicao via `PatrimonioController@update` com alteracao de `status` e/ou `status_locacao`.

Onde cada uma e logada e por que:
- Automaticas: sem chamada customizada `ActionLogger::log(...evento_manual...)` no modulo Patrimonio, para evitar duplicidade de semantica de negocio.
- Manuais: chamadas customizadas no `PatrimonioController@update` para `ativacao`, `inativacao`, `descarte` e `disponibilizado`.
- CRUD geral permanece no observer universal por decisao aprovada (opcao A).

## Exemplo Real de Registro
### 1) Exemplo JSON - avaria de patrimonio que estava locado
Observacao: neste escopo nao foi implementado evento customizado `patrimonio.avaria` porque a operacao ocorre no fluxo de retorno da locacao e nao havia endpoint/manual dedicado em `PatrimonioController`.
Exemplo de registro existente no padrao atual (evento `updated` automatico):
```json
{
  "id_empresa": 10,
  "acao": "patrimonio.editado",
  "descricao": "Editou patrimonio #386 (S/N: ABC123) do produto Cadeira Gamer XYZ",
  "entidade_tipo": "Patrimonio",
  "entidade_id": 386,
  "evento": "updated",
  "contexto": {
    "evento": "updated",
    "campos_alterados": ["status_locacao", "id_locacao_atual", "data_ultima_movimentacao", "observacoes"]
  },
  "antes": {
    "status_locacao": "Locado",
    "id_locacao_atual": 554,
    "observacoes": "Sem avarias"
  },
  "depois": {
    "status_locacao": "Em Manutencao",
    "id_locacao_atual": null,
    "observacoes": "Retorno com avaria no encosto"
  }
}
```

### 2) Exemplo JSON - edicao de tabela de precos com 3 faixas alteradas
```json
{
  "id_empresa": 10,
  "acao": "tabela_precos.editada",
  "descricao": "Editou tabela de precos 'Tabela Verao 2026' do produto Cadeira Gamer XYZ - 3 faixas de preco alteradas",
  "entidade_tipo": "TabelaPreco",
  "entidade_id": 21,
  "evento": "updated",
  "contexto": {
    "evento": "updated",
    "campos_alterados": ["d1", "d7", "d30"]
  },
  "antes": {
    "d1": "50.00",
    "d7": "35.00",
    "d30": "20.00"
  },
  "depois": {
    "d1": "60.00",
    "d7": "42.00",
    "d30": "24.00"
  }
}
```

## Pendencias e Observacoes
- O `UniversalObserver` atual nao permite customizar `contexto/antes/depois` por evento no Map; por isso os formatos detalhados (R$ em `antes/depois` e datas `dd/mm/yyyy` em todos os campos) nao foram aplicados nesses blocos do CRUD.
- Nao foram encontrados fluxos confirmados de `duplicacao` e `reajuste_lote` para `tabela_precos`; por confirmacao, nao foram implementados.
- Nao foi implementado evento customizado `transferencia` por falta de operacao manual confirmada em `PatrimonioController` com campo persistente de localizacao fisica (`localizacao_atual`) nesse fluxo.
- Nao foi implementado evento customizado `avaria` dedicado no modulo de Patrimonio neste escopo, pois a mudanca ocorre no fluxo automatico de retorno da locacao.

## Atualizacao de UX - Modal de Logs de Produtos
Foi implementada a navegacao por escopo dentro do modal de log de atividades do produto em `resources/views/produtos/index.blade.php`:
- Botao `Todos` (logs agregados do produto + patrimonios + tabelas de precos).
- Botao `Produto`.
- Botao `Patrimonios`.
- Botao `Tabela de Precos`.

Comportamento:
- Ao clicar nos filtros, o modal permanece aberto e atualiza os registros carregados no mesmo painel.
- Cada card agora mostra badge de origem (`Produto`, `Patrimonio`, `Tabela de Precos`) para facilitar leitura.

Backend de suporte:
- `ProdutoController::logsAtividades()` passou a aceitar `?escopo=todos|produto|patrimonios|tabela_precos`.
- O endpoint agrega logs relacionados ao `id_produto` consultando IDs de `patrimonios` e `tabela_precos` vinculados.
- O endpoint retorna `contagens` por escopo para exibir os totais nos botoes do modal.
