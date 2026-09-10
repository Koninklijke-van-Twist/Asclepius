param(
    [string]$OutputDir = "./vapid-keys",
    [string]$Subject = "mailto:ict@kvt.nl"
)

$ErrorActionPreference = "Stop"

function Get-RequiredCommandPath {
    param([string]$Name)

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if (-not $command) {
        throw "Required command '$Name' not found in PATH."
    }

    return $command.Source
}

Write-Host "Generating VAPID keys..."

$opensslPath = Get-RequiredCommandPath -Name "openssl"
$phpPath = Get-RequiredCommandPath -Name "php"

$fullOutputDir = Resolve-Path -Path "." | ForEach-Object { Join-Path $_.Path $OutputDir }
if (-not (Test-Path -Path $fullOutputDir)) {
    New-Item -Path $fullOutputDir -ItemType Directory | Out-Null
}

$privatePemPath = Join-Path $fullOutputDir "vapid_private.pem"
$publicPemPath = Join-Path $fullOutputDir "vapid_public.pem"
$snippetPath = Join-Path $fullOutputDir "auth_vapid_snippet.php"
$tmpPublicPhpPath = Join-Path $fullOutputDir "tmp_vapid_public.php"
$tmpPrivatePhpPath = Join-Path $fullOutputDir "tmp_vapid_private.php"

& $opensslPath ecparam -name prime256v1 -genkey -noout -out $privatePemPath | Out-Null
& $opensslPath ec -in $privatePemPath -pubout -out $publicPemPath | Out-Null

$phpPublicCode = @'
$p = openssl_pkey_get_public(file_get_contents($argv[1]));
if ($p === false) {
    fwrite(STDERR, "Failed to load public key\n");
    exit(1);
}
$d = openssl_pkey_get_details($p);
if (!isset($d["ec"]["x"], $d["ec"]["y"])) {
    fwrite(STDERR, "Public key is not an EC P-256 key\n");
    exit(1);
}
$raw = chr(4) . $d["ec"]["x"] . $d["ec"]["y"];
echo rtrim(strtr(base64_encode($raw), "+/", "-_"), "=");
'@

$phpPrivateCode = @'
$pem = trim(file_get_contents($argv[1]));
echo str_replace(PHP_EOL, "\\n", $pem);
'@

Set-Content -Path $tmpPublicPhpPath -Value ("<?php`n" + $phpPublicCode) -Encoding UTF8
Set-Content -Path $tmpPrivatePhpPath -Value ("<?php`n" + $phpPrivateCode) -Encoding UTF8

$publicKeyOutputLines = & $phpPath $tmpPublicPhpPath $publicPemPath
if ($LASTEXITCODE -ne 0) {
    throw "Failed to derive public VAPID key using PHP."
}
$publicKeyBase64Url = [string]::Join("`n", @($publicKeyOutputLines)).Trim()
if ([string]::IsNullOrWhiteSpace($publicKeyBase64Url)) {
    throw "Could not derive VAPID public key in base64url format."
}

$privateKeyOutputLines = & $phpPath $tmpPrivatePhpPath $privatePemPath
if ($LASTEXITCODE -ne 0) {
    throw "Failed to convert private VAPID key using PHP."
}
$privateKeyEscaped = [string]::Join("`n", @($privateKeyOutputLines)).Trim()
if ([string]::IsNullOrWhiteSpace($privateKeyEscaped)) {
    throw "Could not convert private key to escaped string format."
}

if (Test-Path -Path $tmpPublicPhpPath) {
    Remove-Item -Path $tmpPublicPhpPath -Force
}
if (Test-Path -Path $tmpPrivatePhpPath) {
    Remove-Item -Path $tmpPrivatePhpPath -Force
}

$snippet = @"
// Add this to web/auth.php
`$webPushSettings = [
    'vapid_public_key' => '$publicKeyBase64Url',
    'vapid_private_pem' => '$privateKeyEscaped',
    'subject' => '$Subject',
];
"@

Set-Content -Path $snippetPath -Value $snippet -Encoding UTF8

Write-Host ""
Write-Host "Done. Files generated:"
Write-Host "  Private key: $privatePemPath"
Write-Host "  Public key : $publicPemPath"
Write-Host "  Snippet    : $snippetPath"
Write-Host ""
Write-Host "Paste the snippet into web/auth.php and then hook those values into the constants/env flow if needed."
