<?php
/* #########################################################
   logout.php — CERRAR SESIÓN
   No tiene HTML: borra la sesión y manda al login.
   ######################################################### */


// Hay que "abrir" la sesión para poder borrarla
require_once __DIR__ . '/includes/sesion.php';   // arranca la sesión con cookie segura


// 1. Vaciar todos los datos guardados (usuario_id, nombre, rol...)
$_SESSION = [];


// 2. Borrar la cookie de sesión del navegador.
//    Se "borra" enviándola otra vez con fecha ya pasada (time() - 3600 = hace una hora),
//    con los mismos parámetros con los que se creó.
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);


// 3. Destruir la sesión en el servidor
session_destroy();


// 4. Al login
header('Location: index.php');
exit;
