<?php
require_once __DIR__ . '/../../config/Database.php';

class Usuario {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function autenticar($identificador, $password) {
        // No se trae foto_perfil aquí: puede pesar varios MB y se sirve aparte
        // mediante index.php?route=avatar
        $sql = "SELECT u.id_cuenta_usuario, u.nombre, u.correo, u.contraseña_hash,
                       r.nombre AS rol, p.ci
                FROM cuenta_usuario u
                JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN paciente p ON u.id_cuenta_usuario = p.id_cuenta_usuario
                WHERE u.correo = :id OR u.nombre = :id OR p.ci = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $identificador);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['contraseña_hash'])) {
            return $user;
        }
        return false;
    }

    /**
     * Devuelve el valor de foto_perfil: un data URI (data:image/...;base64,...)
     * para las fotos nuevas o el nombre de archivo de las fotos antiguas.
     */
    public function obtenerFoto($idCuentaUsuario) {
        $stmt = $this->db->prepare(
            "SELECT foto_perfil FROM cuenta_usuario WHERE id_cuenta_usuario = :id"
        );
        $stmt->execute([':id' => (int)$idCuentaUsuario]);
        $foto = $stmt->fetchColumn();
        return $foto === false ? null : $foto;
    }

    /**
     * Obtiene los datos actualizados del usuario (útil para recargar la sesión o ver el perfil)
     */
    public function obtenerPorId($idCuentaUsuario) {
        $sql = "SELECT u.id_cuenta_usuario, u.nombre, u.correo, r.nombre AS rol
                FROM cuenta_usuario u
                JOIN rol r ON u.id_rol = r.id_rol
                WHERE u.id_cuenta_usuario = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $idCuentaUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function obtenerEspecialidadesActivas() {
        $sql = "SELECT id_especialidad, nombre, descripcion 
                FROM especialidades 
                WHERE estado = 'Activo' 
                ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerHash($id) {
    $stmt = $this->db->prepare("SELECT contraseña_hash FROM cuenta_usuario WHERE id_cuenta_usuario = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn();
}

public function existeDuplicado($nombre, $correo, $idExcluir) {
    $sql = "SELECT 1 FROM cuenta_usuario
            WHERE (LOWER(correo) = LOWER(:correo) OR LOWER(nombre) = LOWER(:nombre))
              AND id_cuenta_usuario <> :id
            LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':correo' => $correo, ':nombre' => $nombre, ':id' => $idExcluir]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Actualiza nombre y correo; contraseña y foto solo si se envían (no null).
 * $foto es un data URI (data:image/...;base64,...) que se guarda en Neon.
 */
public function actualizarPerfil($id, $nombre, $correo, $hash = null, $foto = null) {
    $campos = ['nombre = :nombre', 'correo = :correo'];
    $params = [':nombre' => $nombre, ':correo' => $correo, ':id' => $id];

    if ($hash !== null) {
        $campos[] = 'contraseña_hash = :hash';
        $params[':hash'] = $hash;
    }
    if ($foto !== null) {
        $campos[] = 'foto_perfil = :foto';
        $params[':foto'] = $foto;
    }

    $sql = "UPDATE cuenta_usuario SET " . implode(', ', $campos) . " WHERE id_cuenta_usuario = :id";
    return $this->db->prepare($sql)->execute($params);
}
}