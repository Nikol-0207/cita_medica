<?php
require_once __DIR__ . '/../../config/Database.php';

class Horario {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
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

    /** Días válidos (nombres; la BD guarda el número ISO 1..7). */
    public static function dias() {
        return ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    }

    /**
     * Nombre del día -> número ISO (1 = lunes ... 7 = domingo).
     * Acepta también el número ya convertido (string o int).
     * Devuelve 0 si no reconoce el valor.
     */
    public static function diaANumero($dia) {
        if (is_int($dia) || (is_string($dia) && ctype_digit($dia))) {
            $n = (int)$dia;
            return ($n >= 1 && $n <= 7) ? $n : 0;
        }
        $n = array_search(trim((string)$dia), self::dias(), true);
        return $n === false ? 0 : $n + 1;
    }

    /** Número ISO -> nombre del día (para mostrar en vistas). */
    public static function diaANombre($numero) {
        $n = (int)$numero;
        return ($n >= 1 && $n <= 7) ? self::dias()[$n - 1] : '';
    }

    /**
     * Opciones del formulario: cada médico con cada una de sus especialidades
     * activas, tal como se muestra en el ejemplo ("Doctor1 Swift - PEDIATRIA").
     */
    public function opcionesMedicoEspecialidad() {
        return $this->db->query(
            "SELECT me.id_medico, me.id_especialidad,
                    p.nombres, p.apellidos, e.nombre AS especialidad
             FROM medico_especialidad me
             JOIN medico m         ON m.id_medico       = me.id_medico
             JOIN persona p        ON p.id_persona      = m.id_persona
             JOIN especialidades e ON e.id_especialidad = me.id_especialidad
             WHERE me.estado = 'Activo' AND p.estado = 'Activo' AND e.estado = 'Activo'
             ORDER BY p.apellidos, p.nombres, e.nombre"
        )->fetchAll();
    }

    /** ¿Ya tiene ese médico un horario activo ese día que se solape con el rango? */
    public function haySolape($idMedico, $dia, $ini, $fin) {
        $diaNum = self::diaANumero($dia);
        if ($diaNum <= 0) {
            return false;
        }
        $stmt = $this->db->prepare(
            "SELECT 1 FROM horario_atencion h
              JOIN medico_especialidad me ON me.id_medico_especialidad = h.id_medico_especialidad
              WHERE me.id_medico = :med AND h.dia_semana = :dia AND h.estado = 'Activo'
                AND h.hora_inicio < :fin AND h.hora_fin > :ini
              LIMIT 1"
        );
        $stmt->execute([
            ':med' => (int)$idMedico,
            ':dia' => $diaNum,
            ':fin' => $fin,
            ':ini' => $ini,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Registra un horario.
     * - dia_semana se guarda como número ISO (1..7): el nombre viene del formulario.
     * - cantidad_fichas se calcula aquí con el mismo criterio que el trigger
     *   trg_calcular_fichas_horario (FLOOR): fichas COMPLETAS de duracion_cita
     *   minutos que caben en el rango. Si el valor superara lo que cabe, la BD
     *   rechaza el INSERT.
     * - fecha_fin vacía = sin vencimiento (NULL en la BD).
     */
    public function crear(array $d) {
        $diaNum = self::diaANumero($d['dia_semana'] ?? 0);
        if ($diaNum <= 0) {
            throw new InvalidArgumentException('Día de la semana no válido.');
        }

        // id_medico_especialidad: se recibe directo o se resuelve con medico + especialidad.
        $idMe = (int)($d['id_medico_especialidad'] ?? 0);
        if ($idMe <= 0) {
            $stmt = $this->db->prepare(
                "SELECT id_medico_especialidad FROM medico_especialidad
                  WHERE id_medico = :med AND id_especialidad = :esp AND estado = 'Activo'
                  LIMIT 1"
            );
            $stmt->execute([
                ':med' => (int)($d['id_medico'] ?? 0),
                ':esp' => (int)($d['id_especialidad'] ?? 0),
            ]);
            $idMe = (int)$stmt->fetchColumn();
            if ($idMe <= 0) {
                throw new InvalidArgumentException('El médico no tiene esa especialidad asignada.');
            }
        }

        $ini  = substr((string)$d['hora_inicio'], 0, 5);
        $fin  = substr((string)$d['hora_fin'], 0, 5);
        $dur  = max(1, (int)$d['duracion_cita']);

        $minIni = (int)substr($ini, 0, 2) * 60 + (int)substr($ini, 3, 2);
        $minFin = (int)substr($fin, 0, 2) * 60 + (int)substr($fin, 3, 2);
        if ($minFin <= $minIni) {
            throw new InvalidArgumentException('La hora final debe ser posterior a la hora inicial.');
        }
        // Mismo cálculo que hace trg_calcular_fichas_horario: si no cabe ni
        // una ficha completa, la BD lo rechaza ("duración excede el horario").
        $fichas = intdiv($minFin - $minIni, $dur);
        if ($fichas < 1) {
            throw new InvalidArgumentException('La duración de la cita excede el horario de atención.');
        }

        $fechaFin = trim((string)($d['fecha_fin'] ?? ''));
        if ($fechaFin === '' || $fechaFin === '0000-00-00') {
            $fechaFin = null;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO horario_atencion
                    (id_medico_especialidad, dia_semana, hora_inicio, hora_fin,
                     duracion_cita, cantidad_fichas, estado, fecha_inicio, fecha_fin)
             VALUES (:me, :dia, :ini, :fin, :dur, :fichas, 'Activo', CURRENT_DATE, :fecha_fin)
             RETURNING id_horario"
        );
        $stmt->execute([
            ':me'        => $idMe,
            ':dia'       => $diaNum,
            ':ini'       => $ini . ':00',
            ':fin'       => $fin . ':00',
            ':dur'       => $dur,
            ':fichas'    => $fichas,
            ':fecha_fin' => $fechaFin,
        ]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * ¿Está vigente hoy el horario?
     * fecha_fin NULL = sin vencimiento → siempre vigente.
     */
    public static function vigente($fechaFin) {
        return $fechaFin === null || $fechaFin >= date('Y-m-d');
    }

    /**
     * Un horario activo con su doctor y especialidad (para elegir al reservar cita).
     * - dia_semana viene como nombre (alias CASE) para que las vistas no cambien.
     * - cantidad_fichas se recalcula con FLOOR (el mismo criterio que
     *   trg_calcular_fichas_horario) por si las filas antiguas quedaron mal.
     */
    public function porId($idHorario) {
        $stmt = $this->db->prepare(
            "SELECT h.id_horario, me.id_medico, h.id_medico_especialidad,
                    me.id_especialidad,
                    CASE h.dia_semana
                        WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                        WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado'
                        ELSE 'Domingo' END AS dia_semana,
                    h.hora_inicio, h.hora_fin, h.duracion_cita,
                    FLOOR(EXTRACT(EPOCH FROM (h.hora_fin - h.hora_inicio)) / 60.0
                         / GREATEST(h.duracion_cita, 1))::int AS cantidad_fichas,
                    h.fecha_inicio, h.fecha_fin,
                    p.nombres, p.apellidos, e.nombre AS especialidad
             FROM horario_atencion h
             JOIN medico_especialidad me ON me.id_medico_especialidad = h.id_medico_especialidad
             JOIN medico m               ON m.id_medico       = me.id_medico
             JOIN persona p              ON p.id_persona      = m.id_persona
             JOIN especialidades e       ON e.id_especialidad = me.id_especialidad
             WHERE h.id_horario = :id AND h.estado = 'Activo'
               AND p.estado = 'Activo' AND e.estado = 'Activo'"
        );
        $stmt->execute([':id' => (int)$idHorario]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /** Horarios activos, listos para mostrar en el selector de nueva cita. */
    public function activosParaCita() {
        return $this->db->query(
            "SELECT h.id_horario, me.id_medico, me.id_especialidad,
                    CASE h.dia_semana
                        WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                        WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado'
                        ELSE 'Domingo' END AS dia_semana,
                    h.hora_inicio, h.hora_fin,
                    h.duracion_cita,
                    FLOOR(EXTRACT(EPOCH FROM (h.hora_fin - h.hora_inicio)) / 60.0
                         / GREATEST(h.duracion_cita, 1))::int AS cantidad_fichas,
                    h.fecha_fin,
                    p.nombres, p.apellidos, e.nombre AS especialidad
             FROM horario_atencion h
             JOIN medico_especialidad me ON me.id_medico_especialidad = h.id_medico_especialidad
             JOIN medico m               ON m.id_medico       = me.id_medico
             JOIN persona p              ON p.id_persona      = m.id_persona
             JOIN especialidades e       ON e.id_especialidad = me.id_especialidad
             WHERE h.estado = 'Activo' AND p.estado = 'Activo' AND e.estado = 'Activo'
               AND (h.fecha_fin IS NULL OR h.fecha_fin >= CURRENT_DATE)
             ORDER BY h.dia_semana, h.hora_inicio, p.apellidos"
        )->fetchAll();
    }

    /**
     * Horarios de atención con filtros opcionales:
     *  - estado : 'Activo' | 'Inactivo' | '' (todos)
     *  - medico : id del médico
     * Ordena por día de la semana (de lunes a domingo) y luego por hora.
     */
    public function listar(array $f = []) {
        $sql = "SELECT h.id_horario, me.id_medico,
                       CASE h.dia_semana
                           WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                           WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado'
                           ELSE 'Domingo' END AS dia_semana,
                       h.hora_inicio, h.hora_fin,
                       h.duracion_cita,
                       FLOOR(EXTRACT(EPOCH FROM (h.hora_fin - h.hora_inicio)) / 60.0
                            / GREATEST(h.duracion_cita, 1))::int AS cantidad_fichas,
                       h.estado, h.fecha_inicio, h.fecha_fin,
                       p.nombres, p.apellidos,
                       e.nombre AS especialidad,
                       -- Fichas (tramos de duracion_cita) que ya ocupó una cita de este horario
                       (SELECT COALESCE(SUM(GREATEST(1, FLOOR(
                                    EXTRACT(EPOCH FROM (c.hora_fin - c.hora_inicio)) / 60.0
                                    / GREATEST(h.duracion_cita, 1)))), 0)
                          FROM cita c
                         WHERE c.id_horario = h.id_horario
                           AND c.estado <> 'Cancelada')::int AS fichas_reservadas
                FROM horario_atencion h
                JOIN medico_especialidad me ON me.id_medico_especialidad = h.id_medico_especialidad
                JOIN medico m               ON m.id_medico       = me.id_medico
                JOIN persona p              ON p.id_persona      = m.id_persona
                JOIN especialidades e       ON e.id_especialidad = me.id_especialidad
                WHERE 1 = 1";

        $params = [];

        if (!empty($f['estado']) && in_array($f['estado'], ['Activo', 'Inactivo'], true)) {
            $sql .= " AND h.estado = :estado";
            $params[':estado'] = $f['estado'];
        }
        if (!empty($f['medico'])) {
            $sql .= " AND me.id_medico = :medico";
            $params[':medico'] = (int)$f['medico'];
        }
        if (!empty($f['especialidad'])) {
            $sql .= " AND me.id_especialidad = :especialidad";
            $params[':especialidad'] = (int)$f['especialidad'];
        }
        // Solo horarios vigentes (sin fecha límite o no vencidos).
        if (!empty($f['vigente'])) {
            $sql .= " AND (h.fecha_fin IS NULL OR h.fecha_fin >= CURRENT_DATE)";
        }
        // Solo horarios de médicos activos (un médico desactivado se oculta
        // de la reserva y del calendario; sus citas existentes se conservan).
        if (!empty($f['medicoActivo'])) {
            $sql .= " AND p.estado = 'Activo'";
        }

        $sql .= " ORDER BY h.dia_semana, h.hora_inicio, p.apellidos";

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
             JOIN medico_especialidad me ON me.id_especialidad = e.id_especialidad
             JOIN horario_atencion h     ON h.id_medico_especialidad = me.id_medico_especialidad
             WHERE e.estado = 'Activo' AND h.estado = 'Activo'
               AND (h.fecha_fin IS NULL OR h.fecha_fin >= CURRENT_DATE)
             ORDER BY e.nombre"
        )->fetchAll();
    }

    /**
     * Convierte los horarios en una grilla semanal:
     *   filas  = tramos de una hora (08:00 - 09:00, ...)
     *   dias   = Lunes ... Domingo
     *   celdas = [indiceDia][indiceFila] => items (doctor, especialidad, rango)
     * Si un horario abarca 08:00-10:00, ocupa las filas 08:00-09:00 y 09:00-10:00.
     * Los horarios llegan con dia_semana ya convertido a nombre (alias SQL).
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
