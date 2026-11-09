<?php
require_once __DIR__ . '/../../config/Database.php';

class Usuario {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Login por correo, nombre de usuario o CI.
     * El correo y el CI viven en `persona`; el usuario y la contraseña en
     * `cuenta_usuario` (nombre_usuario / contrasena_hash).
     */
    public function autenticar($identificador, $password) {
        // No se trae foto_perfil aquí: puede pesar varios MB y se sirve aparte
        // mediante index.php?route=avatar
        $sql = "SELECT u.id_cuenta_usuario, u.nombre_usuario AS nombre,
                       p.correo, u.contrasena_hash,
                       r.nombre AS rol, p.ci
                FROM cuenta_usuario u
                JOIN rol r     ON u.id_rol   = r.id_rol
                JOIN persona p ON p.id_persona = u.id_persona
                WHERE LOWER(p.correo) = LOWER(:id)
                   OR LOWER(u.nombre_usuario) = LOWER(:id)
                   OR p.ci = :id2
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $identificador, ':id2' => $identificador]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['contrasena_hash'])) {
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
        $sql = "SELECT u.id_cuenta_usuario, u.nombre_usuario AS nombre,
                       p.correo, r.nombre AS rol
                FROM cuenta_usuario u
                JOIN rol r     ON u.id_rol      = r.id_rol
                JOIN persona p ON p.id_persona  = u.id_persona
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
    $stmt = $this->db->prepare("SELECT contrasena_hash FROM cuenta_usuario WHERE id_cuenta_usuario = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn();
}

    /**
     * ¿Ya usa otro usuario ese nombre de usuario o esa persona ese correo?
     * El correo es único en `persona` (índice sobre LOWER(correo)) y también
     * sirve para las personas sin cuenta.
     */
public function existeDuplicado($nombre, $correo, $idExcluir) {
    $sql = "SELECT 1 FROM cuenta_usuario
            WHERE LOWER(nombre_usuario) = LOWER(:nombre)
              AND id_cuenta_usuario <> :id
            LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':nombre' => $nombre, ':id' => $idExcluir]);
    if ((bool)$stmt->fetchColumn()) {
        return true;
    }

    $sql = "SELECT 1 FROM persona
            WHERE LOWER(correo) = LOWER(:correo)
              AND id_persona IS DISTINCT FROM
                  (SELECT id_persona FROM cuenta_usuario WHERE id_cuenta_usuario = :id2)
            LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':correo' => $correo, ':id2' => $idExcluir]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Actualiza el nombre de usuario y el correo (persona); contraseña y foto
 * solo si se envían (no null).
 * $foto es un data URI (data:image/...;base64,...) que se guarda en Neon.
 */
public function actualizarPerfil($id, $nombre, $correo, $hash = null, $foto = null) {
    $this->db->beginTransaction();
    try {
        $campos = ['nombre_usuario = :nombre'];
        $params = [':nombre' => $nombre, ':id' => (int)$id];

        if ($hash !== null) {
            $campos[] = 'contrasena_hash = :hash';
            $params[':hash'] = $hash;
        }
        if ($foto !== null) {
            $campos[] = 'foto_perfil = :foto';
            $params[':foto'] = $foto;
        }

        $sql = "UPDATE cuenta_usuario SET " . implode(', ', $campos) . " WHERE id_cuenta_usuario = :id";
        $this->db->prepare($sql)->execute($params);

        // El correo de inicio de sesión es el correo de la persona.
        $this->db->prepare(
            "UPDATE persona
                SET correo = :correo
              WHERE id_persona = (SELECT id_persona FROM cuenta_usuario WHERE id_cuenta_usuario = :id2)"
        )->execute([':correo' => $correo, ':id2' => (int)$id]);

        $this->db->commit();
        return true;
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        throw $e;
    }
}
}
