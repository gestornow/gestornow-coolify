param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectPath,

    [Parameter(Mandatory = $true)]
    [string]$PhpPath,

    [string]$TaskName = "GestorNow Laravel Scheduler"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ProjectPath)) {
    throw "ProjectPath nao encontrado: $ProjectPath"
}

if (-not (Test-Path $PhpPath)) {
    throw "PhpPath nao encontrado: $PhpPath"
}

$artisanPath = Join-Path $ProjectPath "artisan"
if (-not (Test-Path $artisanPath)) {
    throw "Arquivo artisan nao encontrado em: $artisanPath"
}

$logDir = Join-Path $ProjectPath "storage\logs"
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

$logFile = Join-Path $logDir "scheduler.log"
$command = "Set-Location '$ProjectPath'; & '$PhpPath' artisan schedule:run *> '$logFile'"

$existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -Command `"$command`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description "Executa php artisan schedule:run a cada minuto"

Write-Host "Task Scheduler registrado com sucesso: $TaskName"
Write-Host "Projeto: $ProjectPath"
Write-Host "PHP: $PhpPath"
Write-Host "Log: $logFile"
