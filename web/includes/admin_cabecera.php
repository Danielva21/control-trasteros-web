<?php
/* #########################################################
   includes/admin_cabecera.php — PARTE DE ARRIBA de TODAS las
   páginas del panel de administración.

   Contiene:
     1. La comprobación de que es admin (seguridad).
     2. El <head> (título, fuente, estilos).
     3. El sidebar (menú oscuro).
     4. La barra superior (título de la página + admin conectado).
   Deja ABIERTO el <main>: el contenido lo pone cada página.

   CÓMO SE USA, en cada página de admin/:

       $titulo        = 'Usuarios';    // lo que sale en el título y las migas
       $pagina_activa = 'usuarios';    // qué enlace del sidebar se resalta
       require __DIR__ . '/../includes/admin_cabecera.php';

       ... contenido de la página ...

       require __DIR__ . '/../includes/admin_pie.php';

   Las rutas del HTML ("../assets/...") son relativas a la PÁGINA
   que incluye este archivo (que está en admin/), no a includes/.
   ######################################################### */



/* =========================================================
   1. SESIÓN: solo entra un ADMIN
   La comprobación está en solo_admin.php. Cada página ya lo
   carga al principio (antes de consultar la BD); aquí se
   vuelve a pedir por si alguna página se olvidara.
   require_ONCE = si ya se cargó antes, no lo carga otra vez.
   ========================================================= */

require_once __DIR__ . '/solo_admin.php';

// Funciones comunes (mensajes, fechas…) y token CSRF para los formularios
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/csrf.php';



/* =========================================================
   2. DATOS DEL ADMIN (vienen de la sesión)
   ========================================================= */

$admin_nombre  = $_SESSION['nombre'];
$admin_inicial = mb_strtoupper(mb_substr($admin_nombre, 0, 1));



/* =========================================================
   3. ENLACES DEL SIDEBAR
   Un array con los grupos y sus enlaces. Abajo, un foreach
   los pinta todos. Así, añadir una página al menú = añadir
   UNA línea aquí, en vez de copiar HTML.

   Cada enlace: 'clave' => ['Texto que se ve', 'archivo.php']
   La 'clave' es la que cada página pone en $pagina_activa.
   ========================================================= */

$menu_admin = [
    'Gestión' => [
        'dashboard'   => ['Dashboard',            'index.php'],
        'usuarios'    => ['Usuarios',             'usuarios.php'],
        'trasteros'   => ['Trasteros',            'trasteros.php'],
        'permisos'    => ['Permisos',             'permisos.php'],
        'historial'   => ['Historial de accesos', 'historial.php'],
    ],
    'Monitorización' => [
        'actividad'   => ['Actividad (Grafana)',  'actividad.php'],
        'red'         => ['Estado de red y PLC',  'red.php'],
    ],
    'Empresa' => [
        'comunicados' => ['Comunicados',          'comunicados.php'],
        'ajustes'     => ['Ajustes',              'ajustes.php'],
    ],
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
    <title><?= htmlspecialchars($titulo) ?> · Administración · Control de Trasteros</title>

    <!-- Fuente Plus Jakarta Sans (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Modo claro/oscuro: en el <head> y sin "defer", para aplicarlo ANTES de pintar -->
    <script src="../assets/js/tema.js"></script>

    <!-- Estilos: base.css (comunes a toda la web) y admin.css (solo el panel) -->
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>



<!-- =========================================================
     CUERPO (lo que se ve)
     ========================================================= -->
<body>

<!-- Contenedor general: sidebar a la izquierda + zona principal a la derecha -->
<div class="admin">


    <!-- Fondo oscuro detrás del sidebar cuando se abre en MÓVIL (tocarlo = cerrar).
         En PC no se ve nunca. -->
    <div class="sidebar-fondo"></div>


    <!-- ===== BLOQUE 1: SIDEBAR (menú lateral oscuro) ===== -->
    <nav class="sidebar" aria-label="Menú de administración">

        <!-- 1.1 Marca: icono + nombre -->
        <div class="sidebar-marca">
            <div class="marca-icono">
                <!-- Icono candado -->
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <div class="sidebar-marca-texto">
                <span class="sidebar-marca-nombre">Control de Trasteros</span>
                <span class="sidebar-marca-sub">Administración</span>
            </div>
        </div>

        <!-- 1.2 Grupos y enlaces, pintados desde el array $menu_admin.
             Primer foreach: cada GRUPO (Gestión, Monitorización, Empresa).
             Segundo foreach: cada ENLACE de ese grupo.
             Si la clave del enlace coincide con $pagina_activa → clase "activo". -->
        <?php foreach ($menu_admin as $grupo => $enlaces): ?>
            <span class="sidebar-grupo"><?= htmlspecialchars($grupo) ?></span>

            <?php foreach ($enlaces as $clave => $enlace): ?>
                <?php if ($clave === $pagina_activa): ?>
                    <a href="<?= $enlace[1] ?>" class="sidebar-enlace activo" aria-current="page"><?= htmlspecialchars($enlace[0]) ?></a>
                <?php else: ?>
                    <a href="<?= $enlace[1] ?>" class="sidebar-enlace"><?= htmlspecialchars($enlace[0]) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <!-- 1.3 Abajo del todo: ir a la vista de usuario y cerrar sesión -->
        <div class="sidebar-pie">
            <a href="../usuario/" class="sidebar-enlace">Ver como usuario</a>
            <a href="../logout.php" class="sidebar-enlace">Cerrar sesión</a>
        </div>

    </nav>


    <!-- ===== BLOQUE 2: ZONA PRINCIPAL (barra superior + contenido) ===== -->
    <div class="admin-principal">


        <!-- 2.1 Barra superior: título de la página + avatar del admin -->
        <header class="admin-topbar">

            <!-- Hamburguesa: SOLO se ve en móvil/tablet (en PC el sidebar ya está siempre visible) -->
            <button type="button" class="btn-menu" aria-label="Abrir menú">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <!-- Migas de pan ("dónde estoy") + título de la página -->
            <div class="admin-titulo">
                <span class="admin-migas">Inicio / <?= htmlspecialchars($titulo) ?></span>
                <h1><?= htmlspecialchars($titulo) ?></h1>
            </div>

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

            <!-- Admin conectado -->
            <div class="admin-usuario">
                <div class="avatar"><?= htmlspecialchars($admin_inicial) ?></div>
                <div class="admin-usuario-texto">
                    <span class="admin-usuario-nombre"><?= htmlspecialchars($admin_nombre) ?></span>
                    <span class="admin-usuario-rol">Administrador</span>
                </div>
            </div>

        </header>


        <!-- 2.2 Contenido: lo pone cada página. Este <main> lo cierra admin_pie.php -->
        <main class="admin-contenido">

            <!-- Mensaje de la última acción ("Usuario creado", errores…). Sale una sola vez. -->
            <?php $mensaje = mensaje_sacar(); ?>
            <?php if ($mensaje !== null): ?>
                <p class="flash flash-<?= $mensaje['tipo'] ?>" role="status"><?= htmlspecialchars($mensaje['texto']) ?></p>
            <?php endif; ?>
