<?php
// Espera: $opciones (médico + especialidad), $dias, $colores, $mensajeError, $old
$old = $old ?? [];
$v   = fn($k) => htmlspecialchars($old[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar horario - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'horarios_crear'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Registro de un nuevo horario</h1>

            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <form action="index.php?route=horarios_guardar" method="POST">
                <div class="panel-box panel-azul">
                    <div class="panel-header"><h4>Llene los datos</h4></div>
                    <div class="panel-body">
                        <div class="form-grid">

                            <div class="campo col-6">
                                <label for="doctor">Doctores *</label>
                                <?php if (empty($opciones)): ?>
                                    <p class="placeholder-text">No hay doctores con especialidades activas.</p>
                                <?php else: ?>
                                    <select id="doctor" name="doctor" required>
                                        <option value="">Seleccionar doctor</option>
                                        <?php foreach ($opciones as $op): ?>
                                            <?php $val = (int)$op['id_medico'] . '-' . (int)$op['id_especialidad']; ?>
                                            <option value="<?= $val ?>" <?= ($old['doctor'] ?? '') === $val ? 'selected' : '' ?>>
                                                Dr(a). <?= htmlspecialchars($op['nombres'] . ' ' . $op['apellidos']) ?> - <?= htmlspecialchars(mb_strtoupper($op['especialidad'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div class="campo col-6">
                                <label for="dia">Día *</label>
                                <select id="dia" name="dia" required>
                                    <option value="">Seleccionar día</option>
                                    <?php foreach ($dias as $d): ?>
                                        <option value="<?= htmlspecialchars($d) ?>" <?= ($old['dia'] ?? '') === $d ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(mb_strtoupper($d)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo col-4">
                                <label for="hora_inicio">Hora Inicio *</label>
                                <input type="time" id="hora_inicio" name="hora_inicio" required
                                       value="<?= $v('hora_inicio') ?>">
                            </div>

                            <div class="campo col-4">
                                <label for="hora_fin">Hora Final *</label>
                                <input type="time" id="hora_fin" name="hora_fin" required
                                       value="<?= $v('hora_fin') ?>">
                            </div>

                            <div class="campo col-4">
                                <label for="duracion">Duración de la cita (min) *</label>
                                <input type="number" id="duracion" name="duracion" required
                                       min="5" max="240" step="5"
                                       value="<?= htmlspecialchars($old['duracion'] ?? '30') ?>">
                                <p class="hint">Entre 5 y 240 minutos. Las fichas se calculan automáticamente.</p>
                            </div>

                            <div class="campo col-6">
                                <label for="fecha_fin">Disponible hasta *</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" required
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= htmlspecialchars($old['fecha_fin'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                                <p class="hint">Hasta esta fecha se podrán programar citas en este horario;
                                    cada semana que caiga dentro del rango tendrá sus propias fichas.</p>
                            </div>

                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=horarios" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Registrar nuevo</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
