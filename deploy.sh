#!/bin/bash
set -e

PROJECT_DIR="/var/www/html"
cd "$PROJECT_DIR" || { echo "❌ Diretório do projeto não encontrado"; exit 1; }

echo "🚀 Iniciando deploy automático..."

# Usuário do processo web (pode sobrescrever via variável de ambiente)
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"

# Verificar se estamos no diretório correto
if [ ! -f "artisan" ]; then
    echo "❌ Erro: Não foi possível encontrar o arquivo artisan."
    exit 1
fi

# Atualizar código
echo "📦 Atualizando código..."
git fetch origin
git reset --hard origin/main

# Composer
echo "📚 Instalando dependências do Composer..."
composer install --no-dev --optimize-autoloader --no-interaction || echo "⚠️ Erro no composer install, continuando..."

# Migrações
echo "🏗️ Executando migrações..."
php artisan migrate --force || echo "⚠️ Erro nas migrações, continuando..."

# Otimizações Laravel
echo "⚡ Otimizando aplicação..."
php artisan config:cache || echo "⚠️ Erro no config:cache"
php artisan route:cache || echo "⚠️ Erro no route:cache"
php artisan view:cache || echo "⚠️ Erro no view:cache"

# NPM / Assets
if command -v npm &> /dev/null; then
    echo "📦 Instalando dependências do NPM..."
    npm ci --production --silent || npm install --production --silent || echo "⚠️ NPM não disponível"
    echo "🏗️ Compilando assets..."
    npm run build || npm run production || echo "⚠️ Erro ao compilar assets"
fi

# Cache
echo "🧹 Limpando cache..."
php artisan cache:clear || echo "⚠️ Erro ao limpar cache"

# Garante estrutura de runtime do Laravel (evita erro de fopen em cache/session)
echo "📁 Garantindo diretórios de runtime..."
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache || echo "⚠️ Erro ao criar diretórios de runtime"

# Permissões
echo "🔒 Ajustando permissões..."
if id "$APP_USER" >/dev/null 2>&1; then
    chown -R "$APP_USER:$APP_GROUP" storage/ bootstrap/cache/ || echo "⚠️ Erro ao ajustar owner"
fi
chmod -R 775 storage/ bootstrap/cache/ || echo "⚠️ Erro ao ajustar permissões"

# Log de deploy
LOG_FILE="$PROJECT_DIR/storage/logs/deploy.log"
echo "$(TZ='America/Sao_Paulo' date '+%Y-%m-%d %H:%M:%S'): Deploy automático concluído" >> "$LOG_FILE"

echo "✅ Deploy concluído com sucesso!"
