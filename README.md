# Control de Trasteros — Web

Web para abrir las puertas de los trasteros desde el móvil y para que el empresario gestione clientes y alquileres.

- **Cliente** → entra y abre sus trasteros (mantener pulsado el botón).
- **Empresario (admin)** → panel para gestionar clientes, trasteros, alquileres y comunicados.
- Un **PLC Siemens S7-1200** abre las cerraduras. **Node-RED** hace de puente entre la web y el PLC.

**Stack:** PHP sin framework · HTML/CSS/JS normales · MariaDB · Node-RED · Grafana. Sin librerías externas.

**Índice:**
1. [Instalación en el servidor (paso a paso)](#1-instalación-en-el-servidor-paso-a-paso) ← **para montarlo en el cuadro**
2. [Conectar con Node-RED](#2-conectar-con-node-red)
3. [Si algo no funciona](#3-si-algo-no-funciona)
4. [Desarrollo en local (XAMPP)](#4-desarrollo-en-local-xampp)
5. [Organización de carpetas](#5-organización-de-carpetas)
6. [Base de datos](#6-base-de-datos)
7. [Seguridad](#7-seguridad)
8. [Estado y lo que falta](#8-estado-y-lo-que-falta)

---

## 1. Instalación en el servidor (paso a paso)

> Para quien monta el servidor de la instalación (el del cuadro eléctrico). Se hace **una vez por instalación**.
> En los comandos, `php` y `mysql` son los del servidor. En Windows con XAMPP: `C:/xampp/php/php.exe` y `C:/xampp/mysql/bin/mysql.exe`.

### Lo que necesita el servidor
- **PHP 8.1 o superior** con las extensiones `pdo_mysql`, `curl` y `mbstring` (XAMPP las trae).
- **MariaDB** (o MySQL).
- Un servidor web (Apache, Nginx…) que sirva **solo la carpeta `web/`**.
- Acceso desde el servidor a **Node-RED** (normalmente en el mismo equipo, puerto 1880).
- **HTTPS** (el túnel / subdominio). Imprescindible: por ahí viajan contraseñas.

### Paso 1 — Copiar los archivos
Copiar la carpeta del proyecto al servidor. **Al público solo se sirve `web/`**: las carpetas `herramientas/` y `simulador/` **no** deben quedar accesibles desde internet (en Apache/Nginx, la raíz del sitio es `web/`).

### Paso 2 — Crear la base de datos (vacía)
```bash
mysql -u root -p < web/database/trasteros_schema.sql
```
⚠️ **NO** ejecutar `datos_prueba.sql` en una instalación real: borra todo y mete usuarios de prueba.

### Paso 3 — Crear el usuario de MariaDB que usa la web
1. Generar una contraseña aleatoria:
   ```bash
   php -r "echo bin2hex(random_bytes(12));"
   ```
2. Abrir `web/database/crear_usuario_bd.sql` y cambiar `CAMBIA_ESTA_CONTRASEÑA` (aparece **dos** veces) por esa contraseña.
3. Ejecutarlo:
   ```bash
   mysql -u root -p < web/database/crear_usuario_bd.sql
   ```

### Paso 4 — Crear `config.php`
1. Copiar `web/includes/config.ejemplo.php` como `web/includes/config.php`.
2. Rellenar:

| Dato | Qué poner |
|---|---|
| `BD_PASSWORD` | La contraseña del paso 3 |
| `NODERED_URL` | Dónde está Node-RED **visto desde el servidor**. Si están en el mismo equipo: `http://127.0.0.1:1880` |
| `NODERED_TOKEN` | El token que pide Node-RED para abrir (el mismo que tiene configurado el flujo). Ver [sección 2](#2-conectar-con-node-red) |

`config.php` tiene contraseñas: **nunca** se sube a git (ya está en `.gitignore`).

### Paso 5 — Crear la cuenta del empresario (admin)
Los admins no se pueden crear desde la web (por seguridad). Se crean con:
```bash
php herramientas/crear_admin.php "Nombre del empresario" email@empresa.com "contraseña-de-8-o-más"
```

### Paso 6 — Comprobar la conexión con Node-RED
```bash
php herramientas/probar_nodered.php
```
Comprueba la configuración, que Node-RED responde, el formato de la respuesta y (cuando ya haya trasteros) que cada trastero existe en Node-RED. Dice exactamente qué falla. Ver [sección 3](#3-si-algo-no-funciona).

### Paso 7 — Dar de alta los trasteros
Entrar en la web con el admin → **Trasteros** → **+ Añadir trastero**. En **"Nº en Node-RED"** poner el `id` de esa puerta en Node-RED (el mismo número que sale en `GET /api/puertas`).

Después, volver a ejecutar `php herramientas/probar_nodered.php`: el punto 3 debe salir todo en verde.

### Paso 8 — Probar una apertura real
1. Admin → **Usuarios** → crear un cliente de prueba.
2. Admin → **Permisos** → asignarle un trastero.
3. Entrar como ese cliente (mejor desde el móvil) y **mantener pulsado** el botón.
4. Comprobar en Admin → **Historial** que aparece como **Abierta** (resultado `ok`).

También se puede probar el token directamente (⚠️ **abre la puerta de verdad**):
```bash
php herramientas/probar_nodered.php --abrir=1
```

---

## 2. Conectar con Node-RED

```
Navegador ──▶ api/abrir.php ──▶ Node-RED ──▶ PLC S7-1200
 (inicio.js)   comprueba todo     (puente)     (DB_Puertas)
               y guarda en accesos
```

- El navegador **nunca** habla con Node-RED: siempre pasa por `web/api/`. Solo el servidor conoce la dirección y el token.
- El **único** archivo que habla con Node-RED es `web/includes/nodered.php`. Si algún día cambia la API de Node-RED, solo hay que tocar ese archivo.

### Lo que la web espera de Node-RED

Es **la misma API que usaba la web anterior** (Next.js, `INTEGRACION.md`), así que el flujo de Node-RED que ya funcionaba con ella no hay que cambiarlo.

**1. `GET {NODERED_URL}/api/puertas`** → lista con el estado de cada puerta:
```json
[
  { "id": 1, "name": "Puerta 1", "state": "Cerrada", "fault": false, "lastUpdate": "2026-09-21T10:00:00.000Z" },
  { "id": 2, "name": "Puerta 2", "state": "Abierta", "fault": false, "lastUpdate": "2026-09-21T10:00:00.000Z" }
]
```
La web usa `id`, `state` (`"Cerrada"` | `"Abierta"` | `"Abriendo"` | `"Desconocida"`) y `fault`. El resto de campos se ignoran.

**2. `POST {NODERED_URL}/api/puerta/{id}/abrir`** con la cabecera `Authorization: Bearer {NODERED_TOKEN}` → responde `{"ok": true}`.
Node-RED pone `Cmd_Abrir` en el PLC; el pulso lo temporiza el PLC.

### Dónde encontrar el token
En la web anterior se ponía al compilarla (`NEXT_PUBLIC_API_TOKEN=... npm run build`). El valor está **en el flujo de Node-RED**: buscar el nodo (normalmente un *function* o *switch*) que va después del *http in* `POST /api/puerta/:id/abrir` y comprueba la cabecera `Authorization`.

💡 **Recomendado:** como en la web anterior el token iba dentro de la página (cualquiera podía verlo), conviene **cambiarlo por uno nuevo** en Node-RED y en `config.php`:
```bash
php -r "echo bin2hex(random_bytes(16));"
```

### Cómo se relacionan los trasteros con las puertas
Cada trastero de la BD tiene un **"Nº en Node-RED"** (columna `puertas.plc_id`) que es el `{id}` de esa puerta en Node-RED. Ejemplo: si el *Trastero 28* es la puerta 5 de `DB_Puertas`, su Nº en Node-RED es `5`.

### Qué se ve en la web
- **Cliente:** el estado se actualiza cada 3 s. Para abrir hay que **mantener pulsado 1 segundo** (así no se abre por un toque sin querer). Estados: *Cerrada* → *Abriendo…* → *Abierta* → *Cerrada*. Si el PLC no responde: *Sin conexión* y el botón desactivado.
- **Admin:** el **Dashboard** y **Estado de red y PLC** muestran el estado real y si Node-RED responde.
- **Historial:** cada intento queda guardado: `ok`, `denegado` (sin permiso / suspendido / trastero de baja), `caducado` o `error_plc` (Node-RED no respondió).

---

## 3. Si algo no funciona

Lo primero, siempre: `php herramientas/probar_nodered.php` y mirar Admin → **Estado de red y PLC**.

| Síntoma | Causa probable | Solución |
|---|---|---|
| La web dice *"Ahora mismo no se puede acceder al sistema"* | No conecta con la BD | Revisar `BD_*` en `config.php` y que MariaDB esté arrancado. El error exacto está en el log de PHP |
| Todos los trasteros en **"Sin conexión"** | La web no llega a Node-RED | `NODERED_URL` mal (IP o puerto), Node-RED parado, o un cortafuegos entre el servidor y Node-RED |
| Un trastero en **"Sin datos"** y los demás bien | Su "Nº en Node-RED" no existe en Node-RED | Admin → Trasteros → Editar → poner el `id` correcto |
| Se ve el estado bien, pero al abrir sale *"La instalación no responde"* y en el historial `error_plc` | **El token** no coincide | `NODERED_TOKEN` en `config.php` = el del flujo de Node-RED. `probar_nodered.php --abrir=ID` lo confirma (código 401/403) |
| Al abrir sale *"No tienes permiso…"* | El cliente no tiene ese trastero asignado | Admin → Permisos → Asignar |
| Al abrir sale *"Tu permiso ha caducado"* | `fecha_fin` ya pasó | Admin → Permisos → Renovar |
| Al enviar un formulario: *"La petición no es válida"* | Token CSRF caducado (página abierta mucho rato) | Recargar la página y repetir |
| No se puede entrar con el email y la contraseña correctos | El navegador autorrellena otro email | Borrar el campo (Ctrl+A, Supr) y escribirlo a mano |
| No entra por HTTPS / la sesión se pierde | Cookies | Servir siempre por HTTPS (con HTTPS, la cookie se marca `Secure` sola) |

**Los errores técnicos no se enseñan al usuario** (por seguridad): van al **log de errores de PHP** del servidor (en XAMPP: `C:/xampp/php/logs/php_error_log`; con `php -S` en local, salen en la propia terminal del servidor). Ahí aparece, por ejemplo, `Node-RED POST /api/puerta/5/abrir falló: código 401`.

---

## 4. Desarrollo en local (XAMPP)

1. **MariaDB:** panel de control de XAMPP → **Start** en *MySQL*.
2. **Servidor PHP** (terminal de VS Code, desde `TRASTEROS_WEB`):
   ```bash
   C:/xampp/php/php.exe -S localhost:8000 -t web
   ```
3. **Node-RED simulado** (en **otra** terminal, desde `TRASTEROS_WEB`):
   ```bash
   C:/xampp/php/php.exe -S 127.0.0.1:1880 simulador/nodered_simulado.php
   ```
   - Responde igual que el Node-RED real. Al abrir: *Abriendo* (2 s) → *Abierta* (6 s) → *Cerrada*.
   - Para simular que el PLC no responde: crear un archivo vacío `simulador/APAGADO` (borrarlo para volver).
   - Para probar con un Node-RED real en vez del simulador: parar el simulador (usan el mismo puerto) y poner su URL y token en `config.php`.
4. **Navegador:** `http://localhost:8000` ← con el `:8000`

### Usuarios de prueba (contraseña de todos: `1234`)

| Email | Rol | Situación |
|---|---|---|
| `dani@gmail.com` | **admin** | El empresario → va al panel |
| `javi@gmail.com` | usuario | Trasteros 15 (hasta abr-2027) y 28 |
| `lucia@gmail.com` | usuario | Trastero 05 |
| `pedro@gmail.com` | usuario | Trastero 31 (su permiso del 03 está **caducado**) |
| `ana@gmail.com` | usuario | **Suspendida** → no puede entrar |

### Volver a los datos de prueba limpios
```bash
C:/xampp/mysql/bin/mysql.exe -u root < web/database/datos_prueba.sql
```
⚠️ Vacía todas las tablas. **Solo en desarrollo**, nunca en una instalación real.

---

## 5. Organización de carpetas

```
TRASTEROS_WEB/
├── README.md                → este archivo
├── CLAUDE.md                → instrucciones de trabajo y decisiones
├── herramientas/            → se usan desde la TERMINAL (NO se publican)
│   ├── crear_admin.php      → crear la cuenta del empresario
│   └── probar_nodered.php   → comprobar la conexión con Node-RED y decir qué falla
├── simulador/               → SOLO desarrollo (NO se publica)
│   └── nodered_simulado.php → Node-RED "de mentira" para probar sin PLC
└── web/                     → LA WEB (lo único que se sirve)
    ├── index.php            → LOGIN. Lee la tabla usuarios y redirige según el rol
    ├── logout.php           → cierra la sesión y vuelve al login
    │
    ├── usuario/             → páginas del CLIENTE (menú lateral)
    │   ├── index.php        → Inicio: sus trasteros con el botón de abrir + último comunicado
    │   ├── trasteros.php    → Mis trasteros: todos, con fechas y si están caducados
    │   ├── historial.php    → sus intentos de apertura
    │   ├── comunicados.php  → avisos publicados de la empresa
    │   ├── normas.php       → horarios y normas (texto fijo)
    │   ├── soporte.php      → datos de contacto (texto fijo)
    │   └── perfil.php       → sus datos + cambiar contraseña
    │
    ├── admin/               → páginas del EMPRESARIO (solo rol admin)
    │   ├── index.php        → Dashboard: resumen, usuarios, estado real de los trasteros
    │   ├── usuarios.php     → añadir, editar, suspender/reactivar clientes
    │   ├── trasteros.php    → añadir, editar, dar de baja trasteros (con su Nº en Node-RED)
    │   ├── permisos.php     → asignar trasteros con fechas, renovar, quitar
    │   ├── historial.php    → todos los intentos de apertura (con filtro)
    │   ├── comunicados.php  → escribir, editar, archivar avisos
    │   ├── actividad.php    → últimos 7 días + hueco para Grafana
    │   ├── red.php          → estado de Node-RED/PLC y de cada cerradura
    │   └── ajustes.php      → mi cuenta (cambiar contraseña) + datos de la instalación
    │
    ├── api/                 → lo llama el JavaScript; responde JSON
    │   ├── estado.php       → estado real de los trasteros del usuario (pregunta a Node-RED)
    │   └── abrir.php        → abrir: comprueba todo → Node-RED → guarda en accesos
    │
    ├── includes/            → trozos de PHP que usan varias páginas
    │   ├── config.php           → BD y Node-RED (⚠️ contraseñas, fuera de git)
    │   ├── config.ejemplo.php   → plantilla de config.php (sí va a git)
    │   ├── nodered.php          → el ÚNICO que habla con Node-RED
    │   ├── sesion.php           → sesión con cookie segura (HttpOnly, SameSite)
    │   ├── csrf.php             → token CSRF de los formularios
    │   ├── funciones.php        → fechas, mensajes, cambiar contraseña…
    │   ├── solo_admin.php / admin_cabecera.php / admin_pie.php       → común del panel
    │   ├── solo_usuario.php / usuario_cabecera.php / usuario_pie.php → común del cliente
    │   └── header.php, footer.php → (vacíos desde el principio; ya no se usan)
    │
    ├── database/
    │   ├── trasteros_schema.sql   → ESTRUCTURA: crea la BD y las 5 tablas
    │   ├── crear_usuario_bd.sql   → usuario de MariaDB de la web (instalación)
    │   └── datos_prueba.sql       → datos de prueba (⚠️ SOLO desarrollo)
    │
    └── assets/
        ├── css/  base.css (común) · login.css · inicio.css (cliente) · admin.css (panel)
        ├── js/   inicio.js (cliente: abrir, menú, ventanas, saludo) · admin.js (panel: ventanas, buscadores)
        │         tema.js (modo claro/oscuro, en todas las páginas)
        └── img/  producto.svg (imagen de prueba del login)
```

### Cómo es una página del admin
```php
require_once __DIR__ . '/../includes/solo_admin.php';   // 1. ¿es admin? si no, fuera
require      __DIR__ . '/../includes/config.php';       // 2. conectar a la BD ($pdo)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {             // 3. acciones de los formularios
    csrf_comprobar();  /* validar → guardar → */  mensaje_guardar('ok', '…');  volver_a('pagina.php');
}

$datos = $pdo->query('SELECT ...')->fetchAll();          // 4. sus consultas

$titulo = 'Usuarios';  $pagina_activa = 'usuarios';      // 5. cabecera común
require __DIR__ . '/../includes/admin_cabecera.php';
?>
    ... contenido de la página ...
<?php require __DIR__ . '/../includes/admin_pie.php'; ?> // 6. pie común
```

---

## 6. Base de datos

```
 usuarios ──1───N── permisos ──N───1── puertas
    │                                     │
    │1                                    │1
    N                                     N
    └──────────── accesos ────────────────┘
 usuarios ──1───N── comunicados   (autor)
```

| Tabla | Qué guarda | Columnas clave |
|---|---|---|
| `usuarios` | Empresario y clientes | email (único), password_hash, rol (`admin`/`usuario`), activo, ultimo_acceso, ultimo_comunicado_visto (para la campana) |
| `puertas` | Los trasteros | nombre, ubicacion, plc_id (**Nº en Node-RED**), activa |
| `permisos` | Qué cliente tiene qué trastero | usuario_id, puerta_id, fecha_inicio, fecha_fin (vacía = indefinido) |
| `accesos` | Historial de aperturas | usuario_id, puerta_id, fecha, resultado (ok/denegado/caducado/error_plc), ip |
| `comunicados` | Avisos de la empresa | titulo, texto, activo, autor_id |

**Reglas:**
- Nunca se borra nada: "eliminar" = `activo = 0` (para no perder el historial).
- Un cliente puede abrir un trastero si: él está activo + el trastero está activo + su permiso no ha caducado.
- El estado abierta/cerrada **no** se guarda: se pregunta en directo a Node-RED.

**Usuarios de MariaDB:** `root` solo para administrar a mano (la web **nunca** lo usa) · `trasteros_web` el de la web (leer, añadir, modificar; **no** borrar).

**Ver la BD de forma visual (local):** arrancar *Apache* en XAMPP → `http://localhost/phpmyadmin` → `trasteros` (pestaña *Diseñador* para ver las relaciones).

---

## 7. Seguridad

- [x] Contraseñas con `password_hash` / `password_verify` (nunca en texto). Mínimo 8 caracteres para las nuevas.
- [x] Consultas con **sentencias preparadas** (probado contra inyección SQL).
- [x] `session_regenerate_id()` al entrar; cookies `HttpOnly` + `SameSite=Lax` (+ `Secure` automático con HTTPS); logout que destruye sesión y cookie.
- [x] Token **CSRF** en todos los formularios (login incluido) y en `api/abrir.php`.
- [x] `htmlspecialchars()` en todo lo que se pinta; validación en el servidor de todo lo que llega.
- [x] `usuario/` y `admin/` protegidos; un cliente no puede entrar en `admin/` escribiendo la URL. Los admins solo se crean por terminal.
- [x] Si el admin suspende a un usuario, se le echa en su siguiente clic (y no puede abrir).
- [x] Abrir: solo POST; el servidor comprueba usuario activo, trastero activo y permiso vigente; **cada intento queda en `accesos`**.
- [x] El token de Node-RED solo lo conoce el servidor (en la web anterior iba dentro de la página).
- [x] Usuario de BD con permisos mínimos; credenciales fuera de git.
- [ ] Bloquear tras varios intentos de login fallidos.

---

## 8. Estado y lo que falta

### ✅ Hecho
- Login real, con redirección por rol.
- **Cliente:** todas las páginas; abrir sus trasteros (mantener pulsado) con estado en directo; cambiar contraseña.
- **Admin:** todas las páginas, versión móvil, y gestión completa: usuarios, trasteros, permisos con fechas, comunicados, contraseña, buscadores.
- **Conexión con Node-RED** (misma API que la web anterior), probada con el simulador en todos los casos.
- Herramientas de instalación: `crear_admin.php`, `probar_nodered.php`, `crear_usuario_bd.sql`.

### ⏳ Falta
1. **Probar con el Node-RED real** (pasos 6–8 de la instalación).
2. **Grafana** embebido en Actividad (usuario de BD `grafana_ro`, solo lectura).
3. Bloquear el login tras varios intentos fallidos.

### ✅ Detalles hechos
- **Campana de notificaciones** del cliente: cuenta los comunicados que aún no ha visto (los que tienen un `id` mayor que `usuarios.ultimo_comunicado_visto`). Al abrir *Comunicados* se marcan como leídos. El aviso de la portada solo sale mientras haya alguno sin leer.
- **Saludo según la hora** (JS, hora del móvil): 06–14 *Buenos días*, 14–22 *Buenas tardes*, 22–06 *Buenas noches*.
- **Modo oscuro** con botón luna/sol en el cliente y en el admin (el login lo respeta). Se recuerda en ese navegador; si nunca se ha elegido, usa el del sistema.

### 🎨 Para pulir
- Datos de contacto reales en *Soporte* y normas en *Horarios y normas* (ahora `[entre corchetes]`).
- Nombre de la empresa en el login (`[Nombre del cliente]`) y foto real `producto.jpg`.
- Iconos en los menús · "Mantener la sesión iniciada".

### ⚠️ Pendiente fuera del código
- El repositorio de git está en la carpeta de usuario (`C:\Users\danie`), no en el proyecto. Crear uno propio en `TRASTEROS_WEB` antes de hacer commits del proyecto.
