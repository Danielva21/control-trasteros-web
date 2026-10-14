// =========================================================
// tema.js — MODO CLARO / OSCURO
//
// Se carga en el <head> de todas las páginas (ANTES de pintar
// nada), para que la página salga ya con el tema correcto y
// no se vea un "fogonazo" blanco al cargar en modo oscuro.
//
// - Lo elegido se guarda en el navegador (localStorage) y se
//   recuerda la próxima vez, en ese mismo navegador.
// - Si nunca se ha elegido, usa el tema del sistema (móvil/PC).
// - Cualquier botón con  data-cambiar-tema  alterna entre los dos.
//
// El tema se aplica poniendo data-tema="oscuro" en <html>;
// el CSS (base.css, apartado 12) cambia los colores.
// =========================================================


// ---------------------------------------------------------
// 1. Leer y guardar la preferencia
//    try/catch: en modo incógnito o con el almacenamiento
//    bloqueado, localStorage puede fallar. Si falla, seguimos
//    sin recordar la preferencia (no pasa nada grave).
// ---------------------------------------------------------
function leerTemaGuardado() {
    try {
        return localStorage.getItem('tema');   // 'oscuro', 'claro' o null (nunca elegido)
    } catch (error) {
        return null;
    }
}

function guardarTema(tema) {
    try {
        localStorage.setItem('tema', tema);
    } catch (error) {
        // no se puede guardar: el tema funciona igual, solo que no se recordará
    }
}


// ---------------------------------------------------------
// 2. Aplicar un tema: poner o quitar data-tema en <html>
//    document.documentElement = la etiqueta <html>
// ---------------------------------------------------------
function aplicarTema(tema) {
    if (tema === 'oscuro') {
        document.documentElement.dataset.tema = 'oscuro';
    } else {
        delete document.documentElement.dataset.tema;
    }
}


// ---------------------------------------------------------
// 3. Al cargar: el guardado; si no hay, el del sistema.
//    matchMedia('(prefers-color-scheme: dark)') = ¿el móvil/PC
//    está en modo oscuro?
// ---------------------------------------------------------
const temaInicial = leerTemaGuardado()
    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro');

aplicarTema(temaInicial);


// ---------------------------------------------------------
// 4. Los botones de cambiar tema.
//    Este archivo se carga en el <head>, cuando los botones aún
//    no existen; por eso esperamos a DOMContentLoaded ("el HTML
//    ya está cargado") para buscarlos.
// ---------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('[data-cambiar-tema]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const nuevo = document.documentElement.dataset.tema === 'oscuro' ? 'claro' : 'oscuro';
            aplicarTema(nuevo);
            guardarTema(nuevo);
        });
    });
});
