<#
  Arranca el panel del despacho en este equipo con la base de datos local (database\despacho.sqlite).
  Las variables solo valen para este proceso: no cambian nada del terminal. No lances las pruebas desde esta ventana
  (las pruebas corren siempre en memoria; ver CLAUDE.md).

  Uso:   .\scripts\arrancar-panel.ps1            (puerto 8099)
         .\scripts\arrancar-panel.ps1 -Puerto 8100
#>
param(
    [int]$Puerto = 8099,
    [string]$BaseDeDatos = (Join-Path $PSScriptRoot '..\database\despacho.sqlite')
)

$ErrorActionPreference = 'Stop'
$raiz = Resolve-Path (Join-Path $PSScriptRoot '..')
$base = [System.IO.Path]::GetFullPath($BaseDeDatos)
if (-not (Test-Path $base)) { throw "No existe la base de datos: $base (ver docs\ARRANCAR_EN_LOCAL.md, paso 2)" }

$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $base
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'log'

Set-Location $raiz
Write-Host "Panel en http://127.0.0.1:$Puerto  (base: $base)" -ForegroundColor Green
Write-Host 'Para el procesado de documentos, abre otra ventana con .\scripts\trabajador-documental.ps1' -ForegroundColor DarkGray
php artisan serve --host=127.0.0.1 --port=$Puerto
