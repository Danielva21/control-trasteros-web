<?php
/* #########################################################
   includes/csrf.php — PROTECCIÓN CSRF DE LOS FORMULARIOS

   ¿Qué es un ataque CSRF?
   Dani tiene la sesión abierta en el panel. Visita otra web
   maliciosa que, por debajo, envía un formulario a NUESTRO
   panel ("suspender a todos los usuarios"). Como el navegador
   de Dani manda su cookie de sesión, nuestro servidor creería
   que lo ha pedido Dani.

   La defensa: un TOKEN (código aleatorio y secreto) que:
     1. se guarda en la sesión del usuario, y
     2. va escondido dentro de CADA formulario nuestro.
   Al recibir un formulario, se comprueba que el token que
   llega es igual al de la sesión. La web maliciosa no puede
   conocerlo, así que su formulario falso es rechazado.

   CÓMO SE USA:
     - Dentro de cada <form method="post">:   <?php csrf_campo(); ?>
     - Al procesar un POST, lo primero:       csrf_comprobar();
   ######################################################### */



/* =========================================================
   csrf_token(): devuelve el token de la sesión (si no hay, lo crea)
   random_bytes(32) = 32 bytes aleatorios de verdad (seguros);
   bin2hex() los convierte en texto (64 caracteres 0-9 a-f).
   ========================================================= */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}



/* =========================================================
   csrf_campo(): escribe el campo oculto con el token
   type="hidden" = el usuario no lo ve, pero viaja con el formulario.
   ========================================================= */

function csrf_campo()
{
    echo '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}



/* =========================================================
   csrf_comprobar(): si el token no coincide, para TODO.
   hash_equals() compara dos textos de forma segura (tarda
   lo mismo aunque fallen en la primera letra o en la última,
   así no se puede adivinar el token midiendo tiempos).
   ========================================================= */

function csrf_comprobar()
{
    $enviado = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);   // 400 = "petición incorrecta"
        exit('La petición no es válida (puede que la página llevara mucho tiempo abierta). Vuelve atrás, recarga e inténtalo de nuevo.');
    }
}
