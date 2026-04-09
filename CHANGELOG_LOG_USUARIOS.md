## Resumo
Foi implementado o mapeamento de log centralizado do modulo de Usuarios seguindo o padrao existente no projeto.
Foi criado o map `UsuariosMap`, registrado no `LogMap` e integrado aos models `Usuario` e `User` via trait `RegistraAtividade` para captura automatica de `created`, `updated` e `deleted` (soft delete).
Nao foram feitas alteracoes na infraestrutura base de log, nem refatoracoes de regra de negocio.

## Arquivos Criados
| Caminho | Responsabilidade |
| --- | --- |
| `app/ActivityLog/Maps/UsuariosMap.php` | Definir entidade, label, campos sensiveis e eventos de log do modulo Usuarios |
| `CHANGELOG_LOG_USUARIOS.md` | Documentar implementacao, eventos e decisoes tecnicas |

## Arquivos Modificados
### 1) `app/ActivityLog/LogMap.php`
- Trecho adicionado:
```php
use App\ActivityLog\Maps\UsuariosMap;

private static array $maps = [
    'FluxoCaixa' => FluxoCaixaMap::class,
    'Usuario' => UsuariosMap::class,
    'User' => UsuariosMap::class,
    'Produto' => ProdutosMap::class,
];
```
- Motivo da alteracao:
Registrar o map de Usuarios no resolvedor central e cobrir tambem fluxos que ainda referenciam o basename `User`.

### 2) `app/Domain/Auth/Models/Usuario.php`
- Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, RegistraAtividade;
```
- Motivo da alteracao:
Conectar o model principal de usuarios ao `UniversalObserver` para logs automaticos de CRUD.

### 3) `app/Models/User.php`
- Trecho adicionado:
```php
use App\ActivityLog\Traits\RegistraAtividade;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, RegistraAtividade;
```
- Motivo da alteracao:
Cobrir fluxos legados de autenticacao que possam persistir usuario por este model.

## Eventos Registrados
| Evento | Acao gravada | Descricao gerada | Tags | Cor |
| --- | --- | --- | --- | --- |
| `created` | `usuario.criado` | `Cadastrou o usuario {nome}` | `usuarios`, `acesso`, `novo_cadastro` | `verde` |
| `updated` | `usuario.editado` | `Editou o cadastro do usuario {nome}` | `usuarios`, `acesso`, `edicao` | `amarelo` |
| `deleted` | `usuario.excluido` | `Excluiu o usuario {nome}` | `usuarios`, `acesso`, `exclusao` | `vermelho` |

## Tratamento de dados sensiveis
Campos sensiveis definidos no map:
- `cpf`
- `rg`
- `telefone`
- `endereco`
- `cep`
- `bairro`
- `observacoes`

Campos sensiveis globais ja protegidos pelo observer:
- `senha`
- `remember_token`
- `session_token`
- `codigo_reset`
- `google_calendar_token`

Como sao protegidos no log (`antes`/`depois`):
- O `UniversalObserver` aplica mascaramento automatico desses campos com valor padrao do projeto (`[OCULTO]`).

## Decisoes tecnicas
- Nao foram adicionados eventos manuais para alteracao de senha/status para evitar duplicidade com o `updated` automatico neste momento.
- O fluxo de CRUD de usuarios no modulo atual usa `App\Domain\Auth\Models\Usuario`; por seguranca, foi incluido alias para `User` no `LogMap`.

## Checklist de validacao recomendada
- Criar usuario e verificar `usuario.criado` em `registro_atividades`.
- Editar usuario (incluindo status) e verificar `usuario.editado` com `campos_alterados`.
- Excluir usuario (soft delete) e verificar `usuario.excluido`.
- Confirmar mascaramento de campos sensiveis em `antes`/`depois`.
- Confirmar preenchimento de `id_empresa`, `id_usuario` (ator), `entidade_tipo` e `entidade_id`.
