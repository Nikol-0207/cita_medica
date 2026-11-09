<?php
require_once __DIR__ . '/../../config/Database.php';

/**
 * En el nuevo modelo NO existe la tabla recepcionista: un recepcionista es
 * una `persona` con una `cuenta_usuario` cuyo rol es Recepcionista.
 * Los alias (id_recepcionista = id_persona, celular = telefono) mantienen
 * sin cambios las vistas y los controladores existentes.
 */
class Recepcionista {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /** ¿Ya existe una persona con ese correo? (el correo es único en persona). */
    public function existeCorreo($correo) {
        $stmt = $this->db->prepare("SELECT 1 FROM persona WHERE LOWER(correo) = LOWER(:c) LIMIT 1");
        $stmt->execute([':c' => $correo]);
        return (bool)$stmt->fetchColumn();
    }

    /** ¿Ya existe una persona con ese CI? (el CI es único en persona). */
    public function existeCi($ci) {
        $stmt = $this->db->prepare("SELECT 1 FROM persona WHERE ci = :ci LIMIT 1");
        $stmt->execute([':ci' => $ci]);
        return (bool)$stmt->fetchColumn();
    }

    /** Datos personales del recepcionista vinculado a la cuenta. */
    public function datosDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT p.id_persona AS id_recepcionista, p.nombres, p.apellidos, p.ci,
                    p.telefono AS celular, p.fecha_nacimiento, p.direccion,
                    p.estado, p.fecha_registro, p.correo
             FROM cuenta_usuario u
             JOIN persona p ON p.id_persona = u.id_persona
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

    /** ¿Otra persona ya usa ese correo? (se excluye la de la cuenta actual). */
    public function existeCorreoDeOtro($correo, $idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona p
             JOIN cuenta_usuario u ON u.id_persona = p.id_persona
             WHERE LOWER(p.correo) = LOWER(:c)
               AND u.id_cuenta_usuario IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':c' => $correo, ':id' => (int)$idCuenta]);
        return (bool)$stmt->fetchColumn();
    }

    /** Actualiza los datos personales de la ficha del recepcionista. */
    public function actualizarDatos($idCuenta, array $d) {
        $celular = $d['celular'] ?? ($d['telefono'] ?? '');
        $stmt = $this->db->prepare(
            "UPDATE persona
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    ci = :ci,
                    telefono = :celular,
                    fecha_nacimiento = :fecha,
                    direccion = :direccion
              WHERE id_persona = (SELECT id_persona FROM cuenta_usuario WHERE id_cuenta_usuario = :id)"
        );
        $stmt->execute([
            ':nombres'   => $d['nombres'],
            ':apellidos' => $d['apellidos'],
            ':ci'        => $d['ci'],
            ':celular'   => $celular,
            ':fecha'     => $d['fecha_nacimiento'],
            ':direccion' => $d['direccion'],
            ':id'        => (int)$idCuenta,
        ]);
        return $stmt->rowCount();
    }

    /** Ficha completa para la pantalla "Ver información". */
    public function obtenerDetalle($idRecepcionista) {
        $stmt = $this->db->prepare(
            "SELECT p.id_persona AS id_recepcionista, u.id_cuenta_usuario,
                    p.nombres, p.apellidos, p.ci, p.telefono AS celular,
                    p.fecha_nacimiento, p.direccion, p.estado, p.fecha_registro,
                    p.correo AS correo_cuenta,
                    u.nombre_usuario AS usuario
             FROM persona p
             JOIN cuenta_usuario u ON u.id_persona = p.id_persona
             JOIN rol r            ON r.id_rol    = u.id_rol
             WHERE p.id_persona = :id
               AND r.nombre ILIKE 'recepc%'
             LIMIT 1"
        );
        $stmt->execute([':id' => (int)$idRecepcionista]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** ¿Otra persona ya usa ese CI? (excluye la ficha que se edita). */
    public function existeCiDeOtroId($ci, $idRecepcionista) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona
             WHERE ci = :ci
               AND id_persona IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':ci' => $ci, ':id' => (int)$idRecepcionista]);
        return (bool)$stmt->fetchColumn();
    }

    /** ¿Otro correo ya está en uso? (excluye la ficha que se edita). */
    public function existeCorreoDeOtroId($correo, $idRecepcionista) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM persona
             WHERE LOWER(correo) = LOWER(:c)
               AND id_persona IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':c' => $correo, ':id' => (int)$idRecepcionista]);
        return (bool)$stmt->fetchColumn();
    }

    /** Actualiza los datos personales de la ficha del recepcionista por su id. */
    public function actualizarPorId($idRecepcionista, array $d) {
        $celular = $d['celular'] ?? ($d['telefono'] ?? '');
        $stmt = $this->db->prepare(
            "UPDATE persona
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    ci = :ci,
                    telefono = :celular,
                    fecha_nacimiento = :fecha,
                    direccion = :direccion
              WHERE id_persona = :id"
        );
        $stmt->execute([
            ':nombres'   => $d['nombres'],
            ':apellidos' => $d['apellidos'],
            ':ci'        => $d['ci'],
            ':celular'   => $celular,
            ':fecha'     => $d['fecha_nacimiento'],
            ':direccion' => $d['direccion'],
            ':id'        => (int)$idRecepcionista,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Crea la persona + la cuenta de acceso en una sola transacción.
     */
    public function crear(array $d, $hash) {
        $this->db->beginTransaction();
        try {
            $idRol = $this->db->query("SELECT id_rol FROM rol WHERE nombre ILIKE '%recepc%' LIMIT 1")->fetchColumn();
            if (!$idRol) {
                throw new RuntimeException('No existe el rol de recepcionista en la tabla rol.');
            }

            $stmt = $this->db->prepare(
                "INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, telefono, correo, direccion, estado)
                 VALUES (:ci, :nombres, :apellidos, :fecha, :celular, :correo, :direccion, 'Activo')
                 RETURNING id_persona"
            );
            $stmt->execute([
                ':ci'        => $d['ci'],
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':fecha'     => $d['fecha_nacimiento'],
                ':celular'   => $d['celular'],
                ':correo'    => $d['correo'],
                ':direccion' => $d['direccion'],
            ]);
            $idPersona = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "INSERT INTO cuenta_usuario (id_persona, id_rol, nombre_usuario, contrasena_hash)
                 VALUES (:persona, :rol, :usuario, :hash)
                 RETURNING id_cuenta_usuario"
            );
            $stmt->execute([
                ':persona' => $idPersona,
                ':rol'     => $idRol,
                ':usuario' => $d['usuario_nombre'],
                ':hash'    => $hash,
            ]);
            $idCuenta = (int)$stmt->fetchColumn();

            $this->db->commit();
            return $idCuenta;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Solo las personas con cuenta del rol Recepcionista. */
    public function listar() {
    $sql = "SELECT p.id_persona AS id_recepcionista, p.nombres, p.apellidos, p.ci,
                   p.telefono AS celular, p.correo, p.estado, p.fecha_registro
            FROM persona p
            JOIN cuenta_usuario u ON u.id_persona = p.id_persona
            JOIN rol r            ON r.id_rol    = u.id_rol
            WHERE r.nombre ILIKE 'recepc%'
            ORDER BY p.apellidos, p.nombres";
    return $this->db->query($sql)->fetchAll();
}

    public function existeNombreCuenta($nombre) {
        $stmt = $this->db->prepare("SELECT 1 FROM cuenta_usuario WHERE LOWER(nombre_usuario) = LOWER(:n) LIMIT 1");
        $stmt->execute([':n' => $nombre]);
    return (bool)$stmt->fetchColumn();
    }


}
