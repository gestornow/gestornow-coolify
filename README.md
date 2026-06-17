# GestorNow

Sistema ERP multi-tenant para gestão de locação de equipamentos, PDV, financeiro e faturamento. Construído com Laravel 12 + PHP 8.2.

## O que é

O GestorNow é uma plataforma SaaS voltada para empresas que trabalham com **locação de equipamentos e venda**, permitindo controlar todo o ciclo operacional em um único sistema: do orçamento ao contrato, da entrega ao retorno, do financeiro ao faturamento.

O sistema é **multi-tenant**: cada empresa (tenant) opera de forma completamente isolada. Um usuário pode pertencer a mais de uma filial e alternar entre elas sem precisar fazer novo login.

---

## Módulos do sistema

### Locações

O núcleo do sistema. Controla o ciclo completo de locação de equipamentos:

- **Orçamentos e contratos** — criação de orçamentos que evoluem para contratos ativos, com status controlado (orçamento → ativo → concluído → cancelado)
- **Itens do contrato** — produtos com patrimônios individuais, serviços avulsos, despesas, salas e produtos de terceiros (sublocação)
- **Disponibilidade de patrimônios** — verificação em tempo real de quais unidades estão disponíveis em um determinado período
- **Expedição / Logística** — painel kanban para controle do fluxo de saída de contratos (separação, carregamento, entrega)
- **Checklist de entrega/retorno** — registro fotográfico e confirmação por etapas do estado dos equipamentos ao sair e ao voltar
- **Retorno parcial** — devolução de parte dos itens mantendo o contrato ativo para o restante
- **Troca de produto** — substituição de um patrimônio por outro durante o contrato, com registro e comprovante em PDF
- **Renovação / Aditivo** — prorrogação de datas por item ou do contrato inteiro, gerando aditivo
- **Medições** — controle de movimentações mensais de contratos de longa duração (envios e retornos parciais por período)
- **Assinatura digital** — envio de link por e-mail para o cliente assinar o contrato digitalmente, sem precisar de conta no sistema
- **Contrato em PDF** — geração de contrato a partir de modelos personalizados por empresa, com variáveis dinâmicas
- **Modelos de contrato** — editor de templates com suporte a orçamento, contrato padrão e aditivo; possibilidade de duplicar e definir padrão
- **Relatório gerencial de contratos** — exportação em PDF com visão consolidada dos contratos ativos

### Produtos e Patrimônios

- **Produtos** — cadastro com foto, categoria, unidade, código interno, observações e histórico de locações; exportação de ficha em PDF e Excel
- **Patrimônios** — cada produto pode ter N unidades serializadas (número de patrimônio, plaqueta, série); cadastro em massa; controle de status (disponível, locado, em manutenção)
- **Acessórios** — itens complementares que podem ser vinculados a produtos
- **Manutenção** — registro de ordens de manutenção por patrimônio, com histórico
- **Tabela de preços** — múltiplas tabelas por produto com cálculo por período (diária, semanal, quinzenal, mensal)
- **Controle de estoque** — movimentações manuais de entrada e saída com histórico
- **Produtos de terceiros** — cadastro de equipamentos de fornecedores para sublocação dentro de contratos

### PDV — Ponto de Venda

- Tela de caixa com busca de produto por nome ou código de barras
- Verificação de estoque em tempo real
- Carrinho de compras, aplicação de desconto e fechamento de venda
- Catálogo de **produtos de venda** separado dos produtos de locação, com controle de estoque próprio

### Financeiro

- **Contas a pagar** — com parcelamento, recorrência, baixa manual ou automática, histórico de pagamentos, recibo em PDF e log de atividades
- **Contas a receber** — idem ao pagar, com vínculo opcional a locações e vendas
- **Boletos** — emissão integrada com três provedores: **Mercado Pago**, **PagHiper** e **Cora** (OAuth2); consulta de status, alteração de vencimento, histórico e download em PDF
- **Fluxo de caixa** — visão de entradas e saídas por período, com exportação em PDF e Excel
- **Relatórios** — contas a pagar e a receber filtrados por status, período e categoria, com exportação em PDF e Excel
- **Faturamento de locações** — tela de medição e faturamento mensal de contratos; preview antes de faturar; faturamento individual ou em lote; cancelamento; geração de PDF
- **Categorias financeiras** — organização por tipo (receita / despesa)
- **Formas de pagamento** — cadastro para uso em baixas e vendas
- **Bancos** — cadastro de contas bancárias da empresa

### Clientes

- Cadastro completo: dados pessoais / CNPJ, endereço com busca de CEP, contatos, observações e foto
- Histórico de locações e log de atividades
- Busca de cidades por UF

### Fornecedores

- Cadastro de fornecedores de produtos e serviços
- Vinculação com produtos de terceiros para sublocação

### Usuários e Permissões

- Criação de usuários com envio de link de definição de senha por e-mail
- **Grupos de permissões** — perfis criados por empresa com chaves granulares (ex.: `locacoes.visualizar`, `financeiro.boletos`, `expedicao.logistica.mover-card`)
- Atribuição de grupo por empresa (um usuário pode ter perfis diferentes em cada filial)
- Suporte a **sessão única** — cada usuário só pode ter uma sessão ativa por vez
- Controle de tema (dark/light) salvo por usuário

### Calendário

- Visualização de locações e eventos por data em formato de calendário

### Configurações da Empresa

- Dados cadastrais, logo e configurações gerais da empresa
- Gestão de grupos de permissões

### Billing (gestão da plataforma)

- Controle de planos e módulos disponíveis
- Assinatura self-service pelo dashboard: upgrade de plano, gestão de método de pagamento, cancelamento
- Integração com **Asaas** para cobrança recorrente (webhooks de pagamento)
- Onboarding obrigatório para novos clientes: preenchimento de dados e aceite do contrato de licenciamento
- Painel admin para gestão de planos contratados, filiais e módulos

### Admin (suporte interno)

- Gerenciamento de planos, módulos e categorias de menu
- Visualização de logs de atividade de todas as empresas
- Troca de filial sem novo login (exclusivo para usuários de suporte)

---

## Integrações externas

| Serviço | Finalidade |
|---|---|
| **Asaas** | Cobrança recorrente da plataforma (billing SaaS); webhooks de confirmação de pagamento |
| **Mercado Pago** | Emissão de boletos para clientes das empresas |
| **PagHiper** | Emissão de boletos (provedor alternativo) |
| **Cora** | Emissão de boletos via OAuth2 |

---

## Arquitetura

A aplicação segue uma arquitetura híbrida em migração progressiva para domínio modular.

```
app/
├── Domain/               # Camada de domínio (destino de todo código novo de negócio)
│   ├── Auth/             # Empresa, Usuario, UsuarioPermissao
│   ├── Cliente/          # Cliente
│   ├── Locacao/          # Locacao, LocacaoProduto, LocacaoServico, LocacaoDespesa,
│   │                     # LocacaoSala, LocacaoRetornoPatrimonio, LocacaoModeloContrato,
│   │                     # LocacaoAssinaturaDigital, LocacaoChecklist, LocacaoTrocaProduto,
│   │                     # ProdutoTerceirosLocacao
│   ├── Produto/          # Produto, Patrimonio, PatrimonioHistorico, ProdutoHistorico,
│   │                     # Acessorio, ProdutoAcessorio, ProdutoTerceiro, ProdutoVenda,
│   │                     # TabelaPreco, Manutencao, MovimentacaoEstoque
│   ├── User/             # Serviços de usuário
│   └── Venda/            # Venda, VendaItem
│
├── Http/
│   ├── Controllers/      # Camada fina: orquestra request → service → response
│   │   ├── Locacao/      # LocacaoController, ExpedicaoController, ModeloContratoController
│   │   ├── Produto/      # ProdutoController, PatrimonioController, TabelaPrecoController...
│   │   ├── Financeiro/   # ContasAPagarController, BoletosController, FaturamentoController...
│   │   ├── Venda/        # PDVController
│   │   ├── Billing/      # AssinaturaController, MeuFinanceiroController
│   │   ├── Admin/        # PlanosController, FiliaisController, ModulosController, AdminLogController
│   │   └── Onboarding/   # OnboardingController
│   ├── Requests/         # Toda validação vive aqui (FormRequests por contexto)
│   ├── Resources/        # Transformadores de resposta API
│   └── Middleware/       # CheckPermissao, VerificarAcessoEmpresa, VerifyUniqueSession,
│                         # SecureAuthMiddleware, LocaleMiddleware
│
├── Models/               # CONGELADO — modelos legados (ContasAPagar, ContasAReceber,
│                         # Boleto, FluxoCaixa, FormaPagamento, Plano, Notificacao...)
├── Services/             # TRANSIÇÃO — serviços transversais e infraestrutura
│                         # (PermissaoService, FinanceiroService, ParcelamentoService,
│                         #  EstoqueService, NotificacaoService, LimiteService...)
├── ActivityLog/          # Log centralizado: ActionLogger, Maps/, Traits/, Observers/
├── Facades/              # Perm (facade sobre PermissaoService)
└── Policies/             # ContasAPagarPolicy, ContasAReceberPolicy

resources/views/
├── locacoes/             # Telas de locação
├── produtos/             # Telas de produtos e patrimônios
├── financeiro/           # Contas, boletos, fluxo de caixa, faturamento
├── cliente/              # Cadastro de clientes
├── fornecedor/           # Cadastro de fornecedores
├── pdv/                  # Ponto de venda
├── acessorios/           # Gestão de acessórios
├── billing/              # Assinatura e planos
├── usuario/              # Gestão de usuários
├── configuracoes/        # Configurações da empresa
├── calendario/           # Calendário de locações
├── onboarding/           # Fluxo de onboarding
├── layouts/              # contentNavbarLayout (principal), blankLayout (auth)
└── _partials/            # Macros, modais, offcanvas reutilizáveis
```

### Convenções principais

| Tema | Regra |
|---|---|
| **Multi-tenancy** | Toda query em dado de tenant deve ser filtrada por `id_empresa` |
| **PKs** | Não-padrão: `id_clientes`, `id_locacao`, `id_produto`, `id_patrimonio`... sempre verifique `$primaryKey` |
| **Permissões** | Chaves string (`locacoes.visualizar`), middleware `permissao:chave`, facade `Perm::pode()` |
| **Log de atividade** | Trait `RegistraAtividade` no model + `Maps/<Entidade>Map.php`; manual via `ActionLogger` |
| **Soft deletes** | Entidades principais usam `SoftDeletes`; use `withTrashed()` só quando explicitamente necessário |
| **Migrations** | Nunca editar arquivos existentes; sempre novo arquivo timestamped |
| **Validação** | Sempre em `FormRequest`, nunca no controller |
| **Regras de negócio** | Nunca no controller; sempre em `Domain/<Contexto>/Services/` |

---

## Requisitos

- PHP 8.2+
- MySQL 8.0+
- Node.js (para compilação dos assets com Laravel Mix)
- Composer

## Instalação local

```bash
# 1. Instalar dependências
composer install
npm install

# 2. Configurar ambiente
cp .env.example .env
php artisan key:generate

# 3. Criar banco e rodar migrations
php artisan migrate

# 4. Compilar assets
npm run dev

# 5. Subir servidor
php artisan serve
```

### Com Docker (Laravel Sail)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
```

---

## Documentação técnica

Documentação e decisões arquiteturais estão em [`docs/`](docs/):

| Arquivo | Conteúdo |
|---|---|
| [`arquitetura-e-organizacao.md`](docs/arquitetura-e-organizacao.md) | Estrutura, padrões e backlog de refatoração |
| [`permissoes-clientes-produtos-locacoes.md`](docs/permissoes-clientes-produtos-locacoes.md) | Sistema de permissões por chave |
| [`log-atividade-centralizado.md`](docs/log-atividade-centralizado.md) | Como funciona o ActivityLog |
| [`sessao-unica.md`](docs/sessao-unica.md) | Sessão única por usuário/empresa |
| [`asaas-webhook-scheduler.md`](docs/asaas-webhook-scheduler.md) | Integração Asaas e webhooks |
| [`auth-flutter.md`](docs/auth-flutter.md) | Autenticação para clientes mobile |
| [`planos-modulos-atualizacao.md`](docs/planos-modulos-atualizacao.md) | Gestão de planos e módulos |
| [`changelogs/`](docs/changelogs/) | Notas de implementação do ActivityLog por módulo (Locacao, Financeiro, Produtos, PDV...) |

---

## Cloudflare R2 no Coolify

O projeto já possui suporte a disco S3 e agora inclui disco dedicado `r2` em `config/filesystems.php`.
Para usar um bucket R2 já existente no deploy via Coolify, configure as variáveis abaixo:

```env
FILESYSTEM_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_DEFAULT_REGION=auto
R2_BUCKET=
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_URL=
R2_USE_PATH_STYLE_ENDPOINT=false
```

Notas:
- `R2_URL` é opcional, mas recomendado quando você usa domínio público/custom domain para servir arquivos.
- Se preferir, você pode continuar usando `s3` com as variáveis `AWS_*`; o disco `r2` também aceita fallback para elas.
- Bucket já criado: basta apontar as variáveis e redeployar no Coolify.

---

## Licença

MIT License — Copyright (c) 2026 gestornow. Veja o arquivo [LICENSE](LICENSE) para detalhes.
