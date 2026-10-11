$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Invoke-LolaTool {
    param([string]$Executable, [string[]]$Arguments)
    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Perintah gagal (exit $LASTEXITCODE): $Executable"
    }
}

$lolaRoot = Split-Path -Path $PSScriptRoot -Parent
$lolaWindows = [Environment]::OSVersion.Platform -eq [PlatformID]::Win32NT
$lolaNpmName = if ($lolaWindows) { 'npm.cmd' } else { 'npm' }
try {
    $lolaPhp = Get-Command php -CommandType Application -ErrorAction Stop | Select-Object -First 1 -ExpandProperty Source
    $lolaComposer = Get-Command composer -CommandType Application -ErrorAction Stop | Select-Object -First 1 -ExpandProperty Source
    $lolaNpm = Get-Command $lolaNpmName -CommandType Application -ErrorAction Stop | Select-Object -First 1 -ExpandProperty Source
    $lolaNode = Get-Command node -CommandType Application -ErrorAction Stop | Select-Object -First 1 -ExpandProperty Source
} catch {
    throw 'PHP 8.4+, Composer 2, dan Node.js 22/24 dengan npm harus tersedia di PATH. Lihat docs/windows.md.'
}

$lolaPhpBanner = (& $lolaPhp --version) -join "`n"
$lolaPhpMatch = [regex]::Match($lolaPhpBanner, '(?m)^PHP (\d+\.\d+\.\d+)')
if ($LASTEXITCODE -ne 0 -or -not $lolaPhpMatch.Success -or [version]$lolaPhpMatch.Groups[1].Value -lt [version]'8.4.0') {
    throw 'PHP yang aktif harus 8.4+. Jalankan php -v dan where.exe php; lihat docs/windows.md untuk memilih PHP baru.'
}
$lolaNodeVersion = & $lolaNode --version
if ($LASTEXITCODE -ne 0 -or $lolaNodeVersion -notmatch '^v(22|23|24)\.') {
    throw 'Gunakan Node.js 22 atau 24 yang kompatibel dengan lockfile frontend.'
}

Push-Location -LiteralPath $lolaRoot
try {
    Set-Location -LiteralPath (Join-Path $lolaRoot 'backend')
    Invoke-LolaTool -Executable $lolaComposer -Arguments @('install', '--no-interaction', '--prefer-dist')
    $lolaEnv = Join-Path $lolaRoot 'backend/.env'
    if (-not [IO.File]::Exists($lolaEnv)) {
        [IO.File]::Copy((Join-Path $lolaRoot 'backend/.env.example'), $lolaEnv, $false)
    }
    $lolaKeyMatch = [regex]::Match([IO.File]::ReadAllText($lolaEnv), '(?m)^APP_KEY=([^\r\n]*)')
    $lolaHasKey = $lolaKeyMatch.Success -and $lolaKeyMatch.Groups[1].Value.Trim().Trim([char[]]@('"', "'")) -ne ''
    if (-not $lolaHasKey) {
        Invoke-LolaTool -Executable $lolaPhp -Arguments @('artisan', 'key:generate')
    }
    $lolaDatabase = Join-Path $lolaRoot 'backend/database/database.sqlite'
    if (-not [IO.File]::Exists($lolaDatabase)) {
        [IO.File]::Open($lolaDatabase, [IO.FileMode]::CreateNew).Dispose()
    }
    Invoke-LolaTool -Executable $lolaPhp -Arguments @('artisan', 'migrate', '--force', '--seed')
    Set-Location -LiteralPath (Join-Path $lolaRoot 'frontend')
    Invoke-LolaTool -Executable $lolaNpm -Arguments @('ci')
    Write-Host 'Setup selesai. Buat akun dari backend, lalu node scripts/dev.mjs dari root.'
} finally {
    Pop-Location
}
