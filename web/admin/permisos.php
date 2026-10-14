<?php
/* #########################################################
   admin/permisos.php — PANEL DE ADMINISTRACIÓN: PERMISOS

   Los "alquileres": qué cliente tiene qué trastero, desde
   cuándo y hasta cuándo. Acciones:
     - Asignar trastero (cliente + trastero + fechas)
     - Renovar (cambiar la fecha de fin)
     - Quitar (el permiso acaba AHORA; no se borra, queda como caducado)
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
       1.1 ASIGNAR TRASTERO A UN CLIENTE
       --------------------------------------------------------- */
    if ($accion === 'asignar') {

        $usuario_id = (int) ($_POST['usuario_id'] ?? 0);
        $puerta_id  = (int) ($_POST['puerta_id'] ?? 0);

        // Fechas: inicio obligatoria; fin opcional (vacía = indefinido)
        $inicio    = fecha_formulario($_POST['fecha_inicio'] ?? '');
        $fin_texto = $_POST['fecha_fin'] ?? '';
        $fin       = ($fin_texto === '') ? null : fecha_formulario($fin_texto, true);

        // Comprobar que el cliente y el trastero EXISTEN y son válidos
        // (el formulario solo ofrece los buenos, pero se puede manipular)
        $consulta = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = ? AND rol = 'usuario'");
        $consulta->execute([$usuario_id]);
        $cliente_ok = $consulta->fetchColumn() == 1;

        $consulta = $pdo->prepare('SELECT COUNT(*) FROM puertas WHERE id = ? AND activa = 1');
        $consulta->execute([$puerta_id]);
        $trastero_ok = $consulta->fetchColumn() == 1;

        if (!$cliente_ok || !$trastero_ok) {
            mensaje_guardar('error', 'Elige un cliente y un trastero en servicio.');
        } elseif ($inicio === false || $fin === false) {
            mensaje_guardar('error', 'Alguna de las fechas no es válida.');
        } elseif ($fin !== null && $fin < $inicio) {
            // Las fechas "AAAA-MM-DD hh:mm:ss" se pueden comparar como texto: el orden es el mismo
            mensaje_guardar('error', 'La fecha de fin no puede ser anterior a la de inicio.');
        } else {
            // La pareja cliente + trastero es UNIQUE en la tabla. Si ya existía (por ejemplo,
            // un permiso caducado), en vez de dar error se ACTUALIZAN sus fechas:
            // "ON DUPLICATE KEY UPDATE" = si choca con el UNIQUE, haz este UPDATE.
            $pdo->prepare(
                'INSERT INTO permisos (usuario_id, puerta_id, fecha_inicio, fecha_fin)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE fecha_inicio = VALUES(fecha_inicio),
                                         fecha_fin    = VALUES(fecha_fin)'
            )->execute([$usuario_id, $puerta_id, $inicio, $fin]);

            mensaje_guardar('ok', 'Trastero asignado.');
        }
    }


    /* ---------------------------------------------------------
       1.2 RENOVAR: nueva fecha de fin (vacía = indefinido)
       --------------------------------------------------------- */
    elseif ($accion === 'renovar') {

        $fin_texto = $_POST['fecha_fin'] ?? '';
        $fin       = ($fin_texto === '') ? null : fecha_formulario($fin_texto, true);

        if ($fin === false) {
            mensaje_guardar('error', 'La fecha no es válida.');
        } else {
            $consulta = $pdo->prepare('UPDATE permisos SET fecha_fin = ? WHERE id = ?');
            $consulta->execute([$fin, $id]);
            mensaje_guardar('ok', $fin === null ? 'Permiso renovado sin fecha de fin.' : 'Permiso renovado hasta el ' . fecha_es($fin, false) . '.');
        }
    }


    /* ---------------------------------------------------------
       1.3 QUITAR: el permiso termina ahora mismo.
       No se borra la fila: queda como "caducado" y se puede
       volver a renovar o asignar más adelante.
       --------------------------------------------------------- */
    elseif ($accion === 'quitar') {

        // "NOW() - 1 segundo" y no "NOW()": las consultas de vigencia usan "fecha_fin >= NOW()",
        // y si se ejecutan en el MISMO segundo, NOW() >= NOW() sería verdadero y el permiso
        // seguiría valiendo durante ese segundo. Restando uno, caduca al instante.
        $pdo->prepare('UPDATE permisos SET fecha_fin = NOW() - INTERVAL 1 SECOND WHERE id = ?')->execute([$id]);
        mensaje_guardar('ok', 'Permiso retirado: el cliente ya no puede abrir ese trastero.');
    }


    volver_a('permisos.php');
}



/* #########################################################
   2. DATOS PARA PINTAR LA PÁGINA
   ######################################################### */

/* ---------------------------------------------------------
   2.1 TODOS LOS PERMISOS, con el nombre del cliente y del trastero
   Orden: los que caducan antes, primero; los indefinidos al final.
   --------------------------------------------------------- */

$permisos = $pdo->query(
    'SELECT pe.id, pe.fecha_inicio, pe.fecha_fin,
            u.nombre AS cliente, u.email, u.activo AS cliente_activo,
            p.nombre AS trastero, p.activa AS trastero_activo
       FROM permisos pe
       JOIN usuarios u ON u.id = pe.usuario_id
       JOIN puertas  p ON p.id = pe.puerta_id
      ORDER BY pe.fecha_fin IS NULL, pe.fecha_fin, p.nombre'
)->fetchAll();


/* ---------------------------------------------------------
   2.2 ESTADO DE CADA PERMISO (el primero que se cumpla, gana)
   --------------------------------------------------------- */

$ahora         = time();
$dentro_30dias = $ahora + 30 * 24 * 60 * 60;

foreach ($permisos as &$pe) {

    $fin = ($pe['fecha_fin'] === null) ? null : strtotime($pe['fecha_fin']);

    if ((int) $pe['cliente_activo'] !== 1) {
        $pe['estado'] = ['texto' => 'Cliente suspendido', 'clase' => 'pill-aviso'];
    } elseif ((int) $pe['trastero_activo'] !== 1) {
        $pe['estado'] = ['texto' => 'Trastero de baja',   'clase' => 'pill-neutra'];
    } elseif ($fin !== null && $fin < $ahora) {
        $pe['estado'] = ['texto' => 'Caducado',           'clase' => 'pill-error'];
    } elseif ($fin !== null && $fin <= $dentro_30dias) {
        $pe['estado'] = ['texto' => 'Caduca pronto',      'clase' => 'pill-aviso'];
    } else {
        $pe['estado'] = ['texto' => 'Vigente',            'clase' => 'pill-ok'];
    }

    // ¿Está en vigor? (para saber si enseñar el botón "Quitar")
    $pe['en_vigor'] = ($fin === null || $fin >= $ahora);

    // Fecha de fin en formato del <input type="date"> (AAAA-MM-DD), para rellenar "Renovar"
    $pe['fin_formulario'] = ($fin === null) ? '' : date('Y-m-d', $fin);
}
unset($pe);


/* ---------------------------------------------------------
   2.3 OPCIONES DE LOS DESPLEGABLES de "Asignar trastero"
   Solo clientes activos y trasteros en servicio.
   --------------------------------------------------------- */

$opciones_clientes = $pdo->query(
    "SELECT id, nombre, email FROM usuarios WHERE rol = 'usuario' AND activo = 1 ORDER BY nombre"
)->fetchAll();

$opciones_trasteros = $pdo->query(
    'SELECT id, nombre, ubicacion FROM puertas WHERE activa = 1 ORDER BY nombre'
)->fetchAll();



/* =========================================================
   3. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Permisos';
$pagina_activa = 'permisos';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <!-- --- 1. Barra de herramientas --- -->
            <div class="herramientas">
                <div class="buscador">
                    <label for="buscar" class="solo-lectores">Buscar permiso</label>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M21 21l-4.3-4.3"></path>
                    </svg>
                    <input id="buscar" type="search" placeholder="Buscar por cliente o trastero">
                </div>
                <button type="button" class="btn btn-primario btn-pequeno" data-dialogo="dlg-nuevo">+ Asignar trastero</button>
            </div>


            <!-- --- 2. Tabla de permisos --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Alquileres</h2>
                    <span class="texto-sec" id="contador"><?= count($permisos) ?> permisos</span>
                </div>

                <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Trastero</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Estado</th>
                            <th class="tabla-acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permisos as $pe): ?>
                        <tr>
                            <td>
                                <div class="tabla-usuario">
                                    <span class="tabla-usuario-nombre"><?= htmlspecialchars($pe['cliente']) ?></span>
                                    <span class="tabla-usuario-email"><?= htmlspecialchars($pe['email']) ?></span>
                                </div>
                            </td>
                            <td><span class="etiqueta"><?= htmlspecialchars($pe['trastero']) ?></span></td>
                            <td class="texto-sec"><?= fecha_es($pe['fecha_inicio'], false) ?></td>
                            <td><?= fecha_es($pe['fecha_fin'], false, 'Indefinido') ?></td>
                            <td><span class="pill <?= $pe['estado']['clase'] ?>"><?= $pe['estado']['texto'] ?></span></td>
                            <td class="tabla-acciones">
                                <button type="button" class="btn-tabla"
                                        data-dialogo="dlg-renovar"
                                        data-valores="<?= htmlspecialchars(json_encode(['id' => $pe['id'], 'fecha_fin' => $pe['fin_formulario'], 'descripcion' => $pe['cliente'] . ' · ' . $pe['trastero']])) ?>">
                                    Renovar
                                </button>

                                <?php if ($pe['en_vigor']): ?>
                                <form method="post" class="form-en-linea"
                                      data-confirmar="¿Quitar a <?= htmlspecialchars($pe['cliente']) ?> el <?= htmlspecialchars($pe['trastero']) ?>? Dejará de poder abrirlo desde ahora.">
                                    <?php csrf_campo(); ?>
                                    <input type="hidden" name="accion" value="quitar">
                                    <input type="hidden" name="id" value="<?= $pe['id'] ?>">
                                    <button type="submit" class="btn-tabla btn-tabla-peligro">Quitar</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <p class="tabla-vacia" id="sin-resultados" hidden>No hay permisos que coincidan con la búsqueda.</p>

            </section>



            <!-- #####################################################
                 VENTANAS EMERGENTES
                 ##################################################### -->

            <!-- --- Asignar trastero --- -->
            <dialog id="dlg-nuevo" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="asignar">

                    <h2>Asignar trastero</h2>

                    <div class="campo">
                        <label for="asignar-cliente">Cliente</label>
                        <select id="asignar-cliente" name="usuario_id" required>
                            <option value="">Elige un cliente…</option>
                            <?php foreach ($opciones_clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' — ' . $c['email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="asignar-trastero">Trastero</label>
                        <select id="asignar-trastero" name="puerta_id" required>
                            <option value="">Elige un trastero…</option>
                            <?php foreach ($opciones_trasteros as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ($t['ubicacion'] ? ' — ' . $t['ubicacion'] : '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="asignar-inicio">Fecha de inicio</label>
                        <!-- value = hoy, ya rellenado (date('Y-m-d') = la fecha de hoy en ese formato) -->
                        <input id="asignar-inicio" name="fecha_inicio" type="date" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="campo">
                        <label for="asignar-fin">Fecha de fin (opcional)</label>
                        <input id="asignar-fin" name="fecha_fin" type="date">
                        <span class="ayuda">Vacía = sin fecha de fin. El permiso sirve hasta el final de ese día.</span>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Asignar</button>
                    </div>
                </form>
            </dialog>


            <!-- --- Renovar (admin.js rellena id, fecha_fin y la descripción) --- -->
            <dialog id="dlg-renovar" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="renovar">
                    <input type="hidden" name="id" value="">

                    <h2>Renovar permiso</h2>

                    <!-- Solo informativo: dice qué permiso se está renovando (readonly = no se puede editar) -->
                    <div class="campo">
                        <label for="renovar-descripcion">Permiso</label>
                        <input id="renovar-descripcion" name="descripcion" type="text" readonly>
                    </div>

                    <div class="campo">
                        <label for="renovar-fin">Nueva fecha de fin</label>
                        <input id="renovar-fin" name="fecha_fin" type="date">
                        <span class="ayuda">Vacía = sin fecha de fin (indefinido).</span>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Guardar</button>
                    </div>
                </form>
            </dialog>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
