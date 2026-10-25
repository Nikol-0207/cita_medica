<?php
$old = $old ?? [];
$v = fn($k) => htmlspecialchars($old[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar recepcionista - Clínica Vida Salud</title>
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

    <?php $activo = 'recepcionistas_crear'; require __DIR__ . '/../../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Registro de un nuevo recepcionista</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <form action="index.php?route=recepcionistas_guardar" method="POST">
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
                                <label for="ci">CI *</label>
                                <input type="text" id="ci" name="ci" required value="<?= $v('ci') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="celular">Celular *</label>
                                <input type="text" id="celular" name="celular" required value="<?= $v('celular') ?>">
                            </div>

                            <div class="campo col-3">
                                <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                                       max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>">
                            </div>
                            <div class="campo col-9">
                                <label for="direccion">Dirección *</label>
                                <input type="text" id="direccion" name="direccion" required value="<?= $v('direccion') ?>">
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

                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=admin" class="btn btn-sec">Cancelar</a>
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