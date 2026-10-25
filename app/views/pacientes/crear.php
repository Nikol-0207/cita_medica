<?php
$old = $old ?? [];
$v   = fn($k) => htmlspecialchars($old[$k] ?? '');
$sel = fn($k, $val) => (($old[$k] ?? '') === $val) ? 'selected' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar paciente - Clínica Vida Salud</title>
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

    <?php $activo = 'pacientes_crear'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Registro de un nuevo paciente</h1>

            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <form action="index.php?route=pacientes_guardar" method="POST">
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
                                <label for="nro_seguro">Nro de seguro *</label>
                                <input type="text" id="nro_seguro" name="nro_seguro" value="<?= $v('nro_seguro') ?>">
                            </div>

                            <div class="campo col-3">
                                <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                                       max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="sexo">Género</label>
                                <select id="sexo" name="sexo">
                                    <option value="M" <?= $sel('sexo', 'M') ?>>MASCULINO</option>
                                    <option value="F" <?= $sel('sexo', 'F') ?>>FEMENINO</option>
                               </select>
                            </div>
                            <div class="campo col-3">
                                <label for="telefono">Celular *</label>
                                <input type="text" id="telefono" name="telefono" required value="<?= $v('telefono') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="correo">Correo *</label>
                                <input type="email" id="correo" name="correo"  value="<?= $v('correo') ?>">
                            </div>

                            <div class="campo col-6">
                                <label for="direccion">Dirección *</label>
                                <input type="text" id="direccion" name="direccion" required value="<?= $v('direccion') ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="tipo_sangre">Grupo sanguíneo</label>
                                <select id="tipo_sangre" name="tipo_sangre">
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $g): ?>
                                        <option value="<?= $g ?>" <?= $sel('tipo_sangre', $g) ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo col-3">
                                <label for="alergias">Alergias *</label>
                                <input type="text" id="alergias" name="alergias" placeholder="Ninguna" value="<?= $v('alergias') ?>">
                            </div>

                            <div class="campo col-4">
                                <label for="contacto_nombre">Contacto de emergencia *</label>
                                <input type="text" id="contacto_nombre" name="contacto_nombre"  value="<?= $v('contacto_nombre') ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="contacto_parentesco">Parentesco *</label>
                                <input type="text" id="contacto_parentesco" name="contacto_parentesco" value="<?= $v('contacto_parentesco') ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="contacto_telefono">Teléfono del contacto *</label>
                                <input type="text" id="contacto_telefono" name="contacto_telefono"  value="<?= $v('contacto_telefono') ?>">
                            </div>

                            <div class="campo col-12">
                                <label for="observaciones">Observaciones</label>
                                <textarea id="observaciones" name="observaciones" rows="2"><?= $v('observaciones') ?></textarea>
                            </div>
                               <div class="col-12 check-cuenta">
    <label class="check-label">
        <input type="checkbox" id="crear_cuenta" name="crear_cuenta" value="1" <?= !empty($old['crear_cuenta']) ? 'checked' : '' ?>>
        Crear cuenta de usuario para este paciente
    </label>
    <p class="hint">Permite que el paciente ingrese al sistema con su usuario, correo o CI. Requiere el correo.</p>
</div>

<div class="col-12" id="bloque-cuenta" hidden>
    <div class="form-grid">
        <div class="campo col-4">
            <label for="usuario_nombre">Nombre de usuario *</label>
            <input type="text" id="usuario_nombre" name="usuario_nombre" value="<?= $v('usuario_nombre') ?>" autocomplete="off">
        </div>
        <div class="campo col-4">
            <label for="password">Password *</label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password">
        </div>
        <div class="campo col-4">
            <label for="password_confirmar">Password verificación *</label>
            <input type="password" id="password_confirmar" name="password_confirmar" minlength="8" autocomplete="new-password">
        </div>
    </div>
</div>
                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=pacientes" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Registrar paciente</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

  <script>
(function () {
    const chk       = document.getElementById('crear_cuenta');
    const bloque    = document.getElementById('bloque-cuenta');
    const correo    = document.getElementById('correo');
    const etqCorreo = document.querySelector('label[for="correo"]');
    const campos    = bloque.querySelectorAll('input');

    function actualizar() {
        const on = chk.checked;
        bloque.hidden = !on;
        campos.forEach(c => { c.disabled = !on; c.required = on; });
        correo.required = on;
        etqCorreo.textContent = on ? 'Correo *' : 'Correo';
    }

    chk.addEventListener('change', actualizar);
    actualizar();
})();
</script>
</body>
</html>