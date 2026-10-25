<?php
require_once __DIR__ . '/../../config/Database.php';

class Horario {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function medicosActivos() {
        return $this->db->query(
            "SELECT id_medico, nombres, apellidos
             FROM medico
             WHERE estado = 'Activo'
             ORDER BY apellidos, nombres"
        )->fetchAll();
    }

    /** Días válidos (coinciden con el CHECK de la tabla horario_atencion). */
    public static function dias() {
        return ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    }

    /**
     * Opciones del formulario: cada médico con cada una de sus especialidades
     * activas, tal como se muestra en el ejemplo ("Doctor1 Swift - PEDIATRIA").
     */
    public function opcionesMedicoEspecialidad() {
        return $this->db->query(
            "SELECT me.id_medico, me.id_especialidad,
                    m.nombres, m.apellidos, e.nombre AS especialidad
             FROM medico_especialidad me
             JOIN medico m         ON m.id_medico       = me.id_medico
             JOIN especialidades e ON e.id_especialidad = me.id_especialidad
             WHERE me.estado = 'Activo' AND m.estado = 'Activo' AND e.estado = 'Activo'
             ORDER BY m.apellidos, m.nombres, e.nombre"
        )->fetchAll();
    }

    /** ¿Ya tiene ese médico un horario activo ese día que se solape con el rango? */
    public function haySolape($idMedico, $dia, $ini, $fin) {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM horario_atencion
              WHERE id_medico = :med AND dia_semana = :dia AND estado = 'Activo'
                AND hora_inicio < :fin AND hora_fin > :ini
              LIMIT 1"
        );
        $stmt->execute([
            ':med' => (int)$idMedico,
            ':dia' => $dia,
            ':fin' => $fin,
            ':ini' => $ini,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    /** Registra un horario; la cantidad de fichas la calcula un trigger de la BD. */
    public function crear(array $d) {
        $stmt = $this->db->prepare(
            "INSERT INTO horario_atencion
                    (id_especialidad, id_medico, dia_semana, hora_inicio, hora_fin,
                     duracion_cita, estado, fecha_fin)
             VALUES (:esp, :med, :dia, :ini, :fin, :dur, 'Activo', :fecha_fin)
             RETURNING id_horario"
        );
        $stmt->execute([
            ':esp' => (int)$d['id_especialidad'],
            ':med' => (int)$d['id_medico'],
            ':dia' => $d['dia_semana'],
            ':ini' => $d['hora_inicio'],
            ':fin' => $d['hora_fin'],
            ':dur' => (int)$d['duracion_cita'],
            ':fecha_fin' => $d['fecha_fin'],
        ]);
        return (int)$stmt->fetchColumn();
    }

    /** ¿Está vigente hoy el horario (no venció su fecha límite)? */
    public static function vigente($fechaFin) {
        return $fechaFin !== null && $fechaFin >= date('Y-m-d');
    }

    /** Un horario activo con su doctor y especialidad (para elegir al reservar cita). */
    public function porId($idHorario) {
        $stmt = $this->db->prepare(
            "SELECT h.id_horario, h.id_medico, h.id_especialidad, h.dia_semana,
                    h.hora_inicio, h.hora_fin, h.duracion_cita, h.cantidad_fichas,
                    h.fecha_fin,
                    m.nombres, m.apellidos, e.nombre AS especialidad
             FROM horario_atencion h
             JOIN medico m         ON m.id_medico       = h.id_medico
             JOIN especialidades e ON e.id_especialidad = h.id_especialidad
             WHERE h.id_horario = :id AND h.estado = 'Activo'
               AND m.estado = 'Activo' AND e.estado = 'Activo'"
        );
        $stmt->execute([':id' => (int)$idHorario]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** Horarios activos, listos para mostrar en el selector de nueva cita. */
    public function activosParaCita() {
        return $this->db->query(
            "SELECT h.id_horario, h.dia_semana, h.hora_inicio, h.hora_fin,
                    h.duracion_cita, h.cantidad_fichas, h.fecha_fin,
                    m.nombres, m.apellidos, e.nombre AS especialidad
             FROM horario_atencion h
             JOIN medico m         ON m.id_medico       = h.id_medico
             JOIN especialidades e ON e.id_especialidad = h.id_especialidad
             WHERE h.estado = 'Activo' AND m.estado = 'Activo' AND e.estado = 'Activo'
               AND h.fecha_fin >= CURRENT_DATE
             ORDER BY CASE h.dia_semana
                          WHEN 'Lunes' THEN 1 WHEN 'Martes' THEN 2 WHEN 'Miércoles' THEN 3
                          WHEN 'Jueves' THEN 4 WHEN 'Viernes' THEN 5 WHEN 'Sábado' THEN 6
                          ELSE 7 END,
                      h.hora_inicio, m.apellidos"
        )->fetchAll();
    }

    /**
     * Horarios de atención con filtros opcionales:
     *  - estado : 'Activo' | 'Inactivo' | '' (todos)
     *  - medico : id del médico
     * Ordena por día de la semana (de lunes a domingo) y luego por hora.
     */
    public function listar(array $f = []) {
        $sql = "SELECT h.id_horario, h.dia_semana, h.hora_inicio, h.hora_fin,
                       h.duracion_cita, h.cantidad_fichas, h.estado, h.fecha_fin,
                       m.id_medico, m.nombres, m.apellidos,
                       e.nombre AS especialidad,
                       -- Fichas (tramos de duracion_cita) que ya ocupó una cita de este horario
                       (SELECT COALESCE(SUM(GREATEST(1, CEIL(
                                    EXTRACT(EPOCH FROM (c.hora_fin - c.hora_inicio)) / 60.0
                                    / GREATEST(h.duracion_cita, 1)))), 0)
                          FROM cita c
                         WHERE c.id_horario = h.id_horario
                           AND c.estado <> 'Cancelada')::int AS fichas_reservadas
                FROM horario_atencion h
                JOIN medico m          ON m.id_medico       = h.id_medico
                JOIN especialidades e  ON e.id_especialidad = h.id_especialidad
                WHERE 1 = 1";

        $params = [];

        if (!empty($f['estado']) && in_array($f['estado'], ['Activo', 'Inactivo'], true)) {
            $sql .= " AND h.estado = :estado";
            $params[':estado'] = $f['estado'];
        }
        if (!empty($f['medico'])) {
            $sql .= " AND h.id_medico = :medico";
            $params[':medico'] = (int)$f['medico'];
        }
        if (!empty($f['especialidad'])) {
            $sql .= " AND h.id_especialidad = :especialidad";
            $params[':especialidad'] = (int)$f['especialidad'];
        }
        // Solo horarios vigentes (no venció su fecha límite).
        if (!empty($f['vigente'])) {
            $sql .= " AND h.fecha_fin >= CURRENT_DATE";
        }
        // Solo horarios de médicos activos (un médico desactivado se oculta
        // de la reserva y del calendario; sus citas existentes se conservan).
        if (!empty($f['medicoActivo'])) {
            $sql .= " AND m.estado = 'Activo'";
        }

        $sql .= " ORDER BY CASE h.dia_semana
                            WHEN 'Lunes' THEN 1 WHEN 'Martes' THEN 2 WHEN 'Miércoles' THEN 3
                            WHEN 'Jueves' THEN 4 WHEN 'Viernes' THEN 5 WHEN 'Sábado' THEN 6
                            WHEN 'Domingo' THEN 7 ELSE 8 END,
                          h.hora_inicio, m.apellidos";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // CALENDARIO SEMANAL (dashboard)
    // ----------------------------------------------------------------

    /** Especialidades activas que al menos tienen un horario de atención. */
    public function especialidadesConHorario() {
        return $this->db->query(
            "SELECT DISTINCT e.id_especialidad, e.nombre
             FROM especialidades e
             JOIN horario_atencion h ON h.id_especialidad = e.id_especialidad
             WHERE e.estado = 'Activo' AND h.estado = 'Activo'
               AND h.fecha_fin >= CURRENT_DATE
             ORDER BY e.nombre"
        )->fetchAll();
    }

    /**
     * Convierte los horarios en una grilla semanal:
     *   filas  = tramos de una hora (08:00 - 09:00, ...)
     *   dias   = Lunes ... Domingo
     *   celdas = [indiceDia][indiceFila] => items (doctor, especialidad, rango)
     * Si un horario abarca 08:00-10:00, ocupa las filas 08:00-09:00 y 09:00-10:00.
     */
    public static function armarCalendario(array $horarios) {
        $dias  = self::dias();
        $vacio = ['filas' => [], 'dias' => $dias, 'celdas' => [], 'doctores' => []];
        if (!$horarios) {
            return $vacio;
        }

        $min = 24 * 60;
        $max = 0;
        foreach ($horarios as $h) {
            $min = min($min, self::aMinutos($h['hora_inicio']));
            $max = max($max, self::aMinutos($h['hora_fin']));
        }

        $ini = intdiv($min, 60) * 60;                                  // hora exacta
        $fin = (intdiv($max, 60) + ($max % 60 > 0 ? 1 : 0)) * 60;      // redondeada hacia arriba

        $filas = [];
        for ($t = $ini; $t < $fin && $t < 24 * 60; $t += 60) {
            $filas[] = [
                'ini'      => $t,
                'fin'      => min($t + 60, 24 * 60),
                'etiqueta' => self::aHora($t) . ' - ' . self::aHora(min($t + 60, 24 * 60)),
            ];
        }

        $celdas   = [];
        $doctores = [];
        $paleta   = ['#0D9488', '#2563EB', '#DB2777', '#D97706', '#7C3AED', '#059669', '#DC2626', '#0891B2'];
        $orden    = 0;

        foreach ($horarios as $h) {
            $d = array_search($h['dia_semana'], $dias, true);
            if ($d === false) {
                continue;
            }

            $idMed = (int)$h['id_medico'];
            if (!isset($doctores[$idMed])) {
                $doctores[$idMed] = [
                    'nombre'        => 'Dr(a). ' . $h['nombres'] . ' ' . $h['apellidos'],
                    'color'         => $paleta[$orden % count($paleta)],
                    'especialidades' => [],
                ];
                $orden++;
            }
            $doctores[$idMed]['especialidades'][$h['especialidad']] = $h['especialidad'];

            $hi = self::aMinutos($h['hora_inicio']);
            $hf = self::aMinutos($h['hora_fin']);

            foreach ($filas as $i => $f) {
                if ($hi < $f['fin'] && $hf > $f['ini']) {   // solape de la fila con el horario
                    $celdas[$d][$i][] = [
                        'id_medico'     => $idMed,
                        'nombre'        => 'Dr(a). ' . $h['nombres'] . ' ' . $h['apellidos'],
                        'especialidad'  => $h['especialidad'],
                        'color'         => $doctores[$idMed]['color'],
                        'inicio'        => substr($h['hora_inicio'], 0, 5),
                        'fin'           => substr($h['hora_fin'], 0, 5),
                        'duracion'      => (int)$h['duracion_cita'],
                        'fichas'        => (int)$h['cantidad_fichas'],
                    ];
                }
            }
        }

        // Doctores ordenados alfabéticamente para la leyenda
        uasort($doctores, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

        return ['filas' => $filas, 'dias' => $dias, 'celdas' => $celdas, 'doctores' => $doctores];
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
