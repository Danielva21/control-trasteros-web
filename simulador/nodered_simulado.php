<?php
/* #########################################################
   simulador/nodered_simulado.php — NODE-RED "DE MENTIRA" PARA PROBAR

   ⚠️  SOLO PARA DESARROLLO. No es parte de la web (por eso está
       fuera de la carpeta web/). En la instalación real, en su
       lugar está el Node-RED de verdad conectado al PLC.

   Responde EXACTAMENTE igual que el Node-RED real
   (contrato de INTEGRACION.md de la web anterior):

     GET  /api/puertas                → estado de las 7 puertas
     POST /api/puerta/{id}/abrir      → "abre" la puerta {id}
                                        (pide Authorization: Bearer <token>)

   Cómo simula el PLC al abrir una puerta:
     0 a 2 s  → "Abriendo"   (el motor liberando la cerradura)
     2 a 8 s  → "Abierta"
     después  → "Cerrada"    (el PLC vuelve a bloquear sola)

   Para simular que el PLC NO responde ("Sin conexión"):
     crea un archivo llamado APAGADO (vacío) en esta carpeta.
     Bórralo para volver a la normalidad.

   ARRANCARLO (desde la carpeta TRASTEROS_WEB), en otra terminal:
     C:/xampp/php/php.exe -S 127.0.0.1:1880 simulador/nodered_simulado.php
   ######################################################### */



// El token tiene que ser el mismo que el de la web: lo leemos de su config.php
require __DIR__ . '/../web/includes/config.php';

// Cuántas puertas tiene el "PLC"
const NUM_PUERTAS = 7;

// Dónde guarda el simulador cuándo se abrió cada puerta (un archivo en la carpeta temporal)
$archivo_estado = sys_get_temp_dir() . '/simulador_nodered.json';

// Todas las respuestas son JSON
header('Content-Type: application/json; charset=utf-8');



/* =========================================================
   ¿PLC "apagado"? → 503 = servicio no disponible
   ========================================================= */

if (file_exists(__DIR__ . '/APAGADO')) {
    http_response_code(503);
    echo json_encode(['error' => 'PLC sin respuesta (simulado)']);
    return;
}



/* =========================================================
   Leer cuándo se abrió cada puerta por última vez
   (si el archivo no existe aún, ninguna se ha abierto)
   ========================================================= */

$aperturas = file_exists($archivo_estado)
    ? json_decode(file_get_contents($archivo_estado), true)
    : [];

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);   // la dirección sin "?…"



/* =========================================================
   GET /api/puertas
   ========================================================= */

if ($metodo === 'GET' && $ruta === '/api/puertas') {

    $ahora  = microtime(true);   // ahora, en segundos con decimales
    $lista  = [];

    for ($id = 1; $id <= NUM_PUERTAS; $id++) {

        $abierta_hace = isset($aperturas[$id]) ? $ahora - $aperturas[$id] : null;

        if ($abierta_hace !== null && $abierta_hace < 2) {
            $estado = 'Abriendo';
        } elseif ($abierta_hace !== null && $abierta_hace < 8) {
            $estado = 'Abierta';
        } else {
            $estado = 'Cerrada';
        }

        $lista[] = [
            'id'         => $id,
            'name'       => 'Puerta ' . $id,
            'zone'       => 'Simulador',
            'state'      => $estado,
            'lock'       => $estado === 'Abriendo' ? 'Accionando' : 'Reposo',
            'fault'      => false,
            'lastUpdate' => gmdate('Y-m-d\TH:i:s.000\Z'),   // formato ISO 8601, como el real
        ];
    }

    echo json_encode($lista);
    return;
}



/* =========================================================
   POST /api/puerta/{id}/abrir
   preg_match con un patrón: comprueba la forma de la dirección
   y "atrapa" el número (lo que va entre paréntesis → $partes[1]).
   ========================================================= */

if ($metodo === 'POST' && preg_match('#^/api/puerta/(\d+)/abrir$#', $ruta, $partes)) {

    // 1. ¿Trae el token correcto?
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($cabecera !== 'Bearer ' . NODERED_TOKEN) {
        http_response_code(401);   // 401 = no autorizado
        echo json_encode(['ok' => false, 'error' => 'Token no válido']);
        return;
    }

    // 2. ¿Existe esa puerta?
    $id = (int) $partes[1];
    if ($id < 1 || $id > NUM_PUERTAS) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Puerta no existe']);
        return;
    }

    // 3. "Abrirla": apuntar la hora de apertura
    $aperturas[$id] = microtime(true);
    file_put_contents($archivo_estado, json_encode($aperturas));

    echo json_encode(['ok' => true]);
    return;
}



/* =========================================================
   Cualquier otra dirección → 404
   ========================================================= */

http_response_code(404);
echo json_encode(['error' => 'No existe esa ruta en el simulador']);
