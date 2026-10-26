<?php
require_once __DIR__ . '/../../config/Database.php';

class Cita {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /** Estados válidos (coinciden con el CHECK de la tabla cita). */
    public static function estados() {
        return ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'];
    }

    public function medicosActivos() {
        return $this->db->query(
            "SELECT id_medico, nombres, apellidos
             FROM medico
             WHERE estado = 'Activo'
             ORDER BY apellidos, nombres"
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
                       p.codigo AS paciente_codigo, p.ci AS paciente_ci,
                       p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                       m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                       e.nombre AS especialidad
                FROM cita c
                JOIN paciente p                ON p.id_paciente     = c.id_paciente
                JOIN medico m                  ON m.id_medico       = c.id_medico
                JOIN horario_atencion h        ON h.id_horario      = c.id_horario
                LEFT JOIN especialidades e     ON e.id_especialidad = h.id_especialidad
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
                        OR p.codigo      ILIKE :b_paciente
                        OR p.ci          ILIKE :b_ci
                        OR p.nombres     ILIKE :b_nombres
                        OR p.apellidos   ILIKE :b_apellidos)";
            $params[':b_codigo']    = $like;
            $params[':b_paciente']  = $like;
            $params[':b_ci']        = $like;
            $params[':b_nombres']   = $like;
            $params[':b_apellidos'] = $like;
        }

        $sql .= " ORDER BY c.fecha ASC, c.hora_inicio ASC, m.apellidos";

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

    /** Programa la cita en estado "Programada" y devuelve su id. */
    public function crear(array $d) {
        $id = (int)$this->db->query(
            "SELECT nextval(pg_get_serial_sequence('cita', 'id_cita'))"
        )->fetchColumn();

        $this->db->prepare(
            "INSERT INTO cita (id_horario, id_paciente, id_medico, codigo,
                               fecha, hora_inicio, hora_fin, motivo, estado)
             VALUES (:horario, :paciente, :medico, :codigo, :fecha, :ini, :fin, :motivo, 'Programada')"
        )->execute([
            ':horario'  => (int)$d['id_horario'],
            ':paciente' => (int)$d['id_paciente'],
            ':medico'   => (int)$d['id_medico'],
            ':codigo'   => 'CIT-' . str_pad($id, 5, '0', STR_PAD_LEFT),
            ':fecha'    => $d['fecha'],
            ':ini'      => $d['hora_inicio'],
            ':fin'      => $d['hora_fin'],
            ':motivo'   => $d['motivo'],
        ]);

        return $id;
    }

    /** Pasa una cita de "Programada" a "Confirmada" (dispara el historial en la BD). */
    public function confirmar($idCita) {
        $stmt = $this->db->prepare(
            "UPDATE cita
                SET estado = 'Confirmada', fecha_actualizacion = NOW()
              WHERE id_cita = :id AND estado = 'Programada'
         RETURNING id_cita"
        );
        $stmt->execute([':id' => (int)$idCita]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Cancela una cita mientras su fecha + hora de inicio NO haya pasado
     * (programada a las 16:00, cancelada a las 15:00 → se permite).
     * Guarda el motivo indicado por quien cancela.
     *
     * Si $idPaciente > 0 solo cancela la cita de ESE paciente (rol Paciente).
     * Devuelve:
     *   'ok'        → cancelada (la ficha queda libre para volver a reservar)
     *   'no_existe' → no existe o no es del paciente
     *   'estado'    → no está en estado Programada/Confirmada
     *   'pasada'    → ya empezó (o terminó) la atención
     */
    public function cancelar($idCita, $idPaciente = 0, $motivo = '') {
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
        if (!in_array($cita['estado'], ['Programada', 'Confirmada'], true)) {
            return 'estado';
        }
        if (strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time()) {
            return 'pasada';
        }

        // 2) Se cancela repitiendo las mismas condiciones: si entre el SELECT y
        //    aquí cambió algo, la BD no cancela nada.
        $sql  = "UPDATE cita
                    SET estado = 'Cancelada',
                        motivo_cancelacion = :motivo,
                        fecha_actualizacion = NOW()
                  WHERE id_cita = :id2
                    AND estado IN ('Programada', 'Confirmada')
                    AND (fecha + hora_inicio) > NOW()";
        $params = [':id2' => (int)$idCita, ':motivo' => $motivo];
        if ($idPaciente > 0) {
            $sql .= " AND id_paciente = :pac2";
            $params[':pac2'] = (int)$idPaciente;
        }
        $sql .= " RETURNING id_cita";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ? 'ok' : 'pasada';
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
