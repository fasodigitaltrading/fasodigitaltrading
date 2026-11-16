[CmdletBinding()]
param(
    [string] $PhpExecutable,
    [ValidateRange(1, 65535)]
    [int] $Port = 8102
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($PhpExecutable)) {
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if (-not $phpCommand) {
        $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    }

    if (-not $phpCommand) {
        throw "PHP n'est pas dans PATH. Relancez avec -PhpExecutable 'C:\chemin\php.exe'."
    }

    $PhpExecutable = $phpCommand.Source
}

if (-not (Test-Path -LiteralPath $PhpExecutable -PathType Leaf)) {
    throw "Exécutable PHP introuvable : $PhpExecutable"
}

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$publicPath = Join-Path $projectRoot 'public'
$router = Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'

if (-not (Test-Path -LiteralPath $router -PathType Leaf)) {
    throw 'Dépendances Laravel absentes. Exécutez composer install avant de lancer le serveur.'
}

$tempRoot = if (-not [string]::IsNullOrWhiteSpace($env:TEMP)) {
    $env:TEMP
} else {
    [System.IO.Path]::GetTempPath()
}

$uploadTemp = Join-Path $tempRoot 'fdt-php-upload-tmp'
New-Item -ItemType Directory -Force -Path $uploadTemp | Out-Null

Write-Host "Aperçu local : http://127.0.0.1:$Port"
Write-Host "Fichiers temporaires PHP : $uploadTemp"
Write-Host 'Arrêtez le serveur avec Ctrl+C.'

$previousAppUrl = $env:APP_URL
$env:APP_URL = "http://127.0.0.1:$Port"

Push-Location $publicPath
try {
    & $PhpExecutable `
        -d "upload_tmp_dir=$uploadTemp" `
        -d "sys_temp_dir=$uploadTemp" `
        -S "127.0.0.1:$Port" `
        -t $publicPath `
        $router

    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
} finally {
    Pop-Location
    if ($null -eq $previousAppUrl) {
        Remove-Item Env:APP_URL -ErrorAction SilentlyContinue
    } else {
        $env:APP_URL = $previousAppUrl
    }
}
