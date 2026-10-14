<?php
/* #########################################################
index.php — PANTALLA DE LOGIN

Este archivo tiene dos partes:
PARTE 1 (PHP): sesión, usuarios de prueba y lógica del login.
PARTE 2 (HTML): lo que se ve en pantalla.
   ######################################################### */



/* =========================================================
1.1 SESIÓN
Arranca (o recupera) la sesión. Tiene que ir ANTES de
cualquier HTML, porque usa una cookie y las cookies se
envían antes que el contenido.
   ========================================================= */

require_once __DIR__ . '/includes/sesion.php';   // arranca la sesión con cookie segura



/* =========================================================
1.2 CONEXIÓN A LA BASE DE DATOS
Los usuarios ya NO están en un array: están en la tabla
"usuarios" de MariaDB. config.php crea la variable $pdo
(la conexión). Contraseña de prueba de todos: 1234.
   ========================================================= */

require __DIR__ . '/includes/config.php';

// Token CSRF (protección de formularios, ver includes/csrf.php)
require_once __DIR__ . '/includes/csrf.php';



/* =========================================================
1.3 LÓGICA DEL LOGIN
El login se muestra siempre, aunque ya haya sesión.
"Recordar la contraseña" lo hace el gestor de contraseñas
del navegador (gracias a los autocomplete del formulario).
   ========================================================= */

$error = '';   // mensaje de error para el HTML (vacío = no hay error)
$email = '';   // para volver a rellenar el campo si falla

// ¿Se ha pulsado "Entrar"? (el formulario envía por POST; abrir la página normal es GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lo primero: comprobar que el formulario viene de NUESTRA página
    csrf_comprobar();

    // Lo que ha escrito el usuario. "?? ''" = si no llega, cadena vacía.
    // trim() quita espacios al principio y al final del email.
    // strtolower() lo pasa a minúsculas: "Dani@Gmail.com" = "dani@gmail.com"
    // (los emails no distinguen mayúsculas; en el array/BD se guardan en minúsculas).
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';


    // --- Paso A: buscar el usuario por email en la BD ---
    // SENTENCIA PREPARADA (obligatorio, ver CLAUDE.md):
    //   1. prepare(): mandamos la consulta con un HUECO "?" en vez del email.
    //   2. execute([...]): mandamos el email APARTE, para rellenar el hueco.
    // Así, aunque alguien escriba algo malicioso en el campo email
    // (ej: ' OR 1=1 --), la BD lo trata como un texto a buscar,
    // NUNCA como parte de la consulta. Esto evita la "inyección SQL".
    $consulta = $pdo->prepare(
        'SELECT id, nombre, email, password_hash, rol, activo
           FROM usuarios
          WHERE email = ?
          LIMIT 1'
    );
    $consulta->execute([$email]);

    // fetch() = dame UNA fila. Si no hay ninguna, devuelve false.
    $encontrado = $consulta->fetch();


    // --- Paso B: comprobar que existe, está activo y la contraseña coincide ---
    // (int) convierte a número: la BD devuelve "activo" como texto "1".
    if ($encontrado !== false
        && (int) $encontrado['activo'] === 1
        && password_verify($password, $encontrado['password_hash'])) {

        // ✅ Login correcto
        session_regenerate_id(true);   // ID de sesión NUEVO al entrar (evita "fijación de sesión")

        // Apuntamos en la BD cuándo ha entrado (columna ultimo_acceso).
        // NOW() = fecha y hora actuales, las pone MariaDB.
        $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')
            ->execute([$encontrado['id']]);

        // Guardamos en la sesión QUIÉN es. Nunca la contraseña.
        $_SESSION['usuario_id'] = $encontrado['id'];
        $_SESSION['nombre']     = $encontrado['nombre'];
        $_SESSION['email']      = $encontrado['email'];
        $_SESSION['rol']        = $encontrado['rol'];


        // --- Paso C: redirigir según el rol ---
        if ($encontrado['rol'] === 'admin') {
            header('Location: admin/');     // empresario → panel de administración
        } else {
            header('Location: usuario/');   // cliente → sus puertas
        }
        exit;   // imprescindible: si no, PHP seguiría pintando el login

    } else {
        // ❌ Algo ha fallado. Mismo mensaje para email o contraseña:
        // así nadie puede averiguar qué emails existen.
        $error = 'Email o contraseña incorrectos.';
    }
}

?>



<!-- #########################################################
    PARTE 2: HTML
    ######################################################### -->

<!DOCTYPE html>
<html lang="es">

<!-- =========================================================
    CABECERA (no se ve: título, fuente y estilos)
    ========================================================= -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso · Control de Trasteros</title>

    <!-- Fuente Plus Jakarta Sans (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Modo claro/oscuro (el que el usuario eligió dentro de la web, o el del sistema) -->
    <script src="assets/js/tema.js"></script>

    <!-- Estilos: base.css (comunes a toda la web) y login.css (solo esta pantalla) -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>



<!-- =========================================================
    CUERPO (lo que se ve)
    ========================================================= -->
<body>

<!-- Contenedor general: dos columnas en PC, una en móvil -->
<div class="login">


    <!-- ===== BLOQUE 1: COLUMNA IZQUIERDA (marca + formulario + ayuda) ===== -->
    <div class="login-panel">

        <!-- Foto del producto muy difuminada detrás del formulario (se pinta con CSS) -->
        <div class="login-fondo"></div>


        <!-- 1.1 Marca: icono + nombre de la app + nombre del cliente -->
        <div class="marca">
            <div class="marca-icono">
                <!-- Icono candado (Tabler Icons, trazo fino) -->
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <div class="marca-texto">
                <span class="marca-nombre">Control de Trasteros</span>
                <span class="marca-cliente">[Nombre del cliente]</span>
            </div>
        </div>


        <!-- 1.2 Tarjeta con el formulario de acceso -->
        <form class="login-form" method="post" action="index.php">

            <!-- Token CSRF oculto (ver includes/csrf.php) -->
            <?php csrf_campo(); ?>

            <!-- Título y subtítulo -->
            <div class="login-cabecera">
                <h1>Accede a tu trastero</h1>
                <p>Entra con el usuario que te ha dado la instalación.</p>
            </div>

            <!-- Mensaje de error: solo se pinta si $error tiene texto -->
            <?php if ($error !== ''): ?>
                <p class="login-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <!-- Campo email (si el login falla, se vuelve a rellenar con lo escrito) -->
            <div class="campo">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="tu@email.com" autocomplete="username" required
                    value="<?= htmlspecialchars($email) ?>">
            </div>

            <!-- Campo contraseña + enlace "¿La has olvidado?" en la misma línea que la etiqueta -->
            <div class="campo">
                <div class="campo-fila">
                    <label for="password">Contraseña</label>
                    <a href="#" class="enlace-olvido">¿La has olvidado?</a>
                </div>
                <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
            </div>

            <!-- Mantener sesión iniciada (todavía no hace nada) -->
            <div class="campo-check">
                <input id="recordar" name="recordar" type="checkbox">
                <label for="recordar">Mantener la sesión iniciada</label>
            </div>

            <!-- Botón de entrar: envía el formulario (POST) a esta misma página -->
            <button type="submit" class="btn btn-primario">Entrar</button>

        </form>


        <!-- 1.3 Ayuda al pie -->
        <p class="login-ayuda">¿Problemas para entrar? <a href="#">Contacta con soporte</a></p>

    </div>


    <!-- ===== BLOQUE 2: COLUMNA DERECHA (foto del producto) — solo en PC ===== -->
    <div class="login-foto">

        <!-- 2.1 Foto del producto (de momento la imagen de prueba .svg) -->
        <img src="assets/img/producto.svg" alt="Cuadro eléctrico con el PLC y la cerradura del trastero">

        <!-- 2.2 Eslogan sobre la foto -->
        <div class="login-eslogan">
            <span class="eslogan-titulo">Abre tu trastero desde el móvil. Sin llaves.</span>
            <span class="eslogan-texto">Acceso 24 h, historial de aperturas y avisos al momento.</span>
        </div>

    </div>


</div>

</body>
</html>
