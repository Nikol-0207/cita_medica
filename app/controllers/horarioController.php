<?php
require_once __DIR__ . '/../helpers/Permisos.php';
require_once __DIR__ . '/../models/Horario.php';
require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../models/Configuracion.php';

class HorarioController {
    private $model;

    public function __construct() {
        $this->model = new Horario();
    }

    /**
     * Admin y recepcionista ven todos los horarios, el paciente puede filtrar
     * por especialidad o doctor, y el médico solo ve los suyos.
     */
    public function listar() {
        Permisos::exigir('horarios.ver');

        $esMedico      = Permisos::esMedico();
        $esPaciente    = Permisos::esPaciente();
        $estado        = trim($_GET['estado'] ?? '');
        $medico        = ctype_digit($_GET['medico'] ?? '') ? (int)$_GET['medico'] : 0;
        $especialidad  = ctype_digit($_GET['especialidad'] ?? '') ? (int)$_GET['especialidad'] : 0;

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            $estado = '';
        }
        // El médico solo ve su propio horario: se ignora el filtro ?medico= de la URL.
        if ($esMedico) {
            $medico = (new Medico())->idDeCuenta($_SESSION['user_id']);
        }

        // Especialidades que tienen horario: sirven para el filtro y para validar la URL.
        $especialidades = $this->model->especialidadesConHorario();
        if ($especialidad && !in_array($especialidad, array_map('intval', array_column($especialidades, 'id_especialidad')), true)) {
            $especialidad = 0;
        }

        $filtros    = ['estado' => $estado, 'medico' => $medico, 'especialidad' => $especialidad];
        $hayFiltros = ($estado !== '' || $especialidad > 0 || (!$esMedico && $medico > 0));

        $colores      = (new Configuracion())->getColoresPersonalizados();
        require_once __DIR__ . '/../helpers/Paginador.php';
        $pag          = Paginador::cortar($this->model->listar($filtros));
        $horarios     = $pag['items'];
        $medicos      = $this->model->medicosActivos();
        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        require_once __DIR__ . '/../views/horarios/listar.php';
    }

    /** Formulario de alta: solo administrador. */
    public function crear() {
        Permisos::exigir('horarios.gestionar');

        $colores      = (new Configuracion())->getColoresPersonalizados();
        $opciones     = $this->model->opcionesMedicoEspecialidad();
        $dias         = Horario::dias();
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        $old          = $_SESSION['old'] ?? [];
        unset($_SESSION['mensaje_error'], $_SESSION['old']);

        require_once __DIR__ . '/../views/horarios/crear.php';
    }

    public function guardar() {
        Permisos::exigir('horarios.gestionar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=horarios_crear");
            exit;
        }

        $doctor = trim($_POST['doctor'] ?? '');   // formato "idMedico-idEspecialidad"
        $dia    = trim($_POST['dia'] ?? '');
        $ini    = trim($_POST['hora_inicio'] ?? '');
        $fin    = trim($_POST['hora_fin'] ?? '');
        $dur    = trim($_POST['duracion'] ?? '30');
        $fechaFin = trim($_POST['fecha_fin'] ?? '');

        $old    = ['doctor' => $doctor, 'dia' => $dia, 'hora_inicio' => $ini, 'hora_fin' => $fin,
                   'duracion' => $dur, 'fecha_fin' => $fechaFin];
        $errores = [];

        // --- Doctor + especialidad: debe ser una de las opciones activas ---
        $idMedico = 0;
        $idEsp    = 0;
        if (preg_match('/^(\d+)-(\d+)$/', $doctor, $m)) {
            $idMedico = (int)$m[1];
            $idEsp    = (int)$m[2];
        }
        if ($idMedico <= 0 || $idEsp <= 0) {
            $errores[] = "Selecciona el doctor y su especialidad.";
        } else {
            $existe = false;
            foreach ($this->model->opcionesMedicoEspecialidad() as $op) {
                if ((int)$op['id_medico'] === $idMedico && (int)$op['id_especialidad'] === $idEsp) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $errores[] = "Ese doctor no tiene esa especialidad asignada.";
            }
        }

        // --- Día ---
        if (!in_array($dia, Horario::dias(), true)) {
            $errores[] = "Selecciona un día de atención válido.";
        }

        // --- Horas ---
        $patron = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';
        if (!preg_match($patron, $ini)) {
            $errores[] = "La hora de inicio no es válida.";
        }
        if (!preg_match($patron, $fin)) {
            $errores[] = "La hora final no es válida.";
        }

        // --- Duración de la cita ---
        if ($dur === '') {
            $dur = '30';
        }
        if (!ctype_digit($dur) || (int)$dur < 5 || (int)$dur > 240) {
            $errores[] = "La duración de la cita debe estar entre 5 y 240 minutos.";
        }

        // --- Hasta qué fecha estará disponible el horario ---
        $fFin = DateTime::createFromFormat('Y-m-d', $fechaFin);
        if (!$fechaFin || !$fFin || $fFin->format('Y-m-d') !== $fechaFin) {
            $errores[] = "Indica hasta qué fecha estará disponible el horario.";
        } elseif ($fechaFin < date('Y-m-d')) {
            $errores[] = "La fecha límite del horario no puede ser pasada.";
        }

        // --- Reglas de negocio (solo si lo básico está bien) ---
        if (!$errores) {
            $ini = substr($ini, 0, 5) . ':00';
            $fin = substr($fin, 0, 5) . ':00';
            if ($fin <= $ini) {
                $errores[] = "La hora final debe ser posterior a la hora de inicio.";
            } elseif ($this->model->haySolape($idMedico, $dia, $ini, $fin)) {
                $errores[] = "Ese doctor ya tiene un horario activo que se solapa en ese día y rango de horas.";
            }
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            $_SESSION['old'] = $old;
            header("Location: index.php?route=horarios_crear");
            exit;
        }

        try {
            $this->model->crear([
                'id_medico'      => $idMedico,
                'id_especialidad' => $idEsp,
                'dia_semana'     => $dia,
                'hora_inicio'    => $ini,
                'hora_fin'       => $fin,
                'duracion_cita'  => (int)$dur,
                'fecha_fin'      => $fechaFin,
            ]);
            $_SESSION['mensaje_exito'] = "Horario registrado correctamente.";
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "No se pudo registrar el horario: " . $e->getMessage();
            $_SESSION['old'] = $old;
            header("Location: index.php?route=horarios_crear");
            exit;
        }

        header("Location: index.php?route=horarios");
        exit;
    }
}
