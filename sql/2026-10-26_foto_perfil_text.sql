-- =============================================================================
-- Foto de perfil persistente en Neon
-- =============================================================================
-- Render usa un filesystem efímero: los archivos subidos a
-- public/uploads/perfiles se borran en cada deploy o reinicio.
--
-- A partir de este cambio, la imagen se guarda dentro de la propia base de
-- datos como un data URI (data:image/jpeg;base64,...). Para que quepa hay que
-- ampliar la columna foto_perfil de VARCHAR(255) a TEXT.
--
-- Ejecutar UNA sola vez en el SQL Editor de Neon (o con psql) antes de
-- desplegar el código nuevo.
-- =============================================================================

ALTER TABLE cuenta_usuario
    ALTER COLUMN foto_perfil TYPE TEXT;

-- Verificación (opcional):
-- SELECT id_cuenta_usuario, nombre,
--        CASE WHEN foto_perfil LIKE 'data:%' THEN 'imagen en BD'
--             ELSE COALESCE(foto_perfil, '(sin foto)') END AS foto
-- FROM cuenta_usuario
-- ORDER BY id_cuenta_usuario;
