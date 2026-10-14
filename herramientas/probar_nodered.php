<?php
/* #########################################################
   herramientas/probar_nodered.php — COMPROBAR LA CONEXIÓN CON NODE-RED

   Se ejecuta en la TERMINAL (no en el navegador), desde la
   carpeta TRASTEROS_WEB:

       php herramientas/probar_nodered.php
           → solo LEE: comprueba la dirección, el formato de la respuesta
             y que los trasteros de la BD existen en Node-RED.
             No abre ninguna puerta.

       php herramientas/probar_nodered.php --abrir=3
           → además ABRE DE VERDAD la puerta 3 de Node-RED
             (sirve para comprobar el TOKEN). ¡Cuidado: abre la puerta real!

   (En Windows con XAMPP, en vez de "php" escribe: C:/xampp/php/php.exe)

   Usa los MISMOS datos y el MISMO código que la web
   (includes/config.php e includes/nodered.php): si aquí funciona,
   en la web también.
   ######################################################### */


// Este archivo solo se puede usar desde la terminal
if (PHP_SAPI !== 'cli') {
    exit('Esta herramienta se ejecuta desde la terminal.');
}

require __DIR__ . '/../web/includes/config.php';
require __DIR__ . '/../web/includes/nodered.php';

// Pequeñas ayudas para escribir con colores en la terminal
function ok($texto)     { echo "  \033[32m✔\033[0m $texto\n"; }
function mal($texto)    { echo "  \033[31m✘\033[0m $texto\n"; }
function aviso($texto)  { echo "  \033[33m!\033[0m $texto\n"; }
function titulo($texto) { echo "\n\033[1m$texto\033[0m\n"; }

$problemas = 0;



/* =========================================================
   1. CONFIGURACIÓN (includes/config.php)
   ========================================================= */

titulo('1. Configuración (web/includes/config.php)');

echo "  NODERED_URL   = " . NODERED_URL . "\n";

if (NODERED_TOKEN === '' || str_starts_with(NODERED_TOKEN, 'PON_AQUI')) {
    mal('NODERED_TOKEN está vacío o sin rellenar. Pon el token que usa tu Node-RED.');
    $problemas++;
} else {
    // No enseñamos el token entero (podría verse en pantalla): solo el principio
    ok('NODERED_TOKEN puesto (' . substr(NODERED_TOKEN, 0, 4) . '…, ' . strlen(NODERED_TOKEN) . ' caracteres)');
}



/* =========================================================
   2. LEER EL ESTADO: GET /api/puertas
   Aquí hacemos la petición "a mano" (no con nodered_estados)
   para poder enseñar el código y la respuesta exactos.
   ========================================================= */

titulo('2. Leer el estado: GET ' . NODERED_URL . '/api/puertas');

$curl = curl_init(NODERED_URL . '/api/puertas');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
$cuerpo = curl_exec($curl);
$codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error  = curl_error($curl);
curl_close($curl);

$lista = null;

if ($cuerpo === false || $codigo === 0) {
    mal("No se puede conectar con Node-RED ($error).");
    aviso('¿Está Node-RED arrancado? ¿Es correcta la dirección y el puerto de NODERED_URL?');
    aviso('Desde ESTE equipo, ¿se llega a esa IP? (prueba a abrir la dirección en un navegador aquí)');
    $problemas++;
} elseif ($codigo === 404) {
    mal('Node-RED responde, pero no existe /api/puertas (404).');
    aviso('El flujo de Node-RED no tiene el nodo "http in" GET /api/puertas, o la dirección base es otra.');
    $problemas++;
} elseif ($codigo !== 200) {
    mal("Node-RED responde con código $codigo.");
    aviso('Respuesta: ' . substr($cuerpo, 0, 200));
    $problemas++;
} else {
    $lista = json_decode($cuerpo, true);

    if (!is_array($lista)) {
        mal('La respuesta no es JSON válido. Empieza por: ' . substr($cuerpo, 0, 120));
        $problemas++;
        $lista = null;
    } else {
        ok('Node-RED responde (' . count($lista) . ' puertas)');

        // ¿Cada puerta trae "id" y "state" (lo que usa la web)?
        foreach ($lista as $p) {
            $id     = $p['id']    ?? '(sin id)';
            $estado = $p['state'] ?? '(sin state)';
            echo "      puerta $id → $estado" . (!empty($p['fault']) ? '  [FALLO]' : '') . "\n";
            if (!isset($p['id']) || !isset($p['state'])) {
                $problemas++;
            }
        }
        if (array_filter($lista, fn($p) => !isset($p['id']) || !isset($p['state']))) {
            mal('Alguna puerta no trae "id" o "state". La web los necesita (ver README, sección Node-RED).');
        }
    }
}



/* =========================================================
   3. ¿COINCIDEN LOS TRASTEROS DE LA BD CON LAS PUERTAS DE NODE-RED?
   Cada trastero en servicio tiene un plc_id ("Nº en Node-RED"
   en Admin → Trasteros) que tiene que existir en Node-RED.
   ========================================================= */

titulo('3. Trasteros de la BD ↔ puertas de Node-RED');

$trasteros = $pdo->query('SELECT nombre, plc_id FROM puertas WHERE activa = 1 ORDER BY nombre')->fetchAll();

if (count($trasteros) === 0) {
    aviso('No hay trasteros en servicio en la BD. Dalos de alta en Admin → Trasteros.');
} elseif ($lista === null) {
    aviso('No se puede comprobar: Node-RED no ha respondido (ver punto 2).');
} else {
    $ids_nodered = array_map(fn($p) => (string) ($p['id'] ?? ''), $lista);

    foreach ($trasteros as $t) {
        if (in_array((string) $t['plc_id'], $ids_nodered, true)) {
            ok($t['nombre'] . ' → puerta ' . $t['plc_id']);
        } else {
            mal($t['nombre'] . ' tiene el Nº ' . $t['plc_id'] . ', pero Node-RED no tiene ninguna puerta con ese id.');
            $problemas++;
        }
    }
}



/* =========================================================
   4. (OPCIONAL) ABRIR DE VERDAD: comprueba el TOKEN
   Solo si se ha pedido con --abrir=ID
   ========================================================= */

$opciones = getopt('', ['abrir:']);   // lee "--abrir=3" de la línea de comandos

titulo('4. Abrir (comprueba el token)');

if (!isset($opciones['abrir'])) {
    echo "  (saltado: para probarlo, añade --abrir=ID. ¡Abre la puerta de verdad!)\n";
} else {
    $id = $opciones['abrir'];

    $curl = curl_init(NODERED_URL . '/api/puerta/' . rawurlencode($id) . '/abrir');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => '',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . NODERED_TOKEN],
    ]);
    $cuerpo = curl_exec($curl);
    $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($codigo === 200 && !empty(json_decode($cuerpo, true)['ok'])) {
        ok("Node-RED ha aceptado la orden de abrir la puerta $id.");
    } elseif ($codigo === 401 || $codigo === 403) {
        mal("Node-RED rechaza el token (código $codigo). NODERED_TOKEN no coincide con el de Node-RED.");
        $problemas++;
    } else {
        mal("Respuesta inesperada (código $codigo): " . substr((string) $cuerpo, 0, 200));
        $problemas++;
    }
}



/* =========================================================
   RESUMEN
   ========================================================= */

titulo('Resultado');

if ($problemas === 0) {
    ok("Todo correcto. La web puede hablar con Node-RED.\n");
} else {
    mal("$problemas problema(s). Revisa los puntos marcados con ✘ (y la sección \"Si algo no funciona\" del README).\n");
}
