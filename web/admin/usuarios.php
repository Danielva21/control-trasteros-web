<?php
/* #########################################################
   admin/usuarios.php — PANEL DE ADMINISTRACIÓN: USUARIOS

   Lista de usuarios con buscador y filtros, y las acciones:
     - Añadir cliente   (ventana "Nuevo usuario")
     - Editar           (ventana "Editar usuario")
     - Suspender / Reactivar

   CÓMO FUNCIONAN LAS ACCIONES (igual en todas las páginas):
     1. Cada botón envía un <form method="post"> a ESTA página,
        con un campo oculto "accion" que dice qué hacer.
     2. Arriba del todo, si llega un POST: se comprueba el token
        CSRF, se hace la acción en la BD, se guarda un mensaje
        y se REDIRIGE a la página (patrón POST → redirigir → GET).
     3. La página se carga normal y enseña el mensaje.
   ######################################################### */



/* =========================================================
   0. SEGURIDAD, CONEXIÓN, FUNCIONES Y CSRF
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/csrf.php';



/* #########################################################
   1. ACCIONES (solo si se ha enviado un formulario)
   ######################################################### */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lo primero: el formulario tiene que venir de NUESTRA página
    csrf_comprobar();

    $accion = $_POST['accion'] ?? '';

    // (int) = convertir a número entero. Si llega "abc", vale 0 (no existe ningún id 0).
    $id = (int) ($_POST['id'] ?? 0);


    /* ---------------------------------------------------------
       1.1 NUEVO USUARIO (siempre con rol 'usuario':
           los admins se crean a mano, nunca desde la web)
       --------------------------------------------------------- */
    if ($accion === 'nuevo') {

        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        // Validar en el SERVIDOR (lo del navegador, como "required", se puede saltar)
        // filter_var(..., FILTER_VALIDATE_EMAIL) = ¿tiene forma de email?
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            mensaje_guardar('error', 'El nombre es obligatorio (máximo 100 caracteres).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            mensaje_guardar('error', 'El email no es válido.');
        } elseif (mb_strlen($password) < PASSWORD_MINIMO) {
            mensaje_guardar('error', 'La contraseña debe tener al menos ' . PASSWORD_MINIMO . ' caracteres.');
        } else {
            // try/catch: si el email ya existe, la BD lo rechaza (UNIQUE) y lo capturamos
            try {
                $pdo->prepare(
                    "INSERT INTO usuarios (nombre, email, password_hash, rol)
                     VALUES (?, ?, ?, 'usuario')"
                )->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);

                mensaje_guardar('ok', 'Usuario "' . $nombre . '" creado. Ya puede entrar con su email y la contraseña que le has puesto.');

            } catch (PDOException $e) {
                if (es_duplicado($e)) {
                    mensaje_guardar('error', 'Ya existe un usuario con el email ' . $email . '.');
                } else {
                    throw $e;   // otro error: que salte (no lo escondemos)
                }
            }
        }
    }


    /* ---------------------------------------------------------
       1.2 EDITAR USUARIO (nombre, email y, si se escribe, contraseña nueva)
       --------------------------------------------------------- */
    elseif ($accion === 'editar') {

        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';   // vacío = no cambiarla

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            mensaje_guardar('error', 'El nombre es obligatorio (máximo 100 caracteres).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            mensaje_guardar('error', 'El email no es válido.');
        } elseif ($password !== '' && mb_strlen($password) < PASSWORD_MINIMO) {
            mensaje_guardar('error', 'La contraseña nueva debe tener al menos ' . PASSWORD_MINIMO . ' caracteres.');
        } else {
            try {
                $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?')
                    ->execute([$nombre, $email, $id]);

                if ($password !== '') {
                    $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }

                mensaje_guardar('ok', 'Cambios guardados.');

            } catch (PDOException $e) {
                if (es_duplicado($e)) {
                    mensaje_guardar('error', 'Ya existe otro usuario con el email ' . $email . '.');
                } else {
                    throw $e;
                }
            }
        }
    }


    /* ---------------------------------------------------------
       1.3 SUSPENDER / REACTIVAR
       "AND rol = 'usuario'": aunque alguien manipule el formulario
       con el id de un admin, la BD no lo tocará.
       --------------------------------------------------------- */
    elseif ($accion === 'suspender' || $accion === 'reactivar') {

        $activo = ($accion === 'reactivar') ? 1 : 0;

        $consulta = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ? AND rol = 'usuario'");
        $consulta->execute([$activo, $id]);

        // rowCount() = cuántas filas se han cambiado (0 = ese id no existía o era admin)
        if ($consulta->rowCount() === 1) {
            mensaje_guardar('ok', $activo ? 'Usuario reactivado: ya puede volver a entrar.' : 'Usuario suspendido: ya no puede entrar ni abrir.');
        } else {
            mensaje_guardar('error', 'No se ha podido cambiar ese usuario.');
        }
    }


    // Pase lo que pase: volver a la página (el mensaje se enseñará arriba)
    volver_a('usuarios.php');
}



/* #########################################################
   2. DATOS PARA PINTAR LA PÁGINA
   ######################################################### */

// Usuarios con sus trasteros vigentes (ver comentarios en admin/index.php)
$usuarios = $pdo->query(
    "SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.ultimo_acceso,
            GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR '|') AS puertas
       FROM usuarios u
       LEFT JOIN permisos pe ON pe.usuario_id = u.id
                            AND (pe.fecha_fin IS NULL OR pe.fecha_fin >= NOW())
       LEFT JOIN puertas  p  ON p.id = pe.puerta_id
                            AND p.activa = 1
      GROUP BY u.id
      ORDER BY u.rol, u.nombre"
)->fetchAll();

foreach ($usuarios as &$u) {
    $u['puertas'] = ($u['puertas'] === null) ? [] : explode('|', $u['puertas']);
}
unset($u);



/* =========================================================
   3. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Usuarios';
$pagina_activa = 'usuarios';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <!-- --- 1. Barra de herramientas: buscador + filtros + botón --- -->
            <div class="herramientas">

                <div class="buscador">
                    <label for="buscar" class="solo-lectores">Buscar usuario</label>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M21 21l-4.3-4.3"></path>
                    </svg>
                    <input id="buscar" type="search" placeholder="Buscar por nombre, email o trastero">
                </div>

                <!-- data-filtro="rol" → admin.js compara con el data-rol de cada fila -->
                <label for="filtro-rol" class="solo-lectores">Filtrar por rol</label>
                <select id="filtro-rol" class="selector" data-filtro="rol">
                    <option value="">Todos los roles</option>
                    <option value="admin">Admin</option>
                    <option value="usuario">Usuario</option>
                </select>

                <label for="filtro-estado" class="solo-lectores">Filtrar por estado</label>
                <select id="filtro-estado" class="selector" data-filtro="activo">
                    <option value="">Todos los estados</option>
                    <option value="1">Activos</option>
                    <option value="0">Suspendidos</option>
                </select>

                <!-- data-dialogo = id de la ventana que abre (admin.js) -->
                <button type="button" class="btn btn-primario btn-pequeno" data-dialogo="dlg-nuevo">+ Añadir usuario</button>

            </div>


            <!-- --- 2. Tabla de usuarios --- -->
            <section class="panel">

                <div class="panel-cabecera">
                    <h2>Todos los usuarios</h2>
                    <span class="texto-sec" id="contador"><?= count($usuarios) ?> usuarios</span>
                </div>

                <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Trasteros</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th class="tabla-acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr data-rol="<?= $u['rol'] ?>" data-activo="<?= (int) $u['activo'] ?>">

                            <td>
                                <div class="tabla-usuario">
                                    <span class="tabla-usuario-nombre"><?= htmlspecialchars($u['nombre']) ?></span>
                                    <span class="tabla-usuario-email"><?= htmlspecialchars($u['email']) ?></span>
                                </div>
                            </td>

                            <td>
                                <?php if ($u['rol'] === 'admin'): ?>
                                    <span class="pill pill-admin">Admin</span>
                                <?php else: ?>
                                    <span class="pill pill-neutra">Usuario</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($u['rol'] === 'admin'): ?>
                                    <span class="texto-sec">Todos</span>
                                <?php elseif (count($u['puertas']) === 0): ?>
                                    <span class="texto-sec">Ninguno</span>
                                <?php else: ?>
                                    <div class="etiquetas">
                                        <?php foreach ($u['puertas'] as $nombre_puerta): ?>
                                            <span class="etiqueta"><?= htmlspecialchars($nombre_puerta) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ((int) $u['activo'] === 1): ?>
                                    <span class="texto-ok">● Activo</span>
                                <?php else: ?>
                                    <span class="texto-aviso">● Suspendido</span>
                                <?php endif; ?>
                            </td>

                            <td class="texto-sec"><?= fecha_es($u['ultimo_acceso'], true, 'Nunca') ?></td>

                            <td class="tabla-acciones">
                                <!-- Editar: abre la ventana y la rellena con los datos de ESTE usuario.
                                     json_encode convierte el array PHP en texto JSON para el JS;
                                     htmlspecialchars lo protege dentro del atributo HTML. -->
                                <button type="button" class="btn-tabla"
                                        data-dialogo="dlg-editar"
                                        data-valores="<?= htmlspecialchars(json_encode(['id' => $u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'password' => ''])) ?>">
                                    Editar
                                </button>

                                <!-- Suspender / Reactivar: un mini formulario con un solo botón.
                                     data-confirmar → admin.js pregunta "¿Seguro?" antes de enviarlo. -->
                                <?php if ($u['rol'] === 'usuario'): ?>
                                    <?php $suspender = (int) $u['activo'] === 1; ?>
                                    <form method="post" class="form-en-linea"
                                          data-confirmar="<?= $suspender ? '¿Suspender a ' . htmlspecialchars($u['nombre']) . '? No podrá entrar ni abrir sus trasteros.' : '¿Reactivar a ' . htmlspecialchars($u['nombre']) . '?' ?>">
                                        <?php csrf_campo(); ?>
                                        <input type="hidden" name="accion" value="<?= $suspender ? 'suspender' : 'reactivar' ?>">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-tabla <?= $suspender ? 'btn-tabla-peligro' : 'btn-tabla-ok' ?>">
                                            <?= $suspender ? 'Suspender' : 'Reactivar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <p class="tabla-vacia" id="sin-resultados" hidden>No hay usuarios que coincidan con la búsqueda.</p>

            </section>



            <!-- #####################################################
                 VENTANAS EMERGENTES (ocultas hasta que se abren)
                 ##################################################### -->

            <!-- --- Nuevo usuario --- -->
            <dialog id="dlg-nuevo" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="nuevo">

                    <h2>Nuevo usuario</h2>

                    <div class="campo">
                        <label for="nuevo-nombre">Nombre</label>
                        <input id="nuevo-nombre" name="nombre" type="text" maxlength="100" required>
                    </div>

                    <div class="campo">
                        <label for="nuevo-email">Email</label>
                        <input id="nuevo-email" name="email" type="email" maxlength="150" required>
                    </div>

                    <div class="campo">
                        <label for="nuevo-password">Contraseña inicial</label>
                        <!-- autocomplete="new-password" = que el navegador no rellene la TUYA aquí -->
                        <input id="nuevo-password" name="password" type="text" minlength="<?= PASSWORD_MINIMO ?>" autocomplete="new-password" required>
                        <span class="ayuda">Mínimo <?= PASSWORD_MINIMO ?> caracteres. Dásela al cliente; podrá cambiarla en "Mi perfil".</span>
                    </div>

                    <p class="ayuda">Se crea como cliente (rol usuario). Para darle trasteros, ve a Permisos.</p>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Crear usuario</button>
                    </div>
                </form>
            </dialog>


            <!-- --- Editar usuario (admin.js rellena los campos al abrirla) --- -->
            <dialog id="dlg-editar" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="">

                    <h2>Editar usuario</h2>

                    <div class="campo">
                        <label for="editar-nombre">Nombre</label>
                        <input id="editar-nombre" name="nombre" type="text" maxlength="100" required>
                    </div>

                    <div class="campo">
                        <label for="editar-email">Email</label>
                        <input id="editar-email" name="email" type="email" maxlength="150" required>
                    </div>

                    <div class="campo">
                        <label for="editar-password">Nueva contraseña (opcional)</label>
                        <input id="editar-password" name="password" type="text" minlength="<?= PASSWORD_MINIMO ?>" autocomplete="new-password">
                        <span class="ayuda">Déjala vacía para no cambiarla. Útil si el cliente ha olvidado la suya.</span>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Guardar cambios</button>
                    </div>
                </form>
            </dialog>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
