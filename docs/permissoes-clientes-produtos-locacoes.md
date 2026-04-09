# Permissoes - Clientes, Produtos e Locacoes

## Objetivo
Documentar as alteracoes realizadas para controle de permissao no backend (rotas) e frontend (exibicao de botoes, abas e atalhos) nos modulos de Clientes, Produtos e Locacoes.

## Escopo aplicado
- Protecao de rotas com middleware `perm:<chave>`.
- Ocultacao de acoes na interface para evitar UX de clique em acao sem permissao.
- Filtro de menu/atalhos para mostrar apenas itens permitidos.
- Alinhamento com as chaves ja existentes no banco (sem criar novo namespace paralelo).

## Chaves de permissao usadas

### Clientes
- `clientes.visualizar`
- `clientes.criar`
- `clientes.editar`
- `clientes.excluir`

### Produtos
- `produtos.visualizar`
- `produtos.criar`
- `produtos.editar`
- `produtos.excluir`
- `produtos.movimentacao`
- `produtos.manutencao`
- `produtos.patrimonio`
- `produtos.tabela-precos`
- `produtos.acessorios`
- `produtos-venda.gerenciar`

### Locacoes
- `locacoes.visualizar`
- `locacoes.criar`
- `locacoes.editar`
- `locacoes.excluir`
- `locacoes.alterar-status`
- `locacoes.retornar`
- `locacoes.expedicao`
- `locacoes.contrato-pdf`
- `locacoes.assinatura-digital`
- `locacoes.trocar-produto`
- `locacoes.renovar`
- `locacoes.medicao`

## Arquivos alterados

### Rotas
- `routes/web.php`
  - Inclusao de middleware `perm:` nas rotas de Clientes, Produtos e Locacoes.
  - Associacao de cada endpoint a chave correspondente por acao.

### Provedor de menu
- `app/Providers/MenuServiceProvider.php`
  - Ajuste no filtro por rota para novo sistema de grupos.
  - Regras para:
    - `clientes` -> `clientes.visualizar`
    - `produtos*` e submodulos -> `produtos.visualizar`
    - `produtos-venda` -> `produtos-venda.gerenciar`
    - `locacoes/expedicao` -> `locacoes.expedicao`
    - `locacoes*` -> `locacoes.visualizar`

### Navbar e dashboard
- `resources/views/layouts/sections/navbar/navbar.blade.php`
  - Atalhos de Clientes, Produtos, Locacoes e Expedicao condicionados por permissao.

- `resources/views/content/dashboard/dashboards-analytics.blade.php`
  - Links "Ver todos" / "Ver locacoes" condicionados por permissao.

### Clientes (views)
- `resources/views/cliente/index.blade.php`
  - Guardas para criar, editar, excluir e exclusao em massa.

- `resources/views/cliente/show.blade.php`
  - Botao editar condicionado.

- `resources/views/cliente/edit.blade.php`
  - Botao deletar condicionado.

### Produtos (views)
- `resources/views/produtos/index.blade.php`
  - Guardas para criar, editar, excluir e exclusao em massa.

- `resources/views/produtos/show.blade.php`
  - Guardas para:
    - botao editar
    - abas de patrimonio/tabela/manutencao/acessorios
    - conteudo das abas
    - modais de cada modulo

- `resources/views/produtos/edit.blade.php`
  - Guardas para:
    - botao deletar
    - abas de patrimonio/tabela/manutencao/estoque
    - conteudo dessas abas

### Locacoes (views)
- `resources/views/locacoes/index.blade.php`
  - Guardas para criar, editar, imprimir, alterar status, retornar e renovar.

- `resources/views/locacoes/show.blade.php`
  - Guardas para editar, alterar status, imprimir e assinatura digital.

- `resources/views/locacoes/contratos.blade.php`
  - Guardas para novo contrato, expedicao, editar, PDF, assinatura digital,
    alterar status, retornar e renovar.

- `resources/views/locacoes/orcamentos.blade.php`
  - Guardas para novo orcamento, editar, imprimir e aprovar.

- `resources/views/locacoes/medicoes.blade.php`
  - Guardas para novo contrato de medicao, editar, imprimir,
    assinatura digital e acoes de medicao.

## Correcao importante realizada
Durante a implementacao, houve erro por chamada incorreta de permissao com apenas 1 argumento.

### Causa
`PermissaoService::pode()` exige 2 argumentos:
- usuario
- chave

### Padrao correto
Usar sempre:
- `\Perm::pode(auth()->user(), 'chave.permissao')`

## Resultado esperado
- Usuario sem permissao nao ve atalhos/acoes proibidas na UI.
- Mesmo que tente acessar URL direta, rota esta protegida por middleware.
- Comportamento consistente entre backend e frontend.

## Checklist rapido de validacao manual
1. Entrar com usuario sem permissoes de Locacoes e validar ocultacao de atalhos e botoes.
2. Entrar com usuario apenas `locacoes.visualizar` e validar acesso somente leitura.
3. Entrar com usuario com `locacoes.editar` e validar aparicao de editar.
4. Entrar com usuario sem `locacoes.contrato-pdf` e validar ausencia de opcoes de impressao.
5. Entrar com usuario sem `locacoes.assinatura-digital` e validar ausencia de enviar/visualizar assinatura.
6. Repetir padrao para Clientes e Produtos.

## Observacao
Este documento registra alteracoes funcionais de permissao. Nao substitui testes automatizados.
