<?php
/* #########################################################
   usuario/historial.php — HISTORIAL DE ACCESOS DEL CLIENTE

   Sus intentos de apertura (solo los SUYOS), los más
   recientes primero.
   ######################################################### */



// 0. Protección + conexión + datos del usuario ($pdo, $usuario, $inicial)
require_once __DIR__ . '/../includes/solo_usuario.php';
require_once __DIR__ . '/../includes/funciones.php';



/* =========================================================
   1. SUS ACCESOS (como mucho los 50 últimos)
   "WHERE a.usuario_id = ?" es lo que hace que cada cliente
   vea SOLO los suyos. El id sale de la BD/sesión, nunca de
   algo que el usuario pueda cambiar en la URL.
   ========================================================= */

$consulta = $pdo->prepare(
    'SELECT a.fecha, a.resultado, p.nombre AS trastero
       FROM accesos a
       JOIN puertas p ON p.id = a.puerta_id
      WHERE a.usuario_id = ?
      ORDER BY a.fecha DESC
      LIMIT 50'
);
$consulta->execute([$usuario['id']]);
$accesos = $consulta->fetchAll();



// 2. Cabecera común
$titulo        = 'Historial de accesos';
$pagina_activa = 'historial';
require __DIR__ . '/../includes/usuario_cabecera.php';

?>

    <h1 class="pagina-titulo">Historial de accesos</h1>

    <section class="bloque">
        <?php if (count($accesos) === 0): ?>
            <p class="texto-sec">Todavía no has abierto ningún trastero.</p>
        <?php else: ?>
        <ul class="filas">
            <?php foreach ($accesos as $a): ?>
                <?php $r = texto_resultado($a['resultado']); ?>
            <li>
                <div class="fila-texto">
                    <span class="fila-principal"><?= htmlspecialchars($a['trastero']) ?></span>
                    <span class="fila-sec"><?= fecha_es($a['fecha']) ?></span>
                </div>
                <span class="pill <?= $r['clase'] ?>"><?= $r['texto'] ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
