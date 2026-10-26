<?php
// Espera: $medico, $especialidades, $asignadas, $old, $mensajeExito, $mensajeError, $colores
$v = function ($k, $default = '') use ($old) {
    $valor = is_array($old) ? ($old[$k] ?? $default) : $default;
    return htmlspecialchars((string)$valor, ENT_QUOTES);
};
$marcadas = is_array($old) && isset($old['especialidades'])
    ? array_map('intval', (array)$old['especialidades'])
    : array_map('intval', $asignadas ?? []);
$fechaLegible = function ($fecha) {
    $ts = !empty($fecha) ? strtotime($fecha) : false;
    return $ts ? date('d/m/Y', $ts) : '—';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del médico - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
        .campo input[readonly] { background: #F8FAFC; color: #475569; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Información del médico</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <form action="index.php?route=medico_actualizar" method="POST">
                <input type="hidden" name="id_medico" value="<?= (int)$medico['id_medico'] ?>">

                <div class="panel-box panel-azul">
                    <div class="panel-header">
                        <h4>Datos personales y profesionales</h4>
                        <a href="index.php?route=medicos" class="link-volver">← Volver al listado</a>
                    </div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="campo col-3">
                                <label>Código</label>
                                <input type="text" readonly value="<?= htmlspecialchars($medico['codigo']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label>Estado</label>
                                <input type="text" readonly value="<?= htmlspecialchars($medico['estado']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label>Fecha de registro</label>
                                <input type="text" readonly value="<?= $fechaLegible($medico['fecha_registro']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label>Cuenta de usuario</label>
                                <input type="text" readonly
                                       value="<?= !empty($medico['usuario']) ? htmlspecialchars($medico['usuario']) : 'Sin cuenta vinculada' ?>">
                            </div>

                            <div class="campo col-6">
                                <label for="nombres">Nombres *</label>
                                <input type="text" id="nombres" name="nombres" required
                                       value="<?= $v('nombres', $medico['nombres']) ?>">
                            </div>
                            <div class="campo col-6">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required
                                       value="<?= $v('apellidos', $medico['apellidos']) ?>">
                            </div>

                            <div class="campo col-4">
                                <label for="registro_profesional">Registro profesional *</label>
                                <input type="text" id="registro_profesional" name="registro_profesional" required
                                       value="<?= $v('registro_profesional', $medico['registro_profesional']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="telefono">Celular *</label>
                                <input type="text" id="telefono" name="telefono" required
                                       value="<?= $v('telefono', $medico['telefono']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="correo">Correo de contacto *</label>
                                <input type="email" id="correo" name="correo" required
                                       value="<?= $v('correo', $medico['correo'] ?? '') ?>">
                            </div>

                            <div class="campo col-12">
                                <label>Especialidades * <small class="hint-inline">(puede elegir más de una)</small></label>
                                <?php if (empty($especialidades)): ?>
                                    <p class="placeholder-text">No hay especialidades registradas.</p>
                                <?php else: ?>
                                    <div class="esp-grid">
                                        <?php foreach ($especialidades as $e): ?>
                                            <?php $inactiva = ($e['estado'] ?? 'Activo') !== 'Activo'; ?>
                                            <label class="esp-item">
                                                <input type="checkbox" name="especialidades[]"
                                                       value="<?= (int)$e['id_especialidad'] ?>"
                                                       <?= in_array((int)$e['id_especialidad'], $marcadas, true) ? 'checked' : '' ?>>
                                                <span>
                                                    <?= htmlspecialchars($e['nombre']) ?>
                                                    <?php if ($inactiva): ?><em class="hint-inline">(inactiva)</em><?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=medicos" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Guardar cambios</button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!empty($medico['correo_cuenta'])): ?>
            <div class="panel-box panel-azul">
                <div class="panel-header"><h4>Cuenta de acceso</h4></div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="campo col-6">
                            <label>Correo de inicio de sesión</label>
                            <input type="text" readonly value="<?= htmlspecialchars($medico['correo_cuenta']) ?>">
                        </div>
                        <div class="campo col-12">
                            <p class="hint">El usuario y el correo de inicio de sesión se administran por separado de los datos profesionales.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
