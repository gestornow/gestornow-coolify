param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectPath,

    [Parameter(Mandatory = $true)]
    [string]$RemotePath,

    [string]$ComposeService = "mysql",
    [string]$DbName,
    [string]$DbUser,
    [string]$DbPassword,
    [int]$KeepRemoteFiles = 2,
    [string]$LocalBackupDir
)

$ErrorActionPreference = "Stop"

function Parse-DotEnv {
    param(
        [Parameter(Mandatory = $true)]
        [string]$EnvFilePath
    )

    $result = @{}

    if (-not (Test-Path $EnvFilePath)) {
        return $result
    }

    Get-Content -Path $EnvFilePath | ForEach-Object {
        $line = $_.Trim()
        if ([string]::IsNullOrWhiteSpace($line)) { return }
        if ($line.StartsWith("#")) { return }

        $idx = $line.IndexOf("=")
        if ($idx -lt 1) { return }

        $key = $line.Substring(0, $idx).Trim()
        $value = $line.Substring($idx + 1).Trim()

        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $result[$key] = $value
    }

    return $result
}

function Escape-ForShSingleQuote {
    param([string]$Value)
    if ($null -eq $Value) { return "" }
    return $Value -replace "'", "'\\''"
}

if (-not (Test-Path $ProjectPath)) {
    throw "ProjectPath nao encontrado: $ProjectPath"
}

$dockerCmd = Get-Command docker -ErrorAction SilentlyContinue
if (-not $dockerCmd) {
    throw "Comando docker nao encontrado no PATH."
}

$rcloneCmd = Get-Command rclone -ErrorAction SilentlyContinue
if (-not $rcloneCmd) {
    throw "Comando rclone nao encontrado no PATH."
}

$composeFile = Join-Path $ProjectPath "docker-compose.yml"
if (-not (Test-Path $composeFile)) {
    throw "docker-compose.yml nao encontrado em: $composeFile"
}

$envPath = Join-Path $ProjectPath ".env"
$envVars = Parse-DotEnv -EnvFilePath $envPath

if ([string]::IsNullOrWhiteSpace($DbName) -and $envVars.ContainsKey("DB_DATABASE")) {
    $DbName = $envVars["DB_DATABASE"]
}
if ([string]::IsNullOrWhiteSpace($DbUser) -and $envVars.ContainsKey("DB_USERNAME")) {
    $DbUser = $envVars["DB_USERNAME"]
}
if ([string]::IsNullOrWhiteSpace($DbPassword) -and $envVars.ContainsKey("DB_PASSWORD")) {
    $DbPassword = $envVars["DB_PASSWORD"]
}

if ([string]::IsNullOrWhiteSpace($DbName) -or [string]::IsNullOrWhiteSpace($DbUser) -or [string]::IsNullOrWhiteSpace($DbPassword)) {
    throw "Nao foi possivel resolver DB_DATABASE, DB_USERNAME e DB_PASSWORD. Informe por parametro ou configure no .env."
}

if ($KeepRemoteFiles -lt 1) {
    throw "KeepRemoteFiles deve ser >= 1."
}

if ([string]::IsNullOrWhiteSpace($LocalBackupDir)) {
    $LocalBackupDir = Join-Path $ProjectPath "storage\app\backups\db"
}

if (-not (Test-Path $LocalBackupDir)) {
    New-Item -ItemType Directory -Path $LocalBackupDir -Force | Out-Null
}

$timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
$fileName = "${DbName}_$timestamp.sql"
$localBackupPath = Join-Path $LocalBackupDir $fileName
$stderrPath = Join-Path $env:TEMP "db-backup-drive-stderr.log"

$escapedPassword = Escape-ForShSingleQuote -Value $DbPassword
$escapedDbName = Escape-ForShSingleQuote -Value $DbName
$escapedDbUser = Escape-ForShSingleQuote -Value $DbUser

$dumpCommand = "export MYSQL_PWD='$escapedPassword'; mysqldump -u'$escapedDbUser' --single-transaction --quick --routines --triggers --events '$escapedDbName'"
$dockerArgs = @(
    "compose",
    "-f", $composeFile,
    "exec",
    "-T",
    $ComposeService,
    "sh",
    "-lc",
    $dumpCommand
)

Write-Host "Gerando dump do banco: $DbName"
$proc = Start-Process -FilePath "docker" -ArgumentList $dockerArgs -NoNewWindow -PassThru -Wait -RedirectStandardOutput $localBackupPath -RedirectStandardError $stderrPath
if ($proc.ExitCode -ne 0) {
    $stderrOutput = ""
    if (Test-Path $stderrPath) {
        $stderrOutput = Get-Content -Path $stderrPath -Raw
    }
    throw "Falha ao gerar dump. ExitCode=$($proc.ExitCode). Detalhes: $stderrOutput"
}

if (-not (Test-Path $localBackupPath)) {
    throw "Dump nao foi criado em: $localBackupPath"
}

Write-Host "Enviando backup para o Drive: $RemotePath/$fileName"
& rclone copyto $localBackupPath "$RemotePath/$fileName"
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao enviar backup para o Drive via rclone."
}

Write-Host "Aplicando retencao: manter apenas $KeepRemoteFiles arquivos no remoto"
$lsJsonRaw = & rclone lsjson $RemotePath
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao listar arquivos no remoto: $RemotePath"
}

$remoteItems = $lsJsonRaw | ConvertFrom-Json

$backupFiles = @($remoteItems | Where-Object {
    -not $_.IsDir -and $_.Name -like "${DbName}_*.sql"
} | Sort-Object -Property ModTime -Descending)

if ($backupFiles.Count -gt $KeepRemoteFiles) {
    $filesToDelete = $backupFiles | Select-Object -Skip $KeepRemoteFiles
    foreach ($file in $filesToDelete) {
        $target = "$RemotePath/$($file.Name)"
        Write-Host "Removendo backup antigo: $target"
        & rclone deletefile $target
        if ($LASTEXITCODE -ne 0) {
            throw "Falha ao remover backup remoto: $target"
        }
    }
}

Write-Host "Backup concluido com sucesso."
Write-Host "Arquivo local: $localBackupPath"
Write-Host "Arquivo remoto: $RemotePath/$fileName"
