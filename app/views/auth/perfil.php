<?php
// La foto se sirve desde la BD (Neon) mediante index.php?route=avatar
$rutaFoto = 'index.php?route=avatar';
$volver = Permisos::inicio();
$datosMedico     = $datosMedico ?? null;
$datosPersonales = $datosPersonales ?? null;
$tipoPerfil      = $tipoPerfil ?? null;
$dp              = $datosPersonales ?? [];
$dpv             = fn($k, $d = '') => htmlspecialchars((string)($dp[$k] ?? $d), ENT_QUOTES);
$fechaNac        = !empty($dp['fecha_nacimiento']) ? substr((string)$dp['fecha_nacimiento'], 0, 10) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
    </style>
</head>
<body>
<main class="main-content">
    <header class="top-nav">
        <div class="top-title">
            <a href="index.php?route=<?= $volver ?>" class="link-volver">← Volver</a>
            &nbsp;/&nbsp; Mi perfil
        </div>
        <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
    </header>

    <div class="content-body">
        <?php if (!empty($mensajeExito)): ?>
            <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
        <?php endif; ?>
        <?php if (!empty($mensajeError)): ?>
            <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>

        <form action="index.php?route=guardar_perfil" method="POST" enctype="multipart/form-data" class="perfil-grid">

            <!-- Foto -->
            <div class="panel-box perfil-foto">
                <div class="panel-body" style="text-align:center;">
                    <div id="avatar-preview">
                        <img src="<?= $rutaFoto ?>" alt="Foto de perfil" class="avatar-grande">
                    </div>
                    <label for="foto" class="btn-claro">Cambiar foto</label>
                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" hidden>
                    <p class="hint">JPG, PNG o WEBP. Máximo 2 MB.</p>
                </div>
            </div>

            <!-- Datos -->
            <div class="panel-box">
                <div class="panel-header"><h4>Datos de la cuenta</h4></div>
                <div class="panel-body">
                    <div class="campo">
                        <label for="nombre">Nombre de usuario</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="<?= htmlspecialchars($perfil['nombre']) ?>">
                    </div>
                    <div class="campo">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" required
                               value="<?= htmlspecialchars($perfil['correo']) ?>">
                    </div>

                    <h4 class="sub-titulo">Cambiar contraseña <small>(déjalo vacío para mantener la actual)</small></h4>
                    <div class="campo">
                        <label for="password_actual">Contraseña actual</label>
                        <input type="password" id="password_actual" name="password_actual" autocomplete="current-password">
                    </div>
                    <div class="campo">
                        <label for="password_nueva">Nueva contraseña</label>
                        <input type="password" id="password_nueva" name="password_nueva" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="campo">
                        <label for="password_confirmar">Confirmar nueva contraseña</label>
                        <input type="password" id="password_confirmar" name="password_confirmar" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-main">💾 Guardar cambios</button>
                </div>
            </div>
        </form>

        <?php if ($tipoPerfil === 'paciente'): ?>
        <!-- Datos personales del paciente (sin información médica ni contactos de emergencia) -->
        <div class="panel-box">
            <div class="panel-header"><h4>Datos personales</h4></div>
            <div class="panel-body">
                <?php if (!$datosPersonales): ?>
                    <p class="placeholder-text">Su cuenta no tiene una ficha de paciente vinculada. Contacte al personal administrativo.</p>
                <?php else: ?>
                <form action="index.php?route=guardar_datos_personales" method="POST">
                    <div class="form-grid">
                        <div class="campo col-6">
                            <label for="nombres">Nombres *</label>
                            <input type="text" id="nombres" name="nombres" required value="<?= $dpv('nombres') ?>">
                        </div>
                        <div class="campo col-6">
                            <label for="apellidos">Apellidos *</label>
                            <input type="text" id="apellidos" name="apellidos" required value="<?= $dpv('apellidos') ?>">
                        </div>
                        <div class="campo col-4">
                            <label for="ci">CI *</label>
                            <input type="text" id="ci" name="ci" required value="<?= $dpv('ci') ?>">
                        </div>
                        <div class="campo col-4">
                            <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                                   max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($fechaNac) ?>">
                        </div>
                        <div class="campo col-4">
                            <label for="sexo">Género</label>
                            <select id="sexo" name="sexo">
                                <option value="M" <?= ($dp['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>MASCULINO</option>
                                <option value="F" <?= ($dp['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>FEMENINO</option>
                            </select>
                        </div>
                        <div class="campo col-6">
                            <label for="telefono">Celular *</label>
                            <input type="text" id="telefono" name="telefono" required value="<?= $dpv('telefono') ?>">
                        </div>
                        <div class="campo col-6">
                            <label for="nro_seguro">Nro de seguro</label>
                            <input type="text" id="nro_seguro" name="nro_seguro" value="<?= $dpv('nro_seguro') ?>">
                        </div>
                        <div class="campo col-12">
                            <label for="direccion">Dirección *</label>
                            <input type="text" id="direccion" name="direccion" required value="<?= $dpv('direccion') ?>">
                        </div>
                    </div>
                    <p class="hint">Para cambiar su correo electrónico o contraseña, use la sección «Datos de la cuenta».</p>
                    <button type="submit" class="btn btn-main">Guardar datos personales</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tipoPerfil === 'recepcionista'): ?>
        <!-- Datos personales del recepcionista -->
        <div class="panel-box">
            <div class="panel-header"><h4>Datos personales</h4></div>
            <div class="panel-body">
                <?php if (!$datosPersonales): ?>
                    <p class="placeholder-text">Su cuenta no tiene una ficha de recepcionista vinculada. Contacte al administrador.</p>
                <?php else: ?>
                <form action="index.php?route=guardar_datos_personales" method="POST">
                    <div class="form-grid">
                        <div class="campo col-6">
                            <label for="nombres">Nombres *</label>
                            <input type="text" id="nombres" name="nombres" required value="<?= $dpv('nombres') ?>">
                        </div>
                        <div class="campo col-6">
                            <label for="apellidos">Apellidos *</label>
                            <input type="text" id="apellidos" name="apellidos" required value="<?= $dpv('apellidos') ?>">
                        </div>
                        <div class="campo col-4">
                            <label for="ci">CI *</label>
                            <input type="text" id="ci" name="ci" required value="<?= $dpv('ci') ?>">
                        </div>
                        <div class="campo col-4">
                            <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                                   max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($fechaNac) ?>">
                        </div>
                        <div class="campo col-4">
                            <label for="celular">Celular *</label>
                            <input type="text" id="celular" name="celular" required value="<?= $dpv('celular') ?>">
                        </div>
                        <div class="campo col-12">
                            <label for="direccion">Dirección *</label>
                            <input type="text" id="direccion" name="direccion" required value="<?= $dpv('direccion') ?>">
                        </div>
                    </div>
                    <p class="hint">Para cambiar su correo electrónico o contraseña, use la sección «Datos de la cuenta».</p>
                    <button type="submit" class="btn btn-main">Guardar datos personales</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($datosMedico)): ?>
        <!-- Datos del médico: solo celular y correo son editables -->
        <div class="panel-box">
            <div class="panel-header"><h4>Datos personales y profesionales</h4></div>
            <div class="panel-body">
                <form action="index.php?route=guardar_datos_personales" method="POST">
                    <div class="form-grid">
                        <div class="campo col-6">
                            <label>Nombre completo</label>
                            <input type="text" readonly
                                   value="<?= htmlspecialchars('Dr(a). ' . $datosMedico['nombres'] . ' ' . $datosMedico['apellidos']) ?>">
                        </div>
                        <div class="campo col-3">
                            <label>Código</label>
                            <input type="text" readonly value="<?= htmlspecialchars($datosMedico['codigo']) ?>">
                        </div>
                        <div class="campo col-3">
                            <label>Registro profesional</label>
                            <input type="text" readonly value="<?= htmlspecialchars($datosMedico['registro_profesional']) ?>">
                        </div>
                        <div class="campo col-12">
                            <label>Especialidades</label>
                            <input type="text" readonly value="<?= htmlspecialchars($datosMedico['especialidades'] ?? 'Sin especialidades asignadas') ?>">
                        </div>
                        <div class="campo col-6">
                            <label for="medico_telefono">Celular *</label>
                            <input type="text" id="medico_telefono" name="medico_telefono" required
                                   value="<?= htmlspecialchars($datosMedico['telefono'] ?? '') ?>">
                        </div>
                        <div class="campo col-6">
                            <label for="medico_correo">Correo de contacto *</label>
                            <input type="email" id="medico_correo" name="medico_correo" required
                                   value="<?= htmlspecialchars($datosMedico['correo'] ?? '') ?>">
                        </div>
                    </div>
                    <p class="hint">Solo puede actualizar su celular y correo de contacto. Para el correo de inicio de sesión use «Datos de la cuenta». El resto de sus datos profesionales los administra el personal administrativo.</p>
                    <button type="submit" class="btn btn-main">Guardar celular y correo</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.getElementById('foto').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        alert('La imagen supera los 2 MB.');
        this.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('avatar-preview').innerHTML =
            '<img src="' + e.target.result + '" alt="Vista previa" class="avatar-grande">';
    };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>