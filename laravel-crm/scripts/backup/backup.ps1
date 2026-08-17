[CmdletBinding()]
param(
    [string]$BackupRoot = $(if ($env:CRM_BACKUP_ROOT) { $env:CRM_BACKUP_ROOT } else { 'C:\xampp\backups\crm' }),
    [int]$RetentionDays = 30
)

$ErrorActionPreference = 'Stop'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$destination = Join-Path $BackupRoot $timestamp
New-Item -ItemType Directory -Path $destination -Force | Out-Null

$env:DB_HOST = if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }
$env:DB_PORT = if ($env:DB_PORT) { $env:DB_PORT } else { '3306' }
$required = @('DB_DATABASE', 'DB_USERNAME')
foreach ($name in $required) {
    if ([string]::IsNullOrWhiteSpace((Get-Item "Env:$name" -ErrorAction SilentlyContinue).Value)) {
        throw "$name must be set in the environment before running a backup."
    }
}

$mysqldump = Get-Command mysqldump -ErrorAction SilentlyContinue
if (-not $mysqldump) {
    throw 'mysqldump was not found on PATH.'
}

$dbFile = Join-Path $destination 'database.sql'
$arguments = @('--single-transaction', '--routines', '--events', '--triggers', '--host', $env:DB_HOST, '--port', $env:DB_PORT, '--user', $env:DB_USERNAME, $env:DB_DATABASE)
if ($env:DB_PASSWORD) {
    $env:MYSQL_PWD = $env:DB_PASSWORD
}
try {
    & $mysqldump.Source @arguments | Out-File -FilePath $dbFile -Encoding utf8
    if ($LASTEXITCODE -ne 0) { throw 'mysqldump failed.' }
} finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}

$storagePath = Join-Path $PSScriptRoot '..\..\storage\app'
$storageArchive = Join-Path $destination 'storage-app.zip'
if (Test-Path $storagePath) {
    Compress-Archive -Path (Join-Path $storagePath '*') -DestinationPath $storageArchive -CompressionLevel Optimal
}

Get-ChildItem -Path $BackupRoot -Directory | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays) } | Remove-Item -Recurse -Force
Write-Output "Backup created at $destination"
Write-Output 'The .env file and secrets were intentionally excluded; store them separately in an approved secret manager.'
