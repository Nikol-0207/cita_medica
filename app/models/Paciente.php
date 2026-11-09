<?php
require_once __DIR__ . '/../../config/Database.php';

class Paciente {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function existeCi($ci) {
        $stmt = $this->db->prepare("SELECT 1 FROM persona WHERE ci = :ci LIMIT 1");
        $stmt->execute([':ci' => $ci]);
        return (bool)$stmt->fetchColumn();
    }

    public function existeCorreo($correo) {
        $stmt = $this->db->prepare("SELECT 1 FROM persona WHERE LOWER(correo) = LOWER(:c) LIMIT 1");
        $stmt->execute([':c' => $correo]);
        return (bool)$stmt->fetchColumn();
    }

    /** Id del paciente vinculado a una cuenta de usuario (0 si no está vinculado). */
    public function idDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT pa.id_paciente
             FROM cuenta_usuario u
             JOIN paciente pa ON pa.id_persona = u.id_persona
             WHERE u.id_cuenta_usuario = :id"
        );
        $stmt->execute([':id' => (int)$idCuenta]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Datos personales del paciente vinculado a la cuenta.
     * No incluye información médica ni contacto de emergencia: el paciente
     * solo puede editar sus datos personales desde su perfil.
     */
    public function datosDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT pa.id_paciente, pa.codigo, p.nombres, p.apellidos, p.ci,
                    p.fecha_nacimiento, p.sexo, p.telefono, p.correo, p.direccion,
                    pa.nro_seguro, p.estado
             FROM cuenta_usuario u
             JOIN persona p   ON p.id_persona = u.id_persona
             JOIN paciente pa ON pa.id_persona = p.id_persona
             WHERE u.id_cuenta_usuario = :id"
        );
        $stmt->execute([':id' => (int)$idCuenta]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** ¿Otra persona ya usa ese CI? (se excluye la de la cuenta actual). */
    public function existeCiDeOtro($ci, $idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona p
             JOIN cuenta_usuario u ON u.id_persona = p.id_persona
             WHERE p.ci = :ci
               AND u.id_cuenta_usuario IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':ci' => $ci, ':id' => (int)$idCuenta]);
        return (bool)$stmt->fetchColumn();
    }

    /** Actualiza solo los datos personales de la ficha del paciente. */
    public function actualizarDatos($idCuenta, array $d) {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE persona
                    SET nombres = :nombres,
                        apellidos = :apellidos,
                        ci = :ci,
                        fecha_nacimiento = :fecha,
                        sexo = :sexo,
                        telefono = :telefono,
                        direccion = :direccion
                  WHERE id_persona = (SELECT id_persona FROM cuenta_usuario WHERE id_cuenta_usuario = :id)"
            )->execute([
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':ci'        => $d['ci'],
                ':fecha'     => $d['fecha_nacimiento'],
                ':sexo'      => $d['sexo'],
                ':telefono'  => $d['telefono'],
                ':direccion' => $d['direccion'],
                ':id'        => (int)$idCuenta,
            ]);

            $this->db->prepare(
                "UPDATE paciente
                    SET nro_seguro = :seguro
                  WHERE id_persona = (SELECT id_persona FROM cuenta_usuario WHERE id_cuenta_usuario = :id)"
            )->execute([
                ':seguro' => ($d['nro_seguro'] ?? '') !== '' ? $d['nro_seguro'] : null,
                ':id'     => (int)$idCuenta,
            ]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ficha completa para la pantalla "Ver información": datos personales,
     * información médica y datos de la cuenta (si tiene una vinculada).
     */
    public function obtenerDetalle($idPaciente) {
        $stmt = $this->db->prepare(
            "SELECT pa.id_paciente, u.id_cuenta_usuario, pa.codigo,
                    p.nombres, p.apellidos, p.ci, p.fecha_nacimiento, p.sexo,
                    p.telefono, p.correo, p.direccion, p.estado, p.fecha_registro,
                    pa.nro_seguro, pa.observaciones,
                    u.nombre_usuario AS usuario,
                    p.correo AS correo_cuenta,
                    (SELECT im.tipo_sangre FROM informacion_medica im
                      WHERE im.id_paciente = pa.id_paciente
                      ORDER BY im.fecha_actualizacion DESC NULLS LAST LIMIT 1) AS tipo_sangre,
                    (SELECT im.alergias FROM informacion_medica im
                      WHERE im.id_paciente = pa.id_paciente
                      ORDER BY im.fecha_actualizacion DESC NULLS LAST LIMIT 1) AS alergias,
                    (SELECT MAX(im.fecha_actualizacion) FROM informacion_medica im
                      WHERE im.id_paciente = pa.id_paciente) AS fecha_actualizacion_medica
             FROM paciente pa
             JOIN persona p ON p.id_persona = pa.id_persona
             LEFT JOIN cuenta_usuario u ON u.id_persona = p.id_persona
             WHERE pa.id_paciente = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => (int)$idPaciente]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** Contactos de emergencia registrados para el paciente. */
    public function contactosEmergencia($idPaciente) {
        $stmt = $this->db->prepare(
            "SELECT nombre, parentesco, telefono
             FROM contacto_emergencia
             WHERE id_paciente = :id"
        );
        $stmt->execute([':id' => (int)$idPaciente]);
        return $stmt->fetchAll();
    }

    /** ¿El paciente tiene alguna cita (pasada o futura) con ese médico? */
    public function tieneCitaConMedico($idPaciente, $idMedico) {
        if ($idMedico <= 0) {
            return false;
        }
        $stmt = $this->db->prepare(
            "SELECT 1 FROM cita
             WHERE id_paciente = :pac AND id_medico = :med
             LIMIT 1"
        );
        $stmt->execute([':pac' => (int)$idPaciente, ':med' => (int)$idMedico]);
        return (bool)$stmt->fetchColumn();
    }

    /** ¿Otra persona ya usa ese CI? (excluye la ficha que se está editando). */
    public function existeCiDeOtroPaciente($ci, $idPaciente) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona p
             JOIN paciente pa ON pa.id_persona = p.id_persona
             WHERE p.ci = :ci
               AND pa.id_paciente IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':ci' => $ci, ':id' => (int)$idPaciente]);
        return (bool)$stmt->fetchColumn();
    }

    /** ¿Otra persona ya usa ese correo? (excluye la ficha que se está editando). */
    public function existeCorreoDeOtroPaciente($correo, $idPaciente) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona p
             JOIN paciente pa ON pa.id_persona = p.id_persona
             WHERE LOWER(p.correo) = LOWER(:c)
               AND pa.id_paciente IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':c' => $correo, ':id' => (int)$idPaciente]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Actualiza la ficha completa del paciente (datos personales, información
     * médica y contactos de emergencia) en una sola transacción.
     */
    public function actualizarDetalle($idPaciente, array $d, array $contactos) {
        $nul = fn($x) => ($x !== '' && $x !== null) ? $x : null;

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE persona
                    SET nombres = :nombres,
                        apellidos = :apellidos,
                        ci = :ci,
                        fecha_nacimiento = :fecha,
                        sexo = :sexo,
                        telefono = :telefono,
                        correo = :correo,
                        direccion = :direccion
                  WHERE id_persona = (SELECT id_persona FROM paciente WHERE id_paciente = :id)"
            )->execute([
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':ci'        => $d['ci'],
                ':fecha'     => $d['fecha_nacimiento'],
                ':sexo'      => $d['sexo'],
                ':telefono'  => $d['telefono'],
                ':correo'    => $nul($d['correo']),
                ':direccion' => $d['direccion'],
                ':id'        => (int)$idPaciente,
            ]);

            $this->db->prepare(
                "UPDATE paciente
                    SET nro_seguro = :seguro,
                        observaciones = :obs
                  WHERE id_paciente = :id"
            )->execute([
                ':seguro' => $nul($d['nro_seguro']),
                ':obs'    => $nul($d['observaciones']),
                ':id'     => (int)$idPaciente,
            ]);

            // Información médica: se crea si el paciente aún no tenía ficha.
            $this->db->prepare(
                "INSERT INTO informacion_medica (id_paciente, tipo_sangre, alergias, fecha_actualizacion)
                 SELECT :id, :sangre, :alergias, NOW()
                  WHERE NOT EXISTS (SELECT 1 FROM informacion_medica WHERE id_paciente = :id2)"
            )->execute([
                ':id'       => (int)$idPaciente,
                ':sangre'   => $d['tipo_sangre'],
                ':alergias' => $nul($d['alergias']),
                ':id2'      => (int)$idPaciente,
            ]);

            $this->db->prepare(
                "UPDATE informacion_medica
                    SET tipo_sangre = :sangre,
                        alergias = :alergias,
                        fecha_actualizacion = NOW()
                  WHERE id_paciente = :id"
            )->execute([
                ':sangre'   => $d['tipo_sangre'],
                ':alergias' => $nul($d['alergias']),
                ':id'       => (int)$idPaciente,
            ]);

            // Los contactos se reemplazan por los que se enviaron en el formulario.
            $this->db->prepare("DELETE FROM contacto_emergencia WHERE id_paciente = :id")
                     ->execute([':id' => (int)$idPaciente]);

            if ($contactos) {
                $stmt = $this->db->prepare(
                    "INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono)
                     VALUES (:id, :nombre, :parentesco, :telefono)"
                );
                foreach ($contactos as $c) {
                    $stmt->execute([
                        ':id'         => (int)$idPaciente,
                        ':nombre'     => $c['nombre'],
                        ':parentesco' => $nul($c['parentesco']),
                        ':telefono'   => $c['telefono'],
                    ]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Pacientes activos para elegir en el formulario de nueva cita. */
    public function activos() {
        return $this->db->query(
            "SELECT pa.id_paciente, pa.codigo, p.nombres, p.apellidos, p.ci
             FROM paciente pa
             JOIN persona p ON p.id_persona = pa.id_persona
             WHERE p.estado = 'Activo'
             ORDER BY p.apellidos, p.nombres"
        )->fetchAll();
    }

    public function listar() {
        $sql = "SELECT pa.id_paciente, pa.codigo, p.nombres, p.apellidos, p.ci,
                       p.telefono, p.correo, p.estado, im.tipo_sangre
                FROM paciente pa
                JOIN persona p ON p.id_persona = pa.id_persona
                LEFT JOIN informacion_medica im ON im.id_paciente = pa.id_paciente
                ORDER BY p.apellidos, p.nombres";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Solo los pacientes que tienen (o tuvieron) una cita con ese médico,
     * con el total de sus citas y la próxima fecha. Lo usa el rol Médico.
     * Cada placeholder se usa una sola vez (PDO con PostgreSQL no repite nombres).
     */
    public function listarDelMedico($idMedico) {
        $sql = "SELECT pa.id_paciente, pa.codigo, p.nombres, p.apellidos, p.ci,
                       p.telefono, p.correo, p.estado, im.tipo_sangre,
                       (SELECT COUNT(*) FROM cita c
                         WHERE c.id_paciente = pa.id_paciente AND c.id_medico = :med1) AS total_citas,
                       (SELECT MIN(c.fecha) FROM cita c
                         WHERE c.id_paciente = pa.id_paciente AND c.id_medico = :med2
                           AND c.fecha >= CURRENT_DATE) AS proxima_fecha
                FROM paciente pa
                JOIN persona p ON p.id_persona = pa.id_persona
                LEFT JOIN informacion_medica im ON im.id_paciente = pa.id_paciente
                WHERE EXISTS (SELECT 1 FROM cita c
                               WHERE c.id_paciente = pa.id_paciente AND c.id_medico = :med3)
                ORDER BY p.apellidos, p.nombres";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':med1' => (int)$idMedico, ':med2' => (int)$idMedico, ':med3' => (int)$idMedico]);
        return $stmt->fetchAll();
    }

    public function existeCorreoCuenta($correo) {
        $stmt = $this->db->prepare("SELECT 1 FROM persona WHERE LOWER(correo) = LOWER(:c) LIMIT 1");
        $stmt->execute([':c' => $correo]);
        return (bool)$stmt->fetchColumn();
    }

    public function existeNombreCuenta($nombre) {
        $stmt = $this->db->prepare("SELECT 1 FROM cuenta_usuario WHERE LOWER(nombre_usuario) = LOWER(:n) LIMIT 1");
        $stmt->execute([':n' => $nombre]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Crea persona + (cuenta opcional) + paciente + información médica +
     * contacto de emergencia en una transacción.
     */
    public function crear(array $d, $hash = null) {
    $nul = fn($x) => ($x !== '' && $x !== null) ? $x : null;

    $this->db->beginTransaction();
    try {
        // --- Persona ---
        $stmt = $this->db->prepare(
            "INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo,
                                  telefono, correo, direccion, estado)
             VALUES (:ci, :nombres, :apellidos, :fecha, :sexo,
                     :telefono, :correo, :direccion, 'Activo')
             RETURNING id_persona"
        );
        $stmt->execute([
            ':ci'        => $d['ci'],
            ':nombres'   => $d['nombres'],
            ':apellidos' => $d['apellidos'],
            ':fecha'     => $d['fecha_nacimiento'],
            ':sexo'      => $d['sexo'],
            ':telefono'  => $d['telefono'],
            ':correo'    => $nul($d['correo']),
            ':direccion' => $d['direccion'],
        ]);
        $idPersona = (int)$stmt->fetchColumn();

        // --- Cuenta de usuario (opcional) ---
        if ($hash !== null) {
            $idRol = $this->db->query("SELECT id_rol FROM rol WHERE nombre ILIKE '%pacient%' LIMIT 1")->fetchColumn();
            if (!$idRol) {
                throw new RuntimeException('No existe el rol Paciente en la tabla rol.');
            }
            $stmt = $this->db->prepare(
                "INSERT INTO cuenta_usuario (id_persona, id_rol, nombre_usuario, contrasena_hash)
                 VALUES (:persona, :rol, :usuario, :hash)"
            );
            $stmt->execute([
                ':persona' => $idPersona,
                ':rol'     => $idRol,
                ':usuario' => $d['usuario_nombre'],
                ':hash'    => $hash,
            ]);
        }

        // --- Paciente ---
        $id     = $this->nextId('paciente', 'id_paciente');
        $codigo = 'PAC-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);

        $this->db->prepare(
            "INSERT INTO paciente (id_paciente, id_persona, codigo, nro_seguro, observaciones)
             VALUES (:id, :persona, :codigo, :seguro, :obs)"
        )->execute([
            ':id'      => $id,
            ':persona' => $idPersona,
            ':codigo'  => $codigo,
            ':seguro'  => $nul($d['nro_seguro']),
            ':obs'     => $nul($d['observaciones']),
        ]);

        // --- Información médica ---
        $this->db->prepare(
            "INSERT INTO informacion_medica (id_paciente, tipo_sangre, alergias, fecha_actualizacion)
             VALUES (:id, :sangre, :alergias, NOW())"
        )->execute([
            ':id'       => $id,
            ':sangre'   => $d['tipo_sangre'],
            ':alergias' => $nul($d['alergias']),
        ]);

        // --- Contacto de emergencia (opcional) ---
        if ($d['contacto_nombre'] !== '') {
            $this->db->prepare(
                "INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono)
                 VALUES (:id, :nombre, :parentesco, :telefono)"
            )->execute([
                ':id'         => $id,
                ':nombre'     => $d['contacto_nombre'],
                ':parentesco' => $nul($d['contacto_parentesco']),
                ':telefono'   => $d['contacto_telefono'],
            ]);
        }

        $this->db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        throw $e;
    }
}

    /** Siguiente valor de una secuencia (para armar el código del paciente). */
    private function nextId($tabla, $columna) {
        return (int)$this->db->query(
            "SELECT nextval(pg_get_serial_sequence('{$tabla}', '{$columna}'))"
        )->fetchColumn();
    }
}
