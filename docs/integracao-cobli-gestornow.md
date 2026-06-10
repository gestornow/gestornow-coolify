# Integracao Cobli no GestorNow

Guia pratico para entender como aplicar a Cobli no GestorNow, quais dados sincronizar e o que precisa estar pronto para iniciar.

## 1) Objetivo da integracao

A Cobli deve entrar no projeto como camada de operacao de frota e logistica.

O GestorNow continua sendo o sistema mestre para:
- clientes
- locacoes
- produtos
- servicos de locacao
- faturamento
- regras de negocio

A Cobli passa a ser a plataforma de apoio para:
- veiculos
- motoristas
- rotas
- ETA e acompanhamento operacional
- odometro e telemetria
- manutencao da frota
- combustivel e eventos operacionais em fases futuras

Essa separacao evita conflito de dados e reduz a complexidade da sincronizacao.

## 2) Onde isso encaixa no projeto atual

Hoje o GestorNow ja tem os blocos principais necessarios para sustentar essa integracao:

- Cliente: `App\Domain\Cliente\Models\Cliente`
- Locacao: `App\Domain\Locacao\Models\Locacao`
- Itens da locacao: `App\Domain\Locacao\Models\LocacaoProduto`
- Servicos da locacao: `App\Domain\Locacao\Models\LocacaoServico`
- Manutencao: `App\Domain\Produto\Models\Manutencao`

Pontos importantes do modelo atual:

- A locacao ja possui datas, horarios, endereco, contato e `status_logistica`.
- A locacao ja possui campos ligados a transporte, como `data_transporte_ida`, `hora_transporte_ida`, `data_transporte_volta` e `hora_transporte_volta`.
- O frete/transporte se encaixa melhor como servico da locacao do que como produto.
- A manutencao local hoje esta mais ligada a produto/patrimonio do que a frota. Se a operacao com Cobli crescer, vale separar um dominio proprio de veiculos.

## 3) Regra principal de desenho

Nao tente manter escrita bidirecional completa entre Cobli e GestorNow logo no inicio.

Comece assim:

- GestorNow cria e controla a locacao.
- GestorNow envia para a Cobli apenas os dados operacionais necessarios.
- Cobli devolve status da rota, motorista, veiculo, ETA e eventos.
- GestorNow grava esses retornos como dados operacionais vinculados a locacao.

Resumo:

- mestre de negocio: GestorNow
- mestre de operacao de frota: Cobli

## 4) O que sincronizar

### 4.1 Clientes

Nao sincronize cliente para a Cobli como cadastro mestre de CRM.

Use o cliente do GestorNow como origem para:
- endereco de entrega
- endereco de retirada
- nome do contato
- telefone do contato
- observacoes da operacao

Na pratica, o cliente entra na Cobli como destino, ponto de parada ou referencia operacional da rota.

### 4.2 Produtos

Nao trate os produtos do GestorNow como produtos da Cobli.

Os itens de locacao devem ser enviados para a operacao como:
- descricao da carga
- quantidade
- observacoes logisticas
- volume operacional, quando fizer sentido

Se o seu caso de uso for transporte de itens locados, o papel do produto na integracao e informar o que esta sendo levado, nao virar cadastro de estoque dentro da Cobli.

### 4.2.1 Veiculo como produto no GestorNow

Sim, isso e possivel, mas com uma regra importante:

- o `Produto` representa o cadastro base do veiculo no ERP
- o item fisico que deve conversar com a Cobli deve ser tratado como unidade identificavel

No desenho atual do projeto, a melhor forma de fazer isso e usar:

- `Produto` para representar o tipo/cadastro do veiculo
- `Patrimonio` para representar o veiculo fisico individual

Em outras palavras:

- `Produto`: categoria ou cadastro comercial do veiculo
- `Patrimonio`: o carro/caminhao/van especifico que tem identidade propria

Isso combina melhor com a Cobli porque a Cobli trabalha com veiculo individual, nao com item generico de catalogo.

Exemplo:

- Produto: `Fiorino 1.4 Flex`
- Patrimonio 1: placa `ABC1D23`
- Patrimonio 2: placa `XYZ9K88`

Os dois podem apontar para o mesmo produto, mas cada um precisa ter identidade propria para integracao.

### 4.2.2 Regra de uso com a Cobli

Se voce decidir cadastrar veiculos no GestorNow, o recomendado e:

- cadastrar o veiculo no GestorNow
- vincular esse cadastro a um patrimonio unico
- sincronizar esse patrimonio com a Cobli
- salvar o `cobli_vehicle_id` no cadastro local do veiculo

Na pratica, o objeto integrado com a Cobli nao deve ser o produto generico, e sim o registro do veiculo individual.

### 4.2.3 O que falta no modelo atual para isso ficar bom

Hoje `Produto` e `Patrimonio` ja ajudam, mas ainda faltam campos tipicos de frota.

Campos minimos recomendados para um veiculo integrado:

- placa
- renavam
- chassi
- marca
- modelo
- ano
- tipo de combustivel
- odometro atual
- `cobli_vehicle_id`
- status de sincronizacao

Voce pode guardar isso de duas formas:

- caminho rapido: adicionar campos em `patrimonios`
- caminho mais organizado: criar uma tabela auxiliar, por exemplo `patrimonios_veiculos`

### 4.2.4 Recomendacao pratica para o projeto

Se a sua pergunta for "posso cadastrar o veiculo no GestorNow e usa-lo na Cobli?", a resposta e:

- sim, pode
- mas o melhor desenho e usar `Produto + Patrimonio`, nao apenas `Produto`

Padrao recomendado:

1. Cadastra o veiculo no GestorNow.
2. Marca esse cadastro como item de frota.
3. Cria o patrimonio individual do veiculo.
4. Vincula o patrimonio ao registro da Cobli.
5. Quando a operacao precisar de um veiculo, o GestorNow escolhe o veiculo local e envia o `cobli_vehicle_id` correspondente.

### 4.2.5 Quando usar apenas produto e quando usar patrimonio

Use apenas `Produto` quando:

- o item for generico
- nao houver identidade individual relevante
- a Cobli nao precisar reconhecer esse item como veiculo unico

Use `Produto + Patrimonio` quando:

- cada unidade precisa ser identificada individualmente
- existe manutencao por unidade
- existe disponibilidade por unidade
- existe controle por serie, placa ou identificador
- o registro vai conversar com a Cobli como veiculo unico

### 4.3 Transporte

O transporte deve nascer da locacao.

O modelo atual ja ajuda porque a locacao possui:
- janela de saida e retorno
- local de entrega e retirada
- cidade, estado, CEP
- observacoes da entrega
- status logistico

Melhor pratica:

- manter frete/transporte como `LocacaoServico`
- usar a `Locacao` como origem da ordem de rota
- salvar o vinculo da rota da Cobli na locacao ou em tabela auxiliar

### 4.4 Manutencao

A Cobli possui endpoints para historico e operacao de manutencao.

Para o inicio, a recomendacao e:

- importar manutencoes da Cobli para consulta no GestorNow
- exibir custos, status e ocorrencias por veiculo
- nao fazer escrita bidirecional no primeiro momento

Quando a operacao amadurecer, voce decide entre:

- continuar apenas consumindo manutencao da Cobli
- ou criar um modulo proprio de frota no GestorNow, separado de produto/patrimonio

### 4.5 Motoristas

Pela decisao atual do projeto, os motoristas serao escolhidos no GestorNow e, num primeiro momento, farao parte da tabela `usuarios`.

Isso funciona bem como ponto de partida porque o cadastro de usuario ja possui dados como:
- nome
- telefone
- cpf

Mas, para integrar corretamente com a Cobli, voce precisa prever pelo menos estes campos adicionais para o usuario que tambem for motorista:
- indicador de que o usuario pode atuar como motorista
- `cobli_driver_id`
- numero da CNH
- categoria da CNH
- validade da CNH
- codigo de associacao do motorista, se a operacao usar esse conceito
- status de sincronizacao com a Cobli

Recomendacao pratica:

- se quiser o caminho mais rapido, adicionar os campos minimos diretamente em `usuarios`
- se quiser um desenho mais limpo, criar uma tabela de perfil de motorista ligada ao usuario

Fluxo sugerido:

1. O usuario e marcado como motorista no GestorNow.
2. O sistema sincroniza ou vincula esse usuario a um motorista da Cobli.
3. Na locacao, o motorista e escolhido no GestorNow.
4. No envio da rota, o sistema usa o `cobli_driver_id` vinculado ao usuario escolhido.

## 5) Casos de uso recomendados

### Caso 1: Roteirizacao da locacao

Fluxo sugerido:

1. Usuario aprova a locacao no GestorNow.
2. A locacao entra no fluxo logistico interno e segue para separacao.
3. O motorista e definido no GestorNow.
4. Quando a carga estiver pronta para sair, o sistema monta a ordem logistica com base na locacao.
5. Essa ordem e enviada para a Cobli como rota.
6. A Cobli responde com identificador da rota.
7. O GestorNow salva o vinculo e acompanha status, ETA e execucao.

### Quando a rota deve ser criada

Esse foi o ponto que ficou em aberto, e o melhor jeito de entender e separar o evento comercial do evento operacional.

No contexto atual do sistema:

- `aprovado` significa que a locacao foi aprovada comercialmente
- `para_separar` significa que a operacao ainda esta preparando os itens
- `pronto_patio` significa que a carga ja esta pronta para sair
- `em_rota` significa que a execucao ja comecou

Recomendacao para o seu projeto:

- nao criar a rota automaticamente no momento em que a locacao vira `aprovado`
- usar `aprovado` para validar endereco, contato, motorista e dados da operacao
- criar a rota da Cobli quando o `status_logistica` mudar para `pronto_patio`

Motivo:

- `aprovado` ainda e um evento mais comercial do que logistico
- `pronto_patio` representa melhor o momento em que a operacao esta realmente pronta para despacho

Padrao recomendado:

1. `status = aprovado`: locacao apta para planejamento logistico.
2. `status_logistica = para_separar`: equipe organiza itens e agenda.
3. `status_logistica = pronto_patio`: sistema cria a rota na Cobli.
4. `status_logistica = em_rota`: sistema passa a refletir execucao da viagem.

Se quiser mais controle no inicio, voce tambem pode combinar isso com um botao manual `Enviar para Cobli`, mesmo usando `pronto_patio` como regra principal.

### Caso 2: Acompanhamento operacional

O GestorNow consulta ou recebe da Cobli:
- motorista vinculado
- veiculo vinculado
- horario previsto de chegada
- horario real da operacao
- eventos de trajeto

Esses dados alimentam o `status_logistica` da locacao.

### Caso 3: Historico de manutencao

O GestorNow busca manutencoes da Cobli para:
- exibir painel de manutencao da frota
- apoiar decisao operacional
- eventualmente gerar alertas internos

## 6) Endpoints da Cobli mais relevantes

Para o inicio da integracao, os endpoints mais uteis sao:

- Veiculos: `GET /public/v1/vehicles`
- Motoristas: `GET /public/v1/drivers`
- Rotas: `GET /public/v2/routes`
- Manutencao: `POST /analytics/v1/maintenance/history`

Autenticacao:

- metodo: API Key
- header: `cobli-api-key`

## 7) Arquitetura recomendada no Laravel

### 7.1 Configuracao

Adicionar uma chave `cobli` em `config/services.php`.

Exemplo:

```php
'cobli' => [
    'api_key' => env('COBLI_API_KEY', ''),
    'base_url' => env('COBLI_BASE_URL', 'https://api.cobli.co'),
    'timeout' => env('COBLI_TIMEOUT', 30),
],
```

Observacao importante:

Como o sistema e multiempresa, o ideal e nao depender apenas de `.env`.
Se cada empresa puder integrar sua propria conta Cobli, o melhor desenho e:

- guardar configuracao da Cobli por empresa no banco
- criptografar a API key
- deixar `config/services.php` apenas com defaults tecnicos

### 7.2 Gateway da integracao

Criar um servico dedicado no mesmo padrao usado por integracoes externas.

Sugestao:

- `app/Services/Integracoes/Cobli/CobliGatewayService.php`

Responsabilidades:

- autenticar usando `cobli-api-key`
- encapsular requests HTTP
- normalizar erros
- registrar logs tecnicos
- expor metodos como:
  - `listVehicles()`
  - `listDrivers()`
  - `listRoutes()`
  - `getMaintenanceHistory()`
  - `createRoute()` se a fase de roteirizacao for implementada

### 7.3 Servicos de dominio

Separar a integracao tecnica da regra de negocio.

Sugestao:

- `app/Services/Integracoes/Cobli/CobliLocacaoService.php`
- `app/Services/Integracoes/Cobli/CobliManutencaoService.php`
- `app/Services/Integracoes/Cobli/CobliSincronizacaoService.php`

Exemplos de responsabilidade:

- `CobliLocacaoService`: transforma locacao em payload de rota
- `CobliManutencaoService`: importa historico de manutencao
- `CobliSincronizacaoService`: coordena jobs de sincronizacao

### 7.4 Tabelas auxiliares

Para nao poluir tabelas centrais logo no inicio, o melhor e criar tabelas de apoio.

#### Tabela 1: configuracao por empresa

Exemplo: `empresa_integracoes_cobli`

Campos sugeridos:
- `id`
- `id_empresa`
- `api_key` criptografada
- `base_url`
- `ativo`
- `ultimo_teste_em`
- `ultimo_erro`
- timestamps

#### Tabela 2: vinculos locais x remotos

Exemplo: `integracao_vinculos`

Campos sugeridos:
- `id`
- `id_empresa`
- `provider` ex: `cobli`
- `entity_type` ex: `locacao`, `veiculo`, `motorista`
- `local_id`
- `remote_id`
- `payload_snapshot`
- timestamps

#### Tabela 3: operacao logistica da locacao

Exemplo: `locacao_logistica`

Campos sugeridos:
- `id`
- `id_empresa`
- `id_locacao`
- `cobli_route_id`
- `cobli_vehicle_id`
- `cobli_driver_id`
- `status`
- `eta_previsto`
- `inicio_real`
- `fim_real`
- `distancia_metros`
- `payload_envio`
- `payload_retorno`
- `ultima_sincronizacao_em`
- timestamps

Com isso, o nucleo da locacao fica preservado e a operacao da Cobli fica desacoplada.

#### Tabela 4: perfil de motorista local

Se a decisao for usar `usuarios` como base para os motoristas, voce precisa de um lugar para guardar os dados especificos de motorista e o vinculo com a Cobli.

Voce pode fazer isso de duas formas:

- caminho rapido: adicionar campos na tabela `usuarios`
- caminho mais organizado: criar uma tabela auxiliar

Exemplo: `usuarios_motoristas`

Campos sugeridos:
- `id`
- `id_usuario`
- `id_empresa`
- `cobli_driver_id`
- `cnh_numero`
- `cnh_categoria`
- `cnh_validade`
- `codigo_associacao`
- `ativo`
- `sincronizado_em`
- `ultimo_erro`
- timestamps

Com isso, o GestorNow consegue escolher o motorista localmente e ainda manter o vinculo tecnico com a Cobli.

#### Tabela 5: perfil de veiculo local integrado

Se a escolha for cadastrar os veiculos no GestorNow e usa-los na Cobli, o ideal e ter um lugar para guardar os campos especificos da frota e o vinculo remoto.

Exemplo: `patrimonios_veiculos`

Campos sugeridos:
- `id`
- `id_patrimonio`
- `id_empresa`
- `placa`
- `renavam`
- `chassi`
- `marca`
- `modelo`
- `ano_modelo`
- `ano_fabricacao`
- `tipo_combustivel`
- `odometro_atual`
- `cobli_vehicle_id`
- `ativo`
- `sincronizado_em`
- `ultimo_erro`
- timestamps

Isso permite manter o `Produto` como cadastro base, o `Patrimonio` como unidade fisica e o perfil do veiculo como camada de frota.

### 7.5 Jobs e scheduler

Use fila e scheduler para nao depender apenas de acao manual.

Sugestao de jobs:

- `SyncCobliVehiclesJob`
- `SyncCobliDriversJob`
- `SyncCobliRoutesJob`
- `SyncCobliMaintenanceJob`
- `CreateCobliRouteFromLocacaoJob`

Use cases:

- sincronizacao periodica de veiculos e motoristas
- atualizacao de status das rotas abertas
- importacao de manutencao do periodo

## 8) Como aplicar isso no GestorNow

### Fase 1: leitura e vinculacao

Objetivo: conectar Cobli ao sistema sem alterar o fluxo principal.

Entregas:
- tela para configurar API key por empresa
- botao de testar conexao
- estrutura para cadastrar veiculo localmente no GestorNow e vinculá-lo a um veiculo da Cobli
- estrutura para vincular usuarios-motoristas do GestorNow aos motoristas da Cobli
- persistencia dos IDs remotos

Resultado:
- voce confirma conectividade
- valida o formato dos dados
- descobre como vai mapear a operacao real

### Fase 2: roteirizacao da locacao

Objetivo: usar a locacao como origem operacional.

Entregas:
- botao `Enviar para Cobli` na locacao
- definicao do motorista pelo GestorNow
- montagem de payload da rota
- gravacao do `cobli_route_id`
- retorno do status operacional na tela da locacao

Regra sugerida de disparo:

- a locacao pode ser preparada quando estiver `aprovado`
- a rota deve ser criada quando `status_logistica = pronto_patio`

Resultado:
- a locacao passa a conversar com a operacao de campo

### Fase 3: manutencao da frota

Objetivo: trazer contexto operacional para dentro do ERP.

Entregas:
- importacao de manutencoes da Cobli
- dashboard ou lista filtravel
- vinculo por veiculo e empresa

Resultado:
- manutencao passa a ser acompanhada no GestorNow

### Fase 4: telemetria e automacoes

Objetivo: enriquecer a locacao e a logistica.

Possibilidades:
- ETA automatico
- confirmacao de chegada
- alertas de atraso
- odometro
- combustivel
- checklist operacional

## 9) O que voce precisa para dar inicio

Checklist minimo:

### Negocio

- cada empresa tera sua propria conta Cobli
- a integracao sera opcional por empresa
- o gatilho recomendado para criacao de rota sera `status_logistica = pronto_patio`
- `aprovado` sera usado como etapa de preparacao e validacao da operacao
- os motoristas serao escolhidos pelo GestorNow e vinculados a usuarios
- ainda falta decidir se o veiculo sera escolhido no GestorNow ou apenas vinculado a partir da Cobli

### Tecnico

- conta Cobli ativa
- API key gerada no painel da Cobli
- ambiente de homologacao ou empresa piloto
- tabela para configuracao da integracao por empresa
- tabela para vinculos remotos
- tabela para operacao logistica da locacao
- estrutura para motorista vinculado a usuario
- fila ativa para jobs
- logs tecnicos para request e response

### Dados

- padrao de endereco confiavel nas locacoes
- contato e telefone de entrega padronizados
- regra de vinculo entre locacao e rota
- regra de vinculo entre usuario e motorista Cobli
- regra de vinculo entre veiculo local e `cobli_vehicle_id`

## 10) Mapeamento inicial sugerido

### Locacao -> rota Cobli

- `id_locacao` -> referencia interna
- `numero_contrato` -> codigo operacional
- `cliente.nome` -> destinatario/referencia
- `local_entrega` e `endereco_entrega` -> destino
- `local_retirada` -> coleta
- `data_transporte_ida` e `hora_transporte_ida` -> janela de entrega
- `data_transporte_volta` e `hora_transporte_volta` -> janela de retirada
- `observacoes_entrega` -> instrucoes operacionais
- `status_logistica` -> espelho local do status operacional

### Usuario motorista -> motorista Cobli

- `usuarios.id_usuario` -> referencia local do motorista
- `usuarios.nome` -> nome do motorista
- `usuarios.telefone` -> telefone
- `usuarios.cpf` -> documento base
- `usuarios_motoristas.cobli_driver_id` -> identificador remoto
- dados de CNH -> complemento para sincronizacao

### Veiculo local -> veiculo Cobli

- `produtos.id_produto` -> cadastro base do veiculo
- `patrimonios.id_patrimonio` -> unidade fisica do veiculo
- placa/chassi/renavam -> identificadores do veiculo
- `patrimonios_veiculos.cobli_vehicle_id` -> identificador remoto
- odometro e status -> dados operacionais sincronizaveis

### Itens da locacao -> carga operacional

- `produto.nome` -> descricao do item
- `quantidade` -> volume
- `observacoes` -> informacoes extras

### Servico da locacao -> custo de transporte

- `descricao` -> servico logistico
- `preco_unitario` e `valor_total` -> custo/frete interno
- `fornecedor_nome` -> parceiro, se houver

### Manutencao Cobli -> manutencao local

No inicio, mapear para leitura e historico:

- tipo
- status
- data da ocorrencia
- custo
- veiculo
- observacoes

## 11) Riscos e decisoes importantes

### Nao comece por sincronizacao completa

Se tentar sincronizar cliente, produto, veiculo, rota e manutencao com escrita dos dois lados logo no inicio, a integracao fica mais fragil que util.

### Evite acoplar frota em produto cedo demais

Hoje a manutencao local esta ligada a produto/patrimonio. Isso pode servir no inicio, mas, se a Cobli virar parte central da operacao, o melhor caminho pode ser um modulo proprio de frota.

### Pense em multiempresa desde o primeiro dia

Se a integracao for por empresa, a chave da Cobli, vinculos e logs precisam respeitar `id_empresa` em todas as tabelas.

## 12) Ordem recomendada de implementacao

1. Criar configuracao Cobli por empresa.
2. Criar `CobliGatewayService`.
3. Implementar teste de conexao.
4. Criar estrutura de configuracao opcional por empresa.
5. Criar estrutura de motorista local vinculado a usuario.
6. Sincronizar ou vincular motoristas da Cobli.
7. Sincronizar veiculos.
8. Criar tabela de vinculos remotos.
9. Criar tabela operacional da locacao.
10. Implementar envio da locacao para rota quando `status_logistica = pronto_patio`.
11. Implementar atualizacao de status da rota.
12. Importar manutencao.

## 13) MVP recomendado

Se a ideia e comecar rapido com baixo risco, o MVP ideal e:

- configurar Cobli por empresa
- cadastrar e vincular veiculos locais do GestorNow com os veiculos da Cobli
- vincular usuarios-motoristas aos motoristas da Cobli
- adicionar botao `Roteirizar na Cobli` na locacao
- escolher motorista localmente no GestorNow
- escolher veiculo localmente no GestorNow
- gravar `route_id`, motorista, veiculo e ETA
- refletir o andamento da rota no `status_logistica`

Esse MVP ja entrega valor real sem exigir uma reestruturacao grande do sistema.

## 14) Exemplo de definicao tecnica inicial

### Variaveis base

```env
COBLI_API_KEY=
COBLI_BASE_URL=https://api.cobli.co
COBLI_TIMEOUT=30
```

### Exemplo de headers HTTP

```http
accept: application/json
cobli-api-key: SUA_CHAVE
```

### Estrutura sugerida de pastas

```text
app/
  Services/
    Integracoes/
      Cobli/
        CobliGatewayService.php
        CobliLocacaoService.php
        CobliManutencaoService.php
        CobliSincronizacaoService.php
  Jobs/
    SyncCobliVehiclesJob.php
    SyncCobliDriversJob.php
    SyncCobliRoutesJob.php
    SyncCobliMaintenanceJob.php
    CreateCobliRouteFromLocacaoJob.php
```

## 15) Conclusao

Para o GestorNow, a melhor aplicacao da Cobli e esta:

- cliente continua no ERP
- locacao continua no ERP
- transporte nasce da locacao
- Cobli opera rota, veiculo e motorista
- manutencao da Cobli entra primeiro como leitura

Se voce seguir essa linha, consegue iniciar com baixo risco, entregar valor cedo e manter espaco para evoluir a integracao sem travar o dominio principal de locacao.