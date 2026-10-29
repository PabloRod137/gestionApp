# Demostración de LexGuardian con DATOS INVENTADOS, para enseñarla desde el móvil en la misma wifi.
#   Uso (PowerShell, desde la carpeta del proyecto):   .\scripts\demo-movil.ps1
# Crea una base temporal con los datos de ejemplo (nunca toca database\despacho.sqlite, que es la real),
# arranca el servidor abierto a la red local y escribe la dirección que hay que teclear en el móvil.
# Parar: Ctrl+C. Al terminar se borra la base temporal.
# Las cuentas de ejemplo y sus contraseñas están en database\seeders\UserSeeder.php (cliente@cliente.es, admin@admin.es, ...).
param([int]$Puerto = 8095)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

$carpeta = Join-Path ([IO.Path]::GetTempPath()) 'lexguardian-demo'
New-Item -ItemType Directory -Force $carpeta | Out-Null
$db = Join-Path $carpeta 'demo.sqlite'
if (Test-Path $db) { Remove-Item $db -Force }
New-Item -ItemType File $db | Out-Null

$env:DB_CONNECTION = 'sqlite'; $env:DB_DATABASE = $db
$env:SESSION_DRIVER = 'file'; $env:CACHE_STORE = 'file'; $env:QUEUE_CONNECTION = 'sync'; $env:MAIL_MAILER = 'log'
$env:APP_ENV = 'local'; $env:ALLOW_SAMPLE_DATA = '1'

Write-Host 'Preparando la demostración (datos inventados)...' -ForegroundColor Cyan
php artisan migrate --force | Out-Null
php artisan db:seed --force | Out-Null

# la dirección de la tarjeta que sale a internet (la wifi), no la de VirtualBox, Docker, etc.
$ip = Get-NetIPConfiguration |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1 -ExpandProperty IPv4Address |
    Select-Object -ExpandProperty IPAddress

Write-Host ''
Write-Host 'Listo. En el móvil (conectado a la MISMA wifi que este ordenador), abre:' -ForegroundColor Green
if ($ip) { Write-Host "    http://${ip}:$Puerto" -ForegroundColor Yellow } else { Write-Host '    (no se ha encontrado la IP; mírala con ipconfig)' -ForegroundColor Yellow }
Write-Host "Área del cliente: /login   ·   Panel: /login con una cuenta de equipo."
Write-Host 'Si no carga: Windows pregunta la primera vez si permites el acceso a la red; di que sí (red privada).'
Write-Host 'Ctrl+C para parar.' -ForegroundColor DarkGray
Write-Host ''

try {
    php artisan serve --host=0.0.0.0 --port=$Puerto
}
finally {
    Remove-Item $db -Force -ErrorAction SilentlyContinue
    Write-Host 'Demostración cerrada y base temporal borrada.' -ForegroundColor Cyan
}
