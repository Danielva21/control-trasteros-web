<?php
/* #########################################################
   includes/nodered.php — HABLAR CON NODE-RED (el puente con el PLC)

   Es el ÚNICO archivo de la web que habla con Node-RED.
   El navegador nunca llama a Node-RED: llama a nuestros
   archivos de api/, y estos usan las funciones de aquí.

   La API de Node-RED (contrato de la web anterior, INTEGRACION.md):

     GET  {NODERED_URL}/api/puertas
          → lista: [{"id":1, "state":"Cerrada", "fault":false,
                     "lastUpdate":"2026-09-21T10:00:00.000Z", ...}, ...]
            state: "Cerrada" | "Abierta" | "Abriendo" | "Desconocida"

     POST {NODERED_URL}/api/puerta/{id}/abrir
          cabecera: Authorization: Bearer {NODERED_TOKEN}
          → {"ok": true}   (Node-RED pone Cmd_Abrir; el pulso lo temporiza el PLC)

   El {id} de Node-RED es lo que guardamos en la columna
   puertas.plc_id de nuestra BD (1, 2, 3…).

   Usa cURL: la herramienta de PHP para hacer peticiones HTTP
   a otro servidor (como hace el navegador con una web).

   NECESITA: config.php cargado antes (NODERED_URL y NODERED_TOKEN).
   ######################################################### */



/* =========================================================
   nodered_peticion($metodo, $ruta)
   Hace UNA petición a Node-RED y devuelve la respuesta ya
   convertida de JSON a array PHP, o null si algo falla
   (Node-RED apagado, no responde a tiempo, error, JSON roto…).
   Las otras dos funciones usan esta.
   ========================================================= */

function nodered_peticion($metodo, $ruta)
{
    // 1. Preparar la petición
    $curl = curl_init(NODERED_URL . $ruta);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,     // devolver la respuesta como texto (no pintarla)
        CURLOPT_CONNECTTIMEOUT => 2,        // si en 2 s no conecta, abandonar
        CURLOPT_TIMEOUT        => 4,        // si en 4 s no ha respondido entero, abandonar
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . NODERED_TOKEN,   // la clave, solo entre servidor y Node-RED
        ],
    ]);

    if ($metodo === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '');   // POST sin cuerpo (todo va en la dirección)
    }

    // 2. Enviarla y ver qué ha pasado
    $respuesta = curl_exec($curl);
    $codigo    = curl_getinfo($curl, CURLINFO_HTTP_CODE);   // 200 = bien, 401 = token malo, 500 = error…
    $error     = curl_error($curl);
    curl_close($curl);

    // 3. Si no hubo respuesta, o el código no es 2xx (200-299), es un fallo
    if ($respuesta === false || $codigo < 200 || $codigo >= 300) {
        // El detalle va al log del servidor (no al usuario)
        error_log("Node-RED $metodo $ruta falló: código $codigo $error");
        return null;
    }

    // 4. Convertir el JSON en array. json_decode(…, true) = array asociativo.
    $datos = json_decode($respuesta, true);

    return is_array($datos) ? $datos : null;
}



/* =========================================================
   nodered_estados()
   Pide a Node-RED el estado de todas las puertas.
   Devuelve un array ordenado por id de Node-RED, para poder
   buscar rápido el de cada trastero:
       $estados['3'] = ['estado' => 'cerrada', 'fallo' => false]
   o null si Node-RED no responde.

   Traducimos los estados de Node-RED a los nuestros (minúsculas,
   como en el resto de la web):
       Cerrada → cerrada · Abierta → abierta · Abriendo → abriendo
       Desconocida (o cualquier otra cosa) → desconocido
   ========================================================= */

function nodered_estados()
{
    $lista = nodered_peticion('GET', '/api/puertas');

    if ($lista === null) {
        return null;
    }

    $estados = [];

    foreach ($lista as $puerta) {
        // Si una entrada no trae id, la saltamos ("continue" = pasar a la siguiente vuelta)
        if (!isset($puerta['id'])) {
            continue;
        }

        $estado = match ($puerta['state'] ?? '') {
            'Cerrada'  => 'cerrada',
            'Abierta'  => 'abierta',
            'Abriendo' => 'abriendo',
            default    => 'desconocido',
        };

        // (string) para que la clave sea texto: plc_id en la BD también es texto
        $estados[(string) $puerta['id']] = [
            'estado' => $estado,
            'fallo'  => !empty($puerta['fault']),
        ];
    }

    return $estados;
}



/* =========================================================
   nodered_abrir($plc_id)
   Pide a Node-RED que abra la puerta con ese id.
   Devuelve true si Node-RED aceptó la orden, false si no.
   OJO: esta función NO comprueba permisos. Eso lo hace
   api/abrir.php ANTES de llamarla.
   ========================================================= */

function nodered_abrir($plc_id)
{
    // rawurlencode: por si el id llevara algún carácter raro, que no rompa la dirección
    $respuesta = nodered_peticion('POST', '/api/puerta/' . rawurlencode($plc_id) . '/abrir');

    return $respuesta !== null && !empty($respuesta['ok']);
}
