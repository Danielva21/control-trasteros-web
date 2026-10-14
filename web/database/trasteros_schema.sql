-- #########################################################
-- trasteros_schema.sql — ESTRUCTURA de la base de datos
--
-- Crea la base de datos y sus tablas (vacías).
-- Los datos de prueba van aparte, en datos_prueba.sql.
--
-- Cómo ejecutarlo (desde la carpeta TRASTEROS_WEB):
--   C:/xampp/mysql/bin/mysql.exe -u root < web/database/trasteros_schema.sql
--
-- "IF NOT EXISTS" = si ya existe, no hace nada y no da error.
-- Así se puede ejecutar varias veces sin romper nada.
-- #########################################################



-- =========================================================
-- CODIFICACIÓN: este archivo está en UTF-8 (tildes, ñ, "·"…).
-- SET NAMES le dice a MariaDB que lo lea como UTF-8. Sin esto,
-- la consola de Windows lo lee en otra codificación y guarda
-- "Lucía" como "Luc├¡a".
-- =========================================================

SET NAMES utf8mb4;


-- =========================================================
-- 0. LA BASE DE DATOS
-- utf8mb4 = codificación que admite TODO: tildes, ñ, emojis…
--   (el "utf8" a secas de MySQL/MariaDB es incompleto, no usarlo)
-- utf8mb4_unicode_ci = cómo se comparan y ordenan los textos.
--   "ci" = case insensitive: 'Dani' y 'dani' se consideran iguales.
-- =========================================================

CREATE DATABASE IF NOT EXISTS trasteros
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- A partir de aquí, todo se crea DENTRO de "trasteros"
USE trasteros;



-- =========================================================
-- 1. USUARIOS
-- Clientes (rol 'usuario') y empresario (rol 'admin').
-- "Eliminar" un usuario = activo = 0. Nunca se borra la fila,
-- para no perder su historial de accesos.
-- =========================================================

CREATE TABLE IF NOT EXISTS usuarios (

    -- Identificador único. AUTO_INCREMENT = la BD pone 1, 2, 3… sola.
    -- UNSIGNED = sin negativos (un id nunca es negativo; así cabe el doble).
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- VARCHAR(n) = texto de como mucho n caracteres.
    -- NOT NULL = obligatorio (no puede quedar vacío).
    nombre          VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,

    -- El HASH de la contraseña (password_hash), NUNCA la contraseña.
    -- 255 porque es lo que recomienda PHP: los hashes pueden crecer en el futuro.
    password_hash   VARCHAR(255) NOT NULL,

    -- ENUM = solo admite uno de estos valores. Si intentas guardar 'jefe', error.
    rol             ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',

    -- TINYINT(1) = número pequeño; lo usamos como sí/no (1 = activo, 0 = suspendido).
    -- DEFAULT 1 = si no se indica, el usuario nace activo.
    activo          TINYINT(1) NOT NULL DEFAULT 1,

    -- DATETIME = fecha y hora. CURRENT_TIMESTAMP = "ahora mismo" al crear la fila.
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- NULL = puede quedar vacío (un usuario recién creado aún no ha entrado nunca).
    ultimo_acceso   DATETIME NULL DEFAULT NULL,

    -- El id del último comunicado que el usuario ha visto (al abrir la
    -- página Comunicados). Los comunicados con un id MAYOR son "no leídos"
    -- (el número de la campana). NULL = nunca los ha mirado.
    -- Se usa el id y no una fecha porque los ids siempre crecen: sin
    -- empates de "mismo segundo".
    ultimo_comunicado_visto INT UNSIGNED NULL DEFAULT NULL,

    -- CLAVE PRIMARIA: la columna que identifica cada fila sin repetirse.
    PRIMARY KEY (id),

    -- ÍNDICE ÚNICO: la BD no deja que dos usuarios tengan el mismo email.
    -- Además hace muy rápida la búsqueda por email (la que hace el login).
    UNIQUE KEY uq_usuarios_email (email)

) ENGINE = InnoDB;
-- InnoDB = el "motor" de tablas que admite claves foráneas y transacciones.



-- =========================================================
-- 2. PUERTAS
-- Cada cerradura que controla el PLC.
-- plc_id = el identificador que entiende Node-RED/el PLC
-- (el id de la puerta en Node-RED: 1, 2, 3…). La web nunca habla con el PLC por
-- nombre ("Garaje"), siempre por este código.
-- =========================================================

CREATE TABLE IF NOT EXISTS puertas (

    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,

    nombre      VARCHAR(100) NOT NULL,            -- "Trastero 28", "Garaje"…
    ubicacion   VARCHAR(150) NULL DEFAULT NULL,   -- "Planta 5 · Pasillo B" (opcional)
    plc_id      VARCHAR(50)  NOT NULL,            -- id en Node-RED: "1", "2"…

    -- 1 = en servicio, 0 = dada de baja (no se borra, por el historial)
    activa      TINYINT(1) NOT NULL DEFAULT 1,

    creada_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Dos puertas no pueden tener el mismo código del PLC
    -- (si no, al abrir una se abriría la otra).
    UNIQUE KEY uq_puertas_plc_id (plc_id)

) ENGINE = InnoDB;



-- #########################################################
-- PARTE 2: TABLAS QUE RELACIONAN
--
-- CLAVE FORÁNEA (FOREIGN KEY) = una columna que "apunta" al id
-- de otra tabla. La BD vigila que ese id EXISTA:
--   - no deja guardar un permiso para el usuario 99 si no existe.
--   - ON DELETE RESTRICT: no deja borrar un usuario/puerta que
--     tenga filas apuntándole (protege el historial; además
--     nosotros nunca borramos, solo ponemos activo = 0).
--   - ON UPDATE CASCADE: si cambiara el id de un usuario,
--     se actualizaría solo en las filas que le apuntan.
-- #########################################################



-- =========================================================
-- 3. PERMISOS  (usuarios N ←→ N puertas)
-- Cada fila = "este usuario puede abrir esta puerta".
-- Es la tabla "del medio" que resuelve el muchos-a-muchos:
--   Javi → Trastero 28      (una fila)
--   Javi → Garaje           (otra fila)
--   Ana  → Garaje           (otra fila)
-- fecha_fin NULL = permiso indefinido.
-- Renovar = cambiar fecha_fin. Quitar = poner fecha_fin a ahora.
-- =========================================================

CREATE TABLE IF NOT EXISTS permisos (

    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Las dos "flechas": a quién y a qué puerta.
    -- Mismo tipo que el id al que apuntan (INT UNSIGNED), si no, la BD no deja crear la relación.
    usuario_id    INT UNSIGNED NOT NULL,
    puerta_id     INT UNSIGNED NOT NULL,

    fecha_inicio  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin     DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),

    -- Un usuario no puede tener DOS permisos para la misma puerta
    -- (la PAREJA usuario + puerta no se repite).
    UNIQUE KEY uq_permisos_usuario_puerta (usuario_id, puerta_id),

    -- Las claves foráneas: usuario_id apunta a usuarios.id, puerta_id a puertas.id
    CONSTRAINT fk_permisos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_permisos_puerta
        FOREIGN KEY (puerta_id) REFERENCES puertas (id)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE = InnoDB;



-- =========================================================
-- 4. ACCESOS  (el historial / log)
-- Cada intento de abrir una puerta, salga bien o mal.
-- Es la fuente de datos de Grafana y de "Aperturas hoy".
-- Solo se AÑADEN filas, nunca se modifican ni se borran.
--
-- resultado:
--   ok         → se abrió
--   denegado   → no tenía permiso (o usuario/puerta inactivos)
--   caducado   → tenía permiso pero ya pasó su fecha_fin
--   error_plc  → tenía permiso, pero el PLC/Node-RED no respondió
-- =========================================================

CREATE TABLE IF NOT EXISTS accesos (

    -- BIGINT: este número crece mucho (cada apertura es una fila), INT podría quedarse corto con los años
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    usuario_id  INT UNSIGNED NOT NULL,
    puerta_id   INT UNSIGNED NOT NULL,

    fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resultado   ENUM('ok', 'denegado', 'caducado', 'error_plc') NOT NULL,

    -- Desde qué IP se pidió (45 caracteres = cabe una IPv6 completa)
    ip          VARCHAR(45) NULL DEFAULT NULL,

    PRIMARY KEY (id),

    -- ÍNDICE (KEY) = como el índice de un libro: no cambia los datos,
    -- pero hace MUY rápidas las búsquedas por esa columna.
    -- Casi todas las consultas del historial filtran por fecha ("hoy", "esta semana").
    KEY idx_accesos_fecha (fecha),

    CONSTRAINT fk_accesos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_accesos_puerta
        FOREIGN KEY (puerta_id) REFERENCES puertas (id)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE = InnoDB;



-- =========================================================
-- 5. COMUNICADOS  (avisos de la empresa a los clientes)
-- Los escribe el admin; salen en la portada del cliente.
-- =========================================================

CREATE TABLE IF NOT EXISTS comunicados (

    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,

    titulo             VARCHAR(150) NOT NULL,

    -- TEXT = texto largo (hasta 65.535 caracteres), para el cuerpo del aviso
    texto              TEXT NOT NULL,

    -- 1 = se muestra a los clientes, 0 = oculto/archivado
    activo             TINYINT(1) NOT NULL DEFAULT 1,

    fecha_publicacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Quién lo escribió (un admin)
    autor_id           INT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_comunicados_autor
        FOREIGN KEY (autor_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE = InnoDB;
