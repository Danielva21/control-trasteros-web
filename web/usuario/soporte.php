<?php
/* #########################################################
   usuario/soporte.php — SOPORTE / CONTACTO

   Datos de contacto de la empresa. Lo que va entre [corchetes]
   lo rellena cada instalación. (Un formulario para mandar un
   mensaje desde aquí sería del bloque A.)
   ######################################################### */



// 0. Protección + conexión + datos del usuario
require_once __DIR__ . '/../includes/solo_usuario.php';



// 1. Cabecera común
$titulo        = 'Soporte';
$pagina_activa = 'soporte';
require __DIR__ . '/../includes/usuario_cabecera.php';

?>

    <h1 class="pagina-titulo">Soporte / Contacto</h1>

    <section class="bloque">
        <h2>Contacta con nosotros</h2>
        <ul class="filas">
            <li>
                <span class="fila-sec">Teléfono</span>
                <span class="fila-principal">[TELÉFONO]</span>
            </li>
            <li>
                <span class="fila-sec">Email</span>
                <span class="fila-principal">[EMAIL DE CONTACTO]</span>
            </li>
            <li>
                <span class="fila-sec">Horario de atención</span>
                <span class="fila-principal">[HORARIO]</span>
            </li>
        </ul>
    </section>

    <section class="bloque">
        <h2>Si tu puerta no se abre</h2>
        <ul class="filas">
            <li><span>Comprueba que tienes conexión a internet en el móvil.</span></li>
            <li><span>Recarga la página e inténtalo de nuevo.</span></li>
            <li><span>Si aparece "Sin conexión", el problema es de la instalación: llámanos.</span></li>
        </ul>
    </section>

<?php require __DIR__ . '/../includes/usuario_pie.php'; ?>
