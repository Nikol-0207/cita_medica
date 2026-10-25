<?php
require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../helpers/Permisos.php';

class PacienteController {
    private $model;

    public function __construct() {
        $this->model = new Paciente();
    }

    public function listar() {
        Permisos::exigir('pacientes.ver');

        // --- Búsqueda por nombre, apellido, CI o código (escribe y filtra) ---
        $busqueda = trim($_GET['busqueda'] ?? '');
        if (mb_strlen($busqueda) > 60) {
            $busqueda = mb_substr($busqueda, 0, 60);
        }

        // El médico solo ve a sus propios pacientes (los que tienen citas con él).
        $esMedico = Permisos::esMedico();
        $todos    = $esMedico
            ? $this->model->listarDelMedico((new Medico())->idDeCuenta($_SESSION['user_id']))
            : $this->model->listar();

        if ($busqueda !== '') {
            $busca = mb_strtolower($busqueda);
            $todos = array_values(array_filter($todos, function ($p) use ($busca) {
                foreach (['nombres', 'apellidos', 'ci', 'codigo'] as $campo) {
                    $valor = trim((string)($p[$campo] ?? ''));
                    if ($valor !== '' && mb_stripos($valor, $busca) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        require_once __DIR__ . '/../helpers/Paginador.php';
        $pag       = Paginador::cortar($todos);
        $pacientes = $pag['items'];
        $hayFiltros = ($busqueda !== '');

        $colores      = (new Configuracion())->getColoresPersonalizados();
        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        require_once __DIR__ . '/../views/pacientes/listar.php';
    }

         public function crear() {
        Permisos::exigir('pacientes.crear');

        $colores      = (new Configuracion())->getColoresPersonalizados();
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        $old          = $_SESSION['old'] ?? [];
        unset($_SESSION['mensaje_error'], $_SESSION['old']);

        require_once __DIR__ . '/../views/pacientes/crear.php';
    }
     
     public function guardar() {
    Permisos::exigir('pacientes.crear');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: index.php?route=pacientes_crear");
        exit;
    }

    $campos = ['nombres', 'apellidos', 'ci', 'nro_seguro', 'fecha_nacimiento', 'sexo', 'telefono',
               'correo', 'direccion', 'tipo_sangre', 'alergias', 'contacto_nombre',
               'contacto_parentesco', 'contacto_telefono', 'observaciones',
               'usuario_nombre'];                                     // NUEVO
    $d = [];
    foreach ($campos as $c) {
        $d[$c] = trim($_POST[$c] ?? '');
    }
    $crearCuenta      = isset($_POST['crear_cuenta']);                // NUEVO
    $d['crear_cuenta'] = $crearCuenta ? '1' : '';                     // NUEVO
    $pass  = $_POST['password'] ?? '';                                // NUEVO
    $pass2 = $_POST['password_confirmar'] ?? '';                      // NUEVO

    $errores = [];

    // 1. Obligatorios (coinciden con los NOT NULL de la tabla paciente)
    $obligatorios = [
        'nombres'          => 'Nombres',
        'apellidos'        => 'Apellidos',
        'ci'               => 'CI',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'sexo'             => 'Género',
        'telefono'         => 'Celular',
        'direccion'        => 'Dirección',
    ];
    foreach ($obligatorios as $campo => $etiqueta) {
        if ($d[$campo] === '') {
            $errores[] = "El campo $etiqueta es obligatorio.";
        }
    }

    // 2. Formato (solo si escribió algo)
    if ($d['ci'] !== '') {
        if (!preg_match('/^[0-9A-Za-z-]{5,20}$/', $d['ci'])) {
            $errores[] = "El CI no es válido.";
        } elseif ($this->model->existeCi($d['ci'])) {
            $errores[] = "Ya existe un paciente con ese CI.";
        }
    }

    if ($d['telefono'] !== '' && !preg_match('/^\+?[0-9]{7,15}$/', $d['telefono'])) {
        $errores[] = "El celular no es válido (solo números, 7 a 15 dígitos).";
    }

    if ($d['fecha_nacimiento'] !== '') {
        $fecha = DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
        if (!$fecha || $fecha->format('Y-m-d') !== $d['fecha_nacimiento']) {
            $errores[] = "La fecha de nacimiento no es válida.";
        } elseif ($fecha > new DateTime()) {
            $errores[] = "La fecha de nacimiento no puede ser futura.";
        }
    }

    if ($d['sexo'] !== '' && !in_array($d['sexo'], ['M', 'F'], true)) {
        $errores[] = "Selecciona un género válido.";
    }

    if ($d['tipo_sangre'] !== '' && !in_array($d['tipo_sangre'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $errores[] = "Selecciona un grupo sanguíneo válido.";
    }

    if ($d['correo'] !== '' && !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // 3. Contacto de emergencia: opcional, pero si lo llena debe estar completo
    $hayContacto = $d['contacto_nombre'] !== '' || $d['contacto_parentesco'] !== '' || $d['contacto_telefono'] !== '';
    if ($hayContacto) {
        if ($d['contacto_nombre'] === '') {
            $errores[] = "Indica el nombre del contacto de emergencia.";
        }
        if ($d['contacto_telefono'] === '') {
            $errores[] = "Indica el teléfono del contacto de emergencia.";
        } elseif (!preg_match('/^\+?[0-9]{7,15}$/', $d['contacto_telefono'])) {
            $errores[] = "El teléfono del contacto de emergencia no es válido.";
        }
    }

    // 4. NUEVO: cuenta de usuario (solo si marcó el check)
    $hash = null;
    if ($crearCuenta) {
        if ($d['correo'] === '') {
            $errores[] = "Para crear la cuenta, el correo es obligatorio.";
        } elseif (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) && $this->model->existeCorreoCuenta($d['correo'])) {
            $errores[] = "Ya existe una cuenta con ese correo.";
        }

        if (strlen($d['usuario_nombre']) < 3) {
            $errores[] = "El nombre de usuario debe tener al menos 3 caracteres.";
        } elseif ($this->model->existeNombreCuenta($d['usuario_nombre'])) {
            $errores[] = "Ese nombre de usuario ya está en uso.";
        }

        if (strlen($pass) < 8) {
            $errores[] = "La contraseña debe tener al menos 8 caracteres.";
        } elseif ($pass !== $pass2) {
            $errores[] = "La verificación de la contraseña no coincide.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
        }
    }

    if ($errores) {
        $_SESSION['mensaje_error'] = implode(' ', $errores);
        $_SESSION['old'] = $d;
        header("Location: index.php?route=pacientes_crear");
        exit;
    }

    try {
        $this->model->crear($d, $hash);                               // NUEVO: segundo parámetro
        $_SESSION['mensaje_exito'] = $crearCuenta
            ? "Paciente registrado con cuenta de usuario."
            : "Paciente registrado correctamente.";
        header("Location: index.php?route=pacientes");
    } catch (Throwable $e) {
        error_log($e->getMessage());
        // TEMPORAL mientras desarrollas: muestra el error real
        $_SESSION['mensaje_error'] = "No se pudo registrar al paciente: " . $e->getMessage();
        $_SESSION['old'] = $d;
        header("Location: index.php?route=pacientes_crear");
    }
    exit;
    }
    
}