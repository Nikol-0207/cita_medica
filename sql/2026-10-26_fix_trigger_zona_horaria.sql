-- =============================================================================
-- Corregir el trigger de citas en fechas/horas pasadas (zona horaria)
-- =============================================================================
-- Problema:
--   fn_trg_validar_fecha_futura_cita() compara la fecha/hora de la cita contra
--   now() usando la zona horaria de la sesión de PostgreSQL (normalmente UTC en
--   Neon). La aplicación trabaja en America/La_Paz (UTC-4).
--
--   Ejemplo: 15:00 en La Paz = 19:00 UTC. Si en UTC son las 18:49, el trigger
--   interpreta "15:00" como UTC y cree que ya pasó, lanzando:
--     "No se pueden registrar o reprogramar citas en fechas u horas pasadas."
--
-- Solución:
--   Recrear la función interpretando NEW.fecha + NEW.hora_inicio como hora
--   local de La Paz y compararla con el instante actual (timestamptz).
--   El trigger existente sigue apuntando a esta función: no hay que recrearlo.
--
-- Ejecutar UNA sola vez en el SQL Editor de Neon, en la misma rama que usa
-- Render.
-- =============================================================================

-- 0) (Opcional) Ver la definición actual, por si tuviera otras validaciones:
-- SELECT pg_get_functiondef('public.fn_trg_validar_fecha_futura_cita()'::regprocedure);

CREATE OR REPLACE FUNCTION public.fn_trg_validar_fecha_futura_cita()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    zona_horaria text := 'America/La_Paz';
    inicio_cita  timestamptz;
BEGIN
    -- Si solo cambió el estado (por ejemplo a Atendida o Inasistencia), no se
    -- valida la fecha: la cita ya existía y puede estar en el pasado.
    IF TG_OP = 'UPDATE'
       AND NEW.fecha IS NOT DISTINCT FROM OLD.fecha
       AND NEW.hora_inicio IS NOT DISTINCT FROM OLD.hora_inicio THEN
        RETURN NEW;
    END IF;

    -- fecha (date) + hora_inicio (time) => timestamp local; AT TIME ZONE lo
    -- convierte al instante real en la zona de la clínica.
    inicio_cita := ((NEW.fecha + NEW.hora_inicio) AT TIME ZONE zona_horaria);

    IF inicio_cita <= now() THEN
        RAISE EXCEPTION 'No se pueden registrar o reprogramar citas en fechas u horas pasadas.';
    END IF;

    RETURN NEW;
END;
$$;

-- 1) Verificación: la cita de hoy a las 15:00 debe quedar en el futuro
--    mientras en La Paz no hayan pasado las 15:00.
-- SELECT ((DATE '2026-10-26' + TIME '15:00') AT TIME ZONE 'America/La_Paz') AS inicio_cita,
--        now() AS ahora_utc,
--        now() AT TIME ZONE 'America/La_Paz' AS ahora_la_paz;
