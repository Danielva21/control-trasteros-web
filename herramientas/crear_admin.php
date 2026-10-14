<?php
/* #########################################################
   herramientas/crear_admin.php — CREAR LA CUENTA DE ADMINISTRADOR

   Los administradores NO se pueden crear desde la web (por
   seguridad). Se crean con esta herramienta, en la TERMINAL,
   desde la carpeta TRASTEROS_WEB:

       php herramientas/crear_admin.php "Nombre" email@empresa.com "contraseña"

   (En Windows con XAMPP, en vez de "php": C:/xampp/php/php.exe)

   La contraseña se guarda cifrada (password_hash), como el resto.
   Si ese email ya existe, NO se cambia nada.
   ######################################################### */


if (PHP_SAPI !== 'cli') {
    exit('Esta herramienta se ejecuta desde la terminal.');
}

require __DIR__ . '/../web/includes/config.php';

// $argv = lo que se escribe detrás del nombre del archivo en la terminal
// ($argv[0] es el propio archivo; luego nombre, email y contraseña)
if (count($argv) !== 4) {
    exit("Uso:  php herramientas/crear_admin.php \"Nombre\" email@empresa.com \"contraseña\"\n");
}

$nombre   = trim($argv[1]);
$email    = strtolower(trim($argv[2]));
$password = $argv[3];

if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("El nombre no puede estar vacío y el email tiene que ser válido.\n");
}
if (mb_strlen($password) < 8) {
    exit("La contraseña tiene que tener al menos 8 caracteres.\n");
}

try {
    $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')")
        ->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);

    echo "Administrador creado: $nombre <$email>. Ya puede entrar en la web.\n";

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        exit("Ya existe un usuario con el email $email. No se ha cambiado nada.\n");
    }
    throw $e;
}
