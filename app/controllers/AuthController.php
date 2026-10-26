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

        // Datos personales según el rol. El paciente solo ve/edita sus datos
        // personales (no información médica ni contacto de emergencia); el
        // recepcionista edita su ficha; el médico solo celular y correo.
        $datosPersonales = null;
        $tipoPerfil      = null;

        if (Permisos::esMedico()) {
            require_once __DIR__ . '/../models/Medico.php';
            $datosMedico = (new Medico())->datosDeCuenta($_SESSION['user_id']);
            $tipoPerfil  = 'medico';
        } elseif (Permisos::esRecepcionista()) {
            require_once __DIR__ . '/../models/Recepcionista.php';
            $tipoPerfil      = 'recepcionista';
            $datosPersonales = (new Recepcionista())->datosDeCuenta($_SESSION['user_id']);
        } elseif (Permisos::esPaciente()) {
            require_once __DIR__ . '/../models/Paciente.php';
            $tipoPerfil      = 'paciente';
            $datosPersonales = (new Paciente())->datosDeCuenta($_SESSION['user_id']);
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
        // Se guarda en la base de datos (Neon) como data URI. El disco de Render
        // es efímero: los archivos subidos se pierden en cada deploy o reinicio.
        $fotoDataUri = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['foto'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $errores[] = "No se pudo subir la imagen (código " . (int)$f['error'] . ").";
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $errores[] = "La imagen no puede superar los 2 MB.";
            } else {
                $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
                $permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $permitidos, true)) {
                    $errores[] = "Formato no permitido. Solo JPG, PNG o WEBP.";
                } else {
                    $bytes = file_get_contents($f['tmp_name']);
                    if ($bytes === false) {
                        $errores[] = "No se pudo leer la imagen subida.";
                    } else {
                        $fotoDataUri = 'data:' . $mime . ';base64,' . base64_encode($bytes);
                    }
                }
            }
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            header("Location: index.php?route=perfil");
            exit;
        }

        // --- Actualizar BD (la imagen viaja dentro de foto_perfil) ---
        try {
            $this->usuarioModel->actualizarPerfil($id, $nombre, $correo, $nuevoHash, $fotoDataUri);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            if ($e instanceof PDOException && (string)$e->getCode() === '22001') {
                // La imagen no cabe: falta aplicar la migración de la columna.
                $_SESSION['mensaje_error'] = "La columna foto_perfil sigue siendo VARCHAR(255). "
                    . "Ejecuta sql/2026-10-26_foto_perfil_text.sql en la MISMA base de datos "
                    . "de Neon que usa Render y vuelve a intentar.";
            } else {
                $_SESSION['mensaje_error'] = "No se pudo guardar el perfil: " . $e->getMessage();
            }
            header("Location: index.php?route=perfil");
            exit;
        }

        // --- Refrescar sesión ---
        $_SESSION['user_name'] = $nombre;
        if ($nuevoHash) {
            session_regenerate_id(true);
        }

        $_SESSION['mensaje_exito'] = "Perfil actualizado correctamente.";
        header("Location: index.php?route=perfil");
        exit;
    }

    /**
     * Guarda los datos personales del perfil según el rol:
     *  - Paciente: solo sus datos personales.
     *  - Recepcionista: su ficha personal.
     *  - Médico: únicamente celular y correo.
     */
    public function guardarDatosPersonales() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?route=home&open_modal=1");
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=perfil");
            exit;
        }

        $id      = (int)$_SESSION['user_id'];
        $errores = [];

        try {
            if (Permisos::esMedico()) {
                require_once __DIR__ . '/../models/Medico.php';
                $modelo = new Medico();

                $telefono = trim($_POST['medico_telefono'] ?? '');
                $correo   = trim($_POST['medico_correo'] ?? '');

                if (!$modelo->datosDeCuenta($id)) {
                    $errores[] = "Su cuenta no tiene una ficha de médico vinculada.";
                } else {
                    if ($telefono === '') {
                        $errores[] = "El celular no puede estar vacío.";
                    }
                    if ($correo === '') {
                        $errores[] = "El correo no puede estar vacío.";
                    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                        $errores[] = "El correo electrónico no es válido.";
                    }

                    if (!$errores) {
                        $modelo->actualizarContacto($id, $telefono, $correo);
                        $_SESSION['mensaje_exito'] = "Celular y correo actualizados correctamente.";
                    }
                }

            } elseif (Permisos::esRecepcionista()) {
                require_once __DIR__ . '/../models/Recepcionista.php';
                $modelo = new Recepcionista();

                if (!$modelo->datosDeCuenta($id)) {
                    $errores[] = "Su cuenta no tiene una ficha de recepcionista vinculada.";
                } else {
                    $d = $this->leerDatosPersonales($_POST, 'celular', $errores);

                    if (!$errores && $modelo->existeCiDeOtro($d['ci'], $id)) {
                        $errores[] = "Ya existe otro recepcionista con ese CI.";
                    }
                    if (!$errores) {
                        $modelo->actualizarDatos($id, $d);
                        $_SESSION['mensaje_exito'] = "Datos personales actualizados correctamente.";
                    }
                }

            } elseif (Permisos::esPaciente()) {
                require_once __DIR__ . '/../models/Paciente.php';
                $modelo = new Paciente();

                if (!$modelo->datosDeCuenta($id)) {
                    $errores[] = "Su cuenta no tiene una ficha de paciente vinculada.";
                } else {
                    $d = $this->leerDatosPersonales($_POST, 'telefono', $errores);

                    if (!in_array($d['sexo'], ['M', 'F'], true)) {
                        $errores[] = "El género seleccionado no es válido.";
                    }
                    if (!$errores && $modelo->existeCiDeOtro($d['ci'], $id)) {
                        $errores[] = "Ya existe otro paciente con ese CI.";
                    }
                    if (!$errores) {
                        $modelo->actualizarDatos($id, $d);
                        $_SESSION['mensaje_exito'] = "Datos personales actualizados correctamente.";
                    }
                }

            } else {
                $errores[] = "Su rol no tiene datos personales editables.";
            }

        } catch (Throwable $e) {
            error_log($e->getMessage());
            if ($e instanceof PDOException && (string)$e->getCode() === '23505') {
                $errores[] = "Ya existe un registro con ese CI.";
            } else {
                $errores[] = "No se pudieron guardar los datos: " . $e->getMessage();
            }
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
        }
        header("Location: index.php?route=perfil");
        exit;
    }

    /**
     * Lee y valida los campos personales comunes a paciente y recepcionista.
     * $campoTelefono es 'telefono' (paciente) o 'celular' (recepcionista).
     */
    private function leerDatosPersonales(array $post, $campoTelefono, array &$errores) {
        $d = [
            'nombres'          => trim($post['nombres'] ?? ''),
            'apellidos'        => trim($post['apellidos'] ?? ''),
            'ci'               => trim($post['ci'] ?? ''),
            'fecha_nacimiento' => trim($post['fecha_nacimiento'] ?? ''),
            'direccion'        => trim($post['direccion'] ?? ''),
            'telefono'         => trim($post[$campoTelefono] ?? ''),
            'sexo'             => trim($post['sexo'] ?? 'M'),
            'nro_seguro'       => trim($post['nro_seguro'] ?? ''),
        ];

        if ($d['nombres'] === '') {
            $errores[] = "Los nombres son obligatorios.";
        }
        if ($d['apellidos'] === '') {
            $errores[] = "Los apellidos son obligatorios.";
        }
        if ($d['ci'] === '') {
            $errores[] = "El CI es obligatorio.";
        }
        if ($d['telefono'] === '') {
            $errores[] = "El celular es obligatorio.";
        }
        if ($d['direccion'] === '') {
            $errores[] = "La dirección es obligatoria.";
        }

        $errorFecha = $this->validarFechaNacimiento($d['fecha_nacimiento']);
        if ($errorFecha !== null) {
            $errores[] = $errorFecha;
        }

        return $d;
    }

    /** Devuelve un mensaje de error o null si la fecha es válida y no futura. */
    private function validarFechaNacimiento($fecha) {
        if ($fecha === '') {
            return "La fecha de nacimiento es obligatoria.";
        }
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            return "La fecha de nacimiento no es válida.";
        }
        if ($fecha > date('Y-m-d')) {
            return "La fecha de nacimiento no puede ser futura.";
        }
        if ($fecha < '1900-01-01') {
            return "La fecha de nacimiento no es válida.";
        }
        return null;
    }

    /**
     * Sirve la foto de perfil del usuario logueado.
     * Las fotos nuevas se guardan en cuenta_usuario.foto_perfil como data URI
     * (base64) y por eso sobreviven a los redeploys de Render. Las antiguas
     * quedaron como nombre de archivo y se sirven desde disco si aún existen.
     */
    public function avatar() {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            exit;
        }

        header('Cache-Control: no-store, max-age=0');

        $foto = $this->usuarioModel->obtenerFoto((int)$_SESSION['user_id']);

        // Foto nueva guardada en la base de datos
        if (is_string($foto) && preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#s', $foto, $m)) {
            $bytes = base64_decode($m[2], true);
            if ($bytes !== false) {
                header('Content-Type: ' . $m[1]);
                header('Content-Length: ' . strlen($bytes));
                echo $bytes;
                exit;
            }
        }

        // Compatibilidad: fotos antiguas guardadas como archivo en disco
        if (is_string($foto) && $foto !== 'default_avatar.png') {
            $ruta = ROOT_PATH . '/public/uploads/perfiles/' . basename($foto);
            if (is_file($ruta)) {
                $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $ruta) ?: 'application/octet-stream';
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . filesize($ruta));
                readfile($ruta);
                exit;
            }
        }

        // Avatar por defecto
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#94a3b8">'
           . '<path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/>'
           . '</svg>';
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