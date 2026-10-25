<?php
session_start();
// La base de datos trabaja en America/La_Paz y la hora del servidor también:
// si PHP se queda en UTC, todas las comparaciones de fecha y hora (crear una
// cita, su vigencia o cancelarla antes de que empiece) se desalían 4 horas.
date_default_timezone_set('America/La_Paz');
define('ROOT_PATH', realpath(__DIR__ . '/..'));
require_once ROOT_PATH . '/app/helpers/Permisos.php';  
$route = $_GET['route'] ?? 'home';


ini_set('display_errors', 1);
error_reporting(E_ALL);

switch ($route) {
    case 'home':
        require_once ROOT_PATH . '/app/controllers/AuthController.php';
        (new AuthController())->showHome();
        break;

    case 'login':
        require_once ROOT_PATH . '/app/controllers/AuthController.php';
        $controller = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->login();
        } else {
            $controller->showHome();
        }
        break;

    case 'logout':
        require_once ROOT_PATH . '/app/controllers/AuthController.php';
        (new AuthController())->logout();
        break;

    case 'citas':
        require_once ROOT_PATH . '/app/controllers/citaController.php';
        (new CitaController())->listar();
        break;
    case 'citas_crear':
        require_once ROOT_PATH . '/app/controllers/citaController.php';
        (new CitaController())->crear();
        break;
    case 'citas_guardar':
        require_once ROOT_PATH . '/app/controllers/citaController.php';
        (new CitaController())->guardar();
        break;
    case 'citas_confirmar':
        require_once ROOT_PATH . '/app/controllers/citaController.php';
        (new CitaController())->confirmar();
        break;
    case 'citas_cancelar':
        require_once ROOT_PATH . '/app/controllers/citaController.php';
        (new CitaController())->cancelar();
        break;
    case 'horarios':
        require_once ROOT_PATH . '/app/controllers/horarioController.php';
        (new HorarioController())->listar();
        break;
    case 'horarios_crear':
        require_once ROOT_PATH . '/app/controllers/horarioController.php';
        (new HorarioController())->crear();
        break;
    case 'horarios_guardar':
        require_once ROOT_PATH . '/app/controllers/horarioController.php';
        (new HorarioController())->guardar();
        break;
    case 'admin':
        require_once ROOT_PATH . '/app/controllers/AdminController.php';
        (new AdminController())->dashboard();
        break;
    case 'configuraciones':
        require_once ROOT_PATH . '/app/controllers/AdminController.php';
        (new AdminController())->configuraciones();
        break;
    case 'cambiar_foto':
        require_once ROOT_PATH . '/app/controllers/AuthController.php';
        (new AuthController())->cambiarFotoPerfil();
        break;
    case 'perfil':
        require_once ROOT_PATH . '/app/controllers/AuthController.php';
        (new AuthController())->perfil();
        break;
    case 'guardar_perfil':
        require_once ROOT_PATH . '/app/controllers/AuthController.php';
        (new AuthController())->guardarPerfil();
        break;
    case 'recepcionistas':
        require_once ROOT_PATH . '/app/controllers/RecepcionistaController.php';
        (new RecepcionistaController())->listar();
    break;
    case 'recepcionistas_crear':
        require_once ROOT_PATH . '/app/controllers/RecepcionistaController.php';
        (new RecepcionistaController())->crear();
        break;
    case 'recepcionistas_guardar':
        require_once ROOT_PATH . '/app/controllers/RecepcionistaController.php';
        (new RecepcionistaController())->guardar();
        break;
        case 'pacientes':
        require_once ROOT_PATH . '/app/controllers/pacienteController.php';
        (new PacienteController())->listar();
        break;
    case 'pacientes_crear':
        require_once ROOT_PATH . '/app/controllers/pacienteController.php';
        (new PacienteController())->crear();
        break;
    case 'pacientes_guardar':
        require_once ROOT_PATH . '/app/controllers/pacienteController.php';
        (new PacienteController())->guardar();
        break;
        case 'medicos':
        require_once ROOT_PATH . '/app/controllers/medicoController.php';
        (new MedicoController())->listar();
        break;
    case 'medicos_crear':
        require_once ROOT_PATH . '/app/controllers/medicoController.php';
        (new MedicoController())->crear();
        break;
    case 'medicos_guardar':
        require_once ROOT_PATH . '/app/controllers/medicoController.php';
        (new MedicoController())->guardar();
        break;
    case 'medicos_estado':
        require_once ROOT_PATH . '/app/controllers/medicoController.php';
        (new MedicoController())->cambiarEstado();
        break;
        case 'especialidades':
        require_once ROOT_PATH . '/app/controllers/especialidadController.php';
        (new EspecialidadController())->listar();
        break;
    case 'especialidades_guardar':
        require_once ROOT_PATH . '/app/controllers/especialidadController.php';
        (new EspecialidadController())->guardar();
        break;
    case 'especialidades_actualizar':
        require_once ROOT_PATH . '/app/controllers/especialidadController.php';
        (new EspecialidadController())->actualizar();
        break;
    case 'recepcion':
        require_once ROOT_PATH . '/app/controllers/AdminController.php';
        (new AdminController())->dashboard();
        break;
    default:
        http_response_code(404);
        echo "Página no encontrada.";
        break;
}