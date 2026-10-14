<?php
/* #########################################################
   admin/actividad.php — PANEL DE ADMINISTRACIÓN: ACTIVIDAD

   Aquí irán los paneles de Grafana (paso 8). Mientras tanto,
   un resumen sencillo de los últimos 7 días sacado de la BD.
   ######################################################### */



/* =========================================================
   0. SEGURIDAD, CONEXIÓN Y FUNCIONES
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';



/* =========================================================
   1. ACCESOS DE LOS ÚLTIMOS 7 DÍAS, AGRUPADOS POR DÍA
   DATE(fecha)        = solo el día, sin la hora.
   GROUP BY DATE(...) = una fila por día.
   SUM(condición)     = cuenta las filas que la cumplen
                        (cada condición vale 1 si se cumple, 0 si no).
   CURDATE() - INTERVAL 6 DAY = hace 6 días (+ hoy = 7 días).
   ========================================================= */

$dias = $pdo->query(
    "SELECT DATE(fecha)              AS dia,
            SUM(resultado = 'ok')    AS aperturas,
            SUM(resultado <> 'ok')   AS fallidos
       FROM accesos
      WHERE fecha >= CURDATE() - INTERVAL 6 DAY
      GROUP BY DATE(fecha)
      ORDER BY dia DESC"
)->fetchAll();



/* =========================================================
   2. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Actividad';
$pagina_activa = 'actividad';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <!-- --- 1. Resumen de los últimos 7 días --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Últimos 7 días</h2>
                </div>

                <?php if (count($dias) === 0): ?>
                    <p class="tabla-vacia">No ha habido accesos en los últimos 7 días.</p>
                <?php else: ?>
                <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Aperturas</th>
                            <th>Intentos fallidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dias as $d): ?>
                        <tr>
                            <td><?= fecha_es($d['dia'], false) ?></td>
                            <td class="texto-ok"><?= $d['aperturas'] ?></td>
                            <!-- Si hubo fallidos, en rojo; si no, en gris -->
                            <td class="<?= $d['fallidos'] > 0 ? 'texto-error' : 'texto-sec' ?>"><?= $d['fallidos'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>

            </section>


            <!-- --- 2. Hueco para Grafana (paso 8) --- -->
            <section class="panel">
                <div class="panel-cabecera">
                    <h2>Grafana</h2>
                </div>
                <div class="grafana-hueco">
                    [Aquí irán los paneles de Grafana: aperturas por hora, accesos por cliente y por trastero]
                </div>
            </section>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
