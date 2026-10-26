<?php
require_once __DIR__ . '/../../config/Database.php';

class Recepcionista {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function existeCorreo($correo) {
        $stmt = $this->db->prepare("SELECT 1 FROM cuenta_usuario WHERE LOWER(correo) = LOWER(:c) LIMIT 1");
        $stmt->execute([':c' => $correo]);
        return (bool)$stmt->fetchColumn();
    }

    public function existeCi($ci) {
        $stmt = $this->db->prepare("SELECT 1 FROM recepcionista WHERE ci = :ci LIMIT 1");
        $stmt->execute([':ci' => $ci]);
        return (bool)$stmt->fetchColumn();
    }

    /** Datos personales del recepcionista vinculado a la cuenta. */
    public function datosDeCuenta($idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT r.id_recepcionista, r.nombres, r.apellidos, r.ci, r.celular,
                    r.fecha_nacimiento, r.direccion, r.estado, r.fecha_registro,
                    u.correo
             FROM recepcionista r
             JOIN cuenta_usuario u ON u.id_cuenta_usuario = r.id_cuenta_usuario
             WHERE r.id_cuenta_usuario = :id"
        );
        $stmt->execute([':id' => (int)$idCuenta]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** ¿Otro recepcionista ya usa ese CI? (se excluye la ficha de la cuenta actual). */
    public function existeCiDeOtro($ci, $idCuenta) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM recepcionista
             WHERE ci = :ci
               AND id_cuenta_usuario IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':ci' => $ci, ':id' => (int)$idCuenta]);
        return (bool)$stmt->fetchColumn();
    }

    /** Actualiza los datos personales de la ficha del recepcionista. */
    public function actualizarDatos($idCuenta, array $d) {
        $stmt = $this->db->prepare(
            "UPDATE recepcionista
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    ci = :ci,
                    celular = :celular,
                    fecha_nacimiento = :fecha,
                    direccion = :direccion
              WHERE id_cuenta_usuario = :id"
        );
        $stmt->execute([
            ':nombres'   => $d['nombres'],
            ':apellidos' => $d['apellidos'],
            ':ci'        => $d['ci'],
            ':celular'   => $d['celular'],
            ':fecha'     => $d['fecha_nacimiento'],
            ':direccion' => $d['direccion'],
            ':id'        => (int)$idCuenta,
        ]);
        return $stmt->rowCount();
    }

    /** Ficha completa para la pantalla "Ver información". */
    public function obtenerDetalle($idRecepcionista) {
        $stmt = $this->db->prepare(
            "SELECT r.id_recepcionista, r.id_cuenta_usuario, r.nombres, r.apellidos,
                    r.ci, r.celular, r.fecha_nacimiento, r.direccion,
                    r.estado, r.fecha_registro,
                    u.nombre AS usuario, u.correo AS correo_cuenta
             FROM recepcionista r
             LEFT JOIN cuenta_usuario u ON u.id_cuenta_usuario = r.id_cuenta_usuario
             WHERE r.id_recepcionista = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => (int)$idRecepcionista]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** ¿Otro recepcionista ya usa ese CI? (excluye la ficha que se edita). */
    public function existeCiDeOtroId($ci, $idRecepcionista) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM recepcionista
             WHERE ci = :ci
               AND id_recepcionista IS DISTINCT FROM :id
             LIMIT 1"
        );
        $stmt->execute([':ci' => $ci, ':id' => (int)$idRecepcionista]);
        return (bool)$stmt->fetchColumn();
    }

    /** Actualiza los datos personales de la ficha del recepcionista por su id. */
    public function actualizarPorId($idRecepcionista, array $d) {
        $stmt = $this->db->prepare(
            "UPDATE recepcionista
                SET nombres = :nombres,
                    apellidos = :apellidos,
                    ci = :ci,
                    celular = :celular,
                    fecha_nacimiento = :fecha,
                    direccion = :direccion
              WHERE id_recepcionista = :id"
        );
        $stmt->execute([
            ':nombres'   => $d['nombres'],
            ':apellidos' => $d['apellidos'],
            ':ci'        => $d['ci'],
            ':celular'   => $d['celular'],
            ':fecha'     => $d['fecha_nacimiento'],
            ':direccion' => $d['direccion'],
            ':id'        => (int)$idRecepcionista,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Crea la cuenta de acceso y la ficha del recepcionista en una sola transacción.
     */
    public function crear(array $d, $hash) {
        $this->db->beginTransaction();
        try {
            $idRol = $this->db->query("SELECT id_rol FROM rol WHERE nombre ILIKE '%recepc%' LIMIT 1")->fetchColumn();
            if (!$idRol) {
                throw new RuntimeException('No existe el rol de recepcionista en la tabla rol.');
            }

            $stmt = $this->db->prepare(
                "INSERT INTO cuenta_usuario (id_rol, nombre, correo, contraseña_hash)
                 VALUES (:rol, :nombre, :correo, :hash)
                 RETURNING id_cuenta_usuario"
            );
            $stmt->execute([
                ':rol'    => $idRol,
                ':nombre' => $d['usuario_nombre'], 
                ':correo' => $d['correo'],
                ':hash'   => $hash
            ]);
            $idCuenta = $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "INSERT INTO recepcionista (id_cuenta_usuario, nombres, apellidos, ci, celular, fecha_nacimiento, direccion)
                 VALUES (:cuenta, :nombres, :apellidos, :ci, :celular, :fecha, :direccion)"
            );
            $stmt->execute([
                ':cuenta'    => $idCuenta,
                ':nombres'   => $d['nombres'],
                ':apellidos' => $d['apellidos'],
                ':ci'        => $d['ci'],
                ':celular'   => $d['celular'],
                ':fecha'     => $d['fecha_nacimiento'],
                ':direccion' => $d['direccion']
            ]);

            $this->db->commit();
            return $idCuenta;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function listar() {
    $sql = "SELECT r.id_recepcionista, r.nombres, r.apellidos, r.ci, r.celular,
                   r.estado, r.fecha_registro, u.correo
            FROM recepcionista r
            JOIN cuenta_usuario u ON u.id_cuenta_usuario = r.id_cuenta_usuario
            ORDER BY r.apellidos, r.nombres";
    return $this->db->query($sql)->fetchAll();
}
    public function existeNombreCuenta($nombre) {
        $stmt = $this->db->prepare("SELECT 1 FROM cuenta_usuario WHERE LOWER(nombre) = LOWER(:n) LIMIT 1");
        $stmt->execute([':n' => $nombre]);
    return (bool)$stmt->fetchColumn();
    }


}