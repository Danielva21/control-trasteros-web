<?php
/* #########################################################
   api/abrir.php — ABRIR UN TRASTERO (lo pide el JS)

   Es la parte MÁS DELICADA de toda la web: aquí se decide si
   una puerta real se abre. Por eso, antes de pedir nada a
   Node-RED, el servidor comprueba TODO (regla del CLAUDE.md):

     1. Es una petición POST con token CSRF válido.
     2. Hay sesión y el usuario sigue ACTIVO.
     3. El trastero existe y está EN SERVICIO.
     4. El usuario tiene PERMISO para ese trastero y NO ha caducado.
   Solo si todo se cumple → Node-RED → PLC.

   CADA intento (bien o mal) se guarda en la tabla accesos:
     ok · denegado · caducado · error_plc

   Recibe (POST):  puerta_id = id del trastero en NUESTRA BD
                   csrf      = token CSRF
   Responde (JSON): {"ok": true/false, "resultado": "...", "mensaje": "..."}
   ######################################################### */



require_once __DIR__ . '/../includes/sesion.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/nodered.php';

header('Content-Type: application/json; charset=utf-8');



/* =========================================================
   responder(...): contestar al JS y TERMINAR.
   Función pequeña para no repetir las mismas 3 líneas.
   ========================================================= */

function responder($codigo_http, $ok, $resultado, $mensaje)
{
    http_response_code($codigo_http);
    echo json_encode(['ok' => $ok, 'resultado' => $resultado, 'mensaje' => $mensaje]);
    exit;
}


/* =========================================================
   registrar_acceso(...): apuntar el intento en el historial.
   $_SERVER['REMOTE_ADDR'] = la IP desde la que se ha pedido.
   ========================================================= */

function registrar_acceso($pdo, $usuario_id, $puerta_id, $resultado)
{
    $pdo->prepare('INSERT INTO accesos (usuario_id, puerta_id, resultado, ip) VALUES (?, ?, ?, ?)')
        ->execute([$usuario_id, $puerta_id, $resultado, $_SERVER['REMOTE_ADDR'] ?? null]);
}



/* =========================================================
   1. SOLO POST + TOKEN CSRF
   (abrir una puerta CAMBIA algo en el mundo real: nunca por GET,
   que se puede disparar con un simple enlace o una imagen)
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, false, 'error', 'Método no permitido.');   // 405 = método no permitido
}

csrf_comprobar();   // si el token no vale, corta aquí (400)



/* =========================================================
   2. SESIÓN Y USUARIO ACTIVO
   ========================================================= */

if (!isset($_SESSION['usuario_id'])) {
    responder(401, false, 'error', 'Tu sesión ha caducado. Vuelve a entrar.');
}

$consulta = $pdo->prepare('SELECT id, activo FROM usuarios WHERE id = ?');
$consulta->execute([$_SESSION['usuario_id']]);
$usuario = $consulta->fetch();

if ($usuario === false) {
    responder(401, false, 'error', 'Tu sesión ha caducado. Vuelve a entrar.');
}



/* =========================================================
   3. EL TRASTERO EXISTE
   Si no existe, no se puede registrar en accesos (la clave
   foránea lo impide), así que solo se responde.
   ========================================================= */

$puerta_id = (int) ($_POST['puerta_id'] ?? 0);

$consulta = $pdo->prepare('SELECT id, plc_id, activa FROM puertas WHERE id = ?');
$consulta->execute([$puerta_id]);
$puerta = $consulta->fetch();

if ($puerta === false) {
    responder(404, false, 'error', 'Ese trastero no existe.');
}



/* =========================================================
   4. COMPROBACIONES DE PERMISO (cada fallo queda registrado)
   ========================================================= */

// 4.1 Usuario suspendido
if ((int) $usuario['activo'] !== 1) {
    registrar_acceso($pdo, $usuario['id'], $puerta['id'], 'denegado');
    responder(403, false, 'denegado', 'Tu cuenta está suspendida. Contacta con la empresa.');   // 403 = prohibido
}

// 4.2 Trastero dado de baja
if ((int) $puerta['activa'] !== 1) {
    registrar_acceso($pdo, $usuario['id'], $puerta['id'], 'denegado');
    responder(403, false, 'denegado', 'Este trastero está fuera de servicio.');
}

// 4.3 ¿Tiene permiso? ¿Está vigente?
$consulta = $pdo->prepare(
    'SELECT fecha_fin,
            (fecha_fin IS NULL OR fecha_fin >= NOW()) AS vigente
       FROM permisos
      WHERE usuario_id = ? AND puerta_id = ?'
);
$consulta->execute([$usuario['id'], $puerta['id']]);
$permiso = $consulta->fetch();

if ($permiso === false) {
    registrar_acceso($pdo, $usuario['id'], $puerta['id'], 'denegado');
    responder(403, false, 'denegado', 'No tienes permiso para abrir este trastero.');
}

if ((int) $permiso['vigente'] !== 1) {
    registrar_acceso($pdo, $usuario['id'], $puerta['id'], 'caducado');
    responder(403, false, 'caducado', 'Tu permiso para este trastero ha caducado. Contacta con la empresa para renovarlo.');
}



/* =========================================================
   5. TODO CORRECTO → PEDIR LA APERTURA A NODE-RED
   ========================================================= */

if (nodered_abrir($puerta['plc_id'])) {
    registrar_acceso($pdo, $usuario['id'], $puerta['id'], 'ok');
    responder(200, true, 'ok', 'Abriendo…');
}

// Node-RED no respondió o rechazó la orden
registrar_acceso($pdo, $usuario['id'], $puerta['id'], 'error_plc');
responder(502, false, 'error_plc', 'La instalación no responde. Inténtalo de nuevo en un momento o contacta con soporte.');   // 502 = fallo del sistema de detrás
