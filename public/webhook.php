<?php
date_default_timezone_set('America/Sao_Paulo'); // ✅ Fuso horário de Brasília

$secret = "4fbL&7d£/n3+";
$payload = file_get_contents("php://input");
$githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$githubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';
$githubIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$calculatedSignature = hash_hmac("sha256", $payload, $secret);
$expectedSignature = 'sha256=' . $calculatedSignature;

$logFile = "/var/www/html/webhook.log";
$deployScript = "/var/www/html/deploy.sh";
$deployLog = "/var/www/html/deploy.log";

function writeLog($message) {
    global $logFile;
    $date = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
}

// 🔐 Verifica assinatura
if (!hash_equals($expectedSignature, $githubSignature)) {
    writeLog("Assinatura inválida. Recebida: " . $githubSignature . " | Esperada: " . $expectedSignature);
    http_response_code(403);
    exit("Assinatura inválida");
}

// Log completo do webhook
writeLog("Webhook recebido. Evento: $githubEvent | IP: $githubIP");
writeLog("Payload: " . $payload);

// Captura branch e commit se disponíveis
$data = json_decode($payload, true);
$branch = $data['ref'] ?? 'unknown';
$commit = $data['head_commit']['id'] ?? 'unknown';
writeLog("Branch: $branch | Último commit: $commit");

// Executa deploy em background
$cmd = "bash " . $deployScript . " >> " . $deployLog . " 2>&1 &";
exec($cmd, $output, $return_var);

writeLog("Deploy iniciado às " . date("Y-m-d H:i:s"));
writeLog("Comando executado: $cmd");
writeLog("Código de retorno: $return_var");
if (!empty($output)) {
    writeLog("Output: " . implode("\n", $output));
}

http_response_code(200);
echo "Deploy iniciado com sucesso!";

