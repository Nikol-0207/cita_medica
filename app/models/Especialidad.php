<?php
require_once __DIR__ . '/../../config/Database.php';

class Especialidad {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function listarActivas() {
        return $this->db->query(
            "SELECT id_especialidad, nombre, descripcion
             FROM especialidades
             WHERE estado = 'Activo'
             ORDER BY nombre"
        )->fetchAll();
    }

    public function existeActiva($id) {
        $stmt = $this->db->prepare("SELECT 1 FROM especialidades WHERE id_especialidad = :id AND estado = 'Activo'");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    /** Compara sin importar mayúsculas; $idExcluir sirve al editar para ignorar el propio registro. */
    public function existeNombre($nombre, $idExcluir = null) {
        $sql = "SELECT 1 FROM especialidades WHERE LOWER(nombre) = LOWER(:n)";
        $params = [':n' => $nombre];
        if ($idExcluir !== null) {
            $sql .= " AND id_especialidad <> :id";
            $params[':id'] = $idExcluir;
        }
        $stmt = $this->db->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function crear($nombre, $descripcion) {
        $stmt = $this->db->prepare(
            "INSERT INTO especialidades (nombre, descripcion, estado) VALUES (:n, :d, 'Activo')"
        );
        return $stmt->execute([':n' => $nombre, ':d' => $descripcion]);
    }

    public function actualizar($id, $nombre, $descripcion) {
        $stmt = $this->db->prepare(
            "UPDATE especialidades SET nombre = :n, descripcion = :d WHERE id_especialidad = :id"
        );
        return $stmt->execute([':n' => $nombre, ':d' => $descripcion, ':id' => $id]);
    }
}