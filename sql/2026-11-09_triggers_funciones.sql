-- ============================================================
-- Triggers, funciones, procedimientos e índices del nuevo modelo
-- (copiado del script proporcionado el 2026-11-09).
-- Nota: la app define app.user_id (set_config ... true) antes de
-- escribir en `cita`, porque fn_usuario_actual() lo exige.
-- ============================================================

--======================
--TRIGGERS PD Y FUNCIONES
--======================
CREATE OR REPLACE FUNCTION fn_usuario_actual() RETURNS INT AS $$
DECLARE v TEXT := current_setting('app.user_id', true);
BEGIN
    IF v IS NULL OR v = '' THEN
        RAISE EXCEPTION 'No se definió el usuario de la operación (app.user_id)';
    END IF;
    RETURN v::INT;
END;
$$ LANGUAGE plpgsql STABLE;

CREATE OR REPLACE FUNCTION fn_trg_historial_cita() RETURNS TRIGGER AS $$
DECLARE v_reprogramada BOOLEAN;
BEGIN
    v_reprogramada := OLD.fecha <> NEW.fecha
                   OR OLD.hora_inicio <> NEW.hora_inicio
                   OR OLD.hora_fin <> NEW.hora_fin;

    IF OLD.estado <> NEW.estado OR v_reprogramada THEN
        INSERT INTO historial_cita (id_cita, id_cuenta_usuario, estado_anterior, estado_nuevo,
                                    fecha_anterior, hora_inicio_anterior, hora_fin_anterior, motivo)
        VALUES (OLD.id_cita, fn_usuario_actual(), OLD.estado, NEW.estado,
                CASE WHEN v_reprogramada THEN OLD.fecha       END,
                CASE WHEN v_reprogramada THEN OLD.hora_inicio END,
                CASE WHEN v_reprogramada THEN OLD.hora_fin    END,
                CASE WHEN NEW.estado = 'Cancelada' THEN NEW.motivo_cancelacion
                     ELSE NULLIF(current_setting('app.motivo', true), '') END);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_historial_cita
    AFTER UPDATE ON cita FOR EACH ROW EXECUTE FUNCTION fn_trg_historial_cita();


CREATE OR REPLACE FUNCTION fn_trg_validar_fecha_futura_cita() RETURNS TRIGGER AS $$
DECLARE v_ahora TIMESTAMP := NOW() AT TIME ZONE 'America/La_Paz';
BEGIN
    IF (NEW.fecha < v_ahora::DATE)
       OR (NEW.fecha = v_ahora::DATE AND NEW.hora_inicio < v_ahora::TIME) THEN
        RAISE EXCEPTION 'No se pueden registrar o reprogramar citas en fechas u horas pasadas.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_trg_validar_medico_activo() RETURNS TRIGGER AS $$
BEGIN
    IF (SELECT p.estado FROM medico m JOIN persona p ON p.id_persona = m.id_persona
         WHERE m.id_medico = NEW.id_medico) IS DISTINCT FROM 'Activo' THEN
        RAISE EXCEPTION 'El médico asignado está inactivo y no puede recibir citas.';
    END IF;
    IF (SELECT p.estado FROM paciente pa JOIN persona p ON p.id_persona = pa.id_persona
         WHERE pa.id_paciente = NEW.id_paciente) IS DISTINCT FROM 'Activo' THEN
        RAISE EXCEPTION 'El paciente está inactivo y no puede reservar citas.';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_trg_auditoria_citas() RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO bitacora_acciones (id_cuenta_usuario, accion, modulo, tabla_afectada,
                                       id_registro_afectado, valor_nuevo)
        VALUES (fn_usuario_actual(), 'Crear', 'Citas', 'cita', NEW.id_cita, to_jsonb(NEW));
    ELSE
        INSERT INTO bitacora_acciones (id_cuenta_usuario, accion, modulo, tabla_afectada,
                                       id_registro_afectado, valor_anterior, valor_nuevo)
        VALUES (fn_usuario_actual(),
                CASE WHEN NEW.estado = 'Cancelada' AND OLD.estado <> 'Cancelada'
                     THEN 'Cancelar' ELSE 'Modificar' END,
                'Citas', 'cita', NEW.id_cita, to_jsonb(OLD), to_jsonb(NEW));
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_trg_calcular_fichas_horario() RETURNS TRIGGER AS $$
DECLARE v_max INT;
BEGIN
    IF NEW.duracion_cita IS NULL OR NEW.duracion_cita <= 0 THEN
        NEW.duracion_cita := 30;
    END IF;
    IF NEW.hora_fin <= NEW.hora_inicio THEN
        RAISE EXCEPTION 'La hora final debe ser posterior a la hora inicial.';
    END IF;

    v_max := FLOOR(EXTRACT(EPOCH FROM (NEW.hora_fin - NEW.hora_inicio)) / 60 / NEW.duracion_cita);
    IF v_max < 1 THEN
        RAISE EXCEPTION 'La duración de la cita excede el horario de atención.';
    END IF;

    IF NEW.cantidad_fichas IS NULL OR NEW.cantidad_fichas <= 0 THEN
        NEW.cantidad_fichas := v_max;
    ELSIF NEW.cantidad_fichas > v_max THEN
        RAISE EXCEPTION 'Con este horario solo caben % fichas.', v_max;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION fn_dia_bloqueado(p_medico INT, p_fecha DATE, p_ini TIME, p_fin TIME)
RETURNS BOOLEAN AS $$
    SELECT EXISTS (
        SELECT 1 FROM dia_bloqueado b
        WHERE p_fecha BETWEEN b.fecha_bloqueo AND COALESCE(b.fecha_fin, b.fecha_bloqueo)
          AND (b.id_medico IS NULL OR b.id_medico = p_medico)
          AND (b.todo_dia OR (p_ini < b.hora_fin AND p_fin > b.hora_inicio))
    );
$$ LANGUAGE sql STABLE;
-- 2. Franjas libres de un horario en una fecha
CREATE OR REPLACE FUNCTION fn_slots_disponibles(p_horario INT, p_fecha DATE)
RETURNS TABLE (slot_inicio TIME, slot_fin TIME) AS $$
    SELECT s::TIME, (s + x.d)::TIME
    FROM horario_atencion h
    JOIN medico_especialidad me ON me.id_medico_especialidad = h.id_medico_especialidad
    CROSS JOIN LATERAL (SELECT (h.duracion_cita || ' minutes')::INTERVAL AS d) x
    CROSS JOIN LATERAL generate_series(p_fecha + h.hora_inicio,
                                       p_fecha + h.hora_fin - x.d, x.d) AS s
    WHERE h.id_horario = p_horario
      AND h.estado = 'Activo'
      AND EXTRACT(ISODOW FROM p_fecha) = h.dia_semana
      AND p_fecha BETWEEN h.fecha_inicio AND COALESCE(h.fecha_fin, 'infinity'::DATE)
      -- no ofrecer franjas que ya pasaron
      AND s > (NOW() AT TIME ZONE 'America/La_Paz')
      -- ni franjas bloqueadas
      AND NOT fn_dia_bloqueado(me.id_medico, p_fecha, s::TIME, (s + x.d)::TIME)
      -- ni franjas ya ocupadas
      AND NOT EXISTS (SELECT 1 FROM cita c
                       WHERE c.id_horario = h.id_horario
                         AND c.fecha = p_fecha
                         AND c.hora_inicio = s::TIME
                         AND c.estado <> 'Cancelada')
    ORDER BY s;
$$ LANGUAGE sql STABLE;

CREATE FUNCTION fn_obtener_horarios_disponibles(p_id_medico INT, p_fecha DATE)
RETURNS TABLE (id_horario INT, id_especialidad INT, slot_inicio TIME, slot_fin TIME) AS $$
    SELECT h.id_horario, me.id_especialidad, s.slot_inicio, s.slot_fin
    FROM medico_especialidad me
    JOIN horario_atencion h ON h.id_medico_especialidad = me.id_medico_especialidad
    CROSS JOIN LATERAL fn_slots_disponibles(h.id_horario, p_fecha) s
    WHERE me.id_medico = p_id_medico AND me.estado = 'Activo'
    ORDER BY s.slot_inicio;
$$ LANGUAGE sql STABLE;

CREATE OR REPLACE FUNCTION fn_programar_cita(
    p_id_horario INT, p_id_paciente INT, p_id_cuenta INT,
    p_fecha DATE, p_hora_inicio TIME, p_motivo TEXT)
RETURNS TABLE (o_id_cita INT, o_codigo VARCHAR) AS $$
DECLARE v_medico INT; v_dur INT; v_id INT; v_cod VARCHAR;
BEGIN
    PERFORM set_config('app.user_id', p_id_cuenta::TEXT, true);

    SELECT me.id_medico, h.duracion_cita INTO v_medico, v_dur
      FROM horario_atencion h
      JOIN medico_especialidad me ON me.id_medico_especialidad = h.id_medico_especialidad
     WHERE h.id_horario = p_id_horario;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'El horario % no existe', p_id_horario;
    END IF;

    INSERT INTO cita (id_paciente, id_medico, id_horario, id_cuenta_registro,
                      fecha, hora_inicio, hora_fin, motivo)
    VALUES (p_id_paciente, v_medico, p_id_horario, p_id_cuenta, p_fecha, p_hora_inicio,
            (p_hora_inicio + (v_dur || ' minutes')::INTERVAL)::TIME, p_motivo)
    RETURNING id_cita, codigo INTO v_id, v_cod;

    o_id_cita := v_id;
    o_codigo  := v_cod;
    RETURN NEXT;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE PROCEDURE sp_cancelar_cita(p_id_cita INT, p_id_usuario INT, p_motivo TEXT)
LANGUAGE plpgsql AS $$
DECLARE v_estado VARCHAR(15);
BEGIN
    PERFORM set_config('app.user_id', p_id_usuario::TEXT, true);

    SELECT estado INTO v_estado FROM cita WHERE id_cita = p_id_cita FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'La cita % no existe.', p_id_cita;
    ELSIF v_estado IN ('Atendida','No asistió') THEN
        RAISE EXCEPTION 'No se puede cancelar una cita en estado %.', v_estado;
    ELSIF v_estado = 'Cancelada' THEN
        RAISE EXCEPTION 'La cita ya se encuentra cancelada.';
    ELSIF COALESCE(TRIM(p_motivo), '') = '' THEN
        RAISE EXCEPTION 'Debe indicar el motivo de la cancelación.';
    END IF;

    UPDATE cita SET estado = 'Cancelada', motivo_cancelacion = p_motivo
     WHERE id_cita = p_id_cita;       -- fecha_actualizacion, historial y auditoría los hacen los triggers
END;
$$;

-- ============================================================
-- 1. Validación de cita (versión final, sin el control de fecha pasada,
--    que ya hace fn_trg_validar_fecha_futura_cita)
-- ============================================================
CREATE OR REPLACE FUNCTION fn_validar_cita() RETURNS trigger AS $$
DECLARE
    h          RECORD;
    v_ocupadas INT;
BEGIN
    SELECT me.id_medico, ha.dia_semana, ha.hora_inicio, ha.hora_fin,
           ha.cantidad_fichas, ha.estado, ha.fecha_inicio, ha.fecha_fin
      INTO h
      FROM horario_atencion ha
      JOIN medico_especialidad me ON me.id_medico_especialidad = ha.id_medico_especialidad
     WHERE ha.id_horario = NEW.id_horario
       FOR UPDATE OF ha;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El horario % no existe', NEW.id_horario;
    END IF;
    IF NEW.id_medico <> h.id_medico THEN
        RAISE EXCEPTION 'El médico de la cita no coincide con el del horario';
    END IF;
    IF EXTRACT(ISODOW FROM NEW.fecha) <> h.dia_semana THEN
        RAISE EXCEPTION 'La fecha no corresponde al día de atención del horario';
    END IF;
    IF NEW.hora_inicio < h.hora_inicio OR NEW.hora_fin > h.hora_fin THEN
        RAISE EXCEPTION 'La hora de la cita está fuera del horario de atención';
    END IF;

    IF NEW.estado <> 'Cancelada' THEN
        IF h.estado <> 'Activo' THEN
            RAISE EXCEPTION 'El horario está inactivo';
        END IF;
        IF NEW.fecha < h.fecha_inicio
           OR (h.fecha_fin IS NOT NULL AND NEW.fecha > h.fecha_fin) THEN
            RAISE EXCEPTION 'La fecha está fuera de la vigencia del horario';
        END IF;
        IF fn_dia_bloqueado(NEW.id_medico, NEW.fecha, NEW.hora_inicio, NEW.hora_fin) THEN
            RAISE EXCEPTION 'El médico no atiende en esa fecha (día bloqueado)';
        END IF;

        SELECT COUNT(*) INTO v_ocupadas
          FROM cita
         WHERE id_horario = NEW.id_horario AND fecha = NEW.fecha
           AND estado <> 'Cancelada'
           AND id_cita IS DISTINCT FROM NEW.id_cita;
        IF v_ocupadas >= h.cantidad_fichas THEN
            RAISE EXCEPTION 'No quedan fichas disponibles para ese horario';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;


-- ============================================================
-- 2. Funciones que faltan
-- ============================================================
CREATE OR REPLACE FUNCTION fn_cita_actualizada() RETURNS trigger AS $$
BEGIN
    NEW.fecha_actualizacion := NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_consulta_validar() RETURNS trigger AS $$
DECLARE v_estado VARCHAR(15);
BEGIN
    SELECT estado INTO v_estado FROM cita WHERE id_cita = NEW.id_cita;
    IF v_estado NOT IN ('Pendiente','Confirmada') THEN
        RAISE EXCEPTION 'No se puede registrar consulta: la cita está en estado %', v_estado;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_fichas_disponibles(p_id_horario INT, p_fecha DATE)
RETURNS TABLE (total_fichas INT, fichas_ocupadas INT, fichas_disponibles INT) AS $$
DECLARE v_total INT; v_ocupadas INT;
BEGIN
    SELECT cantidad_fichas INTO v_total FROM horario_atencion WHERE id_horario = p_id_horario;
    SELECT COUNT(*) INTO v_ocupadas FROM cita
     WHERE id_horario = p_id_horario AND fecha = p_fecha AND estado <> 'Cancelada';
    total_fichas       := COALESCE(v_total, 0);
    fichas_ocupadas    := COALESCE(v_ocupadas, 0);
    fichas_disponibles := GREATEST(0, total_fichas - fichas_ocupadas);
    RETURN NEXT;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_cancelar_por_bloqueo(p_bloqueo INT) RETURNS INT AS $$
DECLARE
    b dia_bloqueado%ROWTYPE;
    r RECORD;
    n INT := 0;
BEGIN
    SELECT * INTO b FROM dia_bloqueado WHERE id_dia_bloqueado = p_bloqueo;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'El bloqueo % no existe', p_bloqueo;
    END IF;
    PERFORM set_config('app.user_id', b.id_cuenta_registro::TEXT, true);

    FOR r IN
        SELECT c.id_cita, c.fecha, c.id_paciente
          FROM cita c
         WHERE c.estado IN ('Pendiente','Confirmada')
           AND c.fecha BETWEEN b.fecha_bloqueo AND COALESCE(b.fecha_fin, b.fecha_bloqueo)
           AND (b.id_medico IS NULL OR c.id_medico = b.id_medico)
           AND (b.todo_dia OR (c.hora_inicio < b.hora_fin AND c.hora_fin > b.hora_inicio))
           FOR UPDATE
    LOOP
        -- el historial y la auditoría los registran los triggers
        UPDATE cita
           SET estado = 'Cancelada',
               motivo_cancelacion = 'Bloqueo: ' || b.tipo || COALESCE(' - ' || b.motivo, '')
         WHERE id_cita = r.id_cita;

        INSERT INTO notificacion (id_cuenta_usuario, id_cita, tipo, mensaje)
        SELECT u.id_cuenta_usuario, r.id_cita, 'Cancelación',
               'Su cita del ' || to_char(r.fecha, 'DD/MM/YYYY')
               || ' fue cancelada (' || b.tipo || COALESCE(': ' || b.motivo, '') || ').'
          FROM paciente pa
          JOIN cuenta_usuario u ON u.id_persona = pa.id_persona
         WHERE pa.id_paciente = r.id_paciente AND u.notificaciones_activas;

        n := n + 1;
    END LOOP;
    RETURN n;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_marcar_no_asistio() RETURNS INT AS $$
DECLARE n INT;
BEGIN
    PERFORM set_config('app.user_id',
        (SELECT u.id_cuenta_usuario::TEXT FROM cuenta_usuario u
           JOIN rol r ON r.id_rol = u.id_rol
          WHERE r.nombre = 'Administrador' ORDER BY u.id_cuenta_usuario LIMIT 1), true);

    UPDATE cita SET estado = 'No asistió'
     WHERE estado IN ('Pendiente','Confirmada')
       AND (fecha + hora_fin) < (NOW() AT TIME ZONE 'America/La_Paz');
    GET DIAGNOSTICS n = ROW_COUNT;
    RETURN n;
END;
$$ LANGUAGE plpgsql;


-- ============================================================
-- 3. Índice
-- ============================================================
CREATE UNIQUE INDEX IF NOT EXISTS uq_cita_medico_hora
    ON cita (id_medico, fecha, hora_inicio) WHERE estado <> 'Cancelada';


-- ============================================================
-- 4. Triggers (los que aún no existen)
-- ============================================================

CREATE TRIGGER trg_validar_cita
    BEFORE INSERT OR UPDATE OF id_medico, id_horario, fecha, hora_inicio, hora_fin
    ON cita FOR EACH ROW EXECUTE FUNCTION fn_validar_cita();

CREATE TRIGGER trg_validar_fecha_futura_cita
    BEFORE INSERT OR UPDATE OF fecha, hora_inicio
    ON cita FOR EACH ROW EXECUTE FUNCTION fn_trg_validar_fecha_futura_cita();

CREATE TRIGGER trg_validar_medico_activo
    BEFORE INSERT ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_trg_validar_medico_activo();

CREATE TRIGGER trg_cita_actualizada
    BEFORE UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_cita_actualizada();

CREATE TRIGGER trg_auditoria_citas
    AFTER INSERT OR UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_trg_auditoria_citas();

CREATE TRIGGER trg_consulta_validar
    BEFORE INSERT ON consulta
    FOR EACH ROW EXECUTE FUNCTION fn_consulta_validar();

CREATE TRIGGER trg_calcular_fichas_horario
    BEFORE INSERT OR UPDATE OF hora_inicio, hora_fin, duracion_cita, cantidad_fichas
    ON horario_atencion
    FOR EACH ROW EXECUTE FUNCTION fn_trg_calcular_fichas_horario();
