# Control de Trasteros — Web profesional

Web para controlar la apertura de puertas de trasteros. Un PLC Siemens S7-1200 abre las cerraduras, Node-RED hace de puente con el PLC y esta web es lo que usan el cliente final (abrir su puerta) y el administrador (gestionar usuarios y permisos).

Es la versión profesional, pensada para enseñar a un cliente/empresa. Parte de una demo anterior en Next.js que ya NO se usa como base.

## Cómo trabajamos (IMPORTANTE)

Yo (Daniel) quiero entender y controlar todo el código. Por eso:

- **Pasos pequeños.** Una pantalla, un archivo o una función cada vez. Nunca generes varias secciones de golpe.
- **Antes de escribir código**, dime en 2-3 líneas qué vas a hacer y en qué archivos. Espera mi OK si es algo nuevo o cambia la estructura.
- **Después de cada cambio**, explícame brevemente qué hace cada parte importante y por qué está hecho así.
- **Nada de "magia":** código sencillo y legible antes que código listo. Comenta en español lo que no sea obvio.
- **No añadas librerías, frameworks ni dependencias** sin preguntarme antes.
- **No hagas commits.** Los commits los hago yo.
- La parte de seguridad del backend (login, sesiones, comprobación de permisos) la escribo yo a mano con tu guía: explícame y revísame, no la generes entera.
- Responde en español, tono directo y práctico.

## Stack

- **PHP** (sin framework) + **HTML / CSS / JS** normales.
- **MariaDB**, una base de datos por cliente/instalación.
- **Node-RED** expone una API HTTP que habla con el PLC. PHP hace de proxy: el navegador NUNCA llama directamente a Node-RED ni a la BD, siempre pasa por `api/`.
- Panel de administrador: **hecho a mano** con nuestro propio CSS (`assets/css/admin.css`, misma paleta que el resto). Se descartó AdminLTE para no depender de Bootstrap y entender todo el código. Modo oscuro más adelante.
- Monitorización: dashboards de **Grafana** embebidos en el panel admin (usuario de BD de solo lectura `grafana_ro`).
- Despliegue: servidor dentro del cuadro eléctrico de cada instalación, accesible por túnel en un subdominio (el servidor lo monta un compañero).

## Estructura de carpetas

```
trasteros-web/
├── CLAUDE.md
├── index.php            → pantalla de login (se muestra siempre; redirige según rol al entrar)
├── logout.php           → cierra la sesión y vuelve al login
├── usuario/             → páginas del cliente (rol usuario). Rutas con "../"
│   └── index.php        → portada: sus puertas
├── admin/               → páginas del empresario (rol admin, panel hecho a mano). Rutas con "../"
│   └── index.php        → dashboard (visual con arrays de prueba; comprueba rol admin)
├── includes/
│   ├── config.php       → conexión BD, URL de Node-RED (NO subir credenciales a git)
│   ├── header.php       → <head>, fuentes, CSS comunes
│   └── footer.php       → scripts comunes y cierre
├── api/                 → endpoints PHP que llama el JS (abrir puerta, estado…)
├── database/
│   └── trasteros_schema.sql
└── assets/
    ├── css/  base.css · login.css · inicio.css · admin.css
    ├── js/   inicio.js · admin.js
    └── img/
```

Mientras hacemos el frontend, los datos (usuario, puertas…) van en **arrays PHP de prueba** dentro de cada página. Luego se sustituyen por consultas a la BD sin tocar el HTML.

## Diseño

Boceto de referencia (login, panel usuario, panel admin) hecho en el lienzo de diseño.

- **Login:** basado en Tabler "sign-in-cover" (https://preview.tabler.io/sign-in-cover.html). En PC: formulario a la izquierda, foto real del producto a la derecha, y esa misma foto muy difuminada de fondo detrás del formulario. En móvil: solo el formulario.
- **Portada usuario (mobile-first):** barra superior con menú hamburguesa, campana de notificaciones y avatar. Tarjeta de saludo ("Buenos días, nombre" + "Tienes X puertas disponibles"). Sección "TUS PUERTAS" con una tarjeta por puerta: icono, nombre, ubicación, estado y botón "Mantén pulsado para abrir". Abajo, tarjeta de aviso/comunicado de la empresa.
- **Estados del botón de apertura:** Cerrada → (mantener pulsado) → Abriendo… → Abierta (cuenta atrás) → Cerrada. Si el PLC no responde: "Sin conexión" y botón deshabilitado.
- **Menú lateral usuario:** Inicio, Mis puertas, Historial de accesos, Comunicados, Horarios y normas, Soporte/Contacto, Mi perfil, Panel de administración (solo si rol = admin), Cerrar sesión.
- **Panel admin:** sidebar oscuro (Gestión: Dashboard, Usuarios, Puertas, Permisos, Historial · Monitorización: Actividad Grafana, Estado de red y PLC · Empresa: Comunicados, Ajustes). Dashboard con 4 tarjetas resumen, tabla de usuarios con sus puertas, estado de puertas y panel de Grafana.

### Paleta (definir como variables CSS en `base.css`)

| Uso | Color |
|---|---|
| Primario (índigo) | `#4338CA` |
| Primario suave | `#EEF0FF` |
| Texto | `#16161D` |
| Texto secundario | `#5B5F6B` |
| Bordes | `#E4E6EB` |
| Fondo | `#F4F5F7` |
| Superficie (tarjetas) | `#FFFFFF` |
| Sidebar oscuro | `#1C1C1F` |
| Cerrada / OK | `#15803D` sobre `#E7F6EC` |
| Abierta / aviso | `#B45309` sobre `#FEF3E2` |
| Sin conexión / error | `#B91C1C` sobre `#FDECEC` |

- Tipografía: **Plus Jakarta Sans** (Google Fonts).
- Radios: 12–16px tarjetas · 10px botones e inputs · 999px píldoras de estado.
- Iconos: trazo fino (Tabler Icons).
- Zonas táctiles mínimo 44px. Contraste de texto AA.

## Base de datos

Esquema en `database/trasteros_schema.sql`. Tablas:

- `usuarios`: rol `admin`/`usuario`. "Eliminar" = `activo = 0`, no se borra (para no perder el historial).
- `puertas`: `plc_id` = identificador que entiende Node-RED.
- `permisos`: usuario ↔ puerta, con `fecha_fin` (NULL = indefinido). Renovar = actualizar `fecha_fin`.
- `accesos`: log de cada intento de apertura (ok / denegado / caducado / error_plc). Es la fuente de Grafana.
- `comunicados`: avisos de la empresa.

Decisiones: el sistema solo controla puertas de **trasteros** (no hay puertas comunes como entrada o garaje); acceso 24h sin franjas horarias; los usuarios NO pueden compartir acceso; el estado de las puertas se consulta en directo a Node-RED (no se guarda en BD).

## Seguridad (mínimos que no se negocian)

- Contraseñas con `password_hash()` / `password_verify()`.
- Consultas SIEMPRE con sentencias preparadas (PDO).
- `session_regenerate_id()` al hacer login; cookies de sesión `HttpOnly`, `Secure` y `SameSite`.
- Token CSRF en todos los formularios y en las llamadas a `api/`.
- Escapar todo lo que se pinta en HTML con `htmlspecialchars()`.
- Antes de abrir una puerta, el servidor comprueba: usuario activo, puerta activa y permiso vigente. Cada intento se registra en `accesos`.
- Credenciales en `includes/config.php`, fuera de git (`.gitignore`).

## Plan de trabajo

1. `assets/css/base.css`: variables de la paleta, fuente, reset, botones, tarjetas, píldoras de estado
2. `index.php` (login) + `login.css`: solo visual
3. `usuario/index.php` + `inicio.css`: portada con puertas de prueba
4. `assets/js/inicio.js`: "mantén pulsado para abrir" y estados (simulado)
5. Menú lateral del usuario
6. Backend: conexión BD, login real con sesiones, `api/abrir.php` → Node-RED
7. Panel admin hecho a mano (`admin/` + `admin.css`)
8. Grafana embebido

**Estado actual:**
- Web completa (cliente + admin + gestión) y conectada a Node-RED con la misma API que la web anterior; probada con `simulador/nodered_simulado.php`. Ver README.md.
- Instalación preparada para el compañero que tiene el Node-RED real: README secciones 1-3, `herramientas/crear_admin.php`, `herramientas/probar_nodered.php`, `web/database/crear_usuario_bd.sql`.
- Patrón de acciones: cada página procesa su propio POST arriba (csrf_comprobar → validar → BD → mensaje_guardar → volver_a).
- Falta: probar con el Node-RED real, Grafana, bloqueo de login por intentos, y pulido (ver README sección 8).
