param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectPath,

    [Parameter(Mandatory = $true)]
    [string]$RemotePath,

    [string]$TaskName = "GestorNow DB Backup Drive",
    [string]$RunAt = "02:00",
    [string]$ComposeService = "mysql",
    [int]$KeepRemoteFiles = 2
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ProjectPath)) {
    throw "ProjectPath nao encontrado: $ProjectPath"
}

$scriptPath = Join-Path $ProjectPath "scripts\backup-db-drive.ps1"
if (-not (Test-Path $scriptPath)) {
    throw "Script de backup nao encontrado em: $scriptPath"
}

$logDir = Join-Path $ProjectPath "storage\logs"
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

$logFile = Join-Path $logDir "db-backup-drive.log"

$timeParts = $RunAt.Split(":")
if ($timeParts.Count -ne 2) {
    throw "Parametro RunAt invalido. Use o formato HH:mm, ex: 02:00"
}

$hour = [int]$timeParts[0]
$minute = [int]$timeParts[1]
$startBoundary = (Get-Date).Date.AddHours($hour).AddMinutes($minute)
if ($startBoundary -lt (Get-Date)) {
    $startBoundary = $startBoundary.AddDays(1)
}

$command = "& '$scriptPath' -ProjectPath '$ProjectPath' -RemotePath '$RemotePath' -ComposeService '$ComposeService' -KeepRemoteFiles $KeepRemoteFiles *> '$logFile'"

$existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -Command `"$command`""
$trigger = New-ScheduledTaskTrigger -Daily -At $startBoundary
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 2)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description "Backup diario do banco para Google Drive com retencao de 2 arquivos"

Write-Host "Task Scheduler registrado com sucesso: $TaskName"
Write-Host "Horario: $RunAt"
Write-Host "Projeto: $ProjectPath"
Write-Host "RemotePath: $RemotePath"
Write-Host "Log: $logFile"
