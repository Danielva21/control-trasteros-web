<?php
/* #########################################################
   usuario/trasteros.php — MIS TRASTEROS

   Todos los trasteros que el cliente tiene o ha tenido, con
   sus fechas: desde cuándo, hasta cuándo y si sigue vigente.
   ######################################################### */



// 0. Protección + conexión + datos del usuario ($pdo, $usuario, $inicial)
require_once __DIR__ . '/../includes/solo_usuario.php';
require_once __DIR__ . '/../includes/funciones.php';



/* =========================================================
   1. TODOS SUS PERMISOS (vigentes y caducados)
   CASE WHEN … THEN … ELSE … END = un "if" dentro de SQL.
   Aquí crea una columna "vigente" que vale 1 o 0, para
   ordenar los vigentes primero.
   ========================================================= */

$consulta = $pdo->prepare(
    'SELECT p.nombre, p.ubicacion, p.activa,
            pe.fecha_inicio, pe.fecha_fin,
            CASE WHEN pe.fecha_fin IS NULL OR pe.fecha_fin >= NOW() THEN 1 ELSE 0 END AS vigente
       FROM permisos pe
       JOIN puertas p ON p.id = pe.puerta_id
      WHERE pe.usuario_id = ?
      ORDER BY vigente DESC, p.nombre'
);
$consulta->execute([$usuario['id']]);
$trasteros = $consulta->fetchAll();



// 2. Cabecera común
$titulo        = 'Mis trasteros';
$pagina_activa = 'trasteros';
require __DIR__ . '/../includes/usuario_cabecera.php';

?>

    <h1 class="pagina-titulo">Mis trasteros</h1>

    <section class="bloque">
        <?php if (count($trasteros) === 0): ?>
            <p class="texto-sec">No tienes ningún trastero asignado.</p>
        <?php else: ?>
        <ul class="filas">
            <?php foreach ($trasteros as $t): ?>
            <li>
                <div class="fila-texto">
                    <span class="fila-principal"><?= htmlspecialchars($t['nombre']) ?></span>
                    <span class="fila-sec"><?= htmlspecialchars($t['ubicacion'] ?? '') ?></span>
                    <span class="fila-sec">
                        Desde <?= fecha_es($t['fecha_inicio'], false) ?>
                        · <?= $t['fecha_fin'] === null ? 'Sin fecha de fin' : 'Hasta ' . fecha_es($t['fecha_fin'], false) ?>
                    </span>
                </div>

                <!-- Estado: el primero que se cumpla (de baja > caducado > activo) -->
                <?php if ((int) $t['activa'] !== 1): ?>
                    <span class="pill pill-neutra">Fuera de servicio</span>
                <?php elseif ((int) $t['vigente'] !== 1): ?>
                    <span class="pill pill-error">Caducado</span>
                <?php else: ?>
                    <span class="pill pill-ok">Activo</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <p class="texto-sec">¿Quieres renovar o ampliar? Escríbenos desde <a href="soporte.php">Soporte / Contacto</a>.</p>

<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
