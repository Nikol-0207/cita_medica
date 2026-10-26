<?php
// La foto se sirve desde la base de datos (Neon) para que sobreviva a los
// redeploys de Render. Si el usuario no tiene foto, la ruta devuelve un SVG.
$rutaFoto = 'index.php?route=avatar';
?>
<a href="index.php?route=perfil" class="top-profile" title="Mi perfil">
    <span class="top-profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'admin') ?></span>
    <img src="<?= $rutaFoto ?>" alt="Foto de perfil" class="top-profile-avatar">
</a>
