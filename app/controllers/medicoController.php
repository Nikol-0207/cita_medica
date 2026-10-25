<?php
require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../helpers/Permisos.php';

class MedicoController {
    private $model;

    public function __construct() {
        $this->model = new Medico();
    }

    public function listar() {
        Permisos::exigir('medicos.ver');

        $filtro = (isset($_GET['especialidad']) && ctype_digit($_GET['especialidad']))
            ? (int)$_GET['especialidad']
            : null;

        $colores        = (new Configuracion())->getColoresPersonalizados();
        $especialidades = $this->model->especialidadesActivas();
        require_once __DIR__ . '/../helpers/Paginador.php';
        $pag            = Paginador::cortar($this->model->listar($filtro));
        $medicos        = $pag['items'];
        $mensajeExito   = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError   = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        require_once __DIR__ . '/../views/medicos/listar.php';
    }

    /**
     * Activar / desactivar un médico (solo admin: permiso medicos.gestionar).
     * Desactivarlo oculta sus horarios de la reserva y del calendario;
     * sus citas existentes se conservan.
     */
    public function cambiarEstado() {
        Permisos::exigir('medicos.gestionar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=medicos");
            exit;
        }

        $id         = ctype_digit($_POST['id_medico'] ?? '') ? (int)$_POST['id_medico'] : 0;
        $estado     = $id > 0 ? $this->model->cambiarEstado($id) : null;
        $especialidad = ctype_digit($_POST['especialidad'] ?? '') ? (int)$_POST['especialidad'] : 0;
        $volver     = 'index.php?route=medicos' . ($especialidad > 0 ? '&especialidad=' . $especialidad : '');

        if ($estado === null) {
            $_SESSION['mensaje_error'] = "Ese médico no existe.";
        } elseif ($estado === 'Inactivo') {
            $_SESSION['mensaje_exito'] = "Médico desactivado: sus horarios ya no se ofrecen al programar citas "
                . "ni aparecen en el calendario. Sus citas existentes se conservan.";
        } else {
            $_SESSION['mensaje_exito'] = "Médico activado: sus horarios vuelven a estar disponibles.";
        }

        header("Location: " . $volver);
        exit;
    }

    public function crear() {
        Permisos::exigir('medicos.crear');

        $colores        = (new Configuracion())->getColoresPersonalizados();
        $especialidades = $this->model->especialidadesActivas();
        $mensajeError   = $_SESSION['mensaje_error'] ?? null;
        $old            = $_SESSION['old'] ?? [];
        unset($_SESSION['mensaje_error'], $_SESSION['old']);

        require_once __DIR__ . '/../views/medicos/crear.php';
    }

    public function guardar() {
        Permisos::exigir('medicos.crear');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=medicos_crear");
            exit;
        }

        $d = [
            'nombres'              => trim($_POST['nombres'] ?? ''),
            'apellidos'            => trim($_POST['apellidos'] ?? ''),
            'registro_profesional' => trim($_POST['registro_profesional'] ?? ''),
            'telefono'             => trim($_POST['telefono'] ?? ''),
            'usuario_nombre'       => trim($_POST['usuario_nombre'] ?? ''),
            'correo'               => trim($_POST['correo'] ?? ''),
        ];
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password_confirmar'] ?? '';

        // Especialidades: solo ids numéricos, sin repetidos
        $seleccion = isset($_POST['especialidades']) && is_array($_POST['especialidades'])
            ? array_values(array_unique(array_map('intval', $_POST['especialidades'])))
            : [];
        $d['especialidades'] = $seleccion;

        $errores = [];

        foreach (['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'registro_profesional' => 'Registro profesional'] as $campo => $etiqueta) {
            if ($d[$campo] === '') {
                $errores[] = "El campo $etiqueta es obligatorio.";
            }
        }

        if (!preg_match('/^\+?[0-9]{7,15}$/', $d['telefono'])) {
            $errores[] = "El teléfono no es válido (solo números, 7 a 15 dígitos).";
        }
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

        // Especialidades: al menos una y todas válidas
        if (!$seleccion) {
            $errores[] = "Selecciona al menos una especialidad.";
        } else {
            $validas = array_map('intval', array_column($this->model->especialidadesActivas(), 'id_especialidad'));
            if (array_diff($seleccion, $validas)) {
                $errores[] = "Alguna especialidad seleccionada no es válida.";
            }
        }

        if (!$errores) {
            if ($d['registro_profesional'] !== '' && $this->model->existeRegistro($d['registro_profesional'])) {
                $errores[] = "Ya existe un médico con ese registro profesional.";
            }
            if ($this->model->existeNombreCuenta($d['usuario_nombre'])) {
                $errores[] = "Ese nombre de usuario ya está en uso.";
            }
            if ($this->model->existeCorreoCuenta($d['correo'])) {
                $errores[] = "Ya existe una cuenta con ese correo.";
            }
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            $_SESSION['old'] = $d;
            header("Location: index.php?route=medicos_crear");
            exit;
        }

        try {
            $this->model->crear($d, password_hash($pass, PASSWORD_DEFAULT));
            $_SESSION['mensaje_exito'] = "Médico registrado correctamente.";
            header("Location: index.php?route=medicos");
        } catch (Throwable $e) {
            error_log($e->getMessage());
            // TEMPORAL mientras desarrollas: muestra el error real
            $_SESSION['mensaje_error'] = "No se pudo registrar al médico: " . $e->getMessage();
            $_SESSION['old'] = $d;
            header("Location: index.php?route=medicos_crear");
        }
        exit;
    }
}