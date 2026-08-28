# Revision : 1.2
# Description : Generate .htpasswd entries (username:{SHA}Base64Hash) for Apache basic auth, using parameter input only. Fixed string-interpolation issue with colon after variable. (Rev 1.2)
# Author : Jason Lamb (with help from ChatGPT)
# Created Date : 2025-11-24
# Modified Date : 2025-11-24

param(
    [Parameter(Mandatory=$true)]
    [array]$Users,

    [Parameter(Mandatory=$true)]
    [string]$OutputFile,

    [switch]$Append
)

function Get-Sha1Base64 {
    param(
        [string]$PlainText
    )
    # Convert plaintext to ASCII bytes
    $bytes = [System.Text.Encoding]::ASCII.GetBytes($PlainText)
    $sha1 = [System.Security.Cryptography.SHA1]::Create()
    $hashBytes = $sha1.ComputeHash($bytes)
    $base64 = [Convert]::ToBase64String($hashBytes)
    return $base64
}

# Handle output file: overwrite by default, append if parameter specified
if (-not $Append.IsPresent) {
    if (Test-Path $OutputFile) {
        Remove-Item -Path $OutputFile -ErrorAction SilentlyContinue
    }
}

foreach ($u in $Users) {
    $userName = $u.User
    $password = $u.Password

    if ([string]::IsNullOrWhiteSpace($userName)) {
        Write-Warning "Empty username skipped."
        continue
    }
    if ($null -eq $password) {
        Write-Warning "Password for user '$userName' is null or empty – skipped."
        continue
    }

    $hash64 = Get-Sha1Base64 -PlainText $password
    # Use braces around variable name to prevent parsing error when colon follows
    $entry = "${userName}:{SHA}$hash64"

    Add-Content -Path $OutputFile -Value $entry
    Write-Host "Added entry for user : $userName"
}

Write-Host "Completed. Output file : $OutputFile"

<#
.EXAMPLE
. .\Generate-Htpasswd.ps1 -Users @{User="bob";Password="P@ssw0rd"}, @{User="alice";Password="Another1"} -OutputFile ".\my.htpasswd"

.EXAMPLE
. .\Generate-Htpasswd.ps1 -Users @{User="charlie";Password="Pass123"} -OutputFile ".\my.htpasswd" -Append
#>
