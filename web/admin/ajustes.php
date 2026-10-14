<?php
/* #########################################################
   admin/ajustes.php — PANEL DE ADMINISTRACIÓN: AJUSTES

   De momento solo enseña información (la cuenta del admin y
   datos de la instalación). Cambiar contraseña, nombre de la
   empresa, etc.: en el bloque A.
   ######################################################### */



/* =========================================================
   0. SEGURIDAD, CONEXIÓN Y FUNCIONES
   ========================================================= */

require_once __DIR__ . '/../includes/solo_admin.php';
require      __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/csrf.php';

const PAGINA_ACTUAL = 'ajustes.php';   // a dónde volver tras la acción



/* #########################################################
   1. ACCIÓN: CAMBIAR CONTRASEÑA
   Toda la lógica está en cambiar_password() (includes/funciones.php),
   que comparten esta página y la de Mi perfil del cliente.
   ######################################################### */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_comprobar();

    if (($_POST['accion'] ?? '') === 'cambiar_password') {

        $error = cambiar_password(
            $pdo,
            $_SESSION['usuario_id'],
            $_POST['actual']   ?? '',
            $_POST['nueva']    ?? '',
            $_POST['repetida'] ?? ''
        );

        // Si no hay error ('' vacío), todo bien
        if ($error === '') {
            mensaje_guardar('ok', 'Contraseña cambiada.');
        } else {
            mensaje_guardar('error', $error);
        }
    }

    volver_a(PAGINA_ACTUAL);
}



/* =========================================================
   2. LA CUENTA DEL ADMIN QUE ESTÁ DENTRO
   Lleva "?" porque usa el id de la sesión.
   ========================================================= */

$consulta = $pdo->prepare(
    'SELECT nombre, email, creado_en, ultimo_acceso
       FROM usuarios
      WHERE id = ?'
);
$consulta->execute([$_SESSION['usuario_id']]);
$cuenta = $consulta->fetch();



/* =========================================================
   3. DATOS DE LA INSTALACIÓN
   VERSION()  = versión de MariaDB.
   PHP_VERSION = constante de PHP con su versión.
   ========================================================= */

$instalacion = [
    'Base de datos'      => BD_NOMBRE,                                     // constante de config.php
    'Versión de MariaDB' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'Versión de PHP'     => PHP_VERSION,
    'Trasteros'          => $pdo->query('SELECT COUNT(*) FROM puertas')->fetchColumn(),
    'Usuarios'           => $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
];



/* =========================================================
   4. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Ajustes';
$pagina_activa = 'ajustes';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <div class="admin-fila">

                <!-- --- 1. Mi cuenta --- -->
                <section class="panel">
                    <div class="panel-cabecera">
                        <h2>Mi cuenta</h2>
                        <button type="button" class="btn-tabla" data-dialogo="dlg-password">Cambiar contraseña</button>
                    </div>

                    <!-- <dl> = lista de "término → definición" (perfecta para "Nombre: Dani") -->
                    <dl class="datos">
                        <dt>Nombre</dt>          <dd><?= htmlspecialchars($cuenta['nombre']) ?></dd>
                        <dt>Email</dt>           <dd><?= htmlspecialchars($cuenta['email']) ?></dd>
                        <dt>Rol</dt>             <dd><span class="pill pill-admin">Admin</span></dd>
                        <dt>Cuenta creada</dt>   <dd><?= fecha_es($cuenta['creado_en'], false) ?></dd>
                        <dt>Último acceso</dt>   <dd><?= fecha_es($cuenta['ultimo_acceso']) ?></dd>
                    </dl>
                </section>


                <!-- --- 2. Instalación --- -->
                <section class="panel">
                    <div class="panel-cabecera">
                        <h2>Instalación</h2>
                    </div>

                    <dl class="datos">
                        <!-- foreach con "clave => valor": la clave es el texto ("Versión de PHP"), el valor el dato -->
                        <?php foreach ($instalacion as $nombre => $valor): ?>
                            <dt><?= htmlspecialchars($nombre) ?></dt>
                            <dd><?= htmlspecialchars((string) $valor) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </section>

            </div>


            <!-- --- Ventana: cambiar contraseña --- -->
            <dialog id="dlg-password" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="cambiar_password">

                    <h2>Cambiar contraseña</h2>

                    <div class="campo">
                        <label for="pass-actual">Contraseña actual</label>
                        <input id="pass-actual" name="actual" type="password" autocomplete="current-password" required>
                    </div>

                    <div class="campo">
                        <label for="pass-nueva">Nueva contraseña</label>
                        <input id="pass-nueva" name="nueva" type="password" minlength="<?= PASSWORD_MINIMO ?>" autocomplete="new-password" required>
                        <span class="ayuda">Mínimo <?= PASSWORD_MINIMO ?> caracteres.</span>
                    </div>

                    <div class="campo">
                        <label for="pass-repetida">Repite la nueva contraseña</label>
                        <input id="pass-repetida" name="repetida" type="password" minlength="<?= PASSWORD_MINIMO ?>" autocomplete="new-password" required>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Cambiar contraseña</button>
                    </div>
                </form>
            </dialog>

<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
