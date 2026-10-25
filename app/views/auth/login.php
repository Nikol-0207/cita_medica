<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - Clínica Vida Salud</title>
</head>
<body>
    <h2>Clínica Vida Salud - Acceso al Sistema</h2>
    <?php if (isset($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form action="index.php?route=login" method="POST">
        <div>
            <label>Correo Electrónico:</label><br>
            <input type="email" name="correo" required>
        </div>
        <br>
        <div>
            <label>Contraseña:</label><br>
            <input type="password" name="password" required>
        </div>
        <br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>