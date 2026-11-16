param(
    [string]$Php = 'D:\xampp\php\php.exe',
    [string]$Project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

# Jalankan secara manual dengan hak yang sesuai bila jadwal tiap menit diperlukan.
if (-not (Test-Path -LiteralPath $Php)) { throw "PHP tidak ditemukan: $Php" }
if (-not (Test-Path -LiteralPath (Join-Path $Project 'spark'))) { throw "Proyek tidak ditemukan: $Project" }
$actionAlert = New-ScheduledTaskAction -Execute $Php -Argument 'spark alert:check' -WorkingDirectory $Project
$actionMonitor = New-ScheduledTaskAction -Execute $Php -Argument 'spark monitor:collect' -WorkingDirectory $Project
$start = (Get-Date).AddMinutes(1)
$triggerAlert = New-ScheduledTaskTrigger -Once -At $start -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 365)
$triggerMonitor = New-ScheduledTaskTrigger -Once -At $start -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 365)
Register-ScheduledTask -TaskName 'TiketLayananAlertCheck' -Action $actionAlert -Trigger $triggerAlert -Description 'Periksa alert Tiket Layanan setiap menit' -Force
Register-ScheduledTask -TaskName 'TiketLayananMonitorCollect' -Action $actionMonitor -Trigger $triggerMonitor -Description 'Simpan metrik Tiket Layanan setiap menit' -Force
