<?php
/* #########################################################
   includes/solo_admin.php — PROTECCIÓN DE LAS PÁGINAS DE admin/

   Va lo PRIMERO de cada página de admin/, antes de consultar
   nada a la BD:
       require_once __DIR__ . '/../includes/solo_admin.php';

   Comprueba:
     1. que haya hecho login (hay usuario en la sesión)
     2. que su rol sea 'admin'
   Si falla cualquiera de las dos → al login, y no se ejecuta
   NADA más de la página (exit).
   ######################################################### */

require_once __DIR__ . '/sesion.php';   // arranca la sesión con cookie segura

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
