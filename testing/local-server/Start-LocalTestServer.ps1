[CmdletBinding()]
param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8080,

    [ValidateNotNullOrEmpty()]
    [string]$HostName = '127.0.0.1',

    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'

function Get-PythonCommand {
    foreach ($commandName in @('py.exe', 'python.exe', 'python3.exe')) {
        $command = Get-Command $commandName -ErrorAction SilentlyContinue
        if ($command) {
            return $command.Source
        }
    }

    throw 'Python was not found. Install Python 3, then rerun this launcher.'
}

function New-TestIndex {
    param(
        [string]$DirectoryPath
    )

    $indexPath = Join-Path $DirectoryPath 'index.html'
    $pages = Get-ChildItem -LiteralPath $DirectoryPath -File -Filter '*.html' |
        Where-Object { $_.Name -ne 'index.html' } |
        Sort-Object Name

    $links = if ($pages) {
        ($pages | ForEach-Object {
            $name = [System.Net.WebUtility]::HtmlEncode($_.Name)
            $href = [Uri]::EscapeDataString($_.Name)
            "    <li><a href=""$href"">$name</a></li>"
        }) -join [Environment]::NewLine
    } else {
        '    <li>No HTML test pages are in this directory yet.</li>'
    }

    $content = @"
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Local Test Pages</title>
</head>
<body>
  <h1>Local Test Pages</h1>
  <ul>
$links
  </ul>
</body>
</html>
"@

    [IO.File]::WriteAllText(
        $indexPath,
        $content,
        [Text.UTF8Encoding]::new($false)
    )

    return $indexPath
}

$testRoot = $PSScriptRoot
$indexPath = New-TestIndex -DirectoryPath $testRoot
$pythonPath = Get-PythonCommand
$urlHost = if ($HostName -eq '0.0.0.0') { '127.0.0.1' } else { $HostName }
$url = "http://${urlHost}:$Port/"
$arguments = "-m http.server $Port --bind $HostName --directory `"$testRoot`""

$process = Start-Process -FilePath $pythonPath -ArgumentList $arguments -WorkingDirectory $testRoot -PassThru
Start-Sleep -Milliseconds 500

if ($process.HasExited) {
    throw "The local test server exited with code $($process.ExitCode)."
}

if (-not $NoBrowser) {
    Start-Process $url
}

[pscustomobject]@{
    ProcessId = $process.Id
    Url = $url
    IndexPath = $indexPath
    DocumentRoot = $testRoot
}
