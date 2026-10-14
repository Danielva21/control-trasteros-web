<?php
/* #########################################################
   usuario/comunicados.php — COMUNICADOS DE LA EMPRESA

   Todos los avisos publicados (activos), el más nuevo arriba.
   Al abrir esta página se marcan como LEÍDOS (desaparece el
   número de la campana y el aviso de la portada).
   ######################################################### */



// 0. Protección + conexión + datos del usuario ($pdo, $usuario, $inicial)
require_once __DIR__ . '/../includes/solo_usuario.php';
require_once __DIR__ . '/../includes/funciones.php';



// 1. Comunicados publicados (los archivados no los ve el cliente).
//    "es_nuevo" = 1 si su id es mayor que el último que había visto
//    (se calcula ANTES de marcarlos como leídos en el paso 2).
$consulta = $pdo->prepare(
    'SELECT titulo, texto, fecha_publicacion,
            (id > COALESCE(?, 0)) AS es_nuevo
       FROM comunicados
      WHERE activo = 1
      ORDER BY fecha_publicacion DESC, id DESC'
);
$consulta->execute([$usuario['ultimo_comunicado_visto']]);
$comunicados = $consulta->fetchAll();


// 2. Marcarlos como leídos: apuntar el id del comunicado más nuevo que existe.
//    La subconsulta (SELECT MAX(id) …) busca ese id dentro de la misma orden.
$pdo->prepare('UPDATE usuarios SET ultimo_comunicado_visto = (SELECT MAX(id) FROM comunicados) WHERE id = ?')
    ->execute([$usuario['id']]);

// La cabecera se pinta después: que la campana ya salga sin número en esta página
$no_leidos = 0;



// 3. Cabecera común
$titulo        = 'Comunicados';
$pagina_activa = 'comunicados';
require __DIR__ . '/../includes/usuario_cabecera.php';

?>

    <h1 class="pagina-titulo">Comunicados</h1>

    <?php if (count($comunicados) === 0): ?>
        <section class="bloque">
            <p class="texto-sec">No hay comunicados ahora mismo.</p>
        </section>
    <?php endif; ?>

    <!-- Un bloque por comunicado -->
    <?php foreach ($comunicados as $c): ?>
    <section class="bloque">
        <h2>
            <?= htmlspecialchars($c['titulo']) ?>
            <?php if ((int) $c['es_nuevo'] === 1): ?>
                <span class="pill pill-aviso">Nuevo</span>
            <?php endif; ?>
        </h2>
        <!-- nl2br: respeta los saltos de línea del texto (después de escapar) -->
        <p><?= nl2br(htmlspecialchars($c['texto'])) ?></p>
        <p class="fila-sec"><?= fecha_es($c['fecha_publicacion'], false) ?></p>
    </section>
    <?php endforeach; ?>

<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
