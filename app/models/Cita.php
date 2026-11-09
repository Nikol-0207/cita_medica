<?php
require_once __DIR__ . '/../../config/Database.php';

class Cita {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /** Estados válidos (coinciden con el CHECK de la tabla cita). */
    public static function estados() {
        return ['Pendiente', 'Confirmada', 'Atendida', 'Cancelada', 'No asistió'];
    }

    /**
     * Ejecuta una escritura sobre `cita` definiendo el usuario actual
     * (app.user_id) dentro de MISMA transacción: los triggers
     * trg_historial_cita y trg_auditoria_citas llaman a fn_usuario_actual()
     * y fallan si la variable no está definida.
     * set_config con is_local=true vive hasta el COMMIT (seguro con PgBouncer).
     *
     * @param int      $idCuenta  usuario de la operación (0 = sesión actual)
     * @param callable $operacion escritura a ejecutar; su retorno se devuelve
     */
    private function conUsuario($idCuenta, callable $operacion) {
        $id = (int)$idCuenta > 0 ? (int)$idCuenta : (int)($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No se definió el usuario de la operación (app.user_id).');
        }

        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare("SELECT set_config('app.user_id', :uid, true)");
            $stmt->execute([':uid' => (string)$id]);
            $resultado = $operacion();
            if ($propia) {
                $this->db->commit();
            }
            return $resultado;
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ¿Está bloqueado ese rango de atención del médico en esa fecha?
     * Usa la función de la BD fn_dia_bloqueado(); si la función aún no
     * existiera, devuelve false y deja que lo decida el trigger al insertar.
     */
    public function diaBloqueado($idMedico, $fecha, $ini, $fin) {
        try {
            $stmt = $this->db->prepare(
                "SELECT fn_dia_bloqueado(CAST(:med AS INT), CAST(:fecha AS DATE),
                                         CAST(:ini AS TIME), CAST(:fin AS TIME))"
            );
            $stmt->execute([
                ':med'   => (int)$idMedico,
                ':fecha' => $fecha,
                ':ini'   => $ini,
                ':fin'   => $fin,
            ]);
            // pdo_pgsql puede devolver el booleano como true/false o como 't'/'f'.
            $valor = $stmt->fetchColumn();
            return $valor === true || $valor === 't' || $valor === 'true'
                || $valor === '1' || $valor === 1;
        } catch (Throwable $e) {
            error_log('fn_dia_bloqueado no disponible: ' . $e->getMessage());
            return false;
        }
    }

    public function medicosActivos() {
        return $this->db->query(
            "SELECT m.id_medico, p.nombres, p.apellidos
             FROM medico m
             JOIN persona p ON p.id_persona = m.id_persona
             WHERE p.estado = 'Activo'
             ORDER BY p.apellidos, p.nombres"
        )->fetchAll();
    }

    /**
     * Listado de citas con filtros opcionales:
     *  - estado   : alguna clave de estados()
     *  - medico   : id del médico
     *  - fecha    : fecha exacta (Y-m-d)
     *  - busqueda : código de cita, código/CI o nombre del paciente
     * Ordena de la cita más próxima a la más lejana.
     */
    public function listar(array $f = []) {
        $sql = "SELECT c.id_cita, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.motivo, c.estado,
                       c.motivo_cancelacion,
                       pac.codigo AS paciente_codigo, pp.ci AS paciente_ci,
                       pp.nombres AS paciente_nombres, pp.apellidos AS paciente_apellidos,
                       mp.nombres AS medico_nombres, mp.apellidos AS medico_apellidos,
                       e.nombre AS especialidad
                FROM cita c
                JOIN paciente pac              ON pac.id_paciente     = c.id_paciente
                JOIN persona pp                ON pp.id_persona       = pac.id_persona
                JOIN medico m                  ON m.id_medico         = c.id_medico
                JOIN persona mp                ON mp.id_persona       = m.id_persona
                JOIN horario_atencion h        ON h.id_horario        = c.id_horario
                JOIN medico_especialidad me    ON me.id_medico_especialidad = h.id_medico_especialidad
                LEFT JOIN especialidades e     ON e.id_especialidad   = me.id_especialidad
                WHERE 1 = 1";

        $params = [];

        if (!empty($f['estado']) && in_array($f['estado'], self::estados(), true)) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = $f['estado'];
        }
        if (!empty($f['medico'])) {
            $sql .= " AND c.id_medico = :medico";
            $params[':medico'] = (int)$f['medico'];
        }
        if (!empty($f['paciente'])) {
            $sql .= " AND c.id_paciente = :paciente";
            $params[':paciente'] = (int)$f['paciente'];
        }
        if (!empty($f['fecha'])) {
            $sql .= " AND c.fecha = :fecha";
            $params[':fecha'] = $f['fecha'];
        }
        if (!empty($f['busqueda'])) {
            // Cada placeholder se usa una sola vez: PDO con PostgreSQL no repite nombres.
            $like = '%' . $f['busqueda'] . '%';
            $sql .= " AND (c.codigo      ILIKE :b_codigo
                        OR pac.codigo    ILIKE :b_paciente
                        OR pp.ci         ILIKE :b_ci
                        OR pp.nombres    ILIKE :b_nombres
                        OR pp.apellidos  ILIKE :b_apellidos)";
            $params[':b_codigo']    = $like;
            $params[':b_paciente']  = $like;
            $params[':b_ci']        = $like;
            $params[':b_nombres']   = $like;
            $params[':b_apellidos'] = $like;
        }

        $sql .= " ORDER BY c.fecha ASC, c.hora_inicio ASC, mp.apellidos";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Las fichas de un horario para una fecha concreta: cada tramo de
     * duracion_cita minutos (08:00-08:15, 08:15-08:30, ...), indicando cuáles
     * ya están ocupadas por citas de ese médico ese día.
     * Las citas Cancelada NO ocupan ficha: al cancelar, la hora queda libre
     * de nuevo para reservar.
     */
    public function disponibilidad(array $horario, $fecha) {
        $stmt = $this->db->prepare(
            "SELECT hora_inicio, hora_fin, estado
             FROM cita
             WHERE id_medico = :med AND fecha = :fecha
               AND estado <> 'Cancelada'"
        );
        $stmt->execute([':med' => (int)$horario['id_medico'], ':fecha' => $fecha]);
        $citadas = [];
        foreach ($stmt->fetchAll() as $c) {
            $citadas[] = [
                'ini'   => self::aMinutos($c['hora_inicio']),
                'fin'   => self::aMinutos($c['hora_fin']),
                'estado' => $c['estado'],
            ];
        }

        $dur = (int)$horario['duracion_cita'];
        $ini = self::aMinutos($horario['hora_inicio']);
        $fin = self::aMinutos($horario['hora_fin']);

        // Para la fecha de hoy no se ofrecen fichas cuya hora de inicio ya pasó,
        // aunque el horario de atención todavía no haya terminado.
        $esHoy    = ($fecha === date('Y-m-d'));
        $ahoraMin = (int)date('H') * 60 + (int)date('i') + (int)date('s') / 60;

        $fichas = [];
        if ($dur > 0) {
            for ($t = $ini; $t + $dur <= $fin; $t += $dur) {
                if ($esHoy && $t < $ahoraMin) {
                    continue;
                }

                $ocupada = null;
                foreach ($citadas as $c) {
                    if ($c['ini'] < $t + $dur && $c['fin'] > $t) {   // solape
                        $ocupada = $c['estado'];
                        break;
                    }
                }
                $fichas[] = [
                    'inicio'  => self::aHora($t),
                    'fin'     => self::aHora($t + $dur),
                    'ocupada' => $ocupada !== null,
                    'estado'  => $ocupada,
                ];
            }
        }
        return $fichas;
    }

    /**
     * Programa la cita en estado "Pendiente" y devuelve su id.
     * - No se inserta `codigo`: es columna generada (GENERATED ALWAYS AS ... STORED).
     * - `id_cuenta_registro` es NOT NULL: registra quién hizo la reserva.
     * - `motivo` es VARCHAR(300): se recorta antes de insertar.
     * - Se define app.user_id para los triggers de historial y auditoría;
     *   trg_validar_cita, trg_validar_fecha_futura_cita y
     *   trg_validar_medico_activo validan la reserva en la BD.
     */
    public function crear(array $d) {
        $motivo = trim((string)($d['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = null;
        } elseif (mb_strlen($motivo) > 300) {
            $motivo = mb_substr($motivo, 0, 300);
        }

        try {
            return $this->conUsuario($d['id_cuenta_registro'] ?? 0, function () use ($d, $motivo) {
                $stmt = $this->db->prepare(
                    "INSERT INTO cita (id_horario, id_paciente, id_medico, id_cuenta_registro,
                                       fecha, hora_inicio, hora_fin, motivo, estado)
                     VALUES (:horario, :paciente, :medico, :registro, :fecha, :ini, :fin, :motivo, 'Pendiente')
                 RETURNING id_cita"
                );
                $stmt->execute([
                    ':horario'   => (int)$d['id_horario'],
                    ':paciente'  => (int)$d['id_paciente'],
                    ':medico'    => (int)$d['id_medico'],
                    ':registro'  => (int)($d['id_cuenta_registro'] ?? ($_SESSION['user_id'] ?? 0)),
                    ':fecha'     => $d['fecha'],
                    ':ini'       => $d['hora_inicio'],
                    ':fin'       => $d['hora_fin'],
                    ':motivo'    => $motivo,
                ]);
                return (int)$stmt->fetchColumn();
            });
        } catch (PDOException $e) {
            // uq_cita_medico_hora / uq_cita_franja / uq_cita_paciente_hora:
            // dos reservas simultáneas para la misma ficha.
            $sqlstate = $e->errorInfo[0] ?? $e->getCode();
            if ($sqlstate === '23505') {
                throw new RuntimeException(
                    'Esa ficha acaba de ser ocupada por otra reserva. Refresque y elija otra.'
                );
            }
            throw $e;
        }
    }

    /** Pasa una cita de "Pendiente" a "Confirmada" (dispara el historial en la BD). */
    public function confirmar($idCita) {
        return $this->conUsuario(0, function () use ($idCita) {
            $stmt = $this->db->prepare(
                "UPDATE cita
                    SET estado = 'Confirmada', fecha_actualizacion = NOW()
                  WHERE id_cita = :id AND estado = 'Pendiente'
             RETURNING id_cita"
            );
            $stmt->execute([':id' => (int)$idCita]);
            return (bool)$stmt->fetchColumn();
        });
    }

    /**
     * Marca como "No asistió" las citas que siguen en "Pendiente" cuando ya
     * pasó su hora de inicio (nadie las confirmó ni canceló).
     * Si se indica un paciente, solo revisa las citas de ese paciente: así el
     * paciente ve su cita vencida como "No asistió" y sin opción de cancelar,
     * mientras que el personal conserva la acción manual de marcarla.
     */
    public function marcarInasistenciasVencidas($idPaciente = 0) {
        return $this->conUsuario(0, function () use ($idPaciente) {
            $sql = "UPDATE cita
                       SET estado = 'No asistió', fecha_actualizacion = NOW()
                     WHERE estado = 'Pendiente'
                       AND ((fecha + hora_inicio) AT TIME ZONE 'America/La_Paz') <= NOW()";
            $params = [];
            if ($idPaciente > 0) {
                $sql .= " AND id_paciente = :pac";
                $params[':pac'] = (int)$idPaciente;
            }
            $sql .= " RETURNING id_cita";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        });
    }

    /**
     * El médico marca como Atendida una cita Confirmada o que quedó en
     * "No asistió" (para corregir a un paciente que llegó tarde).
     * Solo desde que empezó la hora de la cita y solo de su propia agenda.
     */
    public function marcarAtendida($idCita, $idMedico = 0) {
        return $this->conUsuario(0, function () use ($idCita, $idMedico) {
            $sql = "UPDATE cita
                       SET estado = 'Atendida', fecha_actualizacion = NOW()
                     WHERE id_cita = :id
                       AND estado IN ('Confirmada', 'No asistió')
                       AND ((fecha + hora_inicio) AT TIME ZONE 'America/La_Paz') <= NOW()";
            $params = [':id' => (int)$idCita];

            if ($idMedico > 0) {
                $sql .= " AND id_medico = :med";
                $params[':med'] = (int)$idMedico;
            }

            $sql .= " RETURNING id_cita";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (bool)$stmt->fetchColumn();
        });
    }

    /**
     * Marca una cita como "No asistió". El médico solo puede hacerlo con sus
     * propias citas; el personal administrativo, con cualquiera.
     * Solo se permite desde que empezó la hora de la cita.
     */
    public function marcarInasistencia($idCita, $idMedico = 0) {
        return $this->conUsuario(0, function () use ($idCita, $idMedico) {
            $sql = "UPDATE cita
                       SET estado = 'No asistió', fecha_actualizacion = NOW()
                     WHERE id_cita = :id
                       AND estado IN ('Pendiente', 'Confirmada')
                       AND ((fecha + hora_inicio) AT TIME ZONE 'America/La_Paz') <= NOW()";
            $params = [':id' => (int)$idCita];

            if ($idMedico > 0) {
                $sql .= " AND id_medico = :med";
                $params[':med'] = (int)$idMedico;
            }

            $sql .= " RETURNING id_cita";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (bool)$stmt->fetchColumn();
        });
    }

    /**
     * Cancela una cita mientras su fecha + hora de inicio NO haya pasado
     * (programada a las 16:00, cancelada a las 15:00 → se permite).
     * Guarda el motivo indicado por quien cancela (la BD exige que no sea
     * NULL cuando el estado es "Cancelada").
     *
     * Si $idPaciente > 0 solo cancela la cita de ESE paciente (rol Paciente).
     * Devuelve:
     *   'ok'          → cancelada (la ficha queda libre para volver a reservar)
     *   'no_existe'   → no existe o no es del paciente
     *   'estado'      → no está en estado Pendiente/Confirmada
     *   'pasada'      → ya empezó (o terminó) la atención
     *   'inasistencia'→ seguía Pendiente y la hora ya pasó → "No asistió"
     *   'error'       → fallo inesperado de la BD
     */
    public function cancelar($idCita, $idPaciente = 0, $motivo = '') {
        $motivo = trim((string)$motivo);
        if ($motivo === '') {
            $motivo = 'Sin motivo indicado';
        } elseif (mb_strlen($motivo) > 300) {
            $motivo = mb_substr($motivo, 0, 300);
        }

        // 1) Se inspecciona la cita para poder explicar el motivo del rechazo.
        $sql  = "SELECT c.id_cita, c.estado, c.fecha, c.hora_inicio";
        $params = [];
        if ($idPaciente > 0) {
            $sql .= ", (c.id_paciente = :pac1) AS es_mia";
            $params[':pac1'] = (int)$idPaciente;
        }
        $sql .= " FROM cita c WHERE c.id_cita = :id1";
        $params[':id1'] = (int)$idCita;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $cita = $stmt->fetch();

        if (!$cita || (isset($cita['es_mia']) && !$cita['es_mia'])) {
            return 'no_existe';
        }
        if (!in_array($cita['estado'], ['Pendiente', 'Confirmada'], true)) {
            return 'estado';
        }
        if (strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time()) {
            // Si seguía Pendiente, la hora pasó sin confirmación ni
            // cancelación: queda como "No asistió".
            if ($cita['estado'] === 'Pendiente') {
                return $this->marcarInasistencia($idCita) ? 'inasistencia' : 'pasada';
            }
            return 'pasada';
        }

        // 2) Se cancela con el procedimiento de la BD sp_cancelar_cita():
        //    él define app.user_id (triggers de historial y auditoría),
        //    vuelve a validar el estado con bloqueo de fila y exige el motivo.
        $usuario = (int)($_SESSION['user_id'] ?? 0);
        if ($usuario <= 0) {
            return 'error';
        }

        try {
            $stmt = $this->db->prepare("CALL sp_cancelar_cita(:cita, :usuario, :motivo)");
            $stmt->execute([
                ':cita'    => (int)$idCita,
                ':usuario' => $usuario,
                ':motivo'  => $motivo,
            ]);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'no existe') !== false) {
                return 'no_existe';
            }
            if (strpos($msg, 'estado') !== false || strpos($msg, 'cancelada') !== false) {
                // Cambió de estado entre la inspección y aquí (ej.: ya la atendieron).
                return 'estado';
            }
            throw $e;
        }
        return 'ok';
    }

    /** '08:30:00' -> 510 */
    private static function aMinutos($hora) {
        return (int)substr($hora, 0, 2) * 60 + (int)substr($hora, 3, 2);
    }

    /** 510 -> '08:30' */
    private static function aHora($minutos) {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
