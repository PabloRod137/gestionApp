<#
  Trabajador de la cola local de documentos: lee en este equipo los documentos de las carpetas de los clientes y deja
  propuestas en la Bandeja documental. Nada sale del equipo. Para el trabajador con Ctrl+C.

  Antes: DOCUMENTAL_RAIZ en el .env con la carpeta de la biblioteca de documentos sincronizada en este equipo.
  Cada pasada espera 5 segundos si no hay nada que hacer y se reinicia sola cada hora (así libera memoria).

  Uso:   .\scripts\trabajador-documental.ps1
         .\scripts\trabajador-documental.ps1 -Inventariar    (antes de empezar, revisa también las carpetas)
#>
param(
    [string]$BaseDeDatos = (Join-Path $PSScriptRoot '..\database\despacho.sqlite'),
    [switch]$Inventariar
)

$ErrorActionPreference = 'Stop'
$raiz = Resolve-Path (Join-Path $PSScriptRoot '..')
$base = [System.IO.Path]::GetFullPath($BaseDeDatos)
if (-not (Test-Path $base)) { throw "No existe la base de datos: $base" }

$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $base
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'database'
$env:MAIL_MAILER = 'log'

Set-Location $raiz
if ($Inventariar) { php artisan documentos:inventariar }
Write-Host 'Trabajador documental en marcha (cola "documentos"). Ctrl+C para parar.' -ForegroundColor Green
while ($true) {
    php artisan queue:work database --queue=documentos --tries=3 --sleep=5 --max-time=3600
}
