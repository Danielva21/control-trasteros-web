<?php
/* #########################################################
   admin/trasteros.php — PANEL DE ADMINISTRACIÓN: TRASTEROS

   Todos los trasteros: quién lo tiene alquilado y hasta
   cuándo, su código del PLC, la última apertura y si está en
   servicio. Acciones: añadir, editar, dar de baja / reactivar.
   (Cómo funcionan las acciones: ver el comentario de usuarios.php)
   ######################################################### */



/* =========================================================
   0. SEGURIDAD, CONEXIÓN, FUNCIONES Y CSRF
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/csrf.php';



/* #########################################################
   1. ACCIONES
   ######################################################### */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_comprobar();

    $accion = $_POST['accion'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);


    /* ---------------------------------------------------------
       1.1 NUEVO / EDITAR (los dos usan los mismos campos)
       --------------------------------------------------------- */
    if ($accion === 'nuevo' || $accion === 'editar') {

        $nombre    = trim($_POST['nombre'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        // El código del PLC (id de la puerta en Node-RED). En MAYÚSCULAS por si lleva letras
        $plc_id    = strtoupper(trim($_POST['plc_id'] ?? ''));

        // preg_match = ¿el texto cumple este patrón?
        //   ^ y $          = de principio a fin
        //   [A-Z0-9_-]{1,50} = entre 1 y 50 letras mayúsculas, números, _ o -
        // Así el código no puede llevar espacios ni caracteres raros que Node-RED no entienda.
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            mensaje_guardar('error', 'El nombre es obligatorio (máximo 100 caracteres).');
        } elseif (mb_strlen($ubicacion) > 150) {
            mensaje_guardar('error', 'La ubicación es demasiado larga (máximo 150 caracteres).');
        } elseif (!preg_match('/^[A-Z0-9_-]{1,50}$/', $plc_id)) {
            mensaje_guardar('error', 'El código PLC solo puede llevar letras, números, "-" y "_" (normalmente el número de la puerta en Node-RED, por ejemplo 8).');
        } else {

            // Ubicación vacía → NULL en la BD (es opcional)
            $ubicacion = ($ubicacion === '') ? null : $ubicacion;

            try {
                if ($accion === 'nuevo') {
                    $pdo->prepare('INSERT INTO puertas (nombre, ubicacion, plc_id) VALUES (?, ?, ?)')
                        ->execute([$nombre, $ubicacion, $plc_id]);
                    mensaje_guardar('ok', 'Trastero "' . $nombre . '" añadido con el código ' . $plc_id . '.');
                } else {
                    $pdo->prepare('UPDATE puertas SET nombre = ?, ubicacion = ?, plc_id = ? WHERE id = ?')
                        ->execute([$nombre, $ubicacion, $plc_id, $id]);
                    mensaje_guardar('ok', 'Cambios guardados.');
                }
            } catch (PDOException $e) {
                // plc_id es UNIQUE: dos trasteros no pueden tener el mismo código
                if (es_duplicado($e)) {
                    mensaje_guardar('error', 'Ya hay otro trastero con el código ' . $plc_id . '.');
                } else {
                    throw $e;
                }
            }
        }
    }


    /* ---------------------------------------------------------
       1.2 DAR DE BAJA / REACTIVAR
       De baja = activa 0: ya no sale a los clientes ni se puede
       abrir, pero NO se borra (se conserva su historial).
       --------------------------------------------------------- */
    elseif ($accion === 'baja' || $accion === 'reactivar') {

        $activa = ($accion === 'reactivar') ? 1 : 0;

        $consulta = $pdo->prepare('UPDATE puertas SET activa = ? WHERE id = ?');
        $consulta->execute([$activa, $id]);

        if ($consulta->rowCount() === 1) {
            mensaje_guardar('ok', $activa ? 'Trastero reactivado.' : 'Trastero dado de baja. Sus clientes ya no lo verán ni podrán abrirlo.');
        } else {
            mensaje_guardar('error', 'No se ha podido cambiar ese trastero.');
        }
    }


    volver_a('trasteros.php');
}



/* #########################################################
   2. DATOS PARA PINTAR LA PÁGINA
   ######################################################### */

/* ---------------------------------------------------------
   2.1 TRASTEROS (todos; los de servicio primero)
   La última apertura sale con una SUBCONSULTA: una consulta
   dentro de otra. Para cada trastero "p", busca en accesos la
   fecha más alta (MAX) de las aperturas correctas de ESE trastero.
   --------------------------------------------------------- */

$trasteros = $pdo->query(
    "SELECT p.id, p.nombre, p.ubicacion, p.plc_id, p.activa,
            (SELECT MAX(a.fecha)
               FROM accesos a
              WHERE a.puerta_id = p.id
                AND a.resultado = 'ok') AS ultima_apertura
       FROM puertas p
      ORDER BY p.activa DESC, p.nombre"
)->fetchAll();


/* ---------------------------------------------------------
   2.2 QUIÉN TIENE CADA TRASTERO (permisos vigentes)
   Otra consulta, y luego los "repartimos" en PHP por trastero:
   $clientes[3] = lista de clientes del trastero con id 3.
   --------------------------------------------------------- */

$filas = $pdo->query(
    'SELECT pe.puerta_id, u.nombre, u.activo, pe.fecha_fin
       FROM permisos pe
       JOIN usuarios u ON u.id = pe.usuario_id
      WHERE pe.fecha_fin IS NULL OR pe.fecha_fin >= NOW()
      ORDER BY u.nombre'
)->fetchAll();

$clientes = [];
foreach ($filas as $f) {
    $clientes[$f['puerta_id']][] = $f;   // "[]" vacío = AÑADIR al final de esa lista
}


/* ---------------------------------------------------------
   2.3 NÚMEROS PARA LAS TARJETAS (contados en PHP)
   --------------------------------------------------------- */

$resumen = ['en_servicio' => 0, 'ocupados' => 0, 'libres' => 0, 'de_baja' => 0];

foreach ($trasteros as $t) {
    if ((int) $t['activa'] !== 1) {
        $resumen['de_baja']++;
    } else {
        $resumen['en_servicio']++;
        if (isset($clientes[$t['id']])) {
            $resumen['ocupados']++;
        } else {
            $resumen['libres']++;
        }
    }
}



/* =========================================================
   3. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Trasteros';
$pagina_activa = 'trasteros';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <!-- --- 1. Tarjetas de resumen --- -->
            <div class="resumen">
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">En servicio</span>
                    <span class="resumen-numero"><?= $resumen['en_servicio'] ?></span>
                </div>
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Alquilados</span>
                    <span class="resumen-numero"><?= $resumen['ocupados'] ?></span>
                </div>
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">Libres</span>
                    <span class="resumen-numero texto-ok"><?= $resumen['libres'] ?></span>
                </div>
                <div class="resumen-tarjeta">
                    <span class="resumen-etiqueta">De baja</span>
                    <span class="resumen-numero texto-sec"><?= $resumen['de_baja'] ?></span>
                </div>
            </div>


            <!-- --- 2. Barra de herramientas --- -->
            <div class="herramientas">
                <div class="buscador">
                    <label for="buscar" class="solo-lectores">Buscar trastero</label>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M21 21l-4.3-4.3"></path>
                    </svg>
                    <input id="buscar" type="search" placeholder="Buscar por trastero, código o cliente">
                </div>
                <button type="button" class="btn btn-primario btn-pequeno" data-dialogo="dlg-nuevo">+ Añadir trastero</button>
            </div>


            <!-- --- 3. Tabla de trasteros --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Todos los trasteros</h2>
                    <span class="texto-sec" id="contador"><?= count($trasteros) ?> trasteros</span>
                </div>

                <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Trastero</th>
                            <th>Nº en Node-RED</th>
                            <th>Cliente</th>
                            <th>Última apertura</th>
                            <th>Estado</th>
                            <th class="tabla-acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trasteros as $t): ?>
                        <tr>
                            <td>
                                <div class="tabla-usuario">
                                    <span class="tabla-usuario-nombre"><?= htmlspecialchars($t['nombre']) ?></span>
                                    <span class="tabla-usuario-email"><?= htmlspecialchars($t['ubicacion'] ?? '') ?></span>
                                </div>
                            </td>

                            <td><span class="etiqueta"><?= htmlspecialchars($t['plc_id']) ?></span></td>

                            <td>
                                <?php $sus_clientes = $clientes[$t['id']] ?? []; ?>
                                <?php if (count($sus_clientes) === 0): ?>
                                    <span class="texto-sec">Libre</span>
                                <?php endif; ?>
                                <?php foreach ($sus_clientes as $c): ?>
                                    <div class="tabla-usuario">
                                        <span class="tabla-usuario-nombre">
                                            <?= htmlspecialchars($c['nombre']) ?>
                                            <?php if ((int) $c['activo'] !== 1): ?><span class="texto-aviso">(suspendido)</span><?php endif; ?>
                                        </span>
                                        <span class="tabla-usuario-email">
                                            <?= $c['fecha_fin'] === null ? 'Indefinido' : 'Hasta ' . fecha_es($c['fecha_fin'], false) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </td>

                            <td class="texto-sec"><?= fecha_es($t['ultima_apertura'], true, 'Nunca') ?></td>

                            <td>
                                <?php if ((int) $t['activa'] === 1): ?>
                                    <span class="texto-ok">● En servicio</span>
                                <?php else: ?>
                                    <span class="texto-sec">● De baja</span>
                                <?php endif; ?>
                            </td>

                            <td class="tabla-acciones">
                                <button type="button" class="btn-tabla"
                                        data-dialogo="dlg-editar"
                                        data-valores="<?= htmlspecialchars(json_encode(['id' => $t['id'], 'nombre' => $t['nombre'], 'ubicacion' => $t['ubicacion'] ?? '', 'plc_id' => $t['plc_id']])) ?>">
                                    Editar
                                </button>

                                <?php $baja = (int) $t['activa'] === 1; ?>
                                <form method="post" class="form-en-linea"
                                      data-confirmar="<?= $baja ? '¿Dar de baja ' . htmlspecialchars($t['nombre']) . '? Sus clientes dejarán de verlo y de poder abrirlo.' : '¿Volver a poner en servicio ' . htmlspecialchars($t['nombre']) . '?' ?>">
                                    <?php csrf_campo(); ?>
                                    <input type="hidden" name="accion" value="<?= $baja ? 'baja' : 'reactivar' ?>">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-tabla <?= $baja ? 'btn-tabla-peligro' : 'btn-tabla-ok' ?>">
                                        <?= $baja ? 'Dar de baja' : 'Reactivar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <p class="tabla-vacia" id="sin-resultados" hidden>No hay trasteros que coincidan con la búsqueda.</p>

            </section>



            <!-- #####################################################
                 VENTANAS EMERGENTES
                 ##################################################### -->

            <!-- --- Nuevo trastero --- -->
            <dialog id="dlg-nuevo" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="nuevo">

                    <h2>Nuevo trastero</h2>

                    <div class="campo">
                        <label for="nuevo-nombre">Nombre</label>
                        <input id="nuevo-nombre" name="nombre" type="text" maxlength="100" placeholder="Trastero 41" required>
                    </div>

                    <div class="campo">
                        <label for="nuevo-ubicacion">Ubicación (opcional)</label>
                        <input id="nuevo-ubicacion" name="ubicacion" type="text" maxlength="150" placeholder="Planta 6 · Pasillo B">
                    </div>

                    <div class="campo">
                        <label for="nuevo-plc">Código PLC</label>
                        <input id="nuevo-plc" name="plc_id" type="text" maxlength="50" placeholder="8" required>
                        <span class="ayuda">El número de la puerta en Node-RED (el {id} de /api/puerta/{id}/abrir). No puede repetirse.</span>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Añadir trastero</button>
                    </div>
                </form>
            </dialog>


            <!-- --- Editar trastero --- -->
            <dialog id="dlg-editar" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="">

                    <h2>Editar trastero</h2>

                    <div class="campo">
                        <label for="editar-nombre">Nombre</label>
                        <input id="editar-nombre" name="nombre" type="text" maxlength="100" required>
                    </div>

                    <div class="campo">
                        <label for="editar-ubicacion">Ubicación (opcional)</label>
                        <input id="editar-ubicacion" name="ubicacion" type="text" maxlength="150">
                    </div>

                    <div class="campo">
                        <label for="editar-plc">Código PLC</label>
                        <input id="editar-plc" name="plc_id" type="text" maxlength="50" required>
                        <span class="ayuda">Cámbialo solo si la cerradura cambia de código en Node-RED.</span>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Guardar cambios</button>
                    </div>
                </form>
            </dialog>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
