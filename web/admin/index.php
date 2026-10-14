<?php
/* #########################################################
   admin/index.php — PANEL DE ADMINISTRACIÓN: DASHBOARD

   Lo común a todas las páginas del panel (comprobación de
   admin, <head>, sidebar y barra superior) está en
   includes/admin_cabecera.php y includes/admin_pie.php.
   Aquí solo van los DATOS y el CONTENIDO de esta página.
   ######################################################### */



/* =========================================================
   0. SEGURIDAD Y CONEXIÓN
   PRIMERO comprobar que es admin (si no, aquí se corta todo)
   y DESPUÉS conectar con la BD. Así nadie sin permiso llega
   a ejecutar ni una consulta.
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/nodered.php';    // para el estado real de las puertas
require_once __DIR__ . '/../includes/funciones.php';



/* =========================================================
   1. DATOS DESDE LA BD (antes: arrays de prueba)
   ========================================================= */


// ---------------------------------------------------------
// 1.1 Números de las 4 tarjetas de resumen
// COUNT(*) = cuántas filas cumplen la condición.
// fetchColumn() = dame directamente el valor de la primera
// columna (aquí, el número), sin tener que hacer $fila['...'].
// Estas consultas no llevan "?" (no dependen de nada que
// escriba el usuario), por eso se usa query() y no prepare().
// ---------------------------------------------------------

$resumen = [

    // Clientes activos (el admin no cuenta como cliente)
    'usuarios_activos' => $pdo->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario' AND activo = 1"
    )->fetchColumn(),

    // Trasteros en servicio
    'puertas' => $pdo->query(
        'SELECT COUNT(*) FROM puertas WHERE activa = 1'
    )->fetchColumn(),

    // Aperturas correctas desde las 00:00 de hoy. CURDATE() = la fecha de hoy a las 00:00.
    'aperturas_hoy' => $pdo->query(
        "SELECT COUNT(*) FROM accesos WHERE resultado = 'ok' AND fecha >= CURDATE()"
    )->fetchColumn(),

    // Estos dos se rellenan en el apartado 1.3, con lo que responda Node-RED
    'puertas_online' => 0,
    'plc_online'     => false,
];


// ---------------------------------------------------------
// 1.2 Usuarios para la tabla, cada uno con SUS trasteros vigentes
//
// LEFT JOIN (y no JOIN): queremos TODOS los usuarios, también
// los que no tienen ningún trastero (como el admin). Con JOIN
// normal, esos desaparecerían de la lista.
//
// Las condiciones de "vigente" van en el ON (no en el WHERE):
// así, si un permiso está caducado, el usuario sigue saliendo,
// solo que sin ese trastero.
//
// GROUP BY u.id = junta todas las filas de un mismo usuario en una.
// GROUP_CONCAT(...) = pega los nombres de sus trasteros en un solo
// texto separado por "|": "Trastero 15|Trastero 28".
// ---------------------------------------------------------

$usuarios = $pdo->query(
    "SELECT u.id, u.nombre, u.email, u.rol, u.activo,
            GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR '|') AS puertas
       FROM usuarios u
       LEFT JOIN permisos pe ON pe.usuario_id = u.id
                            AND (pe.fecha_fin IS NULL OR pe.fecha_fin >= NOW())
       LEFT JOIN puertas  p  ON p.id = pe.puerta_id
                            AND p.activa = 1
      GROUP BY u.id
      ORDER BY u.rol, u.nombre"
)->fetchAll();

// El HTML espera que "puertas" sea un ARRAY, no un texto.
// explode('|', ...) parte el texto por cada "|": "A|B" → ['A', 'B'].
// Si no tiene ninguno, GROUP_CONCAT devuelve NULL → array vacío.
// "&$u" (con &) = modificar el usuario DE VERDAD dentro del array, no una copia.
foreach ($usuarios as &$u) {
    $u['puertas'] = ($u['puertas'] === null) ? [] : explode('|', $u['puertas']);
}
unset($u);   // buena práctica después de un foreach con &: "soltar" la referencia


// ---------------------------------------------------------
// 1.3 Estado de los trasteros (EN DIRECTO desde Node-RED)
// La LISTA sale de la BD; el ESTADO se le pregunta a Node-RED
// buscando cada trastero por su plc_id.
//   - Node-RED no responde → todos "sin_conexion" y PLC offline.
//   - Responde pero no conoce ese plc_id → "desconocido".
// ---------------------------------------------------------

$puertas = $pdo->query(
    'SELECT nombre, plc_id
       FROM puertas
      WHERE activa = 1
      ORDER BY nombre'
)->fetchAll();

$estados = nodered_estados();   // null si Node-RED no responde
$resumen['plc_online'] = ($estados !== null);

foreach ($puertas as &$p) {
    if ($estados === null) {
        $p['estado'] = 'sin_conexion';
    } elseif (isset($estados[$p['plc_id']])) {
        $p['estado'] = $estados[$p['plc_id']]['estado'];
        $resumen['puertas_online']++;     // Node-RED la conoce y responde
    } else {
        $p['estado'] = 'desconocido';
    }
}
unset($p);



/* =========================================================
    2. CABECERA COMÚN DEL PANEL
    Antes de incluirla, le decimos el título de esta página y
    qué enlace del sidebar tiene que salir resaltado.
    __DIR__ = la carpeta de ESTE archivo (admin/). Así la ruta
    funciona siempre, se ejecute desde donde se ejecute.
   ========================================================= */

$titulo        = 'Dashboard';
$pagina_activa = 'dashboard';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
    CONTENIDO DE LA PÁGINA (va dentro del <main> de la cabecera)
    ######################################################### -->

            <!-- --- 2.2.1 Cuatro tarjetas de resumen --- -->
            <div class="resumen">

                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Clientes activos</span>
                    <span class="resumen-numero"><?= $resumen['usuarios_activos'] ?></span>
                </div>

                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Trasteros</span>
                    <span class="resumen-numero">
                        <?= $resumen['puertas'] ?>
                        <small>· <?= $resumen['puertas_online'] ?> online</small>
                    </span>
                </div>

                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Aperturas hoy</span>
                    <span class="resumen-numero"><?= $resumen['aperturas_hoy'] ?></span>
                </div>

                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">PLC / Node-RED</span>
                    <!-- Operador ternario: (condición) ? 'si sí' : 'si no' -->
                    <span class="resumen-numero <?= $resumen['plc_online'] ? 'texto-ok' : 'texto-error' ?>">
                        <?= $resumen['plc_online'] ? 'Online' : 'Sin conexión' ?>
                    </span>
                </div>

            </div>


            <!-- --- 2.2.2 Fila de dos columnas: tabla de usuarios (ancha) + estado de puertas (estrecha) --- -->
            <div class="admin-fila">


                <!-- Tabla de usuarios -->
                <section class="panel panel-ancho">

                    <div class="panel-cabecera">
                        <h2>Usuarios y accesos</h2>
                        <!-- Enlace a Usuarios; el "#nuevo" hace que admin.js abra directamente la ventana de nuevo usuario -->
                        <a href="usuarios.php#nuevo" class="btn btn-primario btn-pequeno">+ Añadir usuario</a>
                    </div>

                    <!-- <table> = tabla. <thead> = fila de títulos, <tbody> = filas de datos.
                        <tr> = fila, <th> = celda de título, <td> = celda normal. -->
                    <!-- Envoltorio de la tabla: en pantallas estrechas, si la tabla no cabe,
                        se desliza de lado DENTRO de esta caja en vez de romper la página -->
                    <div class="tabla-scroll">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Trasteros</th>
                                <th>Estado</th>
                                <th class="tabla-acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <!-- Nombre + email -->
                                <td>
                                    <div class="tabla-usuario">
                                        <span class="tabla-usuario-nombre"><?= htmlspecialchars($u['nombre']) ?></span>
                                        <span class="tabla-usuario-email"><?= htmlspecialchars($u['email']) ?></span>
                                    </div>
                                </td>

                                <!-- Rol -->
                                <td>
                                    <?php if ($u['rol'] === 'admin'): ?>
                                        <span class="pill pill-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="pill pill-neutra">Usuario</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Puertas: el admin las tiene todas; el resto, una etiqueta por puerta -->
                                <td>
                                    <?php if ($u['rol'] === 'admin'): ?>
                                        <span class="texto-sec">Todas</span>
                                    <?php else: ?>
                                        <div class="etiquetas">
                                            <?php foreach ($u['puertas'] as $nombre_puerta): ?>
                                                <span class="etiqueta"><?= htmlspecialchars($nombre_puerta) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Estado: activo o suspendido (activo = 0) -->
                                <td>
                                    <?php if ((int) $u['activo'] === 1): ?>
                                        <span class="texto-ok">● Activo</span>
                                    <?php else: ?>
                                        <span class="texto-aviso">● Suspendido</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Acciones: se hacen en la página Usuarios (aquí solo un acceso directo) -->
                                <td class="tabla-acciones">
                                    <a href="usuarios.php" class="btn-tabla">Gestionar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                </section>


                <!-- Estado de puertas -->
                <section class="panel">

                    <div class="panel-cabecera">
                        <h2>Estado de trasteros</h2>
                    </div>

                    <ul class="lista-puertas">
                        <?php foreach ($puertas as $p): ?>
                        <li>
                            <span class="lista-puertas-nombre"><?= htmlspecialchars($p['nombre']) ?></span>

                            <!-- texto_estado_puerta() (funciones.php) da el texto y el color -->
                            <?php $e = texto_estado_puerta($p['estado']); ?>
                            <span class="pill <?= $e['clase'] ?>"><?= $e['texto'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                </section>

            </div>


            <!-- --- 2.2.3 Grafana (de momento, un hueco reservado) --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Actividad y red</h2>
                    <a href="actividad.php">Ver actividad →</a>
                </div>

                <div class="grafana-hueco">
                    [Panel de Grafana: aperturas por hora, accesos por usuario, latencia del PLC y estado de la red]
                </div>

            </section>



<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
