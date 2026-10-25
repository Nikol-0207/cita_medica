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
            $this->db->rollBack();
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