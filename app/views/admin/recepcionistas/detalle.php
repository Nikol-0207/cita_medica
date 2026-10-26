<?php
// Espera: $recepcionista, $old, $mensajeExito, $mensajeError, $colores
$v = function ($k, $default = '') use ($old) {
    $valor = is_array($old) ? ($old[$k] ?? $default) : $default;
    return htmlspecialchars((string)$valor, ENT_QUOTES);
};
$fechaLegible = function ($fecha) {
    $ts = !empty($fecha) ? strtotime($fecha) : false;
    return $ts ? date('d/m/Y', $ts) : '—';
};
$fechaNac = !empty($recepcionista['fecha_nacimiento'])
    ? substr((string)$recepcionista['fecha_nacimiento'], 0, 10)
    : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del recepcionista - Clínica Vida Salud</title>
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

    <?php require __DIR__ . '/../../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Información del recepcionista</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <form action="index.php?route=recepcionista_actualizar" method="POST">
                <input type="hidden" name="id_recepcionista" value="<?= (int)$recepcionista['id_recepcionista'] ?>">

                <div class="panel-box panel-azul">
                    <div class="panel-header">
                        <h4>Datos personales</h4>
                        <a href="index.php?route=recepcionistas" class="link-volver">← Volver al listado</a>
                    </div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="campo col-4">
                                <label>Estado</label>
                                <input type="text" readonly value="<?= htmlspecialchars($recepcionista['estado']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label>Fecha de registro</label>
                                <input type="text" readonly value="<?= $fechaLegible($recepcionista['fecha_registro']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label>Cuenta de usuario</label>
                                <input type="text" readonly
                                       value="<?= !empty($recepcionista['usuario']) ? htmlspecialchars($recepcionista['usuario']) : 'Sin cuenta vinculada' ?>">
                            </div>

                            <div class="campo col-6">
                                <label for="nombres">Nombres *</label>
                                <input type="text" id="nombres" name="nombres" required
                                       value="<?= $v('nombres', $recepcionista['nombres']) ?>">
                            </div>
                            <div class="campo col-6">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required
                                       value="<?= $v('apellidos', $recepcionista['apellidos']) ?>">
                            </div>

                            <div class="campo col-4">
                                <label for="ci">CI *</label>
                                <input type="text" id="ci" name="ci" required
                                       value="<?= $v('ci', $recepcionista['ci']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="celular">Celular *</label>
                                <input type="text" id="celular" name="celular" required
                                       value="<?= $v('celular', $recepcionista['celular']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                                       max="<?= date('Y-m-d') ?>"
                                       value="<?= $v('fecha_nacimiento', $fechaNac) ?>">
                            </div>

                            <div class="campo col-12">
                                <label for="direccion">Dirección *</label>
                                <input type="text" id="direccion" name="direccion" required
                                       value="<?= $v('direccion', $recepcionista['direccion']) ?>">
                            </div>
                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=recepcionistas" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Guardar cambios</button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!empty($recepcionista['correo_cuenta'])): ?>
            <div class="panel-box panel-azul">
                <div class="panel-header"><h4>Cuenta de acceso</h4></div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="campo col-6">
                            <label>Correo de inicio de sesión</label>
                            <input type="text" readonly value="<?= htmlspecialchars($recepcionista['correo_cuenta']) ?>">
                        </div>
                        <div class="campo col-12">
                            <p class="hint">El usuario y el correo de inicio de sesión se administran por separado de los datos personales.</p>
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
