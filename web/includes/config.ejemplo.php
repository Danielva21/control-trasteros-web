<?php
/* #########################################################
   includes/config.ejemplo.php — PLANTILLA de config.php

   Este archivo SÍ va a git; config.php NO (tiene contraseñas).

   Para montar una instalación nueva:
     1. Copiar este archivo como config.php (en esta misma carpeta).
     2. Poner los datos reales de ESA instalación.
   ######################################################### */



/* =========================================================
   1. DATOS DE CONEXIÓN
   ========================================================= */

define('BD_HOST',     '127.0.0.1');
define('BD_NOMBRE',   'trasteros');
define('BD_USUARIO',  'trasteros_web');
define('BD_PASSWORD', 'PON_AQUI_LA_CONTRASEÑA');

// API de Node-RED (el puente con el PLC). Ver includes/nodered.php
//   NODERED_URL:   dónde está Node-RED (en la instalación real, el del cuadro eléctrico).
//                  En desarrollo, el simulador: simulador/arrancar_simulador (puerto 1880).
//   NODERED_TOKEN: la clave que pide Node-RED para ABRIR ("Authorization: Bearer ...").
//                  Solo la conoce el servidor: el navegador NUNCA la ve.
define('NODERED_URL',   'http://127.0.0.1:1880');
define('NODERED_TOKEN', 'PON_AQUI_EL_TOKEN_DE_NODE_RED');



/* =========================================================
   2. CONEXIÓN (PDO) — igual que en config.php
   ========================================================= */

$dsn = 'mysql:host=' . BD_HOST . ';dbname=' . BD_NOMBRE . ';charset=utf8mb4';

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, BD_USUARIO, BD_PASSWORD, $opciones);
} catch (PDOException $e) {
    error_log('Error de conexión a la BD: ' . $e->getMessage());
    http_response_code(500);
    exit('Ahora mismo no se puede acceder al sistema. Inténtalo de nuevo en unos minutos.');
}
