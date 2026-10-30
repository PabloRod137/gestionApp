<#
  Arranca el panel del despacho en este equipo con la base de datos real del despacho (MySQL, NO la
  de ejemplo). Las variables solo valen para este proceso: no cambian nada del terminal. No lances las
  pruebas desde esta ventana (las pruebas corren siempre en SQLite en memoria; ver CLAUDE.md).

  Uso:   .\scripts\arrancar-panel.ps1
         .\scripts\arrancar-panel.ps1 -Puerto 8100 -BaseDeDatos despacho_produccion -Usuario nortia -Contrasena ****
#>
param(
    [int]$Puerto = 8099,
    [string]$Host_ = '127.0.0.1',
    [int]$PuertoMysql = 3306,
    [string]$BaseDeDatos = 'nortia',
    [string]$Usuario = 'root',
    [string]$Contrasena = ''
)

$ErrorActionPreference = 'Stop'
$raiz = Resolve-Path (Join-Path $PSScriptRoot '..')

$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = $Host_
$env:DB_PORT = $PuertoMysql
$env:DB_DATABASE = $BaseDeDatos
$env:DB_USERNAME = $Usuario
$env:DB_PASSWORD = $Contrasena
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'log'

Set-Location $raiz
Write-Host "Panel en http://127.0.0.1:$Puerto  (base MySQL: $BaseDeDatos en ${Host_}:$PuertoMysql)" -ForegroundColor Green
Write-Host 'Para el procesado de documentos, abre otra ventana con .\scripts\trabajador-documental.ps1' -ForegroundColor DarkGray
php artisan serve --host=127.0.0.1 --port=$Puerto
