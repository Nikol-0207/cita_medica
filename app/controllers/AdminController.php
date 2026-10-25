<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../helpers/Permisos.php';

class AdminController {
    private $db;
    private $configModel;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->configModel = new Configuracion();
    }

    public function dashboard() {
        Permisos::exigir('dashboard');

        // Obtener colores dinámicos
        $colores = $this->configModel->getColoresPersonalizados();

        // Totales para los widgets (se cuentan personas reales, no cuentas de usuario:
        // había cuentas de prueba con el rol Recepcionista sin recepcionista asociada).
        $totales = [
            'recepcionistas' => (int)($this->db->query("SELECT COUNT(*) FROM recepcionista WHERE estado = 'Activo'")->fetchColumn() ?: 0),
            'pacientes'      => (int)($this->db->query("SELECT COUNT(*) FROM paciente WHERE estado = 'Activo'")->fetchColumn() ?: 0),
            'medicos'        => (int)($this->db->query("SELECT COUNT(*) FROM medico WHERE estado = 'Activo'")->fetchColumn() ?: 0),
            'especialidades' => (int)($this->db->query("SELECT COUNT(*) FROM especialidades WHERE estado = 'Activo'")->fetchColumn() ?: 0),
            'horarios'       => (int)($this->db->query("SELECT COUNT(*) FROM horario_atencion WHERE estado = 'Activo'")->fetchColumn() ?: 0),
            'citas'          => (int)($this->db->query("SELECT COUNT(*) FROM cita WHERE estado NOT IN ('Cancelada')")->fetchColumn() ?: 0),
        ];

        $medicos = $this->db->query("SELECT id_medico, nombres, apellidos FROM medico WHERE estado = 'Activo' ORDER BY apellidos")->fetchAll();

        // --- Calendario de atención: filtros por especialidad y por médico ---
        require_once __DIR__ . '/../models/Horario.php';

        $espFiltro = ctype_digit($_GET['esp'] ?? '') ? (int)$_GET['esp'] : 0;
        $medFiltro = ctype_digit($_GET['medico'] ?? '') ? (int)$_GET['medico'] : 0;

        $especialidades = (new Horario())->especialidadesConHorario();

        // Si el filtro no corresponde a ninguna especialidad con horario, se ignora.
        if ($espFiltro && !in_array($espFiltro, array_map('intval', array_column($especialidades, 'id_especialidad')), true)) {
            $espFiltro = 0;
        }
        if ($medFiltro && !in_array($medFiltro, array_map('intval', array_column($medicos, 'id_medico')), true)) {
            $medFiltro = 0;
        }

        $horariosCal = (new Horario())->listar([
            'estado'       => 'Activo',
            'vigente'      => true,      // los vencidos ya no se atienden
            'medicoActivo' => true,      // un médico desactivado no se atiende
            'especialidad' => $espFiltro,
            'medico'       => $medFiltro,
        ]);
        $calendario = Horario::armarCalendario($horariosCal);

        $filtrosCal = ['esp' => $espFiltro, 'medico' => $medFiltro];
        $hayFiltrosCal = ($espFiltro > 0 || $medFiltro > 0);

        require_once __DIR__ . '/../views/admin/dashboard.php';
    }

    public function configuraciones() {
        Permisos::exigir('configuraciones');

        $mensaje = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Si el usuario aplicó una paleta predefinida
            if (isset($_POST['preset'])) {
                $presets = [
                    'saludcore' => [
                        'primary' => '#0D9488', 'sidebar_bg' => '#0F172A', 'tertiary' => '#6366F1', 'neutral' => '#64748B', 'bg_main' => '#F8FAFC',
                        'card_stat_1' => '#0D9488', 'card_stat_2' => '#10B981', 'card_stat_3' => '#6366F1', 'card_stat_4' => '#F59E0B', 'card_stat_5' => '#64748B', 'card_stat_6' => '#0284C7'
                    ],
                    'azul' => [
                        'primary' => '#1565C0', 'sidebar_bg' => '#1E293B', 'tertiary' => '#3B82F6', 'neutral' => '#6B7280', 'bg_main' => '#F1F5F9',
                        'card_stat_1' => '#1565C0', 'card_stat_2' => '#16A34A', 'card_stat_3' => '#EA580C', 'card_stat_4' => '#CA8A04', 'card_stat_5' => '#475569', 'card_stat_6' => '#0891B2'
                    ],
                    'dark' => [
                        'primary' => '#14B8A6', 'sidebar_bg' => '#020617', 'tertiary' => '#818CF8', 'neutral' => '#94A3B8', 'bg_main' => '#0B0F17',
                        'card_stat_1' => '#0F766E', 'card_stat_2' => '#047857', 'card_stat_3' => '#4338CA', 'card_stat_4' => '#B45309', 'card_stat_5' => '#334155', 'card_stat_6' => '#0369A1'
                    ]
                ];
                $tipo = $_POST['preset'];
                if (isset($presets[$tipo])) {
                    $this->configModel->setValor('colores_personalizados', json_encode($presets[$tipo]), 'Colores del sistema');
                    $mensaje = "Paleta predefinida aplicada correctamente.";
                }
            } 
            // Si el usuario escogió colores manualmente
            elseif (isset($_POST['primary'])) {
                $nuevosColores = [
                    'primary'      => $_POST['primary'],
                    'sidebar_bg'   => $_POST['sidebar_bg'],
                    'tertiary'     => $_POST['tertiary'],
                    'neutral'      => $_POST['neutral'],
                    'bg_main'      => $_POST['bg_main'] ?? '#F8FAFC',
                    'card_stat_1'  => $_POST['card_stat_1'] ?? $_POST['primary'],
                    'card_stat_2'  => $_POST['card_stat_2'] ?? '#10B981',
                    'card_stat_3'  => $_POST['card_stat_3'] ?? $_POST['tertiary'],
                    'card_stat_4'  => $_POST['card_stat_4'] ?? '#F59E0B',
                    'card_stat_5'  => $_POST['card_stat_5'] ?? $_POST['neutral'],
                    'card_stat_6'  => $_POST['card_stat_6'] ?? '#0284C7'
                ];
                $this->configModel->setValor('colores_personalizados', json_encode($nuevosColores), 'Colores personalizados');
                $mensaje = "Paleta personalizada guardada exitosamente.";
            }
        }

        $colores = $this->configModel->getColoresPersonalizados();
        require_once __DIR__ . '/../views/admin/configuraciones.php';
    }
}