<#
.SYNOPSIS
    Uploads a file to your Hostinger file-manager API.

.DESCRIPTION
    Sends a file to /api/upload.php using POST multipart/form-data.
    Automatically logs locally and displays server-side response.
    ComputerName is auto-detected unless overridden.

.PARAMETER FilePath
    Full path to the file you want to upload.

.PARAMETER TargetFolder
    Optional server-side folder (defaults to root uploads folder).

.PARAMETER ComputerName
    Used for logging on server side; defaults to $env:COMPUTERNAME.

.EXAMPLE
    .\Upload-FileToServer.ps1 -FilePath "C:\temp\myfile.txt"

.EXAMPLE
    .\Upload-FileToServer.ps1 -FilePath "report.html" -TargetFolder "html"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$FilePath,

    [string]$TargetFolder = "",

    [string]$ComputerName = $env:COMPUTERNAME
)

# ----------------------------
# CONFIG - Update these values
# ----------------------------
$ApiUrl = "https://jasr.me/file-manager/api/upload.php"
$ApiKey = "REPLACE_WITH_YOUR_API_KEY"

# Local logging
$LocalLogFolder = "C:\temp\powershell-exports"
if (!(Test-Path $LocalLogFolder)) {
    New-Item -ItemType Directory -Path $LocalLogFolder | Out-Null
}
$LogFile = Join-Path $LocalLogFolder ("upload-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".log")

# ----------------------------
# Validate File
# ----------------------------
if (!(Test-Path $FilePath)) {
    Write-Host "ERROR: File does not exist: $FilePath" -ForegroundColor Red
    Add-Content $LogFile "[$(Get-Date)] ERROR - File not found: $FilePath"
    exit 1
}

Write-Host "Uploading: $FilePath" -ForegroundColor Cyan

# ----------------------------
# Build POST body
# ----------------------------
$Form = @{
    file = Get-Item $FilePath
    api_key = $ApiKey
    computer = $ComputerName
}

if ($TargetFolder -ne "") {
    $Form.target_folder = $TargetFolder
}

try {
    $Response = Invoke-RestMethod -Uri $ApiUrl -Method Post -Form $Form -ErrorAction Stop

    if ($Response.status -eq "success") {
        Write-Host "SUCCESS: $($Response.message)" -ForegroundColor Green
        Add-Content $LogFile "[$(Get-Date)] SUCCESS - $($Response.message)"
    }
    else {
        Write-Host "SERVER ERROR: $($Response.message)" -ForegroundColor Yellow
        Add-Content $LogFile "[$(Get-Date)] SERVER ERROR - $($Response.message)"
    }
}
catch {
    Write-Host "HTTP ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Add-Content $LogFile "[$(Get-Date)] HTTP ERROR - $($_.Exception.Message)"
}
