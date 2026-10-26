<?php
// Espera: $citas, $medicos, $estados, $filtros, $hayFiltros, $colores, $mensajeExito, $mensajeError
$esMedico      = $esMedico ?? false;     // el médico solo ve su propia agenda
$esPaciente    = $esPaciente ?? false;   // el paciente solo ve las suyas
$esStaff       = !$esMedico && !$esPaciente; // admin o recepcionista
$puedeCrear      = $puedeCrear ?? false;
$puedeConfirmar  = $puedeConfirmar ?? false;
$puedeCancelar   = $puedeCancelar ?? false;
$titulo      = $esMedico ? 'Mi agenda' : ($esPaciente ? 'Mis citas' : 'Citas médicas');
$claseEstado = fn($e) => 'badge-estado badge-' . str_replace(' ', '-', mb_strtolower($e));
$f           = fn($k) => htmlspecialchars($filtros[$k] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?> - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
        .badge-programada   { background: #DBEAFE; color: #1E3A8A; }
        .badge-confirmada   { background: #CCFBF1; color: #115E59; }
        .badge-atendida     { background: #DCFCE7; color: #166534; }
        .badge-cancelada    { background: #FFE4E6; color: #9F1239; }
        .badge-inasistencia { background: #FEF3C7; color: #92400E; }
        .filtro-busqueda { display: flex; gap: 6px; align-items: center; }
        .filtro-busqueda input[type="text"] {
            padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; min-width: 190px;
        }
        .form-cancelar { display: flex; gap: 5px; align-items: center; flex-wrap: wrap; }
        .form-cancelar input[type="text"] {
            padding: 5px 8px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 12px; width: 190px;
        }
        .motivo-cancelacion { color: #9F1239; font-size: 12px; display: block; margin-top: 3px; }
        .btn-inasistencia { background: #B45309; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'citas'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title"><?= $esMedico ? 'Mi agenda de citas' : $titulo ?></h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <div class="panel-box panel-azul">
                <div class="panel-header">
                    <h4><?= $esMedico ? 'Citas asignadas a mí' : ($esPaciente ? 'Mis citas' : 'Citas registradas') ?> (<?= (int)$pag['total'] ?>)</h4>

                    <form method="GET" action="index.php" class="panel-filter">
                        <input type="hidden" name="route" value="citas">

                        <label for="filtro-estado">Estado:</label>
                        <select id="filtro-estado" name="estado" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($estados as $est): ?>
                                <option value="<?= htmlspecialchars($est) ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($est) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (!$esMedico): ?>
                        <label for="filtro-medico">Médico:</label>
                        <select id="filtro-medico" name="medico" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?= (int)$m['id_medico'] ?>" <?= $filtros['medico'] === (int)$m['id_medico'] ? 'selected' : '' ?>>
                                    Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                        <label for="filtro-fecha">Fecha:</label>
                        <input type="date" id="filtro-fecha" name="fecha" class="form-select"
                               value="<?= $f('fecha') ?>" onchange="this.form.submit()">

                        <span class="filtro-busqueda">
                            <input type="text" name="busqueda" value="<?= $f('busqueda') ?>"
                                   placeholder="Código, CI o paciente">
                            <button type="submit" class="btn btn-main">Buscar</button>
                        </span>

                        <?php if ($hayFiltros): ?>
                            <a href="index.php?route=citas" class="link-volver">Quitar filtros</a>
                        <?php endif; ?>
                    </form>

                    <?php if ($puedeCrear): ?>
                        <a href="index.php?route=citas_crear" class="btn btn-main">Nueva cita</a>
                    <?php endif; ?>
                </div>

                <div class="panel-body">
                    <?php if (empty($citas)): ?>
                        <p class="placeholder-text">
                            <?= $hayFiltros ? 'No hay citas que coincidan con los filtros.' : 'Aún no hay citas registradas.' ?>
                        </p>
                    <?php else: ?>
                        <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Código</th>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Paciente</th>
                                    <th>Médico</th>
                                    <th>Especialidad</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <?php if ($puedeConfirmar || $puedeCancelar || $esMedico || $esStaff): ?><th>Acciones</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($citas as $i => $c): ?>
                                    <?php
                                        $fecha    = date('d/m/Y', strtotime($c['fecha']));
                                        $horario  = substr($c['hora_inicio'], 0, 5) . ' - ' . substr($c['hora_fin'], 0, 5);
                                        $motivo   = trim($c['motivo'] ?? '');
                                        $motivo   = $motivo === '' ? '—' : (mb_strlen($motivo) > 45 ? mb_substr($motivo, 0, 45) . '…' : $motivo);
                                        $paciente = $c['paciente_nombres'] . ' ' . $c['paciente_apellidos'];
                                        // ¿Ya empezó la hora de la cita? Desde ese momento
                                        // el paciente ya no puede cancelar.
                                        $yaEmpezo = strtotime($c['fecha'] . ' ' . $c['hora_inicio']) <= time();
                                    ?>
                                    <tr>
                                        <td><?= (int)$pag['desde'] + $i ?></td>
                                        <td><strong><?= htmlspecialchars($c['codigo']) ?></strong></td>
                                        <td><?= $fecha ?></td>
                                        <td><?= $horario ?></td>
                                        <td>
                                            <?= htmlspecialchars($paciente) ?>
                                            <br><span class="muted">CI <?= htmlspecialchars($c['paciente_ci']) ?></span>
                                        </td>
                                        <td>Dr(a). <?= htmlspecialchars($c['medico_nombres'] . ' ' . $c['medico_apellidos']) ?></td>
                                        <td><?= htmlspecialchars($c['especialidad'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($motivo) ?>
                                            <?php if ($c['estado'] === 'Cancelada' && !empty($c['motivo_cancelacion'])): ?>
                                                <span class="motivo-cancelacion">
                                                    Cancelación: <?= htmlspecialchars($c['motivo_cancelacion']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="<?= $claseEstado($c['estado']) ?>">
                                                <?= htmlspecialchars($c['estado']) ?>
                                            </span>
                                        </td>
                                        <?php if ($puedeConfirmar || $puedeCancelar || $esMedico || $esStaff): ?>
                                        <td>
                                            <?php
                                                $estado = $c['estado'];
                                                $muestraConfirmar = $puedeConfirmar && $estado === 'Programada' && !$yaEmpezo;
                                                $muestraCancelar  = $puedeCancelar && !$yaEmpezo
                                                    && in_array($estado, ['Programada', 'Confirmada'], true);

                                                // El médico marca Atendida desde Confirmada o corrige
                                                // una Inasistencia de un paciente que sí llegó.
                                                $muestraAtender = $esMedico && $yaEmpezo
                                                    && in_array($estado, ['Confirmada', 'Inasistencia'], true);

                                                // Inasistencia: el médico en sus citas vencidas sin
                                                // atención; el personal administrativo cuando el
                                                // paciente no canceló ni confirmó (seguía Programada).
                                                $muestraInasistencia = $yaEmpezo && (
                                                    ($esMedico && in_array($estado, ['Programada', 'Confirmada'], true))
                                                    || ($esStaff && $estado === 'Programada')
                                                );
                                            ?>
                                            <?php if ($muestraConfirmar): ?>
                                                <form action="index.php?route=citas_confirmar" method="POST" style="display:inline"
                                                      onsubmit="return confirm('¿Confirmar la cita <?= htmlspecialchars($c['codigo']) ?>?');">
                                                    <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                                    <button type="submit" class="btn btn-main" style="padding:5px 10px;font-size:12px">Confirmar</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($muestraAtender): ?>
                                                <form action="index.php?route=citas_atender" method="POST" style="display:inline"
                                                      onsubmit="return confirm('¿Marcar la cita <?= htmlspecialchars($c['codigo']) ?> como Atendida?');">
                                                    <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                                    <button type="submit" class="btn btn-main" style="padding:5px 10px;font-size:12px">Atendida</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($muestraInasistencia): ?>
                                                <form action="index.php?route=citas_inasistencia" method="POST" style="display:inline"
                                                      onsubmit="return confirm('¿Marcar la cita <?= htmlspecialchars($c['codigo']) ?> como Inasistencia?');">
                                                    <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                                    <button type="submit" class="btn btn-inasistencia" style="padding:5px 10px;font-size:12px">Inasistencia</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($muestraCancelar): ?>
                                                <form action="index.php?route=citas_cancelar" method="POST" class="form-cancelar"
                                                      onsubmit="return confirm('¿Cancelar la cita <?= htmlspecialchars($c['codigo']) ?>?\nMotivo: ' + this.motivo_cancelacion.value.trim() + '\nSe liberará su ficha y no se puede deshacer.');">
                                                    <input type="hidden" name="id_cita" value="<?= (int)$c['id_cita'] ?>">
                                                    <input type="text" name="motivo_cancelacion" maxlength="300" required
                                                           placeholder="Motivo de la cancelación *"
                                                           aria-label="Motivo de la cancelación">
                                                    <button type="submit" class="btn btn-danger" style="padding:5px 10px;font-size:12px">Cancelar</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (!$muestraConfirmar && !$muestraCancelar && !$muestraAtender && !$muestraInasistencia): ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <?php require __DIR__ . '/../layouts/paginacion.php'; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
