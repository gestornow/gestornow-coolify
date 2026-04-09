# 🚀 Guia Rápido - API de Imagens de Usuários

## Configuração Inicial

### 1. Configure o arquivo `.env`
```env
# Para desenvolvimento local (se a API rodar localmente)
API_FILES_URL=http://localhost:3000

# Para produção
API_FILES_URL=https://api.gestornow.com
```

### 2. Limpe o cache de configuração
```bash
php artisan config:clear
php artisan config:cache
```

### 3. Teste a listagem
```bash
# Acesse no navegador
http://localhost/usuarios
```

## 💡 Como Funciona

### Carregamento Automático
As fotos são carregadas automaticamente na listagem de usuários:

```php
// UserController::index()
$users = $this->userService->getUserList($filters, 50);
// Cada $user->foto_url está disponível
```

### Estrutura de Dados Retornada
```php
$user = [
    'id_usuario' => 151,
    'nome' => 'João Silva',
    'login' => 'joao@example.com',
    'foto_url' => 'https://api.gestornow.com/uploads/usuarios/imagens/1/usuarios_foto_151_1_abc.jpg',
    'inicial' => 'JS', // Gerado automaticamente para fallback
    // ... outros campos
];
```

## 🔄 Invalidar Cache

### Quando usar?
- Após upload de nova foto
- Após exclusão de foto
- Quando a foto não aparece após alteração

### Como invalidar?

#### Via JavaScript (AJAX)
```javascript
fetch('/usuarios/invalidar-cache-fotos', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        user_ids: [151, 200] // Opcional - null = todos
    })
})
.then(response => response.json())
.then(data => {
    console.log('Cache invalidado:', data);
    location.reload(); // Recarregar lista
});
```

#### Via PHP (Controller)
```php
// Invalidar cache de usuários específicos
$this->imageService->invalidateCache([151, 200]);

// Invalidar todo o cache
$this->imageService->invalidateCache();
```

#### Via Artisan Tinker
```bash
php artisan tinker

# Invalidar cache geral
Cache::forget('user_photos_all');

# Verificar cache
Cache::get('user_photos_all');
```

## 🎨 Customizar a View

### Avatar com Foto
```blade
<div class="avatar avatar-sm me-3">
    @if(!empty($user->foto_url))
        <img 
            src="{{ $user->foto_url }}" 
            alt="{{ $user->nome }}" 
            class="rounded-circle"
            style="width: 38px; height: 38px; object-fit: cover;"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <span class="avatar-initial rounded-circle bg-label-primary" style="display: none;">
            {{ $user->inicial }}
        </span>
    @else
        <span class="avatar-initial rounded-circle bg-label-primary">
            {{ $user->inicial }}
        </span>
    @endif
</div>
```

### Alterar Tamanho do Avatar
```html
<!-- Pequeno -->
<img style="width: 32px; height: 32px; object-fit: cover;">

<!-- Médio (padrão) -->
<img style="width: 38px; height: 38px; object-fit: cover;">

<!-- Grande -->
<img style="width: 48px; height: 48px; object-fit: cover;">
```

## 🐛 Troubleshooting

### As fotos não aparecem?

#### 1. Verifique a URL da API
```bash
# No terminal
echo $API_FILES_URL

# Ou via PHP
echo config('custom.api_files_url');
```

#### 2. Teste a API manualmente
```bash
curl https://api.gestornow.com/api/usuarios/imagens
```

#### 3. Verifique os logs
```bash
tail -f storage/logs/laravel.log | grep "UserImageService"
```

#### 4. Limpe o cache
```bash
php artisan cache:clear
```

### Imagens aparecem quebradas?

#### 1. Verifique CORS na API de arquivos
A API deve permitir requisições do domínio do Laravel:
```javascript
// Na API Node.js
app.use(cors({
    origin: ['https://app.gestornow.com', 'http://localhost']
}));
```

#### 2. Verifique se a URL está completa
```php
// No tinker
$user = \App\Domain\Auth\Models\Usuario::find(151);
$photoUrl = app(\App\Domain\User\Services\UserImageService::class)->getUserPhotoUrl($user->id_usuario);
dd($photoUrl); // Deve retornar URL completa
```

### Cache não está funcionando?

#### 1. Verifique o driver de cache
```env
CACHE_DRIVER=file  # ou redis, memcached
```

#### 2. Limpe e recrie o cache
```bash
php artisan cache:clear
php artisan config:clear
```

#### 3. Teste manualmente
```bash
php artisan tinker

# Definir cache
Cache::put('test', 'valor', now()->addMinutes(30));

# Obter cache
Cache::get('test'); // Deve retornar 'valor'
```

## ⚡ Performance

### Otimizações Aplicadas
- ✅ Cache de 30 minutos
- ✅ Busca em lote (todos os usuários da página)
- ✅ Timeout de 10 segundos
- ✅ Fallback instantâneo em caso de erro

### Monitorar Performance
```bash
# Ver requisições HTTP
tail -f storage/logs/laravel.log | grep "UserImageService"

# Ver tempo de cache
tail -f storage/logs/laravel.log | grep "Usando cache"
```

## 🔧 Ajustes Avançados

### Alterar tempo de cache
```php
// Em UserImageService.php
private int $cacheMinutes = 60; // 60 minutos em vez de 30
```

### Alterar timeout da API
```php
// Em UserImageService.php, método fetchPhotosFromApi()
$response = Http::timeout(20)->get($endpoint); // 20 segundos
```

### Pré-carregar cache
```php
// Útil para cron jobs ou comandos artisan
$imageService = app(\App\Domain\User\Services\UserImageService::class);
$imageService->getUsersPhotosUrls(); // Carrega todas as fotos no cache
```

## 📊 Exemplos de Uso

### Buscar foto de um usuário específico
```php
$imageService = app(\App\Domain\User\Services\UserImageService::class);
$fotoUrl = $imageService->getUserPhotoUrl(151);

if ($fotoUrl) {
    echo "Foto disponível: $fotoUrl";
} else {
    echo "Usuário não possui foto";
}
```

### Buscar fotos de múltiplos usuários
```php
$imageService = app(\App\Domain\User\Services\UserImageService::class);
$photosMap = $imageService->getUsersPhotosUrls([151, 200, 305]);

foreach ($photosMap as $idUsuario => $fotoUrl) {
    echo "Usuário $idUsuario: $fotoUrl\n";
}
```

### Usar em outro controller
```php
use App\Domain\User\Services\UserImageService;

class OutroController extends Controller
{
    public function __construct(
        private UserImageService $imageService
    ) {}
    
    public function exemplo()
    {
        $fotoUrl = $this->imageService->getUserPhotoUrl(151);
        return view('exemplo', compact('fotoUrl'));
    }
}
```

## 📞 Suporte

Em caso de dúvidas:
1. Verifique a documentação completa em `docs/integracao-api-imagens-usuarios.md`
2. Consulte os logs em `storage/logs/laravel.log`
3. Teste manualmente com `php artisan tinker`

---

**Desenvolvido com ❤️ seguindo Clean Code e SOLID**
