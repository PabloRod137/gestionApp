<?php

/*
| Servidores intermedios de confianza (proxy inverso, balanceador, túnel) que ponen el https y reenvían la petición.
| TRUSTED_PROXIES: direcciones separadas por comas, o "*" para todos. Vacío = no se confía en ninguno (lo normal en local).
| Detrás de uno sin configurarlo, las direcciones salen con http y el navegador bloquea formularios y estilos.
| Lo lee el middleware de proxies del framework en cada petición.
*/
return [
    'proxies' => ($proxies = trim((string) env('TRUSTED_PROXIES', ''))) !== '' ? $proxies : null,
];
