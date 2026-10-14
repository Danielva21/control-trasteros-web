<?php
/* #########################################################
   admin/historial.php — PANEL DE ADMINISTRACIÓN: HISTORIAL

   Todos los intentos de apertura (tabla "accesos"), los más
   recientes primero. Con un filtro por resultado que ya
   funciona (se hace en el servidor, con la URL ?resultado=…).
   ######################################################### */



/* =========================================================
   0. SEGURIDAD, CONEXIÓN Y FUNCIONES
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';



/* =========================================================
   1. FILTRO POR RESULTADO
   El formulario de abajo usa method="get": lo elegido viaja
   en la URL, por ejemplo historial.php?resultado=denegado
   y PHP lo lee en $_GET['resultado'].

   SEGURIDAD: lo que llega en la URL lo puede escribir
   cualquiera. Por eso solo aceptamos valores de una LISTA
   BLANCA; cualquier otra cosa se ignora (= sin filtro).
   ========================================================= */

$resultados_validos = ['ok', 'denegado', 'caducado', 'error_plc'];

$filtro = $_GET['resultado'] ?? '';

// in_array(valor, lista, true) = ¿está ese valor en la lista? (true = comparación estricta)
if (!in_array($filtro, $resultados_validos, true)) {
    $filtro = '';
}



/* =========================================================
   2. ACCESOS (como mucho los 200 últimos)
   Si hay filtro, la consulta lleva un "?" más, que se rellena
   en execute(). Montamos la consulta por partes:
     - $sql: el texto de la consulta
     - $parametros: los valores para los "?"
   ========================================================= */

$sql = 'SELECT a.fecha, a.resultado, a.ip,
               u.nombre AS cliente,
               p.nombre AS trastero
          FROM accesos a
          JOIN usuarios u ON u.id = a.usuario_id
          JOIN puertas  p ON p.id = a.puerta_id';

$parametros = [];

if ($filtro !== '') {
    $sql .= ' WHERE a.resultado = ?';      // .= significa "añadir al final del texto"
    $parametros[] = $filtro;
}

$sql .= ' ORDER BY a.fecha DESC LIMIT 200';

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$accesos = $consulta->fetchAll();



/* =========================================================
   3. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Historial de accesos';
$pagina_activa = 'historial';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <!-- --- 1. Filtro. method="get" + action vacío = recarga ESTA página con ?resultado=… en la URL --- -->
            <form class="herramientas" method="get" action="">

                <label for="resultado" class="solo-lectores">Filtrar por resultado</label>
                <select id="resultado" name="resultado" class="selector">
                    <option value="">Todos los resultados</option>
                    <!-- "selected" deja marcada la opción que ya estaba elegida -->
                    <option value="ok"        <?= $filtro === 'ok'        ? 'selected' : '' ?>>Abiertas</option>
                    <option value="denegado"  <?= $filtro === 'denegado'  ? 'selected' : '' ?>>Denegados</option>
                    <option value="caducado"  <?= $filtro === 'caducado'  ? 'selected' : '' ?>>Caducados</option>
                    <option value="error_plc" <?= $filtro === 'error_plc' ? 'selected' : '' ?>>Error del PLC</option>
                </select>

                <button type="submit" class="btn btn-primario btn-pequeno">Filtrar</button>

                <?php if ($filtro !== ''): ?>
                    <a href="historial.php">Quitar filtro</a>
                <?php endif; ?>

            </form>


            <!-- --- 2. Tabla de accesos --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Intentos de apertura</h2>
                    <span class="texto-sec"><?= count($accesos) ?> registros</span>
                </div>

                <?php if (count($accesos) === 0): ?>
                    <p class="tabla-vacia">No hay accesos con ese filtro.</p>
                <?php else: ?>
                <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Trastero</th>
                            <th>Resultado</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accesos as $a): ?>
                            <?php $r = texto_resultado($a['resultado']); ?>
                        <tr>
                            <td class="texto-sec"><?= fecha_es($a['fecha']) ?></td>
                            <td><span class="tabla-usuario-nombre"><?= htmlspecialchars($a['cliente']) ?></span></td>
                            <td><span class="etiqueta"><?= htmlspecialchars($a['trastero']) ?></span></td>
                            <td><span class="pill <?= $r['clase'] ?>"><?= $r['texto'] ?></span></td>
                            <td class="texto-sec"><?= htmlspecialchars($a['ip'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>

            </section>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
