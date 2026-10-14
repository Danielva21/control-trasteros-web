<?php
/* #########################################################
   admin/comunicados.php — PANEL DE ADMINISTRACIÓN: COMUNICADOS

   Los avisos de la empresa a los clientes. El activo más
   reciente es el que sale en la portada de cada cliente.
   Acciones: nuevo, editar, archivar / publicar.
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
       1.1 NUEVO / EDITAR
       --------------------------------------------------------- */
    if ($accion === 'nuevo' || $accion === 'editar') {

        $titulo_c = trim($_POST['titulo'] ?? '');
        $texto    = trim($_POST['texto'] ?? '');

        if ($titulo_c === '' || mb_strlen($titulo_c) > 150) {
            mensaje_guardar('error', 'El título es obligatorio (máximo 150 caracteres).');
        } elseif ($texto === '') {
            mensaje_guardar('error', 'El texto del comunicado es obligatorio.');
        } elseif ($accion === 'nuevo') {
            // autor_id = el admin que está dentro (de la sesión). Se publica al momento (activo 1).
            $pdo->prepare('INSERT INTO comunicados (titulo, texto, autor_id) VALUES (?, ?, ?)')
                ->execute([$titulo_c, $texto, $_SESSION['usuario_id']]);
            mensaje_guardar('ok', 'Comunicado publicado. Ya lo ven los clientes en su portada.');
        } else {
            $pdo->prepare('UPDATE comunicados SET titulo = ?, texto = ? WHERE id = ?')
                ->execute([$titulo_c, $texto, $id]);
            mensaje_guardar('ok', 'Cambios guardados.');
        }
    }


    /* ---------------------------------------------------------
       1.2 ARCHIVAR / PUBLICAR (activo 0 / 1)
       --------------------------------------------------------- */
    elseif ($accion === 'archivar' || $accion === 'publicar') {

        $activo = ($accion === 'publicar') ? 1 : 0;
        $pdo->prepare('UPDATE comunicados SET activo = ? WHERE id = ?')->execute([$activo, $id]);
        mensaje_guardar('ok', $activo ? 'Comunicado publicado.' : 'Comunicado archivado: los clientes ya no lo ven.');
    }


    volver_a('comunicados.php');
}



/* #########################################################
   2. DATOS PARA PINTAR LA PÁGINA
   Los activos primero; dentro de cada grupo, el más nuevo arriba.
   ######################################################### */

$comunicados = $pdo->query(
    'SELECT c.id, c.titulo, c.texto, c.activo, c.fecha_publicacion,
            u.nombre AS autor
       FROM comunicados c
       JOIN usuarios u ON u.id = c.autor_id
      ORDER BY c.activo DESC, c.fecha_publicacion DESC, c.id DESC'
)->fetchAll();



/* =========================================================
   3. CABECERA COMÚN DEL PANEL
   ========================================================= */

$titulo        = 'Comunicados';
$pagina_activa = 'comunicados';

require __DIR__ . '/../includes/admin_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA
     ######################################################### -->


            <div class="herramientas">
                <span class="texto-sec">El comunicado publicado más reciente es el que ven los clientes en su portada.</span>
                <button type="button" class="btn btn-primario btn-pequeno btn-derecha" data-dialogo="dlg-nuevo">+ Nuevo comunicado</button>
            </div>


            <?php if (count($comunicados) === 0): ?>
                <section class="panel">
                    <p class="tabla-vacia">Todavía no hay comunicados.</p>
                </section>
            <?php endif; ?>

            <?php foreach ($comunicados as $c): ?>
            <section class="panel">

                <div class="panel-cabecera">
                    <h2><?= htmlspecialchars($c['titulo']) ?></h2>
                    <?php if ((int) $c['activo'] === 1): ?>
                        <span class="pill pill-ok">Publicado</span>
                    <?php else: ?>
                        <span class="pill pill-neutra">Archivado</span>
                    <?php endif; ?>
                </div>

                <div class="panel-cuerpo">
                    <p><?= nl2br(htmlspecialchars($c['texto'])) ?></p>

                    <p class="texto-sec panel-pie-texto">
                        <?= fecha_es($c['fecha_publicacion']) ?> · por <?= htmlspecialchars($c['autor']) ?>
                    </p>

                    <div class="panel-acciones">
                        <button type="button" class="btn-tabla"
                                data-dialogo="dlg-editar"
                                data-valores="<?= htmlspecialchars(json_encode(['id' => $c['id'], 'titulo' => $c['titulo'], 'texto' => $c['texto']])) ?>">
                            Editar
                        </button>

                        <?php $archivar = (int) $c['activo'] === 1; ?>
                        <form method="post" class="form-en-linea"
                              data-confirmar="<?= $archivar ? '¿Archivar este comunicado? Los clientes dejarán de verlo.' : '¿Volver a publicar este comunicado?' ?>">
                            <?php csrf_campo(); ?>
                            <input type="hidden" name="accion" value="<?= $archivar ? 'archivar' : 'publicar' ?>">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-tabla <?= $archivar ? 'btn-tabla-peligro' : 'btn-tabla-ok' ?>">
                                <?= $archivar ? 'Archivar' : 'Publicar' ?>
                            </button>
                        </form>
                    </div>
                </div>

            </section>
            <?php endforeach; ?>



            <!-- #####################################################
                 VENTANAS EMERGENTES
                 ##################################################### -->

            <dialog id="dlg-nuevo" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="nuevo">

                    <h2>Nuevo comunicado</h2>

                    <div class="campo">
                        <label for="nuevo-titulo">Título</label>
                        <input id="nuevo-titulo" name="titulo" type="text" maxlength="150" placeholder="Corte de luz el viernes" required>
                    </div>

                    <div class="campo">
                        <label for="nuevo-texto">Texto</label>
                        <!-- <textarea> = caja de texto de varias líneas -->
                        <textarea id="nuevo-texto" name="texto" required></textarea>
                    </div>

                    <p class="ayuda">Se publica al momento y pasa a ser el que ven los clientes en su portada.</p>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Publicar</button>
                    </div>
                </form>
            </dialog>


            <dialog id="dlg-editar" class="ventana">
                <form method="post" class="formulario">
                    <?php csrf_campo(); ?>
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="">

                    <h2>Editar comunicado</h2>

                    <div class="campo">
                        <label for="editar-titulo">Título</label>
                        <input id="editar-titulo" name="titulo" type="text" maxlength="150" required>
                    </div>

                    <div class="campo">
                        <label for="editar-texto">Texto</label>
                        <textarea id="editar-texto" name="texto" required></textarea>
                    </div>

                    <div class="formulario-botones">
                        <button type="button" class="btn btn-secundario btn-pequeno" data-cerrar>Cancelar</button>
                        <button type="submit" class="btn btn-primario btn-pequeno">Guardar cambios</button>
                    </div>
                </form>
            </dialog>


<?php require __DIR__ . '/../includes/admin_pie.php'; ?>
