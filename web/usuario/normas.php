<?php
/* #########################################################
   usuario/normas.php — HORARIOS Y NORMAS

   Página de texto fijo. Lo que va entre [corchetes] lo tiene
   que rellenar cada instalación (más adelante podría salir
   de la BD y editarse desde Ajustes del admin).
   ######################################################### */



// 0. Protección + conexión + datos del usuario
require_once __DIR__ . '/../includes/solo_usuario.php';



// 1. Cabecera común
$titulo        = 'Horarios y normas';
$pagina_activa = 'normas';
require __DIR__ . '/../includes/usuario_cabecera.php';

?>

    <h1 class="pagina-titulo">Horarios y normas</h1>

    <section class="bloque">
        <h2>Horario</h2>
        <p>Puedes acceder a tu trastero <strong>las 24 horas, todos los días del año</strong>.</p>
    </section>

    <section class="bloque">
        <h2>Uso del acceso</h2>
        <ul class="filas">
            <li><span>El acceso es <strong>personal</strong>: no compartas tu usuario ni tu contraseña.</span></li>
            <li><span>Cada apertura queda registrada en tu historial de accesos.</span></li>
            <li><span>Si la puerta no se abre o ves "Sin conexión", contacta con soporte.</span></li>
        </ul>
    </section>

    <section class="bloque">
        <h2>Normas de la instalación</h2>
        <p class="texto-sec">[Normas de la instalación: objetos no permitidos, limpieza, uso de zonas comunes…]</p>
    </section>

<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
