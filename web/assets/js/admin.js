// =========================================================
// admin.js — JavaScript del panel de administración
// =========================================================


// =========================================================
// SIDEBAR EN MÓVIL: abrir y cerrar
// Igual que el menú del usuario: todo se basa en la clase
// "menu-abierto" del <body>. El CSS hace el resto.
// (En PC la hamburguesa ni se ve, así que esto no hace nada.)
// =========================================================

// 1. Las piezas que usamos
const botonMenu  = document.querySelector('.btn-menu');       // hamburguesa
const fondoMenu  = document.querySelector('.sidebar-fondo');  // capa oscura de detrás

// 2. Abrir y cerrar
function abrirMenu() {
    document.body.classList.add('menu-abierto');
}

function cerrarMenu() {
    document.body.classList.remove('menu-abierto');
}

// 3. Qué hace cada pieza al hacer clic
botonMenu.addEventListener('click', abrirMenu);
fondoMenu.addEventListener('click', cerrarMenu);   // tocar fuera del menú = cerrar


// =========================================================
// VENTANAS EMERGENTES (<dialog>)
// Cualquier botón con  data-dialogo="id-de-la-ventana"  la abre.
// Si además lleva  data-valores='{"id":2,"nombre":"Javi"}'  rellena
// los campos del formulario que tengan ese name (para "Editar").
// Cualquier botón con  data-cerrar  cierra la ventana en la que está.
// =========================================================

document.querySelectorAll('[data-dialogo]').forEach(function (boton) {

    boton.addEventListener('click', function () {

        // dataset.dialogo = el valor del atributo data-dialogo
        const ventana = document.getElementById(boton.dataset.dialogo);

        // ¿Trae valores para rellenar? JSON.parse convierte el texto en un objeto
        if (boton.dataset.valores) {
            const valores = JSON.parse(boton.dataset.valores);

            // "for…in" recorre cada clave del objeto: id, nombre, email…
            for (const campo in valores) {
                const input = ventana.querySelector('[name="' + campo + '"]');
                if (input) {
                    input.value = valores[campo];
                }
            }
        }

        ventana.showModal();   // abrir encima de todo
    });
});

document.querySelectorAll('[data-cerrar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
        // closest('dialog') = la ventana más cercana que contiene a este botón
        boton.closest('dialog').close();
    });
});

// Si la dirección acaba en #nuevo (viene del botón "Añadir" del dashboard), abrir esa ventana
if (location.hash === '#nuevo' && document.getElementById('dlg-nuevo')) {
    document.getElementById('dlg-nuevo').showModal();
}


// =========================================================
// CONFIRMAR ANTES DE ACCIONES DELICADAS
// Un <form data-confirmar="¿Seguro…?"> pregunta antes de enviarse.
// confirm() muestra la ventanita del navegador con Aceptar/Cancelar.
// =========================================================

document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
    formulario.addEventListener('submit', function (evento) {
        if (!confirm(formulario.dataset.confirmar)) {
            evento.preventDefault();   // ha pulsado Cancelar → NO enviar
        }
    });
});


// =========================================================
// BUSCADOR Y FILTROS DE LAS TABLAS
// - #buscar: oculta las filas cuyo texto no contiene lo escrito.
// - <select data-filtro="rol">: oculta las filas cuyo data-rol
//   no coincide con lo elegido (vacío = todas).
// - #contador y #sin-resultados (si existen) se actualizan.
// Solo FILTRA lo que ya está en pantalla; no pregunta nada al servidor.
// =========================================================

const buscador = document.getElementById('buscar');
const filtros  = document.querySelectorAll('select[data-filtro]');

function filtrarTabla() {
    // toLowerCase = minúsculas, para que "JAVI" encuentre "Javi"
    const texto = buscador ? buscador.value.toLowerCase().trim() : '';
    const filas = document.querySelectorAll('.tabla tbody tr');
    let visibles = 0;

    filas.forEach(function (fila) {
        // 1. ¿Contiene el texto buscado?
        let mostrar = fila.textContent.toLowerCase().includes(texto);

        // 2. ¿Cumple todos los filtros? (data-filtro="rol" → mira fila.dataset.rol)
        filtros.forEach(function (filtro) {
            if (filtro.value !== '' && fila.dataset[filtro.dataset.filtro] !== filtro.value) {
                mostrar = false;
            }
        });

        // hidden = true oculta la fila; false la vuelve a mostrar
        fila.hidden = !mostrar;
        if (mostrar) {
            visibles++;
        }
    });

    const contador = document.getElementById('contador');
    if (contador) {
        contador.textContent = visibles + (visibles === 1 ? ' resultado' : ' resultados');
    }

    const sinResultados = document.getElementById('sin-resultados');
    if (sinResultados) {
        sinResultados.hidden = visibles > 0;
    }
}

// "input" = se dispara con cada letra que se escribe; "change" = al elegir en un desplegable
if (buscador) {
    buscador.addEventListener('input', filtrarTabla);
}
filtros.forEach(function (filtro) {
    filtro.addEventListener('change', filtrarTabla);
});
