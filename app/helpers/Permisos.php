<?php
require_once __DIR__ . '/../../config/Database.php';

class Permisos {

    /** Quita tildes y pasa a minúsculas para comparar nombres de rol. */
    private static function normalizar($texto) {
        return mb_strtolower(strtr((string)$texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n', 'ü' => 'u', 'Ü' => 'u',
        ]));
    }

    /** El administrador siempre tiene todo, así un permiso nuevo nunca lo deja afuera. */
    public static function esAdministrador() {
        return self::normalizar($_SESSION['user_role'] ?? '') === 'administrador';
    }

    private static function esAdmin() {
        return self::esAdministrador();
    }

    /** Rol Médico: solo ve su agenda y los pacientes que tienen citas con él. */
    public static function esMedico() {
        return self::normalizar($_SESSION['user_role'] ?? '') === 'medico';
    }

    /** Rol Paciente: solo ve sus propias citas y puede reservar las suyas. */
    public static function esPaciente() {
        return self::normalizar($_SESSION['user_role'] ?? '') === 'paciente';
    }

    /** Rol Recepcionista (el nombre exacto puede variar: "Recepcionista", "Recepcionista..." ). */
    public static function esRecepcionista() {
        return stripos(self::normalizar($_SESSION['user_role'] ?? ''), 'recepc') !== false;
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

    /**
     * Acepta un código ('citas.ver') o una lista de códigos: con lista basta
     * con tener ALGUNO de ellos (any-of), así un código nuevo no bloquea al rol.
     */
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
        $codigos = is_array($permiso) ? $permiso : [$permiso];
        foreach ($codigos as $codigo) {
            if (in_array($codigo, $_SESSION['permisos'], true)) {
                return true;
            }
        }
        return false;
    }

    /** Página de inicio: admin, panel de recepción, citas, o perfil como último recurso. */
    public static function inicio() {
        if (self::esAdmin()) {
            return 'admin';
        }
        if (self::tiene('dashboard')) {
            return 'recepcion';
        }
        if (self::tiene(['citas.ver', 'citas.reservar'])) {
            return 'citas';
        }
        if (self::tiene(['horarios.ver', 'horarios.ver_propios'])) {
            return 'horarios';
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
