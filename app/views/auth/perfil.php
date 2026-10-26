<?php
// La foto se sirve desde la BD (Neon) mediante index.php?route=avatar
$rutaFoto = 'index.php?route=avatar';
$volver = Permisos::inicio();
$datosMedico = $datosMedico ?? null;
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
                    <label for="foto" class="btn-secundario">Cambiar foto</label>
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

                    <button type="submit" class="btn-guardar">💾 Guardar cambios</button>
                </div>
            </div>
        </form>

        <?php if (!empty($datosMedico)): ?>
        <!-- Datos profesionales del médico: solo lectura (los gestiona el administrador) -->
        <div class="panel-box">
            <div class="panel-header"><h4>Datos profesionales</h4></div>
            <div class="panel-body">
                <div class="campo">
                    <label>Nombre completo</label>
                    <input type="text" readonly value="<?= htmlspecialchars('Dr(a). ' . $datosMedico['nombres'] . ' ' . $datosMedico['apellidos']) ?>">
                </div>
                <div class="campo">
                    <label>Código</label>
                    <input type="text" readonly value="<?= htmlspecialchars($datosMedico['codigo']) ?>">
                </div>
                <div class="campo">
                    <label>Registro profesional</label>
                    <input type="text" readonly value="<?= htmlspecialchars($datosMedico['registro_profesional']) ?>">
                </div>
                <div class="campo">
                    <label>Teléfono</label>
                    <input type="text" readonly value="<?= htmlspecialchars($datosMedico['telefono']) ?>">
                </div>
                <div class="campo">
                    <label>Correo profesional</label>
                    <input type="text" readonly value="<?= htmlspecialchars($datosMedico['correo'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label>Especialidades</label>
                    <input type="text" readonly value="<?= htmlspecialchars($datosMedico['especialidades'] ?? 'Sin especialidades asignadas') ?>">
                </div>
                <p class="hint">Estos datos los administra el personal administrativo de la clínica.</p>
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