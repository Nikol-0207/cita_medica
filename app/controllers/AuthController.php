<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/Permisos.php';
require_once __DIR__ . '/../models/Configuracion.php';

class AuthController {
    private $usuarioModel;

    public function __construct() {
        $this->usuarioModel = new Usuario();
    }

    public function showHome() {
        $especialidades = $this->usuarioModel->obtenerEspecialidadesActivas();
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        require_once __DIR__ . '/../views/home/index.php';
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identificador = trim($_POST['identificador'] ?? '');
            $password = $_POST['password'] ?? '';

            $usuario = $this->usuarioModel->autenticar($identificador, $password);

            if ($usuario) {
                $_SESSION['user_id']    = $usuario['id_cuenta_usuario'];
                $_SESSION['user_name']  = $usuario['nombre'];
                $_SESSION['user_role']  = $usuario['rol'];
                $_SESSION['user_photo'] = $usuario['foto_perfil'] ?? null;

                header("Location: index.php?route=" . Permisos::inicio());
            exit;
            } else {
                $_SESSION['login_error'] = "Usuario, CI o contraseña incorrectos.";
                header("Location: index.php?route=home&open_modal=1");
                exit;
            }
        }
    }

    public function logout() {
        session_destroy();
        header("Location: index.php?route=home");
        exit;
    }

    // ---------------------------------------------------------------
    // PERFIL
    // ---------------------------------------------------------------
    public function perfil() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?route=home&open_modal=1");
            exit;
        }

        $colores = (new Configuracion())->getColoresPersonalizados();
        $perfil  = $this->usuarioModel->obtenerPorId($_SESSION['user_id']);

        // Datos profesionales del médico (solo lectura en su perfil)
        $datosMedico = null;
        if (Permisos::esMedico()) {
            require_once __DIR__ . '/../models/Medico.php';
            $datosMedico = (new Medico())->datosDeCuenta($_SESSION['user_id']);
        }

        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        require_once __DIR__ . '/../views/auth/perfil.php';
    }

    public function guardarPerfil() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?route=home&open_modal=1");
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=perfil");
            exit;
        }

        $id        = (int)$_SESSION['user_id'];
        $nombre    = trim($_POST['nombre'] ?? '');
        $correo    = trim($_POST['correo'] ?? '');
        $actual    = $_POST['password_actual'] ?? '';
        $nueva     = $_POST['password_nueva'] ?? '';
        $confirmar = $_POST['password_confirmar'] ?? '';
        $errores   = [];

        // --- Datos básicos ---
        if ($nombre === '') {
            $errores[] = "El nombre de usuario no puede estar vacío.";
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El correo electrónico no es válido.";
        }
        if (!$errores && $this->usuarioModel->existeDuplicado($nombre, $correo, $id)) {
            $errores[] = "Ya existe otro usuario con ese nombre o correo.";
        }

        // --- Contraseña (solo si escribió una nueva) ---
        $nuevoHash = null;
        if ($nueva !== '') {
            $hashActual = $this->usuarioModel->obtenerHash($id);
            if (!$hashActual || !password_verify($actual, $hashActual)) {
                $errores[] = "La contraseña actual es incorrecta.";
            } elseif (strlen($nueva) < 8) {
                $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
            } elseif ($nueva !== $confirmar) {
                $errores[] = "La confirmación de la contraseña no coincide.";
            } else {
                $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
            }
        }

        // --- Foto (opcional) ---
        $extFoto = null;
        $tmpFoto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['foto'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $errores[] = "No se pudo subir la imagen (código " . (int)$f['error'] . ").";
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $errores[] = "La imagen no puede superar los 2 MB.";
            } else {
                $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
                $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($permitidos[$mime])) {
                    $errores[] = "Formato no permitido. Solo JPG, PNG o WEBP.";
                } else {
                    $extFoto = $permitidos[$mime];
                    $tmpFoto = $f['tmp_name'];
                }
            }
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            header("Location: index.php?route=perfil");
            exit;
        }

        // --- Guardar foto en disco ---
        $nombreFoto = null;
        if ($tmpFoto) {
            $dir = ROOT_PATH . '/public/uploads/perfiles/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $nombreFoto = 'perfil_' . $id . '_' . time() . '.' . $extFoto;
            if (!move_uploaded_file($tmpFoto, $dir . $nombreFoto)) {
                $_SESSION['mensaje_error'] = "Error al guardar la imagen en el servidor.";
                header("Location: index.php?route=perfil");
                exit;
            }
        }

        // --- Actualizar BD ---
        $anterior = $this->usuarioModel->obtenerPorId($id);
        $this->usuarioModel->actualizarPerfil($id, $nombre, $correo, $nuevoHash, $nombreFoto);

        // Borrar la foto anterior
        if ($nombreFoto && !empty($anterior['foto_perfil'])) {
            $vieja = ROOT_PATH . '/public/uploads/perfiles/' . basename($anterior['foto_perfil']);
            if (is_file($vieja)) {
                unlink($vieja);
            }
        }

        // --- Refrescar sesión ---
        $_SESSION['user_name'] = $nombre;
        if ($nombreFoto) {
            $_SESSION['user_photo'] = $nombreFoto;
        }
        if ($nuevoHash) {
            session_regenerate_id(true);
        }

        $_SESSION['mensaje_exito'] = "Perfil actualizado correctamente.";
        header("Location: index.php?route=perfil");
        exit;
    }

    // ---------------------------------------------------------------
    // (Opcional) ruta antigua, ya no es necesaria
    // ---------------------------------------------------------------
    public function cambiarFotoPerfil() {
        header("Location: index.php?route=perfil");
        exit;
    }
}