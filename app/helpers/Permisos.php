<?php
require_once __DIR__ . '/../../config/Database.php';

class Permisos {

    /** El administrador siempre tiene todo, así un permiso nuevo nunca lo deja afuera. */
    private static function esAdmin() {
        return ($_SESSION['user_role'] ?? '') === 'Administrador';
    }

    /** Rol Médico: solo ve su agenda y los pacientes que tienen citas con él. */
    public static function esMedico() {
        return ($_SESSION['user_role'] ?? '') === 'Médico';
    }

    /** Rol Paciente: solo ve sus propias citas y puede reservar las suyas. */
    public static function esPaciente() {
        return ($_SESSION['user_role'] ?? '') === 'Paciente';
    }

    /** Lee de la BD los permisos del rol del usuario y los guarda en la sesión. */
    public static function cargar() {
        $_SESSION['permisos'] = [];
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $stmt = Database::getConnection()->prepare(
            "SELECT p.codigo
             FROM cuenta_usuario u
             JOIN rol_permiso rp ON rp.id_rol = u.id_rol
             JOIN permiso p      ON p.id_permiso = rp.id_permiso
             WHERE u.id_cuenta_usuario = :id"
        );
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $_SESSION['permisos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function tiene($permiso) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (self::esAdmin()) {
            return true;
        }
        if (!isset($_SESSION['permisos'])) {
            self::cargar();   // sesiones iniciadas antes de este cambio
        }
        return in_array($permiso, $_SESSION['permisos'], true);
    }

    /** Página de inicio: admin, panel de recepción, citas, o perfil como último recurso. */
    public static function inicio() {
        if (self::esAdmin()) {
            return 'admin';
        }
        if (self::tiene('dashboard')) {
            return 'recepcion';
        }
        if (self::tiene('citas.ver')) {
            return 'citas';
        }
        // Rol sin permisos: el perfil siempre es accesible con sesión iniciada.
        // Evita bucles de redirección (exigir -> inicio -> exigir ...).
        return 'perfil';
    }

    /** Corta la petición si no hay sesión o si el rol no tiene el permiso. */
    public static function exigir($permiso) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?route=home&open_modal=1");
            exit;
        }
        if (!self::tiene($permiso)) {
            header("Location: index.php?route=" . self::inicio());
            exit;
        }
    }
}