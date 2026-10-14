// =========================================================
// inicio.js — JavaScript de la zona del cliente
//   1. Abrir trasteros (estado en directo + "mantén pulsado")
//   2. Menú lateral
//   3. Ventanas emergentes
// =========================================================


// =========================================================
// 1. ABRIR TRASTEROS
//
// Cómo funciona:
//   - Cada 3 segundos pregunta a ../api/estado.php el estado REAL de
//     sus trasteros (ese archivo pregunta a Node-RED, que lee el PLC)
//     y actualiza la píldora y el botón de cada tarjeta.
//   - Para abrir hay que MANTENER PULSADO el botón 1 segundo (así no se
//     abre por un toque sin querer). Al completarse, se manda la orden a
//     ../api/abrir.php, que comprueba permisos y avisa a Node-RED.
//
// Solo hace algo si en la página hay tarjetas de trastero (la portada).
// =========================================================

const tarjetas = document.querySelectorAll('.puerta[data-id]');

// Cada cuánto preguntar el estado, y cuánto hay que mantener pulsado (en milisegundos)
const CADA_CUANTO_MS   = 3000;
const MANTENER_PULSADO = 1000;

// El token CSRF que PHP dejó en <meta name="csrf"> (api/abrir.php lo exige)
const metaCsrf  = document.querySelector('meta[name="csrf"]');
const tokenCsrf = metaCsrf ? metaCsrf.content : '';


// ---------------------------------------------------------
// 1.1 Cómo se ve cada estado: texto y color de la píldora,
//     texto del botón, si se puede pulsar y clase extra del botón.
//     (Los estados vienen de api/estado.php)
// ---------------------------------------------------------
const ASPECTO = {
    cerrada:      { pill: 'Cerrada',      clasePill: 'pill-ok',     boton: 'Mantén pulsado para abrir', activo: true,  claseBoton: '' },
    abriendo:     { pill: 'Abriendo…',    clasePill: 'pill-aviso',  boton: 'Abriendo…',                 activo: false, claseBoton: 'btn-abierta' },
    abierta:      { pill: 'Abierta',      clasePill: 'pill-aviso',  boton: 'Abierta',                   activo: false, claseBoton: 'btn-abierta' },
    sin_conexion: { pill: 'Sin conexión', clasePill: 'pill-error',  boton: 'Sin conexión',              activo: false, claseBoton: 'btn-sin-conexion' },
    desconocido:  { pill: 'Sin datos',    clasePill: 'pill-neutra', boton: 'Sin datos del PLC',         activo: false, claseBoton: '' },
};


// ---------------------------------------------------------
// 1.2 pintarEstado(tarjeta, estado, fallo)
//     Cambia la píldora y el botón de UNA tarjeta.
// ---------------------------------------------------------
function pintarEstado(tarjeta, estado, fallo) {
    const aspecto = ASPECTO[estado] || ASPECTO.desconocido;
    const pill    = tarjeta.querySelector('.puerta-estado');
    const boton   = tarjeta.querySelector('.btn-abrir');

    // Píldora: quitar el color anterior y poner el nuevo
    pill.className   = 'pill puerta-estado ' + aspecto.clasePill;
    pill.textContent = fallo ? aspecto.pill + ' · fallo' : aspecto.pill;

    // Botón (si se está manteniendo pulsado, no lo tocamos para no cortar la pulsación)
    if (!boton.classList.contains('pulsando')) {
        boton.disabled = !aspecto.activo;
        boton.classList.remove('btn-abierta', 'btn-sin-conexion');
        if (aspecto.claseBoton) {
            boton.classList.add(aspecto.claseBoton);
        }
        boton.querySelector('.btn-abrir-texto').textContent = aspecto.boton;
    }
}


// ---------------------------------------------------------
// 1.3 actualizarEstados(): pregunta a api/estado.php y pinta todo
//     async / await = "espera a que llegue la respuesta" sin bloquear la página.
//     try / catch   = si algo falla (sin red, servidor caído…), no romperse.
// ---------------------------------------------------------
async function actualizarEstados() {
    try {
        const respuesta = await fetch('../api/estado.php', { cache: 'no-store' });

        // 401 = la sesión ya no vale (caducada o el admin le ha suspendido) → al login
        if (respuesta.status === 401) {
            window.location.href = '../index.php';
            return;
        }

        const datos = await respuesta.json();

        tarjetas.forEach(function (tarjeta) {
            const info = datos.puertas[tarjeta.dataset.id];
            if (info) {
                pintarEstado(tarjeta, info.estado, info.fallo);
            }
        });

    } catch (error) {
        // No hemos podido ni hablar con NUESTRO servidor → todo "Sin conexión"
        tarjetas.forEach(function (tarjeta) {
            pintarEstado(tarjeta, 'sin_conexion', false);
        });
    }
}


// ---------------------------------------------------------
// 1.4 abrir(tarjeta): manda la orden a api/abrir.php
//     FormData = los datos como si fueran un formulario (puerta_id + csrf)
// ---------------------------------------------------------
async function abrir(tarjeta) {
    const mensaje = tarjeta.querySelector('.puerta-mensaje');
    mensaje.hidden = true;

    // Mientras llega la respuesta, ya enseñamos "Abriendo…"
    pintarEstado(tarjeta, 'abriendo', false);

    const datos = new FormData();
    datos.append('puerta_id', tarjeta.dataset.id);
    datos.append('csrf', tokenCsrf);

    try {
        const respuesta = await fetch('../api/abrir.php', { method: 'POST', body: datos });
        const resultado = await respuesta.json();

        if (!resultado.ok) {
            // No se ha abierto: enseñar el motivo que da el servidor
            mensaje.textContent = resultado.mensaje;
            mensaje.hidden = false;
        }
    } catch (error) {
        mensaje.textContent = 'No se ha podido enviar la orden. Recarga la página e inténtalo de nuevo.';
        mensaje.hidden = false;
    }

    // Sea como sea, pedir el estado real enseguida
    actualizarEstados();
}


// ---------------------------------------------------------
// 1.5 "Mantén pulsado para abrir"
//     pointerdown = se empieza a pulsar (dedo o ratón)
//     pointerup / pointerleave / pointercancel = se suelta o se sale del botón
//     Al empezar: clase "pulsando" (el CSS llena la barra) y un temporizador
//     de 1 s. Si se suelta antes, se cancela. Si llega a 1 s, se abre.
// ---------------------------------------------------------
tarjetas.forEach(function (tarjeta) {

    const boton = tarjeta.querySelector('.btn-abrir');
    let temporizador = null;

    function empezar(evento) {
        if (boton.disabled || temporizador !== null) {
            return;
        }
        evento.preventDefault();   // en el móvil, que no salte el menú de "copiar/seleccionar"
        boton.classList.add('pulsando');
        boton.querySelector('.btn-abrir-texto').textContent = 'Sigue pulsando…';

        temporizador = setTimeout(function () {
            temporizador = null;
            boton.classList.remove('pulsando');
            abrir(tarjeta);
        }, MANTENER_PULSADO);
    }

    function cancelar() {
        if (temporizador !== null) {
            clearTimeout(temporizador);   // se soltó antes de tiempo: no abrir
            temporizador = null;
            boton.classList.remove('pulsando');
            boton.querySelector('.btn-abrir-texto').textContent = ASPECTO.cerrada.boton;
        }
    }

    boton.addEventListener('pointerdown', empezar);
    boton.addEventListener('pointerup', cancelar);
    boton.addEventListener('pointerleave', cancelar);
    boton.addEventListener('pointercancel', cancelar);

    // Con teclado (accesibilidad): mantener Enter o Espacio.
    // evento.repeat = la tecla se está repitiendo por estar mantenida: ignorarlo.
    boton.addEventListener('keydown', function (evento) {
        if ((evento.key === 'Enter' || evento.key === ' ') && !evento.repeat) {
            empezar(evento);
        }
    });
    boton.addEventListener('keyup', cancelar);

    // Que el menú del botón derecho / de la pulsación larga del móvil no aparezca
    boton.addEventListener('contextmenu', function (evento) {
        evento.preventDefault();
    });
});


// ---------------------------------------------------------
// 1.6 Arrancar: preguntar ya, y después cada 3 segundos
// ---------------------------------------------------------
if (tarjetas.length > 0) {
    actualizarEstados();
    setInterval(actualizarEstados, CADA_CUANTO_MS);
}



// =========================================================
// 2. MENÚ LATERAL: abrir y cerrar
// Todo se basa en UNA clase en el <body>: "menu-abierto".
// El CSS ya sabe cómo se ve el menú con y sin esa clase;
// aquí solo la ponemos o la quitamos.
// =========================================================

// Las 3 piezas que usamos (querySelector = la PRIMERA que encuentre)
const botonMenu   = document.querySelector('.btn-menu');        // hamburguesa
const botonCerrar = document.querySelector('.btn-cerrar-menu'); // la X del menú
const fondoMenu   = document.querySelector('.menu-fondo');      // capa oscura de detrás

function abrirMenu() {
    document.body.classList.add('menu-abierto');
}

function cerrarMenu() {
    document.body.classList.remove('menu-abierto');
}

botonMenu.addEventListener('click', abrirMenu);
botonCerrar.addEventListener('click', cerrarMenu);
fondoMenu.addEventListener('click', cerrarMenu);   // tocar fuera del menú = cerrar



// =========================================================
// 3. VENTANAS EMERGENTES (<dialog>) — igual que en admin.js
// Un botón con data-dialogo="id" abre esa ventana;
// un botón con data-cerrar cierra la ventana en la que está.
// (Lo usa "Cambiar contraseña" en Mi perfil.)
// =========================================================

document.querySelectorAll('[data-dialogo]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        document.getElementById(boton.dataset.dialogo).showModal();
    });
});

document.querySelectorAll('[data-cerrar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        boton.closest('dialog').close();
    });
});



// =========================================================
// 4. SALUDO SEGÚN LA HORA
// Cambia "Buenos días" de la portada según la hora del móvil:
//   06:00 – 13:59 → Buenos días
//   14:00 – 21:59 → Buenas tardes
//   22:00 – 05:59 → Buenas noches
// Solo hace algo en la portada (donde existe #saludo-texto).
// =========================================================

function saludoSegunHora() {
    // new Date() = ahora mismo; getHours() = la hora, de 0 a 23
    const hora = new Date().getHours();

    if (hora >= 6 && hora < 14) {
        return 'Buenos días';
    }
    if (hora >= 14 && hora < 22) {
        return 'Buenas tardes';
    }
    return 'Buenas noches';   // de 22 a 23 y de 0 a 5
}

const textoSaludo = document.getElementById('saludo-texto');
if (textoSaludo) {
    textoSaludo.textContent = saludoSegunHora();
}
