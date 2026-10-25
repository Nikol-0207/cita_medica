<?php
$old = $old ?? [];
$v   = fn($k) => htmlspecialchars($old[$k] ?? '');
$marcadas = array_map('intval', $old['especialidades'] ?? []);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar médico - Clínica Vida Salud</title>
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

    <?php $activo = 'medicos_crear'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Registro de un nuevo médico</h1>

            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <form action="index.php?route=medicos_guardar" method="POST">
                <div class="panel-box panel-azul">
                    <div class="panel-header"><h4>Llene los datos</h4></div>
                    <div class="panel-body">
                        <div class="form-grid">

                            <div class="campo col-3">
                                <label for="nombres">Nombres *</label>
                                <input type="text" id="nombres" name="nombres" required value="<?= $v('nombres') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required value="<?= $v('apellidos') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="registro_profesional">Registro profesional *</label>
                                <input type="text" id="registro_profesional" name="registro_profesional" required value="<?= $v('registro_profesional') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="telefono">Teléfono *</label>
                                <input type="text" id="telefono" name="telefono" required value="<?= $v('telefono') ?>">
                            </div>

                            <div class="campo col-3">
                                <label for="usuario_nombre">Nombre de usuario *</label>
                                <input type="text" id="usuario_nombre" name="usuario_nombre" required autocomplete="off" value="<?= $v('usuario_nombre') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="correo">Email *</label>
                                <input type="email" id="correo" name="correo" required value="<?= $v('correo') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="campo col-3">
                                <label for="password_confirmar">Password verificación *</label>
                                <input type="password" id="password_confirmar" name="password_confirmar" required minlength="8" autocomplete="new-password">
                            </div>

                            <div class="campo col-12">
                                <label>Especialidades * <small class="hint-inline">(puede elegir más de una)</small></label>
                                <?php if (empty($especialidades)): ?>
                                    <p class="placeholder-text">No hay especialidades activas registradas.</p>
                                <?php else: ?>
                                    <div class="esp-grid">
                                        <?php foreach ($especialidades as $e): ?>
                                            <label class="esp-item">
                                                <input type="checkbox" name="especialidades[]"
                                                       value="<?= (int)$e['id_especialidad'] ?>"
                                                       <?= in_array((int)$e['id_especialidad'], $marcadas, true) ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($e['nombre']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=medicos" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Registrar médico</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>