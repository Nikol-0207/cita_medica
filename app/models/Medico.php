<?php
require_once __DIR__ . '/../../config/Database.php';

class Medico {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function especialidadesActivas() {
        return $this->db->query(
            "SELECT id_especialidad, nombre FROM especialidades WHERE estado = 'Activo' ORDER BY nombre"
        )->fetchAll();
    }

    /**
     * Id del médico vinculado a una cuenta de usuario (0 si la cuenta no tiene médico).
     * Es lo que permite que un médico solo vea SU agenda y SUS pacientes.
     */
    public function idDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT m.id_medico
             FROM cuenta_usuario u
             JOIN medico m ON m.id_persona = u.id_persona
             WHERE u.id_cuenta_usuario = :id"
        );
        $stmt->execute([':id' => (int)$idCuenta]);
        return (int)$stmt->fetchColumn();
    }

    /** Datos profesionales del médico de la cuenta (para su perfil de solo lectura). */
    public function datosDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT m.id_medico, m.codigo, p.nombres, p.apellidos, m.registro_profesional,
                    p.telefono, p.correo, p.ci, p.estado,
                    STRING_AGG(e.nombre, ' | ' ORDER BY e.nombre) AS especialidades
             FROM cuenta_usuario u
             JOIN medico m  ON m.id_persona = u.id_persona
             JOIN persona p ON p.id_persona = m.id_persona
             LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico AND me.estado = 'Activo'
             LEFT JOIN especialidades e       ON e.id_especialidad = me.id_especialidad
             WHERE u.id_cuenta_usuario = :id
             GROUP BY m.id_medico, m.codigo, p.nombres, p.apellidos,
                      m.registro_profesional, p.telefono, p.correo, p.ci, p.estado"
        );
        $stmt->execute([':id' => (int)$idCuenta]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function existeRegistro($registro) {
        $stmt = $this->db->prepare("SELECT 1 FROM medico WHERE LOWER(registro_profesional) = LOWER(:r) LIMIT 1");
        $stmt->execute([':r' => $registro]);
        return (bool)$stmt->fetchColumn();
    }

    /** El CI es único en persona. */
    public function existeCi($ci) {
        $stmt = $this->db->prepare("SELECT 1 FROM persona WHERE ci = :ci LIMIT 1");
        $stmt->execute([':ci' => $ci]);
        return (bool)$stmt->fetchColumn();
    }

    /** ¿Otra persona ya usa ese CI? (excluye al médico que se edita). */
    public function existeCiDeOtro($ci, $idMedico) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona p
             JOIN medico m ON m.id_persona = p.id_persona
             WHERE p.ci = :ci
               AND m.id_medico IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':ci' => $ci, ':id' => (int)$idMedico]);
        return (bool)$stmt->fetchColumn();
    }

    /** ¿Otro correo ya está en uso por una persona distinta? */
    public function existeCorreoDeOtro($correo, $idMedico) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona p
             JOIN medico m ON m.id_persona = p.id_persona
             WHERE LOWER(p.correo) = LOWER(:c)
               AND m.id_medico IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':c' => $correo, ':id' => (int)$idMedico]);
        return (bool)$stmt->fetchColumn();
    }

    /** El médico solo puede actualizar su celular desde su perfil (el correo
     *  de inicio de sesión y de contacto se edita en «Datos de la cuenta»). */
    public function actualizarContacto($idCuenta, $telefono) {
        $stmt = $this->db->prepare(
            "UPDATE persona
                SET telefono = :telefono
              WHERE id_persona = (SELECT id_persona FROM cuenta_usuario WHERE id_cuenta_usuario = :id)"
        );
        $stmt->execute([
            ':telefono' => $telefono,
            ':id'       => (int)$idCuenta,
        ]);
        return $stmt->rowCount();
    }

    /** Ficha completa para la pantalla "Ver información". */
    public function obtenerDetalle($idMedico) {
        $stmt = $this->db->prepare(
            "SELECT m.id_medico, u.id_cuenta_usuario, m.codigo,
                    p.nombres, p.apellidos, p.ci,
                    m.registro_profesional, p.telefono, p.correo,
                    p.estado, p.fecha_registro,
                    u.nombre_usuario AS usuario,
                    p.correo AS correo_cuenta
             FROM medico m
             JOIN persona p ON p.id_persona = m.id_persona
             LEFT JOIN cuenta_usuario u ON u.id_persona = p.id_persona
             WHERE m.id_medico = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => (int)$idMedico]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** Especialidades activas asignadas al médico. */
    public function especialidadesDe($idMedico) {
        $stmt = $this->db->prepare(
            "SELECT e.id_especialidad, e.nombre
             FROM medico_especialidad me
             JOIN especialidades e ON e.id_especialidad = me.id_especialidad
             WHERE me.id_medico = :id AND me.estado = 'Activo'
             ORDER BY e.nombre"
        );
        $stmt->execute([':id' => (int)$idMedico]);
        return $stmt->fetchAll();
    }

    /**
     * Opciones para el formulario: especialidades activas y también las que el
     * médico ya tiene asignadas (aunque estén inactivas o tengan horarios),
     * para no perderlas.
     */
    public function especialidadesParaEditar($idMedico) {
        $stmt = $this->db->prepare(
            "SELECT e.id_especialidad, e.nombre, e.estado
             FROM especialidades e
             WHERE e.estado = 'Activo'
                OR EXISTS (SELECT 1 FROM medico_especialidad me
                            WHERE me.id_medico = :id
                              AND me.id_especialidad = e.id_especialidad)
             ORDER BY e.nombre"
        );
        $stmt->execute([':id' => (int)$idMedico]);
        return $stmt->fetchAll();
    }

    /** ¿Otro médico ya usa ese registro profesional? (excluye al que se edita). */
    public function existeRegistroDeOtro($registro, $idMedico) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM medico
             WHERE LOWER(registro_profesional) = LOWER(:r)
               AND id_medico IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':r' => $registro, ':id' => (int)$idMedico]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Actualiza los datos de la ficha y sincroniza sus especialidades.
     * Ojo: medico_especialidad es referencia de horario_atencion (FK RESTRICT),
     * por lo que una asignación con horarios NO se elimina: se marca Inactiva.
     */
    public function actualizar($idMedico, array $d, array $especialidades) {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE persona
                    SET nombres = :nombres,
                        apellidos = :apellidos,
                        telefono = :telefono,
                        correo = :correo
                  WHERE id_persona = (SELECT id_persona FROM medico WHERE id_medico = :id)"
            )->execute([
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':telefono'  => $d['telefono'],
                ':correo'    => $d['correo'],
                ':id'        => (int)$idMedico,
            ]);

            $this->db->prepare(
                "UPDATE medico SET registro_profesional = :registro WHERE id_medico = :id"
            )->execute([
                ':registro' => $d['registro_profesional'],
                ':id'       => (int)$idMedico,
            ]);

            $stmt = $this->db->prepare("SELECT id_especialidad FROM medico_especialidad WHERE id_medico = :id");
            $stmt->execute([':id' => (int)$idMedico]);
            $actuales = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $nuevos   = array_map('intval', $especialidades);

            // Ya no seleccionadas: eliminar, o desactivar si tienen horarios.
            foreach (array_diff($actuales, $nuevos) as $idEsp) {
                $tieneHorarios = $this->db->prepare(
                    "SELECT 1
                       FROM horario_atencion h
                       JOIN medico_especialidad me
                         ON me.id_medico_especialidad = h.id_medico_especialidad
                      WHERE me.id_medico = :med AND me.id_especialidad = :esp
                      LIMIT 1"
                );
                $tieneHorarios->execute([':med' => (int)$idMedico, ':esp' => $idEsp]);

                if ($tieneHorarios->fetchColumn()) {
                    $this->db->prepare(
                        "UPDATE medico_especialidad
                            SET estado = 'Inactivo'
                          WHERE id_medico = :med AND id_especialidad = :esp"
                    )->execute([':med' => (int)$idMedico, ':esp' => $idEsp]);
                } else {
                    $this->db->prepare(
                        "DELETE FROM medico_especialidad
                          WHERE id_medico = :med AND id_especialidad = :esp"
                    )->execute([':med' => (int)$idMedico, ':esp' => $idEsp]);
                }
            }

            // Nuevas o reactivadas.
            foreach (array_diff($nuevos, $actuales) as $idEsp) {
                $this->db->prepare(
                    "INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion, estado)
                     VALUES (:medico, :esp, CURRENT_DATE, 'Activo')
                     ON CONFLICT (id_medico, id_especialidad)
                     DO UPDATE SET estado = 'Activo'"
                )->execute([':medico' => (int)$idMedico, ':esp' => $idEsp]);
            }

            // Las que siguen seleccionadas pero estaban inactivas, se reactivan.
            foreach (array_intersect($actuales, $nuevos) as $idEsp) {
                $this->db->prepare(
                    "UPDATE medico_especialidad
                        SET estado = 'Activo'
                      WHERE id_medico = :med AND id_especialidad = :esp AND estado = 'Inactivo'"
                )->execute([':med' => (int)$idMedico, ':esp' => $idEsp]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
     * Lista médicos con todas sus especialidades. Si se filtra por especialidad,
     * solo trae los médicos que la tengan, pero sigue mostrando todas las suyas.
     */
    public function listar($idEspecialidad = null) {
        $sql = "SELECT m.id_medico, m.codigo, p.nombres, p.apellidos, m.registro_profesional,
                       p.telefono, p.correo, p.estado,
                       STRING_AGG(e.nombre, '|' ORDER BY e.nombre) AS especialidades
                FROM medico m
                JOIN persona p ON p.id_persona = m.id_persona
                LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico AND me.estado = 'Activo'
                LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad";
        $params = [];

        if ($idEspecialidad !== null) {
            $sql .= " WHERE EXISTS (SELECT 1 FROM medico_especialidad f
                                    WHERE f.id_medico = m.id_medico
                                      AND f.id_especialidad = :esp
                                      AND f.estado = 'Activo')";
            $params[':esp'] = $idEspecialidad;
        }

        $sql .= " GROUP BY m.id_medico, p.nombres, p.apellidos, p.telefono, p.correo, p.estado
                 ORDER BY p.apellidos, p.nombres";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Activa o desactiva un médico (el estado vive en `persona`) y devuelve su
     * nuevo estado (null si no existe).
     * Al desactivarlo sus horarios dejan de ofrecerse al programar citas y salen
     * del calendario (las consultas filtran por p.estado = 'Activo');
     * las citas ya programadas se conservan tal como están.
     */
    public function cambiarEstado($idMedico) {
        $stmt = $this->db->prepare(
            "UPDATE persona
                SET estado = CASE WHEN estado = 'Activo' THEN 'Inactivo' ELSE 'Activo' END
              WHERE id_persona = (SELECT id_persona FROM medico WHERE id_medico = :id)
         RETURNING estado"
        );
        $stmt->execute([':id' => (int)$idMedico]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : $estado;
    }

    /**
     * Crea persona + cuenta de usuario + médico + especialidades en una sola transacción.
     */
    public function crear(array $d, $hash) {
        $this->db->beginTransaction();
        try {
            $idRol = $this->db->query(
                "SELECT id_rol FROM rol WHERE nombre ILIKE 'm_dico%' OR nombre ILIKE 'doctor%' LIMIT 1"
            )->fetchColumn();
            if (!$idRol) {
                throw new RuntimeException('No existe el rol Médico en la tabla rol.');
            }

            // Persona (datos personales compartidos)
            $stmt = $this->db->prepare(
                "INSERT INTO persona (ci, nombres, apellidos, telefono, correo, estado)
                 VALUES (:ci, :nombres, :apellidos, :telefono, :correo, 'Activo')
                 RETURNING id_persona"
            );
            $stmt->execute([
                ':ci'        => $d['ci'],
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':telefono'  => $d['telefono'],
                ':correo'    => $d['correo'],
            ]);
            $idPersona = (int)$stmt->fetchColumn();

            // Cuenta de usuario: nombre = nombre de usuario
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

            // Médico
            $id = (int)$this->db->query("SELECT nextval(pg_get_serial_sequence('medico', 'id_medico'))")->fetchColumn();
            $codigo = 'MED-' . str_pad($id, 5, '0', STR_PAD_LEFT);

            $this->db->prepare(
                "INSERT INTO medico (id_medico, id_persona, codigo, registro_profesional)
                 VALUES (:id, :persona, :codigo, :registro)"
            )->execute([
                ':id'       => $id,
                ':persona'  => $idPersona,
                ':codigo'   => $codigo,
                ':registro' => $d['registro_profesional'],
            ]);

            // Especialidades (una fila por cada una)
            $stmt = $this->db->prepare(
                "INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion, estado)
                 VALUES (:medico, :esp, CURRENT_DATE, 'Activo')"
            );
            foreach ($d['especialidades'] as $idEsp) {
                $stmt->execute([':medico' => $id, ':esp' => $idEsp]);
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
}
