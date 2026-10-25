<?php
// Espera: $horarios, $pag, $medicos, $especialidades, $filtros, $hayFiltros,
//         $colores, $mensajeExito, $mensajeError
$esMedico   = $esMedico ?? false;     // el médico solo ve su propio horario
$esPaciente = $esPaciente ?? false;
$titulo     = $esMedico ? 'Mi horario' : ($esPaciente ? 'Horarios de atención' : 'Listado de horarios');
$puedeCrear = Permisos::tiene('horarios.gestionar');
$f = fn($k) => htmlspecialchars($filtros[$k] ?? '', ENT_QUOTES);
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
        .fichas-reservadas {
            display: inline-block; min-width: 26px; text-align: center; padding: 2px 7px;
            border-radius: 10px; background: #F1F5F9; color: #64748B;
            font-weight: 700; font-size: 12px;
        }
        .fichas-reservadas.con-reservas { background: #FEF3C7; color: #92400E; }
        .vigencia-vencida { color: #B91C1C; font-weight: 700; font-size: 12px; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'horarios'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title"><?= $esMedico ? 'Mi horario de atención' : $titulo ?></h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <div class="panel-box panel-azul">
                <div class="panel-header">
                    <h4><?= $esMedico ? 'Mis horarios' : 'Horarios registrados' ?> (<?= (int)$pag['total'] ?>)</h4>

                    <div class="panel-filter">
                        <form method="GET" action="index.php" class="panel-filter">
                            <input type="hidden" name="route" value="horarios">

                            <label for="filtro-esp">Especialidad:</label>
                            <select id="filtro-esp" name="especialidad" class="form-select" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                <?php foreach ($especialidades as $e): ?>
                                    <option value="<?= (int)$e['id_especialidad'] ?>"
                                        <?= (int)$filtros['especialidad'] === (int)$e['id_especialidad'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(mb_strtoupper($e['nombre'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (!$esMedico): ?>
                            <label for="filtro-medico">Doctor:</label>
                            <select id="filtro-medico" name="medico" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach ($medicos as $m): ?>
                                    <option value="<?= (int)$m['id_medico'] ?>" <?= $filtros['medico'] === (int)$m['id_medico'] ? 'selected' : '' ?>>
                                        Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>

                            <label for="filtro-estado">Estado:</label>
                            <select id="filtro-estado" name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="Activo"   <?= $filtros['estado'] === 'Activo'   ? 'selected' : '' ?>>Activo</option>
                                <option value="Inactivo" <?= $filtros['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            </select>

                            <?php if ($hayFiltros): ?>
                                <a href="index.php?route=horarios" class="link-volver">Quitar filtros</a>
                            <?php endif; ?>
                        </form>

                        <?php if ($puedeCrear): ?>
                        <a href="index.php?route=horarios_crear" class="btn btn-main">Registrar nuevo</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel-body">
                    <?php if (empty($horarios)): ?>
                        <p class="placeholder-text">
                            <?= $hayFiltros
                                ? 'No hay horarios que coincidan con los filtros.'
                                : ($esMedico ? 'Todavía no tiene horarios de atención registrados.' : 'Aún no hay horarios de atención registrados.') ?>
                        </p>
                    <?php else: ?>
                        <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>Nro</th>
                                    <th>Doctor</th>
                                    <th>Especialidad</th>
                                    <th>Día de atención</th>
                                    <th>Hora inicio</th>
                                    <th>Hora fin</th>
                                    <th>Duración</th>
                                    <th>Fichas</th>
                                    <th>Reservadas</th>
                                    <th>Disponible hasta</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($horarios as $i => $h): ?>
                                    <?php
                                        $reservadas = (int)($h['fichas_reservadas'] ?? 0);
                                        $vencido    = !Horario::vigente($h['fecha_fin'] ?? null);
                                    ?>
                                    <tr>
                                        <td><?= (int)$pag['desde'] + $i ?></td>
                                        <td>Dr(a). <?= htmlspecialchars($h['nombres'] . ' ' . $h['apellidos']) ?></td>
                                        <td><?= htmlspecialchars($h['especialidad']) ?></td>
                                        <td><?= htmlspecialchars(mb_strtoupper($h['dia_semana'])) ?></td>
                                        <td><?= substr($h['hora_inicio'], 0, 5) ?></td>
                                        <td><?= substr($h['hora_fin'], 0, 5) ?></td>
                                        <td><?= (int)$h['duracion_cita'] ?> min</td>
                                        <td><?= (int)$h['cantidad_fichas'] ?></td>
                                        <td>
                                            <span class="fichas-reservadas <?= $reservadas > 0 ? 'con-reservas' : '' ?>">
                                                <?= $reservadas ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?= $vencido ? 'vigencia-vencida' : '' ?>">
                                                <?= $vencido ? 'Vencido' : date('d/m/Y', strtotime($h['fecha_fin'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-estado <?= $h['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                                <?= htmlspecialchars($h['estado']) ?>
                                            </span>
                                        </td>
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
