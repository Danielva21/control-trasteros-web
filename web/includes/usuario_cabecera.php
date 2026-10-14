<?php
/* #########################################################
   includes/usuario_cabecera.php — PARTE DE ARRIBA de TODAS las
   páginas del cliente (carpeta usuario/).

   Contiene: <head>, barra superior y menú lateral.
   Deja ABIERTO el <main>: el contenido lo pone cada página.

   CÓMO SE USA, en cada página de usuario/:

       require_once __DIR__ . '/../includes/solo_usuario.php';  // protección + $usuario
       ... consultas de la página ...
       $titulo        = 'Historial';     // título de la pestaña
       $pagina_activa = 'historial';     // qué enlace del menú se resalta
       require __DIR__ . '/../includes/usuario_cabecera.php';

       ... contenido ...

       require __DIR__ . '/../includes/usuario_pie.php';
   ######################################################### */


// Por si alguna página se olvida de cargarlo antes
require_once __DIR__ . '/solo_usuario.php';

// Funciones comunes (mensajes, fechas…) y token CSRF para los formularios
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/csrf.php';



/* =========================================================
   ENLACES DEL MENÚ LATERAL
   'clave' => ['Texto', 'archivo.php']
   La 'clave' es la que cada página pone en $pagina_activa.
   ========================================================= */

$menu_usuario = [
    'inicio'      => ['Inicio',               'index.php'],
    'trasteros'   => ['Mis trasteros',        'trasteros.php'],
    'historial'   => ['Historial de accesos', 'historial.php'],
    'comunicados' => ['Comunicados',          'comunicados.php'],
    'normas'      => ['Horarios y normas',    'normas.php'],
    'soporte'     => ['Soporte / Contacto',   'soporte.php'],
    'perfil'      => ['Mi perfil',            'perfil.php'],
];

?>
<!DOCTYPE html>
<html lang="es">

<!-- =========================================================
     CABECERA (no se ve: título, fuente y estilos)
     ========================================================= -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> · Control de Trasteros</title>

    <!-- Token CSRF para el JavaScript: inicio.js lo lee de aquí y lo envía
         al pedir abrir una puerta (api/abrir.php lo comprueba) -->
    <meta name="csrf" content="<?= htmlspecialchars(csrf_token()) ?>">

    <!-- Fuente Plus Jakarta Sans (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Modo claro/oscuro: va en el <head> y SIN "defer", para aplicar el tema
         ANTES de pintar la página (si no, se vería un fogonazo blanco) -->
    <script src="../assets/js/tema.js"></script>

    <!-- Estilos: base.css (comunes) e inicio.css (zona del cliente).
         "../" = subir a la carpeta web/ para llegar a assets/ -->
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/inicio.css">
</head>



<!-- =========================================================
     CUERPO (lo que se ve)
     ========================================================= -->
<body>

<!-- ===== BARRA SUPERIOR ===== -->
<header class="topbar">

    <!-- Botón del menú lateral (hamburguesa) -->
    <button type="button" class="btn-icono btn-menu" aria-label="Abrir menú">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Nombre de la app -->
    <span class="topbar-titulo">Control de Trasteros</span>

<!-- Botón de modo claro / oscuro (assets/js/tema.js). Lleva los dos iconos; el CSS enseña uno -->
    <button type="button" class="btn-tema" data-cambiar-tema aria-label="Cambiar entre modo claro y oscuro">
        <svg class="icono-luna" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
        </svg>
        <svg class="icono-sol" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
        </svg>
    </button>

    <!-- Campana: enlace a Comunicados. El número = comunicados sin leer ($no_leidos,
         calculado en solo_usuario.php). Si no hay ninguno, no se pinta el número. -->
    <a href="comunicados.php" class="btn-icono btn-notificaciones"
       aria-label="<?= $no_leidos > 0 ? 'Comunicados: ' . $no_leidos . ' sin leer' : 'Comunicados' ?>">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>
        <?php if ($no_leidos > 0): ?>
            <!-- Más de 9 → "9+" para que quepa en el círculo -->
            <span class="contador"><?= $no_leidos > 9 ? '9+' : $no_leidos ?></span>
        <?php endif; ?>
    </a>

    <!-- Avatar: inicial del usuario -->
    <div class="avatar"><?= htmlspecialchars($inicial) ?></div>

</header>


<!-- ===== MENÚ LATERAL =====
     Oculto al principio; se abre con la hamburguesa (inicio.js).
     El fondo oscuro sirve para cerrarlo al tocar fuera. -->
<div class="menu-fondo"></div>

<nav class="menu-lateral" aria-label="Menú principal">

    <!-- Cabecera del menú: datos del usuario + botón de cerrar -->
    <div class="menu-cabecera">
        <div class="avatar"><?= htmlspecialchars($inicial) ?></div>
        <div class="menu-usuario">
            <span class="menu-usuario-nombre"><?= htmlspecialchars($usuario['nombre']) ?></span>
            <span class="menu-usuario-email"><?= htmlspecialchars($usuario['email']) ?></span>
        </div>
        <button type="button" class="btn-icono btn-cerrar-menu" aria-label="Cerrar menú">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6L6 18M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Enlaces, pintados desde $menu_usuario. El de la página actual sale resaltado. -->
    <ul class="menu-lista">
        <?php foreach ($menu_usuario as $clave => $enlace): ?>
            <?php if ($clave === $pagina_activa): ?>
                <li><a href="<?= $enlace[1] ?>" class="menu-enlace activo" aria-current="page"><?= htmlspecialchars($enlace[0]) ?></a></li>
            <?php else: ?>
                <li><a href="<?= $enlace[1] ?>" class="menu-enlace"><?= htmlspecialchars($enlace[0]) ?></a></li>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Solo si es admin. OJO: esto solo OCULTA el enlace; admin/ se protege
             en el servidor con solo_admin.php. -->
        <?php if ($usuario['rol'] === 'admin'): ?>
            <li><a href="../admin/" class="menu-enlace">Panel de administración</a></li>
        <?php endif; ?>
    </ul>

    <!-- Cerrar sesión, abajo del todo -->
    <a href="../logout.php" class="menu-enlace menu-salir">Cerrar sesión</a>

</nav>


<!-- ===== CONTENIDO: lo pone cada página. Este <main> lo cierra usuario_pie.php ===== -->
<main class="contenido">

    <!-- Mensaje de la última acción ("Usuario creado", errores…). Sale una sola vez. -->
    <?php $mensaje = mensaje_sacar(); ?>
    <?php if ($mensaje !== null): ?>
        <p class="flash flash-<?= $mensaje['tipo'] ?>" role="status"><?= htmlspecialchars($mensaje['texto']) ?></p>
    <?php endif; ?>
