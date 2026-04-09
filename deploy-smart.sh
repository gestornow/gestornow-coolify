#!/bin/bash

# Script de deploy inteligente - detecta branch e deploy no ambiente correto

BRANCH=$1
PROJECT_DIR="/var/www/html"
DEV_DIR="/var/www/html-dev"

if [ -z "$BRANCH" ]; then
    echo "❌ Branch não especificada"
    exit 1
fi

echo "🚀 Iniciando deploy para branch: $BRANCH"

APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"

if [ "$BRANCH" = "main" ] || [ "$BRANCH" = "master" ]; then
    echo "📦 Deploy PRODUCTION"
    cd $PROJECT_DIR
    git pull origin $BRANCH
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    if id "$APP_USER" >/dev/null 2>&1; then
        chown -R "$APP_USER:$APP_GROUP" storage/ bootstrap/cache/
    fi
    chmod -R 775 storage/ bootstrap/cache/
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    echo "✅ Deploy PRODUCTION concluído"
    
elif [ "$BRANCH" = "dev" ]; then
    echo "🧪 Deploy DEVELOPMENT"
    cd $DEV_DIR
    git pull origin $BRANCH
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    if id "$APP_USER" >/dev/null 2>&1; then
        chown -R "$APP_USER:$APP_GROUP" storage/ bootstrap/cache/
    fi
    chmod -R 775 storage/ bootstrap/cache/
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    echo "✅ Deploy DEVELOPMENT concluído"
    
else
    echo "⚠️ Branch $BRANCH não configurada para deploy"
    exit 1
fi