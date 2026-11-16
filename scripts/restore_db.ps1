<#
.SYNOPSIS
  Memulihkan dump `php spark backup:db` ke sebuah database, lalu memasang ulang hak akun aplikasi.

.DESCRIPTION
  Restore memakai akun DBA. Dump berisi DROP/CREATE routine sehingga hak EXECUTE `tiket_app` pada
  fn_sla_hours, fn_resolution_minutes, sp_close_ticket, dan sp_officer_ranking HILANG setelah restore
  (ditemukan pada A11 bug 3). Skrip ini menjalankan ulang database/sql/06_grants.sql untuk database target.

.EXAMPLE
  ./scripts/restore_db.ps1 -Dump writable/backups/tiket_demo-20261116-140333.sql -Database tiket_restore
#>
param(
    [Parameter(Mandatory)][string]$Dump,
    [Parameter(Mandatory)][string]$Database,
    [string]$Mysql = 'D:\xampp\mysql\bin\mysql.exe',
    [string]$DbaUser = 'root',
    [string]$DbaPassword = '',
    [switch]$Recreate
)

$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (-not (Test-Path -LiteralPath $Dump)) { throw "Dump tidak ditemukan: $Dump" }
if ($Database -eq 'tiket_demo' -and -not $Recreate) { throw 'Memulihkan ke tiket_demo menimpa data; tambahkan -Recreate bila memang disengaja.' }
$auth = @("-u$DbaUser")
if ($DbaPassword -ne '') { $auth += "-p$DbaPassword" }

if ($Recreate) { & $Mysql @auth -e "DROP DATABASE IF EXISTS ``$Database``" }
& $Mysql @auth -e "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4"
Get-Content -LiteralPath $Dump -Raw -Encoding UTF8 | & $Mysql @auth $Database
if ($LASTEXITCODE -ne 0) { throw 'Restore gagal.' }

# Pasang ulang hak: ganti nama database pada skrip hak, abaikan SHOW GRANTS dan komentar.
$grants = (Get-Content -LiteralPath (Join-Path $project 'database/sql/06_grants.sql')) -replace 'tiket_demo', $Database |
    Where-Object { $_ -notmatch '^(--|SHOW GRANTS)' }
$grants | & $Mysql @auth
if ($LASTEXITCODE -ne 0) { throw 'Pemasangan ulang hak gagal.' }
Write-Host "Restore ke $Database selesai dan hak tiket_app dipasang ulang."
