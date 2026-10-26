<?php
require_once __DIR__ . '/../models/Recepcionista.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../helpers/Permisos.php';

class RecepcionistaController {
    private $model;

    public function __construct() {
        $this->model = new Recepcionista();
    }

    public function crear() {
        Permisos::exigir('recepcionistas.gestionar');

        $colores      = (new Configuracion())->getColoresPersonalizados();
        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        $old          = $_SESSION['old'] ?? [];
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error'], $_SESSION['old']);

        require_once __DIR__ . '/../views/admin/recepcionistas/crear.php';
    }
    
    public function listar() {
    Permisos::exigir('recepcionistas.gestionar');

    $colores        = (new Configuracion())->getColoresPersonalizados();
    require_once __DIR__ . '/../helpers/Paginador.php';
    $pag            = Paginador::cortar($this->model->listar());
    $recepcionistas = $pag['items'];
    $mensajeExito   = $_SESSION['mensaje_exito'] ?? null;
    $mensajeError   = $_SESSION['mensaje_error'] ?? null;
    unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

    require_once __DIR__ . '/../views/admin/recepcionistas/listar.php';
}

    // ----------------------------------------------------------------
    // VER INFORMACIÓN DEL RECEPCIONISTA (solo administrador)
    // ----------------------------------------------------------------
    public function ver() {
        Permisos::exigir('recepcionistas.gestionar');

        $id             = ctype_digit($_GET['id_recepcionista'] ?? '') ? (int)$_GET['id_recepcionista'] : 0;
        $recepcionista  = $id > 0 ? $this->model->obtenerDetalle($id) : null;

        if (!$recepcionista) {
            $_SESSION['mensaje_error'] = "El recepcionista solicitado no existe.";
            header("Location: index.php?route=recepcionistas");
            exit;
        }

        $old = $_SESSION['old'] ?? null;
        unset($_SESSION['old']);

        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        $colores = (new Configuracion())->getColoresPersonalizados();
        $activo  = 'recepcionistas';

        require_once __DIR__ . '/../views/admin/recepcionistas/detalle.php';
    }

    // ----------------------------------------------------------------
    // GUARDAR CAMBIOS DE LA FICHA DEL RECEPCIONISTA
    // ----------------------------------------------------------------
    public function actualizar() {
        Permisos::exigir('recepcionistas.gestionar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=recepcionistas");
            exit;
        }

        $id            = ctype_digit($_POST['id_recepcionista'] ?? '') ? (int)$_POST['id_recepcionista'] : 0;
        $recepcionista = $id > 0 ? $this->model->obtenerDetalle($id) : null;
        if (!$recepcionista) {
            $_SESSION['mensaje_error'] = "El recepcionista solicitado no existe.";
            header("Location: index.php?route=recepcionistas");
            exit;
        }

        $d = [
            'nombres'          => trim($_POST['nombres'] ?? ''),
            'apellidos'        => trim($_POST['apellidos'] ?? ''),
            'ci'               => trim($_POST['ci'] ?? ''),
            'celular'          => trim($_POST['celular'] ?? ''),
            'fecha_nacimiento' => trim($_POST['fecha_nacimiento'] ?? ''),
            'direccion'        => trim($_POST['direccion'] ?? ''),
        ];

        $errores = [];

        foreach (['nombres' => 'Nombres', 'apellidos' => 'Apellidos',
                  'ci' => 'CI', 'celular' => 'Celular',
                  'fecha_nacimiento' => 'Fecha de nacimiento',
                  'direccion' => 'Dirección'] as $campo => $etiqueta) {
            if ($d[$campo] === '') {
                $errores[] = "El campo $etiqueta es obligatorio.";
            }
        }

        if ($d['ci'] !== '' && !preg_match('/^[0-9A-Za-z-]{5,20}$/', $d['ci'])) {
            $errores[] = "El CI no es válido.";
        }
        if ($d['celular'] !== '' && !preg_match('/^\+?[0-9]{7,15}$/', $d['celular'])) {
            $errores[] = "El celular no es válido (solo números, 7 a 15 dígitos).";
        }

        if ($d['fecha_nacimiento'] !== '') {
            $fecha = DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
            if (!$fecha || $fecha->format('Y-m-d') !== $d['fecha_nacimiento']) {
                $errores[] = "La fecha de nacimiento no es válida.";
            } elseif ($fecha > new DateTime()) {
                $errores[] = "La fecha de nacimiento no puede ser futura.";
            } elseif ($fecha->diff(new DateTime())->y < 18) {
                $errores[] = "El recepcionista debe ser mayor de 18 años.";
            }
        }

        if (!$errores && $this->model->existeCiDeOtroId($d['ci'], $id)) {
            $errores[] = "Ya existe otro recepcionista con ese CI.";
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            $_SESSION['old'] = $d;
            header("Location: index.php?route=recepcionista_ver&id_recepcionista=" . $id);
            exit;
        }

        try {
            $this->model->actualizarPorId($id, $d);
            $_SESSION['mensaje_exito'] = "La información del recepcionista se actualizó correctamente.";
            header("Location: index.php?route=recepcionista_ver&id_recepcionista=" . $id);
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "No se pudo actualizar al recepcionista: " . $e->getMessage();
            $_SESSION['old'] = $d;
            header("Location: index.php?route=recepcionista_ver&id_recepcionista=" . $id);
        }
        exit;
    }

    public function guardar() {
    Permisos::exigir('recepcionistas.gestionar');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: index.php?route=recepcionistas_crear");
        exit;
    }

    $d = [
        'nombres'          => trim($_POST['nombres'] ?? ''),
        'apellidos'        => trim($_POST['apellidos'] ?? ''),
        'ci'               => trim($_POST['ci'] ?? ''),
        'celular'          => trim($_POST['celular'] ?? ''),
        'fecha_nacimiento' => trim($_POST['fecha_nacimiento'] ?? ''),
        'direccion'        => trim($_POST['direccion'] ?? ''),
        'usuario_nombre'   => trim($_POST['usuario_nombre'] ?? ''),   // NUEVO
        'correo'           => trim($_POST['correo'] ?? ''),
    ];
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirmar'] ?? '';
    $errores = [];

    $obligatorios = ['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'direccion' => 'Dirección'];
    foreach ($obligatorios as $campo => $etiqueta) {
        if ($d[$campo] === '') {
            $errores[] = "El campo $etiqueta es obligatorio.";
        }
    }

    if (!preg_match('/^[0-9A-Za-z-]{5,20}$/', $d['ci'])) {
        $errores[] = "El CI no es válido.";
    }
    if (!preg_match('/^\+?[0-9]{7,15}$/', $d['celular'])) {
        $errores[] = "El celular no es válido (solo números, 7 a 15 dígitos).";
    }

    $fecha = DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
    if (!$fecha || $fecha->format('Y-m-d') !== $d['fecha_nacimiento']) {
        $errores[] = "La fecha de nacimiento no es válida.";
    } elseif ($fecha->diff(new DateTime())->invert === 1 || $fecha->diff(new DateTime())->y < 18) {
        $errores[] = "El recepcionista debe ser mayor de 18 años.";
    }

    // NUEVO: nombre de usuario
    if (strlen($d['usuario_nombre']) < 3) {
        $errores[] = "El nombre de usuario debe tener al menos 3 caracteres.";
    }

    if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    if (strlen($pass) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($pass !== $pass2) {
        $errores[] = "La verificación de la contraseña no coincide.";
    }

    if (!$errores) {
        if ($this->model->existeNombreCuenta($d['usuario_nombre'])) {   // NUEVO
            $errores[] = "Ese nombre de usuario ya está en uso.";
        }
        if ($this->model->existeCorreo($d['correo'])) {
            $errores[] = "Ya existe una cuenta con ese correo.";
        }
        if ($this->model->existeCi($d['ci'])) {
            $errores[] = "Ya existe un recepcionista con ese CI.";
        }
    }

    if ($errores) {
        $_SESSION['mensaje_error'] = implode(' ', $errores);
        $_SESSION['old'] = $d;
        header("Location: index.php?route=recepcionistas_crear");
        exit;
    }

    try {
        $this->model->crear($d, password_hash($pass, PASSWORD_DEFAULT));
        $_SESSION['mensaje_exito'] = "Recepcionista registrado correctamente.";
        header("Location: index.php?route=recepcionistas");
    } catch (Throwable $e) {
        error_log($e->getMessage());
        // TEMPORAL mientras desarrollas: muestra el error real
        $_SESSION['mensaje_error'] = "No se pudo registrar al recepcionista: " . $e->getMessage();
        $_SESSION['old'] = $d;
        header("Location: index.php?route=recepcionistas_crear");
    }
    exit;
}
}