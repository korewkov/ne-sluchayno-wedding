param([string]$OutputPath = "$PSScriptRoot\..\.local\production-config.json")
$ErrorActionPreference = 'Stop'
function Read-SecretText([string]$Prompt) {
    $secure = Read-Host $Prompt -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
}
$siteUrl = (Read-Host 'Site URL (https://...)').TrimEnd('/')
if ($siteUrl -notmatch '^https://[a-zA-Z0-9.-]+$') { throw 'Enter an HTTPS URL without a path.' }
$dbName = Read-Host 'MySQL database name'
$dbUser = Read-Host 'MySQL user'
$dbHostName = Read-Host 'MySQL hostname (Enter for localhost)'
if (!$dbHostName) { $dbHostName = 'localhost' }
if ($dbName -notmatch '^[a-zA-Z0-9_]+$' -or $dbHostName -notmatch '^[a-zA-Z0-9.-]+$') { throw 'Invalid database name or hostname.' }
$dbPassword = Read-SecretText 'MySQL password (hidden)'
$adminPassword = Read-SecretText 'New admin password, at least 12 characters (hidden)'
$confirmPassword = Read-SecretText 'Repeat admin password (hidden)'
if ($adminPassword -cne $confirmPassword) { throw 'Passwords do not match.' }
$config = @{public_url=$siteUrl;db_dsn="mysql:host=$dbHostName;dbname=$dbName;charset=utf8mb4";db_user=$dbUser;db_password=$dbPassword;admin_password=$adminPassword;development=$false}
$phpPath = Join-Path $PSScriptRoot '..\.local\php\php.exe'
if (!(Test-Path -LiteralPath $phpPath)) { throw 'Local PHP runtime is missing.' }
$target = [IO.Path]::GetFullPath($OutputPath)
[IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($target)) | Out-Null
$config | ConvertTo-Json -Compress | & $phpPath "$PSScriptRoot\provision.php" $target
if ($LASTEXITCODE -ne 0) { throw 'Configuration could not be created.' }
$config=$null; $dbPassword=$null; $adminPassword=$null; $confirmPassword=$null
Write-Host 'Upload the generated file only as private/config.json, outside public_html. Do not share it in chat.'
