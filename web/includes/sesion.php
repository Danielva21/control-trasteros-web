<?php
/* #########################################################
   includes/sesion.php — ARRANCAR LA SESIÓN DE FORMA SEGURA

   Sustituye al "session_start()" suelto. Antes de arrancar
   la sesión, configura la COOKIE de sesión (la que guarda el
   navegador para que el servidor sepa quién eres):

     httponly = true  → el JavaScript de la página NO puede leer
                        la cookie. Si alguien consiguiera colar un
                        script, no podría robar la sesión.
     samesite = 'Lax' → el navegador no manda la cookie en
                        peticiones que vienen de OTRAS webs (ayuda
                        contra ataques CSRF).
     secure   = true  → la cookie solo viaja por HTTPS. En local
                        vamos por http, así que solo se activa si
                        la página se está viendo por HTTPS (en el
                        servidor real, con el túnel).

   CÓMO SE USA:  require_once __DIR__ . '/sesion.php';
   ######################################################### */


// session_status() dice si la sesión ya está arrancada; así no se arranca dos veces
if (session_status() === PHP_SESSION_NONE) {

    $es_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,          // 0 = la cookie se borra al cerrar el navegador
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $es_https,
    ]);

    session_start();
}
