<?php
/* #########################################################
   usuario/index.php — PORTADA DEL CLIENTE (sus trasteros)

   Lo común de la zona del cliente (protección, <head>, barra
   superior y menú lateral) está en includes/solo_usuario.php,
   usuario_cabecera.php y usuario_pie.php.
   Aquí solo van los DATOS y el CONTENIDO de esta página.
   ######################################################### */



/* =========================================================
   0. PROTECCIÓN + CONEXIÓN + DATOS DEL USUARIO
   Deja preparados $pdo, $usuario y $inicial.
   ========================================================= */

require_once __DIR__ . '/../includes/solo_usuario.php';



/* =========================================================
   1. SUS TRASTEROS (los que puede abrir AHORA MISMO)
     - JOIN: cada permiso se une con su trastero (puertas.id = permisos.puerta_id)
     - es SU permiso                  → pe.usuario_id = ?
     - el trastero está en servicio   → p.activa = 1
     - el permiso no ha caducado      → fecha_fin vacía (indefinido)
                                        o todavía no ha llegado
   Los alias "pe" y "p" son apodos cortos de permisos y puertas.
   ========================================================= */

$consulta = $pdo->prepare(
    'SELECT p.id, p.nombre, p.ubicacion, pe.fecha_fin
       FROM permisos pe
       JOIN puertas p ON p.id = pe.puerta_id
      WHERE pe.usuario_id = ?
        AND p.activa = 1
        AND (pe.fecha_fin IS NULL OR pe.fecha_fin >= NOW())
      ORDER BY p.nombre'
);
$consulta->execute([$usuario['id']]);

// fetchAll() = dame TODAS las filas (un array de arrays)
$puertas = $consulta->fetchAll();



/* =========================================================
   2. ÚLTIMO COMUNICADO SIN LEER
   Solo se enseña en la portada si hay alguno NUEVO para este
   usuario (con un id mayor que el último que vio en la página
   Comunicados). Si ya los ha leído todos, no sale nada.
   fetch() devuelve false si no hay ninguno → el HTML no lo pinta.
   ($no_leidos lo calcula solo_usuario.php: cuántos hay sin leer)
   ========================================================= */

$consulta = $pdo->prepare(
    'SELECT titulo, texto
       FROM comunicados
      WHERE activo = 1
        AND id > COALESCE(?, 0)
      ORDER BY fecha_publicacion DESC, id DESC
      LIMIT 1'
);
$consulta->execute([$usuario['ultimo_comunicado_visto']]);
$comunicado = $consulta->fetch();



/* =========================================================
   3. CABECERA COMÚN
   ========================================================= */

$titulo        = 'Mis trasteros';
$pagina_activa = 'inicio';

require __DIR__ . '/../includes/usuario_cabecera.php';

?>



<!-- #########################################################
     CONTENIDO DE LA PÁGINA (va dentro del <main> de la cabecera)
     ######################################################### -->

    <!-- 1. Tarjeta de saludo -->
    <section class="saludo">
        <!-- "Buenos días" se cambia por "Buenas tardes/noches" según la hora del móvil (inicio.js) -->
        <h1><span id="saludo-texto">Buenos días</span>, <span class="saludo-nombre"><?= htmlspecialchars($usuario['nombre']) ?></span></h1>
        <!-- count() cuenta cuántos trasteros ha devuelto la consulta.
             Operador ternario para el plural: 1 → "trastero", otro número → "trasteros" -->
        <p>Tienes <?= count($puertas) ?> <?= count($puertas) === 1 ? 'trastero disponible' : 'trasteros disponibles' ?></p>
    </section>


    <!-- 2. Sección "TUS TRASTEROS": una tarjeta <article> por trastero -->
    <section class="puertas">
        <h2 class="seccion-titulo">Tus trasteros</h2>

        <!-- Si la consulta no ha devuelto ninguno (sin permisos o todos caducados) -->
        <?php if (count($puertas) === 0): ?>
            <p class="texto-sec">Ahora mismo no tienes ningún trastero activo. Si crees que es un error, contacta con la empresa.</p>
        <?php endif; ?>

        <!-- foreach: repite este bloque <article> UNA VEZ POR CADA trastero.
             En cada vuelta, $puerta contiene los datos de uno. -->
        <?php foreach ($puertas as $puerta): ?>
        <article class="puerta" data-id="<?= $puerta['id'] ?>">
            <div class="puerta-cabecera">
                <div class="puerta-icono">
                    <!-- Icono candado -->
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div class="puerta-info">
                    <!-- htmlspecialchars: escapa el texto para que no se pueda colar HTML ni scripts -->
                    <span class="puerta-nombre"><?= htmlspecialchars($puerta['nombre']) ?></span>
                    <!-- ubicacion puede venir vacía (NULL) de la BD: "?? ''" la convierte en texto vacío -->
                    <span class="puerta-ubicacion"><?= htmlspecialchars($puerta['ubicacion'] ?? '') ?></span>
                </div>
                <!-- Estado: empieza en "Comprobando…" y inicio.js lo cambia por el REAL
                     (pregunta a api/estado.php, que pregunta a Node-RED) -->
                <span class="pill pill-neutra puerta-estado">Comprobando…</span>
            </div>

            <!-- Botón de abrir: deshabilitado hasta saber que la puerta está cerrada y hay conexión.
                 inicio.js: mantener pulsado 1 segundo → api/abrir.php -->
            <button type="button" class="btn btn-primario btn-abrir" disabled>
                <span class="btn-abrir-texto">Comprobando…</span>
            </button>

            <!-- Mensaje de error de la última apertura (oculto si no hay) -->
            <p class="puerta-mensaje" hidden></p>
        </article>
        <?php endforeach; ?>
    </section>


    <!-- 3. Último comunicado SIN LEER. Solo se pinta si hay alguno nuevo.
             Al abrir "Comunicados" se marcan como leídos y deja de salir aquí. -->
    <?php if ($comunicado !== false): ?>
    <section class="aviso">
        <div class="aviso-icono">
            <!-- Icono megáfono -->
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 11l18-5v12L3 14v-3z"></path>
                <path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"></path>
            </svg>
        </div>
        <div class="aviso-texto">
            <h2><?= htmlspecialchars($comunicado['titulo']) ?></h2>
            <p><?= htmlspecialchars($comunicado['texto']) ?></p>
            <!-- Enlace a la página de comunicados (al entrar, se marcan como leídos) -->
            <a href="comunicados.php" class="aviso-enlace">
                <?= $no_leidos > 1 ? 'Ver los ' . $no_leidos . ' comunicados nuevos →' : 'Marcar como leído →' ?>
            </a>
        </div>
    </section>
    <?php endif; ?>


<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
