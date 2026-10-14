-- #########################################################
-- datos_prueba.sql — DATOS DE PRUEBA para desarrollo
--
-- ⚠️  SOLO PARA DESARROLLO. Empieza VACIANDO todas las tablas.
--     NUNCA ejecutarlo en una instalación real: borraría los
--     usuarios, permisos e historial del cliente.
--
-- Se puede ejecutar todas las veces que quieras para volver
-- a un estado limpio de pruebas (desde TRASTEROS_WEB):
--   C:/xampp/mysql/bin/mysql.exe -u root < web/database/datos_prueba.sql
--
-- Contraseña de TODOS los usuarios: 1234
-- #########################################################

-- =========================================================
-- CODIFICACIÓN: este archivo está en UTF-8 (tildes, ñ, "·"…).
-- SET NAMES le dice a MariaDB que lo lea como UTF-8. Sin esto,
-- la consola de Windows lo lee en otra codificación y guarda
-- "Lucía" como "Luc├¡a".
-- =========================================================

SET NAMES utf8mb4;


USE trasteros;



-- =========================================================
-- 0. VACIAR LAS TABLAS
-- TRUNCATE = vaciar la tabla entera y reiniciar el AUTO_INCREMENT a 1.
-- Las claves foráneas no dejan vaciar "usuarios" mientras haya
-- permisos que le apunten, así que las desactivamos un momento
-- (FOREIGN_KEY_CHECKS = 0) y las volvemos a activar al acabar.
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE accesos;
TRUNCATE TABLE permisos;
TRUNCATE TABLE comunicados;
TRUNCATE TABLE puertas;
TRUNCATE TABLE usuarios;

SET FOREIGN_KEY_CHECKS = 1;



-- =========================================================
-- 1. USUARIOS
-- Ponemos el id a mano para que los permisos de abajo se
-- entiendan (en la web real lo pondrá el AUTO_INCREMENT).
-- password_hash = hash de '1234' (cada uno distinto por la "sal").
-- NOW() - INTERVAL 1 DAY = "ahora menos un día" (ayer a esta hora).
-- =========================================================

INSERT INTO usuarios (id, nombre, email, password_hash, rol, activo, ultimo_acceso) VALUES
    (1, 'Dani',  'dani@gmail.com',  '$2y$10$uAJBmITGPwl7md3UDAy5euBs2Dc9I6eYb6nehmn7DRY9IQsgftp4.', 'admin',   1, NOW() - INTERVAL 2 HOUR),
    (2, 'Javi',  'javi@gmail.com',  '$2y$10$9WstX3D4gdJDbGjBfbgAYuK4IlkGDmAtpM.mbNaGXb1o1fGrbtpAu', 'usuario', 1, NOW() - INTERVAL 1 DAY),
    (3, 'Ana',   'ana@gmail.com',   '$2y$10$GHyXIff1MqbzqVUb5e2N7eu7bPdvLY9YTpUnluKwk.V2TTeu7JyFG', 'usuario', 0, NOW() - INTERVAL 12 DAY),   -- suspendida
    (4, 'Lucía', 'lucia@gmail.com', '$2y$10$Oz21/mP48EIxXDwM6p1/KuIEn3qf/K6qXUMwSEYzoV2G31/E9SLRS', 'usuario', 1, NULL),                       -- nunca ha entrado
    (5, 'Pedro', 'pedro@gmail.com', '$2y$10$FjslTWd5LyAx/1THpW5sd.LAhQEHaP4fl7IisdAcdymOnnuL5HBeO', 'usuario', 1, NOW() - INTERVAL 3 HOUR);



-- =========================================================
-- 2. PUERTAS  (solo trasteros)
-- plc_id = el id de la puerta en Node-RED (1, 2, 3…): es el {id} de /api/puerta/{id}/abrir
-- =========================================================

INSERT INTO puertas (id, nombre, ubicacion, plc_id, activa) VALUES
    (1, 'Trastero 03', 'Planta 1 · Pasillo A', '1', 1),
    (2, 'Trastero 05', 'Planta 1 · Pasillo A', '2', 1),
    (3, 'Trastero 12', 'Planta 2 · Pasillo A', '3', 1),
    (4, 'Trastero 15', 'Planta 2 · Pasillo B', '4', 1),
    (5, 'Trastero 28', 'Planta 5 · Pasillo B', '5', 1),
    (6, 'Trastero 31', 'Planta 5 · Pasillo C', '6', 1),
    (7, 'Trastero 40', 'Planta 6 · Pasillo A', '7', 0);   -- dado de baja



-- =========================================================
-- 3. PERMISOS  (qué trastero(s) tiene alquilado cada cliente)
-- fecha_fin NULL = indefinido.
-- Casos para probar:
--   - Javi: 2 trasteros, uno con fecha de fin dentro de 6 meses.
--   - Ana: tiene trastero, pero está suspendida → no debe poder abrir.
--   - Pedro: su permiso del Trastero 03 CADUCÓ hace 5 días.
--   - Trastero 40: dado de baja, nadie lo tiene.
-- =========================================================

INSERT INTO permisos (usuario_id, puerta_id, fecha_inicio, fecha_fin) VALUES
    -- Javi (2)
    (2, 5, NOW() - INTERVAL 90 DAY,  NULL),                         -- Trastero 28, indefinido
    (2, 4, NOW() - INTERVAL 30 DAY,  NOW() + INTERVAL 6 MONTH),     -- Trastero 15, hasta dentro de 6 meses

    -- Ana (3), suspendida
    (3, 3, NOW() - INTERVAL 200 DAY, NULL),                         -- Trastero 12

    -- Lucía (4)
    (4, 2, NOW() - INTERVAL 10 DAY,  NULL),                         -- Trastero 05

    -- Pedro (5)
    (5, 6, NOW() - INTERVAL 60 DAY,  NULL),                         -- Trastero 31
    (5, 1, NOW() - INTERVAL 60 DAY,  NOW() - INTERVAL 5 DAY);       -- Trastero 03, CADUCADO



-- =========================================================
-- 4. ACCESOS  (historial)
-- Unos cuantos de hoy y de días anteriores, con todos los
-- resultados posibles, para que el dashboard y el historial
-- tengan algo que enseñar.
-- =========================================================

INSERT INTO accesos (usuario_id, puerta_id, fecha, resultado, ip) VALUES
    -- Hoy
    (5, 1, NOW() - INTERVAL 3 HOUR,    'caducado',  '192.168.1.40'),   -- Pedro intenta el 03: caducado
    (5, 6, NOW() - INTERVAL 3 HOUR,    'ok',        '192.168.1.40'),
    (2, 5, NOW() - INTERVAL 2 HOUR,    'ok',        '192.168.1.23'),
    (2, 4, NOW() - INTERVAL 90 MINUTE, 'error_plc', '192.168.1.23'),   -- el PLC no respondió
    (2, 4, NOW() - INTERVAL 88 MINUTE, 'ok',        '192.168.1.23'),   -- reintento, esta vez bien
    (4, 2, NOW() - INTERVAL 1 HOUR,    'ok',        '192.168.1.51'),

    -- Ayer
    (2, 5, NOW() - INTERVAL 1 DAY,     'ok',        '192.168.1.23'),
    (4, 2, NOW() - INTERVAL 26 HOUR,   'ok',        '192.168.1.51'),

    -- Hace unos días
    (3, 3, NOW() - INTERVAL 12 DAY,    'ok',        '192.168.1.77'),
    (3, 3, NOW() - INTERVAL 11 DAY,    'denegado',  '192.168.1.77'),   -- Ana ya suspendida
    (5, 1, NOW() - INTERVAL 6 DAY,     'ok',        '192.168.1.40');   -- Pedro, antes de caducar



-- =========================================================
-- 5. COMUNICADOS
-- =========================================================

INSERT INTO comunicados (titulo, texto, activo, fecha_publicacion, autor_id) VALUES
    ('Mantenimiento del ascensor',
     'El jueves de 9:00 a 13:00 el ascensor estará fuera de servicio por mantenimiento. Disculpad las molestias.',
     1, NOW() - INTERVAL 1 DAY, 1);
