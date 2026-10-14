<?php
/* #########################################################
   includes/solo_usuario.php — PROTECCIÓN DE LAS PÁGINAS DE usuario/

   Va lo PRIMERO de cada página de usuario/:
       require_once __DIR__ . '/../includes/solo_usuario.php';

   Hace tres cosas:
     1. Comprueba que haya hecho login (si no → al login).
     2. Conecta con la BD (crea $pdo).
     3. Lee el usuario de la BD y comprueba que sigue ACTIVO
        (si el admin lo ha suspendido → se le echa).
   Al acabar deja preparadas: $pdo, $usuario, $inicial y
   $no_leidos (comunicados sin leer, para la campana).
   ######################################################### */



// 1. ¿Ha hecho login?
require_once __DIR__ . '/sesion.php';   // arranca la sesión con cookie segura

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}


// 2. Conexión a la BD
require __DIR__ . '/config.php';


// 3. Datos del usuario desde la BD (no desde la sesión: la sesión es una
//    "foto" del momento del login y no se entera si lo suspenden después).
$consulta = $pdo->prepare(
    'SELECT id, nombre, email, rol, activo, creado_en, ultimo_acceso, ultimo_comunicado_visto
       FROM usuarios
      WHERE id = ?'
);
$consulta->execute([$_SESSION['usuario_id']]);
$usuario = $consulta->fetch();

// No existe o está suspendido → logout.php destruye la sesión y manda al login
if ($usuario === false || (int) $usuario['activo'] !== 1) {
    header('Location: ../logout.php');
    exit;
}

// Inicial para el avatar (mb_ = entiende tildes y ñ)
$inicial = mb_strtoupper(mb_substr($usuario['nombre'], 0, 1));


// 4. Comunicados SIN LEER (el número de la campana):
//    los publicados con un id MAYOR que el último que ha visto.
//    COALESCE(?, 0) = si nunca ha mirado (NULL), cuenta desde 0 → todos son nuevos.
$consulta = $pdo->prepare(
    'SELECT COUNT(*)
       FROM comunicados
      WHERE activo = 1
        AND id > COALESCE(?, 0)'
);
$consulta->execute([$usuario['ultimo_comunicado_visto']]);
$no_leidos = (int) $consulta->fetchColumn();
