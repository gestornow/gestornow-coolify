<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$projectDir = dirname(__DIR__);
$autoloadPath = $projectDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_file($autoloadPath)) {
    require_once $autoloadPath;

    if (class_exists(Dotenv\Dotenv::class)) {
        Dotenv\Dotenv::createImmutable($projectDir)->safeLoad();
    }
}

$storageLogsDir = $projectDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

if (!is_dir($storageLogsDir)) {
    @mkdir($storageLogsDir, 0775, true);
}

$logFile = $storageLogsDir . DIRECTORY_SEPARATOR . 'webhook-legacy.log';
$deployLog = $storageLogsDir . DIRECTORY_SEPARATOR . 'deploy-legacy.log';
$payload = file_get_contents('php://input') ?: '';
$githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$githubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';
$githubIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

function envValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function writeLog(string $message): void
{
    global $logFile;

    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, '[' . $date . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function findDeployScript(string $projectDir): ?string
{
    $candidates = [
        $projectDir . DIRECTORY_SEPARATOR . 'deploy-smart.sh',
        $projectDir . DIRECTORY_SEPARATOR . 'deploy.sh',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$secret = envValue('GITHUB_WEBHOOK_SECRET');

if ($secret === null) {
    writeLog('Webhook legado recusado: GITHUB_WEBHOOK_SECRET nao configurado.');
    http_response_code(500);
    exit('Webhook secret nao configurado');
}

if ($payload === '') {
    writeLog('Webhook legado recusado: payload vazio.');
    http_response_code(400);
    exit('Payload vazio');
}

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if ($githubSignature === '' || !hash_equals($expectedSignature, $githubSignature)) {
    writeLog('Assinatura invalida recebida no webhook legado. IP: ' . $githubIp);
    http_response_code(403);
    exit('Assinatura invalida');
}

$data = json_decode($payload, true);

if (!is_array($data)) {
    writeLog('Webhook legado recusado: payload JSON invalido.');
    http_response_code(400);
    exit('Payload invalido');
}

$branchRef = (string) ($data['ref'] ?? '');
$branch = preg_replace('/^refs\/heads\//', '', $branchRef) ?: 'unknown';
$repository = (string) ($data['repository']['name'] ?? 'unknown');

writeLog('Webhook recebido. Evento: ' . $githubEvent . ' | Repositorio: ' . $repository . ' | Branch: ' . $branch . ' | IP: ' . $githubIp);

if ($githubEvent !== 'push') {
    http_response_code(202);
    exit('Evento ignorado');
}

$deployScript = findDeployScript($projectDir);

if ($deployScript === null) {
    writeLog('Webhook legado recusado: nenhum script de deploy encontrado.');
    http_response_code(500);
    exit('Script de deploy nao encontrado');
}

$command = 'bash ' . escapeshellarg($deployScript);

if (basename($deployScript) === 'deploy-smart.sh') {
    $command .= ' ' . escapeshellarg($branch);
}

$command .= ' >> ' . escapeshellarg($deployLog) . ' 2>&1 &';

exec($command, $output, $returnCode);

writeLog('Deploy disparado. Script: ' . basename($deployScript) . ' | Retorno exec(): ' . $returnCode);

if (!empty($output)) {
    writeLog('Output imediato: ' . implode(PHP_EOL, $output));
}

http_response_code(200);
echo 'Deploy iniciado com sucesso!';

