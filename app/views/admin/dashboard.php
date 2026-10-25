<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
<style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --tertiary: <?= htmlspecialchars($colores['tertiary'] ?? '#6366F1') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
            --card-stat-1: <?= htmlspecialchars($colores['card_stat_1'] ?? '#0D9488') ?>;
            --card-stat-2: <?= htmlspecialchars($colores['card_stat_2'] ?? '#10B981') ?>;
            --card-stat-3: <?= htmlspecialchars($colores['card_stat_3'] ?? '#6366F1') ?>;
            --card-stat-4: <?= htmlspecialchars($colores['card_stat_4'] ?? '#F59E0B') ?>;
            --card-stat-5: <?= htmlspecialchars($colores['card_stat_5'] ?? '#64748B') ?>;
            --card-stat-6: <?= htmlspecialchars($colores['card_stat_6'] ?? '#0284C7') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
        .card-stat.bg-blue   { background-color: var(--card-stat-1) !important; }
        .card-stat.bg-green  { background-color: var(--card-stat-2) !important; }
        .card-stat.bg-red    { background-color: var(--card-stat-3) !important; }
        .card-stat.bg-yellow { background-color: var(--card-stat-4) !important; }
        .card-stat.bg-gray   { background-color: var(--card-stat-5) !important; }
        .card-stat.bg-teal   { background-color: var(--card-stat-6) !important; }

        /* ---- Calendario semanal de atención ---- */
        .cal-resumen { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .cal-dato {
            background: #fff; border: 1px solid #E2E8F0; border-radius: 6px;
            padding: 6px 12px; font-size: 13px; color: #475569;
        }
        .cal-dato strong { color: var(--primary); font-size: 15px; }
        .cal-leyenda { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-bottom: 14px; font-size: 13px; color: #334155; }
        .cal-leyenda-item { display: inline-flex; align-items: center; gap: 6px; }
        .cal-punto { width: 11px; height: 11px; border-radius: 50%; display: inline-block; }
        .cal-leyenda-esp { color: #64748B; font-size: 12px; }

        .cal-wrap { border: 1px solid #E2E8F0; border-radius: 8px; overflow: auto; max-height: 520px; }
        .cal-grid { min-width: 980px; }
        .cal-grid thead th {
            position: sticky; top: 0; z-index: 2;
            background: #F1F5F9; border-bottom: 2px solid #E2E8F0;
            text-align: center; font-size: 12px; color: #334155; padding: 9px 8px;
        }
        .cal-grid tbody td { border: 1px solid #EEF2F7; padding: 4px; vertical-align: top; }
        .cal-hora {
            width: 108px; min-width: 108px; text-align: center;
            font-size: 12px; font-weight: 700; color: #475569; background: #F8FAFC;
        }
        .cal-celda { min-width: 118px; }
        .cal-vacia { background: repeating-linear-gradient(135deg, #FBFCFD, #FBFCFD 6px, #F4F6F8 6px, #F4F6F8 12px); }
        .cal-bloque {
            border-left: 3px solid; border-radius: 4px;
            padding: 5px 7px; margin-bottom: 4px; font-size: 12px; line-height: 1.35;
        }
        .cal-bloque:last-child { margin-bottom: 0; }
        .cal-bloque-nombre { font-weight: 700; color: #0F172A; font-size: 12px; }
        .cal-bloque-esp { color: #475569; font-size: 11px; }
        .cal-bloque-hora { color: #64748B; font-size: 11px; }
    </style>

</head>
<body class="theme-<?= htmlspecialchars($temaActivo ?? 'saludcore') ?>">

<div class="admin-wrapper">
    <!-- BARRA LATERAL (SIDEBAR) -->
    <?php $activo = 'dashboard'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content">
        <!-- TOPBAR -->
   
            <header class="top-nav">
               <div class="top-title">Sistema de Gestión de Citas Médicas</div>
               <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
            </header>
        

        <div class="content-body">
            <!-- TARJETAS MÉTRICAS (WIDGETS) -->
            <div class="cards-dashboard-grid">
                <?php if (Permisos::tiene('dashboard.personas')): ?>
                <!-- Recepcionistas -->
                <div class="card-stat bg-blue">
                    <div class="inner">
                        <h3><?= $totales['recepcionistas'] ?></h3>
                        <p>Recepcionistas</p>
                    </div>
                    <div class="stat-icon">👤</div>
                    <a href="index.php?route=recepcionistas" class="stat-footer">
                        Más información ➔
                    </a>
                </div>

                <!-- Pacientes -->
                <div class="card-stat bg-green">
                    <div class="inner">
                        <h3><?= $totales['pacientes'] ?></h3>
                        <p>Pacientes</p>
                    </div>
                    <div class="stat-icon">👥</div>
                    <a href="index.php?route=pacientes" class="stat-footer">
                        Más información ➔
                    </a>
                </div>

                <!-- Médicos -->
                <div class="card-stat bg-red">
                    <div class="inner">
                        <h3><?= $totales['medicos'] ?></h3>
                        <p>Médicos</p>
                    </div>
                    <div class="stat-icon">👨‍⚕️</div>
                    <a href="index.php?route=medicos" class="stat-footer">
                        Más información ➔
                    </a>

                </div>
                   <?php endif; ?>
                <!-- Especialidades -->
                <div class="card-stat bg-yellow">
                    <div class="inner">
                        <h3><?= $totales['especialidades'] ?></h3>
                        <p>Especialidades</p>
                    </div>
                    <div class="stat-icon">🩺</div>
                    <a href="index.php?route=especialidades" class="stat-footer">
                        Más información ➔
                    </a>
                </div>

                <!-- Horarios -->
                <div class="card-stat bg-gray">
                    <div class="inner">
                        <h3><?= $totales['horarios'] ?></h3>
                        <p>Horarios Activos</p>
                    </div>
                    <div class="stat-icon">📅</div>
                    <a href="index.php?route=horarios" class="stat-footer">
                        Más información ➔
                    </a>
                </div>

                <!-- Citas / Reservas -->
                <div class="card-stat bg-teal">
                    <div class="inner">
                        <h3><?= $totales['citas'] ?></h3>
                        <p>Citas Activas</p>
                    </div>
                    <div class="stat-icon">📋</div>
                    <a href="index.php?route=citas" class="stat-footer">
                        Más información ➔
                    </a>
                </div>
            </div>

            <!-- SECCIÓN: CALENDARIO SEMANAL DE ATENCIÓN -->
            <?php $rutaActual = $_GET['route'] ?? 'admin'; ?>
            <?php $espPorId = []; foreach ($especialidades as $e) { $espPorId[(int)$e['id_especialidad']] = $e['nombre']; } ?>
            <div class="panel-box">
                <div class="panel-header">
                    <h4>Calendario de Atención de Médicos</h4>
                    <div class="panel-filter">
                        <form method="GET" action="index.php" class="panel-filter">
                            <input type="hidden" name="route" value="<?= htmlspecialchars($rutaActual) ?>">

                            <label for="cal-esp">Especialidad:</label>
                            <select id="cal-esp" name="esp" class="form-select" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                <?php foreach ($especialidades as $e): ?>
                                    <option value="<?= (int)$e['id_especialidad'] ?>"
                                        <?= $filtrosCal['esp'] === (int)$e['id_especialidad'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(mb_strtoupper($e['nombre'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="cal-medico">Médico:</label>
                            <select id="cal-medico" name="medico" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach ($medicos as $m): ?>
                                    <option value="<?= (int)$m['id_medico'] ?>"
                                        <?= $filtrosCal['medico'] === (int)$m['id_medico'] ? 'selected' : '' ?>>
                                        Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($hayFiltrosCal): ?>
                                <a href="index.php?route=<?= htmlspecialchars($rutaActual) ?>" class="link-volver">Quitar filtros</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="panel-body">
                    <?php if (empty($calendario['filas'])): ?>
                        <p class="placeholder-text">
                            <?= $hayFiltrosCal
                                ? 'No hay horarios de atención activos que coincidan con esos filtros.'
                                : 'Aún no hay horarios de atención registrados. Créalos en el módulo de Horarios.' ?>
                        </p>
                    <?php else: ?>

                        <div class="cal-resumen">
                            <span class="cal-dato"><strong><?= count($horariosCal) ?></strong> horarios</span>
                            <span class="cal-dato"><strong><?= count($calendario['doctores']) ?></strong> doctores</span>
                            <?php if ($filtrosCal['esp'] > 0): ?>
                                <span class="cal-dato">
                                    Especialidad:
                                    <strong><?= htmlspecialchars(mb_strtoupper($espPorId[$filtrosCal['esp']] ?? '')) ?></strong>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Leyenda: qué doctores atienden y en qué especialidad -->
                        <div class="cal-leyenda">
                            <?php foreach ($calendario['doctores'] as $doc): ?>
                                <span class="cal-leyenda-item">
                                    <span class="cal-punto" style="background: <?= $doc['color'] ?>"></span>
                                    <?= htmlspecialchars($doc['nombre']) ?>
                                    <span class="cal-leyenda-esp">(<?= htmlspecialchars(implode(' · ', $doc['especialidades'])) ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="tabla-wrap cal-wrap">
                            <table class="tabla cal-grid">
                                <thead>
                                    <tr>
                                        <th class="cal-hora">Hora</th>
                                        <?php foreach ($calendario['dias'] as $dia): ?>
                                            <th><?= htmlspecialchars(mb_strtoupper($dia)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($calendario['filas'] as $i => $fila): ?>
                                        <tr>
                                            <td class="cal-hora"><?= htmlspecialchars($fila['etiqueta']) ?></td>
                                            <?php foreach ($calendario['dias'] as $d => $dia): ?>
                                                <td class="cal-celda<?= empty($calendario['celdas'][$d][$i]) ? ' cal-vacia' : '' ?>">
                                                    <?php foreach ($calendario['celdas'][$d][$i] ?? [] as $b): ?>
                                                        <div class="cal-bloque"
                                                             style="border-left-color: <?= $b['color'] ?>; background: <?= $b['color'] ?>1A;">
                                                            <div class="cal-bloque-nombre"><?= htmlspecialchars($b['nombre']) ?></div>
                                                            <div class="cal-bloque-esp"><?= htmlspecialchars(mb_strtoupper($b['especialidad'])) ?></div>
                                                            <div class="cal-bloque-hora">
                                                                <?= $b['inicio'] ?> - <?= $b['fin'] ?>
                                                                · <?= $b['duracion'] ?> min
                                                                · <?= $b['fichas'] ?> fichas
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>