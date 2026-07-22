# Updates the plugin version across index.php and readme.txt on Windows.
# Usage: .\bin\set-version.ps1 2.0.3

param (
    [Parameter(Mandatory=$true, Position=0)]
    [string]$Version
)

$ErrorActionPreference = "Stop"

if ($Version -notmatch '^\d+\.\d+\.\d+') {
    Write-Error "Version must be in semver format (e.g. 2.0.3)"
    exit 1
}

Write-Host "Updating plugin version to $Version in index.php and readme.txt..." -ForegroundColor Cyan

php -r "
\$ver = \$argv[1];
\$targets = [
    ['index.php', '/(Version:\s*)[0-9][0-9.]*/'],
    ['readme.txt', '/(Stable tag:\s*)[0-9][0-9.]*/']
];
foreach (\$targets as [\$file, \$pattern]) {
    \$content = file_get_contents(\$file);
    \$updated = preg_replace(\$pattern, '\${1}' . \$ver, \$content, 1);
    file_put_contents(\$file, \$updated);
}
" "$Version"

Write-Host "Version updated successfully to $Version." -ForegroundColor Green
