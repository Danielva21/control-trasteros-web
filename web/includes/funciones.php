<?php
/* #########################################################
   includes/funciones.php — FUNCIONES PEQUEÑAS QUE SE REPITEN

   Una función = un trozo de código con nombre que se puede
   usar muchas veces. Se "llama" escribiendo su nombre:
       echo fecha_es('2026-10-14 10:46:12');   // 14/10/2026 10:46

   CÓMO SE USA:
       require_once __DIR__ . '/../includes/funciones.php';
   ######################################################### */



/* =========================================================
   fecha_es($fecha, $con_hora)
   Convierte una fecha de la BD ("2026-10-14 10:46:12") al
   formato español ("14/10/2026 10:46").
     - $fecha:    la fecha tal como viene de la BD (o null).
     - $con_hora: true = con hora (por defecto); false = solo el día.
     - $si_vacia: lo que se devuelve si la fecha es NULL.
   ========================================================= */

function fecha_es($fecha, $con_hora = true, $si_vacia = '—')
{
    if ($fecha === null) {
        return $si_vacia;
    }

    $formato = $con_hora ? 'd/m/Y H:i' : 'd/m/Y';

    // strtotime() convierte el texto de la BD en un número que PHP entiende;
    // date() lo escribe con el formato que le pidamos.
    return date($formato, strtotime($fecha));
}



/* =========================================================
   texto_resultado($resultado)
   Traduce el resultado de un acceso (como se guarda en la BD)
   al texto y la píldora de color que se enseñan en pantalla.
   Devuelve un array: ['texto' => ..., 'clase' => ...]
   ========================================================= */

function texto_resultado($resultado)
{
    // match = como un switch, pero más corto: según el valor, devuelve una cosa u otra
    return match ($resultado) {
        'ok'        => ['texto' => 'Abierta',     'clase' => 'pill-ok'],
        'denegado'  => ['texto' => 'Denegado',    'clase' => 'pill-error'],
        'caducado'  => ['texto' => 'Caducado',    'clase' => 'pill-aviso'],
        'error_plc' => ['texto' => 'Error PLC',   'clase' => 'pill-error'],
        default     => ['texto' => $resultado,    'clase' => 'pill-neutra'],
    };
}



/* #########################################################
   FUNCIONES PARA LOS FORMULARIOS (bloque A)
   ######################################################### */



/* =========================================================
   MENSAJES "FLASH": aviso que se ve UNA vez tras una acción
   ("Usuario creado", "El email ya existe"…).

   Patrón POST → guardar → REDIRIGIR → GET:
     1. El formulario se envía (POST) y PHP guarda en la BD.
     2. PHP guarda el mensaje en la SESIÓN y redirige a la página.
     3. La página se carga normal (GET), saca el mensaje y lo borra.
   Así, si el usuario pulsa F5, no se vuelve a enviar el formulario
   (no se crearía el usuario dos veces).
   ========================================================= */

// Guardar un mensaje. $tipo: 'ok' (verde) o 'error' (rojo)
function mensaje_guardar($tipo, $texto)
{
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $texto];
}

// Sacar el mensaje (y borrarlo, para que no salga otra vez). null si no hay.
function mensaje_sacar()
{
    $mensaje = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    return $mensaje;
}

// Redirigir a una página y parar (paso 2 del patrón)
function volver_a($pagina)
{
    header('Location: ' . $pagina);
    exit;
}



/* =========================================================
   fecha_formulario($texto, $fin_del_dia)
   Comprueba una fecha que llega de un <input type="date">
   (formato "2026-10-14") y la prepara para la BD.
     - Devuelve "2026-10-14 00:00:00" (o "23:59:59" si $fin_del_dia,
       para que un permiso que acaba el día 14 sirva TODO el día 14).
     - Devuelve false si la fecha no es válida.
   ========================================================= */

function fecha_formulario($texto, $fin_del_dia = false)
{
    // createFromFormat: intenta leer el texto con ese formato exacto
    $fecha = DateTime::createFromFormat('Y-m-d', $texto);

    // Si no se pudo leer, o PHP la "corrigió" (ej: 31 de febrero → 3 de marzo), no vale
    if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
        return false;
    }

    return $texto . ($fin_del_dia ? ' 23:59:59' : ' 00:00:00');
}



/* =========================================================
   cambiar_password($pdo, $usuario_id, $actual, $nueva, $repetida)
   La usan Ajustes (admin) y Mi perfil (cliente).
   Devuelve '' si todo fue bien, o el texto del error.
   ========================================================= */

const PASSWORD_MINIMO = 8;   // longitud mínima de las contraseñas NUEVAS

function cambiar_password($pdo, $usuario_id, $actual, $nueva, $repetida)
{
    // 1. La contraseña actual tiene que ser la buena (por si alguien coge el móvil desbloqueado)
    $consulta = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $consulta->execute([$usuario_id]);
    $hash = $consulta->fetchColumn();

    if ($hash === false || !password_verify($actual, $hash)) {
        return 'La contraseña actual no es correcta.';
    }

    // 2. La nueva: mínimo de caracteres y escrita igual dos veces
    // mb_strlen = número de caracteres (cuenta bien tildes y ñ)
    if (mb_strlen($nueva) < PASSWORD_MINIMO) {
        return 'La nueva contraseña debe tener al menos ' . PASSWORD_MINIMO . ' caracteres.';
    }
    if ($nueva !== $repetida) {
        return 'Las dos contraseñas nuevas no coinciden.';
    }

    // 3. Guardar el HASH de la nueva (nunca la contraseña tal cual)
    $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuario_id]);

    return '';
}



/* =========================================================
   es_duplicado($e)
   Cuando se intenta guardar un email (o un código PLC) que ya
   existe, la BD lo rechaza por el UNIQUE y PDO lanza un error
   con código 23000. Esta función dice si el error es ese.
   ========================================================= */

function es_duplicado(PDOException $e)
{
    return $e->getCode() === '23000';
}



/* =========================================================
   texto_estado_puerta($estado)
   Traduce el estado de una puerta (como lo da nodered_estados())
   al texto y la píldora de color para el panel admin.
   ========================================================= */

function texto_estado_puerta($estado)
{
    return match ($estado) {
        'cerrada'      => ['texto' => 'Cerrada',      'clase' => 'pill-ok'],
        'abierta'      => ['texto' => 'Abierta',      'clase' => 'pill-aviso'],
        'abriendo'     => ['texto' => 'Abriendo…',    'clase' => 'pill-aviso'],
        'sin_conexion' => ['texto' => 'Sin conexión', 'clase' => 'pill-error'],
        default        => ['texto' => 'Sin datos',    'clase' => 'pill-neutra'],
    };
}
