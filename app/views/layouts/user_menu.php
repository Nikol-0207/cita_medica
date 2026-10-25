<?php
// Foto de perfil: si no existe el archivo, se muestra el icono por defecto
$rutaFoto = null;
$fotoSesion = $_SESSION['user_photo'] ?? null;
if ($fotoSesion && $fotoSesion !== 'default_avatar.png') {
    $archivo = basename($fotoSesion);
    if (is_file(ROOT_PATH . '/public/uploads/perfiles/' . $archivo)) {
        $rutaFoto = 'uploads/perfiles/' . rawurlencode($archivo);
    }
}
?>
<a href="index.php?route=perfil" class="top-profile" title="Mi perfil">
    <span class="top-profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'admin') ?></span>
    <?php if ($rutaFoto): ?>
        <img src="<?= $rutaFoto ?>" alt="Foto de perfil" class="top-profile-avatar">
    <?php else: ?>
        <span class="top-profile-avatar top-profile-default">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/>
            </svg>
        </span>
    <?php endif; ?>
    </a>