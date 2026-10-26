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
        $stmt = $this->db->prepare("SELECT id_medico FROM medico WHERE id_cuenta_usuario = :id");
        $stmt->execute([':id' => (int)$idCuenta]);
        return (int)$stmt->fetchColumn();
    }

    /** Datos profesionales del médico de la cuenta (para su perfil de solo lectura). */
    public function datosDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT m.id_medico, m.codigo, m.nombres, m.apellidos, m.registro_profesional,
                    m.telefono, m.correo, m.estado,
                    STRING_AGG(e.nombre, ' | ' ORDER BY e.nombre) AS especialidades
             FROM medico m
             LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico AND me.estado = 'Activo'
             LEFT JOIN especialidades e       ON e.id_especialidad = me.id_especialidad
             WHERE m.id_cuenta_usuario = :id
             GROUP BY m.id_medico"
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

    /** El médico solo puede actualizar su celular y correo desde su perfil. */
    public function actualizarContacto($idCuenta, $telefono, $correo) {
        $stmt = $this->db->prepare(
            "UPDATE medico
                SET telefono = :telefono,
                    correo = :correo
              WHERE id_cuenta_usuario = :id"
        );
        $stmt->execute([
            ':telefono' => $telefono,
            ':correo'   => $correo,
            ':id'       => (int)$idCuenta,
        ]);
        return $stmt->rowCount();
    }

    /** Ficha completa para la pantalla "Ver información". */
    public function obtenerDetalle($idMedico) {
        $stmt = $this->db->prepare(
            "SELECT m.id_medico, m.id_cuenta_usuario, m.codigo, m.nombres, m.apellidos,
                    m.registro_profesional, m.telefono, m.correo, m.estado, m.fecha_registro,
                    u.nombre AS usuario, u.correo AS correo_cuenta
             FROM medico m
             LEFT JOIN cuenta_usuario u ON u.id_cuenta_usuario = m.id_cuenta_usuario
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
     * médico ya tiene asignadas aunque estén inactivas, para no perderlas.
     */
    public function especialidadesParaEditar($idMedico) {
        $stmt = $this->db->prepare(
            "SELECT e.id_especialidad, e.nombre, e.estado
             FROM especialidades e
             WHERE e.estado = 'Activo'
                OR EXISTS (SELECT 1 FROM medico_especialidad me
                            WHERE me.id_medico = :id
                              AND me.id_especialidad = e.id_especialidad
                              AND me.estado = 'Activo')
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

    /** Actualiza los datos de la ficha y reemplaza sus especialidades. */
    public function actualizar($idMedico, array $d, array $especialidades) {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE medico
                    SET nombres = :nombres,
                        apellidos = :apellidos,
                        registro_profesional = :registro,
                        telefono = :telefono,
                        correo = :correo
                  WHERE id_medico = :id"
            )->execute([
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':registro'  => $d['registro_profesional'],
                ':telefono'  => $d['telefono'],
                ':correo'    => $d['correo'],
                ':id'        => (int)$idMedico,
            ]);

            $this->db->prepare("DELETE FROM medico_especialidad WHERE id_medico = :id")
                     ->execute([':id' => (int)$idMedico]);

            if ($especialidades) {
                $stmt = $this->db->prepare(
                    "INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion, estado)
                     VALUES (:medico, :esp, NOW(), 'Activo')"
                );
                foreach ($especialidades as $idEsp) {
                    $stmt->execute([':medico' => (int)$idMedico, ':esp' => (int)$idEsp]);
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

    public function existeCorreoCuenta($correo) {
        $stmt = $this->db->prepare("SELECT 1 FROM cuenta_usuario WHERE LOWER(correo) = LOWER(:c) LIMIT 1");
        $stmt->execute([':c' => $correo]);
        return (bool)$stmt->fetchColumn();
    }

    public function existeNombreCuenta($nombre) {
        $stmt = $this->db->prepare("SELECT 1 FROM cuenta_usuario WHERE LOWER(nombre) = LOWER(:n) LIMIT 1");
        $stmt->execute([':n' => $nombre]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Lista médicos con todas sus especialidades. Si se filtra por especialidad,
     * solo trae los médicos que la tengan, pero sigue mostrando todas las suyas.
     */
    public function listar($idEspecialidad = null) {
        $sql = "SELECT m.id_medico, m.codigo, m.nombres, m.apellidos, m.registro_profesional,
                       m.telefono, m.correo, m.estado,
                       STRING_AGG(e.nombre, '|' ORDER BY e.nombre) AS especialidades
                FROM medico m
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

        $sql .= " GROUP BY m.id_medico ORDER BY m.apellidos, m.nombres";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Activa o desactiva un médico y devuelve su nuevo estado (null si no existe).
     * Al desactivarlo sus horarios dejan de ofrecerse al programar citas y salen
     * del calendario (las consultas filtran por m.estado = 'Activo');
     * las citas ya programadas se conservan tal como están.
     */
    public function cambiarEstado($idMedico) {
        $stmt = $this->db->prepare(
            "UPDATE medico
                SET estado = CASE WHEN estado = 'Activo' THEN 'Inactivo' ELSE 'Activo' END
              WHERE id_medico = :id
         RETURNING estado"
        );
        $stmt->execute([':id' => (int)$idMedico]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : $estado;
    }

    /**
     * Crea cuenta de usuario + médico + especialidades en una sola transacción.
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

            // Cuenta de usuario: nombre = nombre de usuario
            $stmt = $this->db->prepare(
                "INSERT INTO cuenta_usuario (id_rol, nombre, correo, contraseña_hash)
                 VALUES (:rol, :nombre, :correo, :hash)
                 RETURNING id_cuenta_usuario"
            );
            $stmt->execute([
                ':rol'    => $idRol,
                ':nombre' => $d['usuario_nombre'],
                ':correo' => $d['correo'],
                ':hash'   => $hash,
            ]);
            $idCuenta = $stmt->fetchColumn();

            // Médico
            $id = (int)$this->db->query("SELECT nextval(pg_get_serial_sequence('medico', 'id_medico'))")->fetchColumn();
            $codigo = 'MED-' . str_pad($id, 5, '0', STR_PAD_LEFT);

            $this->db->prepare(
                "INSERT INTO medico (id_medico, id_cuenta_usuario, codigo, registro_profesional,
                                     nombres, apellidos, telefono, correo, estado, fecha_registro)
                 VALUES (:id, :cuenta, :codigo, :registro, :nombres, :apellidos, :telefono, :correo, 'Activo', NOW())"
            )->execute([
                ':id'        => $id,
                ':cuenta'    => $idCuenta,
                ':codigo'    => $codigo,
                ':registro'  => $d['registro_profesional'],
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':telefono'  => $d['telefono'],
                ':correo'    => $d['correo'],
            ]);

            // Especialidades (una fila por cada una)
            $stmt = $this->db->prepare(
                "INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion, estado)
                 VALUES (:medico, :esp, NOW(), 'Activo')"
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