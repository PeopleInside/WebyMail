param(
    [string]$TargetUrl = "https://mail.example.com",
    [string]$ReportDir = "./reports/zap",
    [string]$ZapPath = "C:\Program Files\ZAP\Zed Attack Proxy\zap.bat"
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $ReportDir)) {
    New-Item -ItemType Directory -Path $ReportDir -Force | Out-Null
}

$reportDirAbs = [System.IO.Path]::GetFullPath($ReportDir)

if (!(Test-Path $ZapPath)) {
    throw "ZAP executable not found at: $ZapPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$htmlReport = Join-Path $reportDirAbs "zap-baseline-$timestamp.html"

Write-Host "Running ZAP baseline against: $TargetUrl"

$zapDir = Split-Path -Parent $ZapPath
Push-Location $zapDir
try {
    & "$ZapPath" -cmd -quickurl "$TargetUrl" -quickout "$htmlReport" -quickprogress
} finally {
    Pop-Location
}

Write-Host "Reports generated:"
Write-Host "- $htmlReport"
