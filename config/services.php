<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GestorNow API Configuration
    |--------------------------------------------------------------------------
    |
    | URL base da API do GestorNow para upload de arquivos (logos, imagens, etc)
    | 
    | Exemplos:
    | - Local: http://localhost:3000
    | - Produção: https://api.gestornow.com
    | - Subdomínio: https://files.seudominio.com
    |
    */
    'gestornow_api' => [
        'base_url' => env('GESTORNOW_API_URL', ''),
    ],

    'banco_inter' => [
        'webhook_url' => env('BOLETO_INTER_WEBHOOK_URL', ''),
    ],

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY', ''),
        'base_url' => env('ASAAS_BASE_URL', 'https://api.asaas.com'),
        'webhook_url' => env('ASAAS_WEBHOOK_URL', ''),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN', ''),
    ],

    'evolution' => [
        'base_url' => env('EVOLUTION_API_BASE_URL', ''),
        'api_key' => env('EVOLUTION_API_KEY', ''),
        'typebot_instance' => env('EVOLUTION_TYPEBOT_INSTANCE', ''),
        'instance_id' => env('EVOLUTION_INSTANCE_ID', ''),
    ],

    'cora' => [
        'base_url' => env('CORA_BASE_URL', 'https://api.stage.cora.com.br'),
        'oauth_authorize_url' => env('CORA_OAUTH_AUTHORIZE_URL', ''),
        'oauth_token_url' => env('CORA_OAUTH_TOKEN_URL', ''),
        'redirect_uri' => env('CORA_REDIRECT_URI', ''),
        'scopes' => env('CORA_SCOPES', 'invoice account payment'),
        'timeout' => env('CORA_TIMEOUT', 60),
    ],

    'mercado_pago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN', ''),
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
        'processing_mode' => env('MERCADO_PAGO_PROCESSING_MODE', 'automatic'),
        'timeout' => env('MERCADO_PAGO_TIMEOUT', 60),
    ],

    'paghiper' => [
        'api_key' => env('PAGHIPER_API_KEY', ''),
        'token' => env('PAGHIPER_TOKEN', ''),
        'base_url' => env('PAGHIPER_BASE_URL', 'https://api.paghiper.com'),
        'notification_url' => env('PAGHIPER_NOTIFICATION_URL', ''),
        'timeout' => env('PAGHIPER_TIMEOUT', 60),
    ],

    // Renight gay
    // Muito gay mesmo

];
