<?php
/* #########################################################
   admin/red.php — PANEL DE ADMINISTRACIÓN: ESTADO DE RED Y PLC

   En directo: si Node-RED (y por tanto el PLC) responde y el
   estado de cada cerradura. Y del historial: los errores del
   PLC registrados en las últimas 24 horas.
   ######################################################### */



/* =========================================================
   0. SEGURIDAD, CONEXIÓN Y FUNCIONES
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/nodered.php';



/* =========================================================
   1. ERRORES DEL PLC EN LAS ÚLTIMAS 24 HORAS (real, de la BD)
   Cada vez que el PLC no respondió a una apertura, quedó un
   acceso con resultado 'error_plc'.
   ========================================================= */

$errores_24h = $pdo->query(
    "SELECT COUNT(*)
       FROM accesos
      WHERE resultado = 'error_plc'
        AND fecha >= NOW() - INTERVAL 24 HOUR"
)->fetchColumn();

// El último error del PLC (para saber cuándo fue)
$ultimo_error = $pdo->query(
    "SELECT MAX(fecha) FROM accesos WHERE resultado = 'error_plc'"
)->fetchColumn();



/* =========================================================
   2. TRASTEROS EN SERVICIO, con su código PLC
   ========================================================= */

$trasteros = $pdo->query(
    'SELECT nombre, plc_id
       FROM puertas
      WHERE activa = 1
      ORDER BY plc_id'
)->fetchAll();



/* =========================================================
   2B. ESTADO EN DIRECTO (Node-RED)
   ========================================================= */

$estados       = nodered_estados();      // null si no responde
$nodered_ok    = ($estados !== null);
$con_fallo     = 0;                      // cerraduras que el PLC marca con fallo

foreach ($trasteros as &$t) {
    if (!$nodered_ok) {
        $t['estado'] = 'sin_conexion';
        $t['fallo']  = false;
    } else {
        $t['estado'] = $estados[$t['plc_id']]['estado'] ?? 'desconocido';
        $t['fallo']  = $estados[$t['plc_id']]['fallo']  ?? false;
        if ($t['fallo']) {
            $con_fallo++;
        }
    }
}
unset($t);



/* =========================================================
   3. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Estado de red y PLC';
$pagina_activa = 'red';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <!-- --- 1. Tarjetas --- -->
            <div class="resumen">
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Node-RED / PLC</span>
                    <span class="resumen-numero <?= $nodered_ok ? 'texto-ok' : 'texto-error' ?>"><?= $nodered_ok ? 'Online' : 'Sin conexión' ?></span>
                </div>
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Cerraduras con fallo</span>
                    <span class="resumen-numero <?= $con_fallo > 0 ? 'texto-error' : 'texto-ok' ?>"><?= $nodered_ok ? $con_fallo : '—' ?></span>
                </div>
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Errores PLC (24 h)</span>
                    <span class="resumen-numero <?= $errores_24h > 0 ? 'texto-error' : 'texto-ok' ?>"><?= $errores_24h ?></span>
                </div>
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Último error PLC</span>
                    <span class="resumen-numero resumen-numero-pequeno"><?= fecha_es($ultimo_error ?: null, true, 'Ninguno') ?></span>
                </div>
            </div>


            <!-- --- 2. Cerraduras --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Cerraduras</h2>
                    <!-- Dirección de Node-RED que usa la web (de config.php), para comprobar que es la buena -->
                    <span class="texto-sec"><?= htmlspecialchars(NODERED_URL) ?></span>
                </div>

                <ul class="lista-puertas">
                    <?php foreach ($trasteros as $t): ?>
                    <li>
                        <span class="lista-puertas-nombre">
                            <span class="etiqueta"><?= htmlspecialchars($t['plc_id']) ?></span>
                            <?= htmlspecialchars($t['nombre']) ?>
                        </span>
                        <?php $e = texto_estado_puerta($t['estado']); ?>
                        <span class="pill <?= $t['fallo'] ? 'pill-error' : $e['clase'] ?>"><?= $e['texto'] ?><?= $t['fallo'] ? ' · fallo' : '' ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

            </section>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
