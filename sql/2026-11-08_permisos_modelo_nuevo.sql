-- ============================================================
-- Ajustes de permisos para el nuevo modelo de BD
-- (persona + especialización medico/paciente, sin tabla recepcionista,
--  cuenta_usuario con nombre_usuario / contrasena_hash)
--
-- Ejecutar en la MISMA base de datos que ya tiene el DDL + POBLACION.
-- Todas las sentencias son idempotentes (ON CONFLICT DO NOTHING).
-- ============================================================

-- ------------------------------------------------------------
-- 1. Permisos que la aplicación usa pero el seed no traía
-- ------------------------------------------------------------
INSERT INTO permiso (codigo, nombre, descripcion) VALUES
  ('citas.ver',            'Ver citas médicas',        'Consultar el listado de citas según el rol'),
  ('medicos.ver',          'Ver médicos',              'Consultar el listado de médicos'),
  ('dashboard',            'Ver el panel',             'Acceder al panel de inicio / dashboard'),
  ('dashboard.personas',   'Ver métricas de personas', 'Ver las tarjetas de recepcionistas, pacientes y médicos')
ON CONFLICT (codigo) DO NOTHING;

-- ------------------------------------------------------------
-- 2. Asignaciones por rol
-- ------------------------------------------------------------
-- Recepcionista: además de lo que ya trae el seed (pacientes.crear,
-- pacientes.ver, citas.reservar, horarios.ver, config.personal).
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM (VALUES
  ('Recepcionista', 'citas.ver'),
  ('Recepcionista', 'citas.gestionar'),
  ('Recepcionista', 'pacientes.editar'),
  ('Recepcionista', 'medicos.ver'),
  ('Recepcionista', 'dashboard'),
  ('Recepcionista', 'dashboard.personas'),
  -- Médico: ve su agenda (el seed solo le daba horarios.ver_propios)
  ('Medico',        'citas.ver'),
  -- Paciente: ve sus propias citas
  ('Paciente',      'citas.ver')
) AS v(rol, permiso)
JOIN rol r     ON r.nombre = v.rol
JOIN permiso p ON p.codigo = v.permiso
ON CONFLICT DO NOTHING;

-- ------------------------------------------------------------
-- 3. La foto de perfil se guarda como data URI (varios MB):
--    re-ejecutar también en una BD recién creada con el DDL nuevo.
-- ------------------------------------------------------------
ALTER TABLE cuenta_usuario ALTER COLUMN foto_perfil TYPE TEXT;
