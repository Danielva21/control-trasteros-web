<?php
/* #########################################################
   api/estado.php — ESTADO DE LOS TRASTEROS (lo pide el JS)

   El JavaScript de la portada lo llama cada pocos segundos
   para saber si cada trastero está cerrado, abierto…
   No devuelve una página, sino datos en JSON:

     {
       "online": true,                 ← ¿responde Node-RED?
       "puertas": {
         "5": {"estado": "cerrada", "fallo": false},
         "4": {"estado": "abierta", "fallo": false}
       }
     }
   La clave ("5", "4") es el id del trastero en NUESTRA BD
   (el data-id de cada tarjeta), no el de Node-RED.

   Solo devuelve los trasteros que ESTE usuario puede abrir
   (el admin: todos los que están en servicio).
   ######################################################### */



require_once __DIR__ . '/../includes/sesion.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/nodered.php';

// Todas las respuestas de este archivo son JSON
header('Content-Type: application/json; charset=utf-8');



/* =========================================================
   1. ¿QUIÉN PREGUNTA?
   Aquí no redirigimos al login (lo llama el JS, no una
   persona): respondemos con un código de error y JSON.
   401 = no has iniciado sesión / ya no vale.
   ========================================================= */

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

$consulta = $pdo->prepare('SELECT id, rol, activo FROM usuarios WHERE id = ?');
$consulta->execute([$_SESSION['usuario_id']]);
$usuario = $consulta->fetch();

if ($usuario === false || (int) $usuario['activo'] !== 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no activo']);
    exit;
}



/* =========================================================
   2. SUS TRASTEROS (con su plc_id para buscarlos en Node-RED)
   ========================================================= */

if ($usuario['rol'] === 'admin') {
    // El admin ve el estado de todos los que están en servicio
    $trasteros = $pdo->query('SELECT id, plc_id FROM puertas WHERE activa = 1')->fetchAll();
} else {
    // Un cliente, solo los suyos vigentes (la misma regla que la portada)
    $consulta = $pdo->prepare(
        'SELECT p.id, p.plc_id
           FROM permisos pe
           JOIN puertas p ON p.id = pe.puerta_id
          WHERE pe.usuario_id = ?
            AND p.activa = 1
            AND (pe.fecha_fin IS NULL OR pe.fecha_fin >= NOW())'
    );
    $consulta->execute([$usuario['id']]);
    $trasteros = $consulta->fetchAll();
}



/* =========================================================
   3. PREGUNTAR A NODE-RED Y MONTAR LA RESPUESTA
   Si Node-RED no responde → online = false y todos
   "sin_conexion". Si responde pero no conoce un plc_id
   (trastero mal configurado) → "desconocido".
   ========================================================= */

$estados = nodered_estados();   // null si Node-RED no responde

$respuesta = [
    'online'  => $estados !== null,
    'puertas' => [],
];

foreach ($trasteros as $t) {
    if ($estados === null) {
        $respuesta['puertas'][$t['id']] = ['estado' => 'sin_conexion', 'fallo' => false];
    } else {
        $respuesta['puertas'][$t['id']] = $estados[$t['plc_id']] ?? ['estado' => 'desconocido', 'fallo' => false];
    }
}

// json_encode = convertir el array PHP en texto JSON. JSON_FORCE_OBJECT: que "puertas"
// sea siempre un objeto {…} aunque esté vacío (si no, saldría una lista [] vacía).
echo json_encode($respuesta, JSON_FORCE_OBJECT);
