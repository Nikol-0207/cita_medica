<?php
// Espera: $horarios, $horario, $fecha, $fichas, $esPaciente, $pacientePropio,
//         $pacientes, $errorDisp, $colores, $mensajeExito, $mensajeError
$esPaciente    = $esPaciente ?? false;
$pacientePropio = $pacientePropio ?? null;
$pacientes     = $pacientes ?? [];
$fichas        = $fichas ?? [];
$horario       = $horario ?? null;
$fecha         = $fecha ?? '';
$errorDisp     = $errorDisp ?? null;

$v  = fn($k, $d = '') => htmlspecialchars($d === '' ? ($_GET[$k] ?? '') : $d, ENT_QUOTES);
$pa = fn($p) => htmlspecialchars(trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')), ENT_QUOTES);

$libres    = count(array_filter($fichas, fn($f) => !$f['ocupada']));
$ocupadas  = count($fichas) - $libres;
$diaSemana = ['Domingo' => 0, 'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3,
              'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar cita - Clínica Vida Salud</title>
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

        .fichas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
        .ficha {
            border: 1px solid #E2E8F0; border-radius: 8px; background: #fff;
            padding: 12px 14px; display: flex; flex-direction: column; gap: 8px;
        }
        .ficha-hora { font-size: 17px; font-weight: 800; color: #0F172A; }
        .ficha-meta { font-size: 12px; color: #64748B; }
        .ficha-libre { border-color: #99F6E4; background: #F0FDFA; }
        .ficha-ocupada { background: #F8FAFC; border-color: #E2E8F0; opacity: .85; }
        .ficha .btn { width: 100%; padding: 8px; font-size: 13px; }
        .resumen-fichas { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; font-size: 14px; margin-bottom: 14px; }
        .dato { background: #fff; border: 1px solid #E2E8F0; border-radius: 6px; padding: 7px 12px; }
        .dato strong { color: var(--primary); }
        .paciente-fijo { padding: 10px 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'citas_crear'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Programar una nueva cita</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>
            <?php if (!empty($errorDisp)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($errorDisp) ?></div>
            <?php endif; ?>

            <?php if (empty($horarios)): ?>
                <div class="panel-box panel-azul">
                    <div class="panel-body">
                        <p class="placeholder-text">No hay horarios de atención activos para programar citas.</p>
                        <div class="form-acciones">
                            <a href="index.php?route=citas" class="btn btn-sec">Volver</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <!-- ============ 1. Elegir horario, fecha y datos ============ -->
                <form action="index.php" method="GET" class="panel-box panel-azul">
                    <input type="hidden" name="route" value="citas_crear">
                    <div class="panel-header"><h4>1. Elija el horario disponible</h4></div>
                    <div class="panel-body">
                        <div class="form-grid">

                            <div class="campo col-6">
                                <label for="horario">Horario (doctor, especialidad y fichas) *</label>
                                <select id="horario" name="horario" required onchange="this.form.submit()">
                                    <option value="">Seleccionar horario</option>
                                    <?php foreach ($horarios as $h): ?>
                                        <option value="<?= (int)$h['id_horario'] ?>"
                                            <?= (int)($horario['id_horario'] ?? 0) === (int)$h['id_horario'] ? 'selected' : '' ?>>
                                            Dr(a). <?= htmlspecialchars($h['nombres'] . ' ' . $h['apellidos']) ?>
                                            - <?= htmlspecialchars(mb_strtoupper($h['especialidad'])) ?>
                                            · <?= htmlspecialchars(mb_strtoupper($h['dia_semana'])) ?>
                                            <?= substr($h['hora_inicio'], 0, 5) ?>-<?= substr($h['hora_fin'], 0, 5) ?>
                                            · <?= (int)$h['cantidad_fichas'] ?> fichas
                                            · hasta <?= date('d/m/Y', strtotime($h['fecha_fin'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo col-6">
                                <label for="fecha">Fecha de la cita<?= $horario ? ' *' : '' ?></label>
                                <input type="date" id="fecha" name="fecha"
                                       min="<?= date('Y-m-d') ?>"
                                       <?= !empty($horario['fecha_fin']) ? 'max="' . htmlspecialchars($horario['fecha_fin']) . '"' : '' ?>
                                       value="<?= htmlspecialchars($fecha) ?>"
                                       <?= $horario ? 'required onchange="this.form.submit()"' : '' ?>>
                                <?php if ($horario): ?>
                                    <p class="hint">
                                        Debe ser un/a <?= htmlspecialchars($horario['dia_semana']) ?> (día del horario)
                                        y hasta el <?= date('d/m/Y', strtotime($horario['fecha_fin'])) ?>.
                                        Cada fecha tiene sus propias fichas.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <?php if ($esPaciente): ?>
                                <div class="campo col-6">
                                    <label>Paciente</label>
                                    <div class="paciente-fijo">
                                        <?php if ($pacientePropio > 0): ?>
                                            Usted (paciente vinculado a su cuenta) — ID <?= (int)$pacientePropio ?>
                                        <?php else: ?>
                                            <span style="color:#B91C1C">Su cuenta no tiene un paciente vinculado.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="campo col-6">
                                    <label for="id_paciente">Paciente<?= $horario ? ' *' : '' ?></label>
                                    <select id="id_paciente" name="id_paciente" <?= $horario ? 'required' : '' ?>>
                                        <option value="">Seleccionar paciente</option>
                                        <?php foreach ($pacientes as $p): ?>
                                            <option value="<?= (int)$p['id_paciente'] ?>"
                                                <?= (int)($_GET['id_paciente'] ?? 0) === (int)$p['id_paciente'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['codigo']) ?> - <?= $pa($p) ?>
                                                (CI <?= htmlspecialchars($p['ci']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="campo col-12">
                                <label for="motivo">Motivo de la cita</label>
                                <textarea id="motivo" name="motivo" rows="2"
                                          placeholder="Ej.: Control general, dolor de cabeza..."><?= $v('motivo') ?></textarea>
                            </div>

                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=citas" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Ver fichas disponibles</button>
                        </div>
                    </div>
                </form>

                <!-- ============ 2. Elegir la ficha ============ -->
                <?php if ($horario && $fecha !== ''): ?>
                    <div class="panel-box panel-azul" style="margin-top:22px">
                        <div class="panel-header">
                            <h4>2. Elija la ficha (<?= count($fichas) ?> en total)</h4>
                        </div>
                        <div class="panel-body">

                            <div class="resumen-fichas">
                                <span class="dato"><strong><?= (int)$horario['duracion_cita'] ?></strong> min por cita</span>
                                <span class="dato"><strong><?= $libres ?></strong> libres</span>
                                <span class="dato"><strong><?= $ocupadas ?></strong> ocupadas</span>
                                <span class="dato">
                                    <?= htmlspecialchars($fecha) ?> (<?= htmlspecialchars($horario['dia_semana']) ?>)
                                </span>
                            </div>

                            <?php if (empty($fichas)): ?>
                                <p class="placeholder-text">
                                    <?= $fecha === date('Y-m-d')
                                        ? 'Ya no quedan fichas por empezar hoy. Elige otra fecha.'
                                        : 'Ese horario no tiene fichas calculadas (revisa la duración y el rango de horas).' ?>
                                </p>
                            <?php else: ?>
                                <div class="fichas-grid">
                                    <?php foreach ($fichas as $f): ?>
                                        <div class="ficha <?= $f['ocupada'] ? 'ficha-ocupada' : 'ficha-libre' ?>">
                                            <div class="ficha-hora"><?= $f['inicio'] ?> - <?= $f['fin'] ?></div>

                                            <?php if ($f['ocupada']): ?>
                                                <span class="badge-estado badge-<?= str_replace(' ', '-', mb_strtolower($f['estado'])) ?>">
                                                    <?= htmlspecialchars($f['estado']) ?>
                                                </span>
                                                <div class="ficha-meta">Ficha no disponible</div>
                                            <?php else: ?>
                                                <div class="ficha-meta">Disponible</div>
                                                <form action="index.php?route=citas_guardar" method="POST"
                                                      onsubmit="return confirm('¿Programar la cita de <?= $f['inicio'] ?> a <?= $f['fin'] ?>?');">
                                                    <input type="hidden" name="horario" value="<?= (int)$horario['id_horario'] ?>">
                                                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                                                    <input type="hidden" name="hora_inicio" value="<?= $f['inicio'] ?>">
                                                    <input type="hidden" name="hora_fin" value="<?= $f['fin'] ?>">
                                                    <input type="hidden" name="motivo" value="<?= $v('motivo') ?>">
                                                    <?php if (!$esPaciente): ?>
                                                        <input type="hidden" name="id_paciente"
                                                               value="<?= (int)($_GET['id_paciente'] ?? 0) ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" class="btn btn-main">Programar cita</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-acciones">
                                <a href="index.php?route=citas" class="btn btn-sec">Volver al listado</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
