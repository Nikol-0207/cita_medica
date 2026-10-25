<?php
require_once __DIR__ . '/../helpers/Permisos.php';
require_once __DIR__ . '/../models/Cita.php';
require_once __DIR__ . '/../models/Horario.php';
require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../models/Configuracion.php';

class CitaController {
    private $model;

    public function __construct() {
        $this->model = new Cita();
    }

    // ----------------------------------------------------------------
    // LISTADO
    // ----------------------------------------------------------------
    public function listar() {
        Permisos::exigir('citas.ver');

        $esMedico   = Permisos::esMedico();
        $esPaciente = Permisos::esPaciente();

        // --- Filtros de la URL, validados antes de tocar la BD ---
        $estado   = trim($_GET['estado'] ?? '');
        $fecha    = trim($_GET['fecha'] ?? '');
        $busqueda = trim($_GET['busqueda'] ?? '');
        $medico   = ctype_digit($_GET['medico'] ?? '') ? (int)$_GET['medico'] : 0;

        if (!in_array($estado, Cita::estados(), true)) {
            $estado = '';
        }
        if ($fecha !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $fecha);
            if (!$d || $d->format('Y-m-d') !== $fecha) {
                $fecha = '';
            }
        }
        if (mb_strlen($busqueda) > 60) {
            $busqueda = mb_substr($busqueda, 0, 60);
        }

        // El médico solo ve SU agenda; el paciente solo las SUYAS.
        if ($esMedico) {
            $medico = (new Medico())->idDeCuenta($_SESSION['user_id']);
        }
        $paciente = 0;
        if ($esPaciente) {
            $paciente = (new Paciente())->idDeCuenta($_SESSION['user_id']);
        }

        $filtros    = ['estado' => $estado, 'medico' => $medico, 'fecha' => $fecha,
                       'busqueda' => $busqueda, 'paciente' => $paciente];
        $hayFiltros = ($estado !== '' || $fecha !== '' || $busqueda !== ''
                       || (!$esMedico && $medico > 0));

        $colores      = (new Configuracion())->getColoresPersonalizados();
        require_once __DIR__ . '/../helpers/Paginador.php';
        $pag          = Paginador::cortar($this->model->listar($filtros));
        $citas        = $pag['items'];
        $medicos      = $this->model->medicosActivos();
        $estados      = Cita::estados();
        $puedeCrear   = Permisos::tiene('citas.crear');
        $puedeConfirmar = Permisos::tiene('citas.confirmar');
        $puedeCancelar  = Permisos::tiene('citas.cancelar');
        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        require_once __DIR__ . '/../views/citas/index.php';
    }

    // ----------------------------------------------------------------
    // NUEVA CITA: elección de horario, fecha y ficha
    // ----------------------------------------------------------------
    public function crear() {
        Permisos::exigir('citas.crear');

        $esPaciente  = Permisos::esPaciente();
        $colores     = (new Configuracion())->getColoresPersonalizados();
        $horarios    = (new Horario())->activosParaCita();

        // Paciente: reserva solo para sí mismo. Admin/recepcionista: elige paciente.
        $pacientePropio = null;
        $pacientes      = [];
        if ($esPaciente) {
            $pacientePropio = (new Paciente())->idDeCuenta($_SESSION['user_id']);
        } else {
            $pacientes = (new Paciente())->activos();
        }

        // --- Parámetros de la disponibilidad ---
        $idHorario = ctype_digit($_GET['horario'] ?? '') ? (int)$_GET['horario'] : 0;
        $fecha     = trim($_GET['fecha'] ?? '');

        $horario   = null;
        $fichas    = [];
        $errorDisp = null;

        if ($idHorario > 0) {
            $horario = (new Horario())->porId($idHorario);
            if (!$horario) {
                $errorDisp = "Ese horario ya no está disponible.";
            } elseif (!Horario::vigente($horario['fecha_fin'])) {
                $errorDisp = "Ese horario venció el " . date('d/m/Y', strtotime($horario['fecha_fin'])) . ".";
            } else {
                if ($fecha === '') {
                    $fecha = $this->proximaFecha($horario['dia_semana'], $horario['hora_fin']);
                }
                $fechaOk = $this->validarFecha($fecha, $horario['dia_semana'], $horario['fecha_fin'], $errorDisp);
                if ($fechaOk !== null) {
                    $fecha = $fechaOk;
                    $fichas = $this->model->disponibilidad($horario, $fecha);
                }
            }
        }

        $mensajeExito = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError = $_SESSION['mensaje_error'] ?? null;
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);

        require_once __DIR__ . '/../views/citas/crear.php';
    }

    // ----------------------------------------------------------------
    // REGISTRO (POST desde la ficha elegida)
    // ----------------------------------------------------------------
    public function guardar() {
        Permisos::exigir('citas.crear');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=citas_crear");
            exit;
        }

        $esPaciente = Permisos::esPaciente();
        $idHorario  = ctype_digit($_POST['horario'] ?? '') ? (int)$_POST['horario'] : 0;
        $fecha      = trim($_POST['fecha'] ?? '');
        $ini        = trim($_POST['hora_inicio'] ?? '');
        $fin        = trim($_POST['hora_fin'] ?? '');
        $motivo     = trim($_POST['motivo'] ?? '');
        $idPaciente = ctype_digit($_POST['id_paciente'] ?? '') ? (int)$_POST['id_paciente'] : 0;

        $volver = "index.php?route=citas_crear&horario={$idHorario}&fecha=" . urlencode($fecha);
        $errores = [];

        // --- Horario ---
        $horario = $idHorario > 0 ? (new Horario())->porId($idHorario) : null;
        if (!$horario) {
            $errores[] = "Elija un horario válido.";
        }

        // --- Fecha: futura, del día del horario y dentro de su vigencia ---
        if ($horario) {
            if (!Horario::vigente($horario['fecha_fin'])) {
                $errores[] = "Ese horario venció el " . date('d/m/Y', strtotime($horario['fecha_fin'])) . ".";
            } else {
                if ($fecha === '') {
                    $fecha = $this->proximaFecha($horario['dia_semana'], $horario['hora_fin']);
                }
                $err = null;
                $fechaOk = $this->validarFecha($fecha, $horario['dia_semana'], $horario['fecha_fin'], $err);
                if ($err) {
                    $errores[] = $err;
                } else {
                    $fecha = $fechaOk;
                }
            }
        }

        // --- Motivo ---
        if (mb_strlen($motivo) > 500) {
            $motivo = mb_substr($motivo, 0, 500);
        }

        // --- Paciente ---
        if ($esPaciente) {
            $idPaciente = (new Paciente())->idDeCuenta($_SESSION['user_id']);
            if ($idPaciente <= 0) {
                $errores[] = "Su cuenta no tiene un paciente vinculado.";
            }
        } elseif ($idPaciente <= 0) {
            $errores[] = "Elija el paciente.";
        } else {
            $activos = array_column((new Paciente())->activos(), 'id_paciente');
            if (!in_array($idPaciente, $activos, true)) {
                $errores[] = "Ese paciente no existe o no está activo.";
            }
        }

        // --- Ficha: debe existir, estar alineada al horario y libre ---
        $fichaOk = false;
        if ($horario && $fecha !== '' && $ini !== '' && $fin !== '' && !$errores) {
            foreach ($this->model->disponibilidad($horario, $fecha) as $f) {
                if ($f['inicio'] === $ini && $f['fin'] === $fin) {
                    $fichaOk = true;
                    if ($f['ocupada']) {
                        $errores[] = "Esa ficha de {$ini} a {$fin} ya está ocupada ({$f['estado']}).";
                    }
                    break;
                }
            }
            if (!$fichaOk && !$errores) {
                $errores[] = "La hora elegida no corresponde a ninguna ficha de ese horario.";
            }
        }

        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            header("Location: " . $volver);
            exit;
        }

        try {
            $this->model->crear([
                'id_horario'   => $horario['id_horario'],
                'id_paciente'  => $idPaciente,
                'id_medico'    => $horario['id_medico'],
                'fecha'        => $fecha,
                'hora_inicio'  => substr($ini, 0, 5) . ':00',
                'hora_fin'     => substr($fin, 0, 5) . ':00',
                'motivo'       => $motivo,
            ]);
            $_SESSION['mensaje_exito'] = "Cita programada correctamente (estado: Programada).";
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $_SESSION['mensaje_error'] = "No se pudo programar la cita: " . $e->getMessage();
        }

        header("Location: " . $volver);
        exit;
    }

    // ----------------------------------------------------------------
    // CONFIRMAR (solo admin y recepcionista)
    // ----------------------------------------------------------------
    public function confirmar() {
        Permisos::exigir('citas.confirmar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=citas");
            exit;
        }

        $id = (int)($_POST['id_cita'] ?? 0);
        if ($id > 0 && $this->model->confirmar($id)) {
            $_SESSION['mensaje_exito'] = "Cita confirmada correctamente.";
        } else {
            $_SESSION['mensaje_error'] = "La cita no existe o no está en estado Programada.";
        }

        header("Location: index.php?route=citas");
        exit;
    }

    // ----------------------------------------------------------------
    // CANCELAR (admin, recepcionista y el paciente solo de la suya)
    // ----------------------------------------------------------------
    public function cancelar() {
        Permisos::exigir('citas.cancelar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=citas");
            exit;
        }

        $id   = ctype_digit($_POST['id_cita'] ?? '') ? (int)$_POST['id_cita'] : 0;
        $motivo = trim($_POST['motivo_cancelacion'] ?? '');
        if (mb_strlen($motivo) > 300) {
            $motivo = mb_substr($motivo, 0, 300);
        }

        // El motivo es obligatorio: es la justificación de la cancelación.
        if ($motivo === '') {
            $_SESSION['mensaje_error'] = "Indica el motivo de la cancelación.";
            header("Location: index.php?route=citas");
            exit;
        }

        // El paciente solo puede cancelar las citas que son suyas.
        $idPaciente = 0;
        if (Permisos::esPaciente()) {
            $idPaciente = (new Paciente())->idDeCuenta($_SESSION['user_id']);
        }

        $resultado = ($id > 0 && ($idPaciente > 0 || !Permisos::esPaciente()))
            ? $this->model->cancelar($id, $idPaciente, $motivo)
            : 'no_existe';

        switch ($resultado) {
            case 'ok':
                $_SESSION['mensaje_exito'] = "Cita cancelada: se registró el motivo y la ficha quedó libre "
                    . "para volver a reservarse.";
                break;
            case 'estado':
                $_SESSION['mensaje_error'] = "Solo se pueden cancelar citas en estado Programada o Confirmada.";
                break;
            case 'pasada':
                $_SESSION['mensaje_error'] = "Ya pasó la fecha y hora de atención: la cita ya no se puede cancelar.";
                break;
            default:
                $_SESSION['mensaje_error'] = "La cita no existe o no le pertenece.";
                break;
        }

        header("Location: index.php?route=citas");
        exit;
    }

    // ----------------------------------------------------------------
    // AUXILIARES
    // ----------------------------------------------------------------
    /** Próximo día (o el de hoy si aún quedan horas) de la semana dada. */
    private function proximaFecha($diaSemana, $horaFin) {
        $dias = ['Domingo' => 0, 'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3,
                 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];
        $objetivo  = $dias[$diaSemana] ?? 1;
        $hoy       = new DateTime('today');
        $diferencia = ($objetivo - (int)$hoy->format('w') + 7) % 7;

        // Si hoy es el día pero el horario ya terminó, se pasa a la próxima semana.
        if ($diferencia === 0) {
            $finHorario = new DateTime('today ' . substr($horaFin, 0, 5));
            if (new DateTime('now') >= $finHorario) {
                $diferencia = 7;
            }
        }
        return $hoy->modify("+$diferencia days")->format('Y-m-d');
    }

    /**
     * Valida que la fecha exista, no sea pasada, caiga en el día del horario
     * y no supere la fecha límite de vigencia del horario.
     * Devuelve la fecha (Y-m-d) o null; en $error deja el mensaje.
     */
    private function validarFecha($fecha, $diaSemana, $fechaFin, &$error) {
        $error = null;
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fecha || !$d || $d->format('Y-m-d') !== $fecha) {
            $error = "La fecha no es válida.";
            return null;
        }

        $dias = ['Domingo' => 0, 'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3,
                 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];
        if ((int)$d->format('w') !== ($dias[$diaSemana] ?? -2)) {
            $error = "El horario es de {$diaSemana}: la fecha elegida debe ser un/a {$diaSemana}.";
            return null;
        }
        if ($d->format('Y-m-d') < date('Y-m-d')) {
            $error = "La fecha de la cita no puede ser pasada.";
            return null;
        }
        if ($fechaFin !== null && $fecha > $fechaFin) {
            $error = "Ese horario está disponible hasta el " . date('d/m/Y', strtotime($fechaFin)) . ".";
            return null;
        }
        return $fecha;
    }
}
