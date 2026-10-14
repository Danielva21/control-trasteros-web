<?php
/* #########################################################
   usuario/perfil.php — MI PERFIL

   Los datos de la cuenta del cliente. Desde aquí puede cambiar su contraseña.
   ######################################################### */



// 0. Protección + conexión + datos del usuario ($usuario ya trae creado_en y ultimo_acceso)
require_once __DIR__ . '/../includes/solo_usuario.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/csrf.php';

const PAGINA_ACTUAL = 'perfil.php';   // a dónde volver tras la acción



/* #########################################################
   1. ACCIÓN: CAMBIAR CONTRASEÑA
   Toda la lógica está en cambiar_password() (includes/funciones.php),
   que comparten esta página y la de Ajustes del admin.
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



// 2. Cabecera común
$titulo        = 'Mi perfil';
$pagina_activa = 'perfil';
require __DIR__ . '/../includes/usuario_cabecera.php';

?>

    <h1 class="pagina-titulo">Mi perfil</h1>

    <section class="bloque">
        <h2>Mis datos</h2>
        <ul class="filas">
            <li>
                <span class="fila-sec">Nombre</span>
                <span class="fila-principal"><?= htmlspecialchars($usuario['nombre']) ?></span>
            </li>
            <li>
                <span class="fila-sec">Email</span>
                <span class="fila-principal"><?= htmlspecialchars($usuario['email']) ?></span>
            </li>
            <li>
                <span class="fila-sec">Cliente desde</span>
                <span class="fila-principal"><?= fecha_es($usuario['creado_en'], false) ?></span>
            </li>
            <li>
                <span class="fila-sec">Último acceso</span>
                <span class="fila-principal"><?= fecha_es($usuario['ultimo_acceso'], true, 'Nunca') ?></span>
            </li>
        </ul>
    </section>

    <section class="bloque">
        <h2>Seguridad</h2>
        <p class="texto-sec">Para cambiar tu nombre o email, contacta con la empresa.</p>
        <!-- Abre la ventana de abajo (inicio.js) -->
        <button type="button" class="btn btn-primario" data-dialogo="dlg-password">Cambiar contraseña</button>
    </section>

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

<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
