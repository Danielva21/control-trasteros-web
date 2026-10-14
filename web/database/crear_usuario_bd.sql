-- #########################################################
-- crear_usuario_bd.sql — USUARIO DE MARIADB QUE USA LA WEB
--
-- Se ejecuta UNA vez al instalar, DESPUÉS de trasteros_schema.sql.
--
-- 1. Cambia 'CAMBIA_ESTA_CONTRASEÑA' (las DOS veces) por una
--    contraseña larga y aleatoria. Por ejemplo, genera una con:
--        php -r "echo bin2hex(random_bytes(12));"
-- 2. Ejecuta (desde la carpeta TRASTEROS_WEB):
--        mysql -u root -p < web/database/crear_usuario_bd.sql
-- 3. Pon esa MISMA contraseña en web/includes/config.php (BD_PASSWORD).
--
-- Este usuario solo puede LEER, AÑADIR y MODIFICAR en la base de
-- datos "trasteros". NO puede borrar nada ni tocar otras bases de
-- datos: si alguien encontrara un fallo en la web, el daño posible
-- es mucho menor que con "root".
-- #########################################################

CREATE USER IF NOT EXISTS 'trasteros_web'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CONTRASEÑA';
CREATE USER IF NOT EXISTS 'trasteros_web'@'127.0.0.1' IDENTIFIED BY 'CAMBIA_ESTA_CONTRASEÑA';

GRANT SELECT, INSERT, UPDATE ON trasteros.* TO 'trasteros_web'@'localhost';
GRANT SELECT, INSERT, UPDATE ON trasteros.* TO 'trasteros_web'@'127.0.0.1';

FLUSH PRIVILEGES;
