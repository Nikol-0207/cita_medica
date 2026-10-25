<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Vida Salud - Portal Institucional</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- 2. Navbar -->
    <!-- Navbar con Isotipo Vectorial -->
<div class="navbar">
    <div class="logo">
        <svg class="logo-icon" viewBox="0 0 100 100" width="38" height="38" xmlns="http://www.w3.org/2000/svg">
            <!-- Clip superior del portapapeles -->
            <path d="M 38 18 C 38 14, 42 12, 50 12 C 58 12, 62 14, 62 18 L 66 18 C 68 18, 70 20, 70 22 L 70 26 L 30 26 L 30 22 C 30 20, 32 18, 34 18 Z" fill="#007bc7"/>
            
            <!-- Cuerpo del portapapeles -->
            <path d="M 30 22 L 18 22 C 15 22, 12 25, 12 28 L 12 88 C 12 91, 15 94, 18 94 L 72 94 C 75 94, 78 91, 78 88 L 78 72" fill="none" stroke="#007bc7" stroke-width="4.5" stroke-linecap="round"/>
            <path d="M 70 22 L 82 22 C 85 22, 88 25, 88 28 L 88 42" fill="none" stroke="#007bc7" stroke-width="4.5" stroke-linecap="round"/>
            
            <!-- Líneas de la ficha médica -->
            <line x1="24" y1="38" x2="68" y2="38" stroke="#007bc7" stroke-width="4" stroke-linecap="round"/>
            <line x1="24" y1="52" x2="48" y2="52" stroke="#007bc7" stroke-width="4" stroke-linecap="round"/>
            <line x1="24" y1="66" x2="40" y2="66" stroke="#007bc7" stroke-width="4" stroke-linecap="round"/>
            <line x1="24" y1="80" x2="52" y2="80" stroke="#007bc7" stroke-width="4" stroke-linecap="round"/>
            
            <!-- Corazón médico -->
            <path d="M 68 44 C 58 35, 46 44, 46 54 C 46 68, 64 80, 68 84 C 72 80, 90 68, 90 54 C 90 44, 78 35, 68 44 Z" fill="#ffffff" stroke="#cf102d" stroke-width="4.5" stroke-linejoin="round"/>
            
            <!-- Línea de pulso cardíaco -->
            <path d="M 52 64 L 62 64 L 66 57 L 72 73 L 77 64 L 95 64" fill="none" stroke="#4b5563" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>Clínica Vida Salud</span>
    </div>
    
    <div class="nav-links">
        <a href="#especialidades">Especialidades</a>
        <a href="javascript:void(0)" onclick="openModal()">Ingresar</a>
    </div>
</div>

    <!-- 3. Hero Banner -->
    <section class="hero">
        <h1>Comprometidos con tu Bienestar</h1>
        <p>Conoce nuestras áreas de atención médica y reserva tu cita con los mejores profesionales.</p>
    </section>

    <!-- 4. Especialidades Disponibles -->
    <div class="container" id="especialidades">
        <h2 class="section-title">Nuestras Especialidades Médicas</h2>
        <div class="cards-grid">
            <?php if (!empty($especialidades)): ?>
                <?php foreach ($especialidades as $esp): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($esp['nombre']) ?></h3>
                        <p><?= htmlspecialchars($esp['descripcion'] ?? 'Atención médica profesional garantizada.') ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; grid-column: 1 / -1;">No hay especialidades registradas actualmente.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. Modal de Inicio de Sesión -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">&times;</button>
            <div class="modal-header">
                <h2>Portal de Acceso</h2>
                <p style="font-size: 13px; color: #666; margin-top: 4px;">Ingrese sus credenciales de usuario</p>
            </div>
            <div class="modal-body">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form action="index.php?route=login" method="POST">
                    <div class="form-group">
                        <label for="identificador">Usuario, Correo o Carnet (CI):</label>
                        <input type="text" id="identificador" name="identificador" placeholder="Ej. admin, 8345912 o correo" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn-submit">Conectar</button>
                </form>
            </div>
            <div class="modal-footer">
                ¿Problemas para ingresar? Contacte a soporte de recepción
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('loginModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Cerrar si hace clic fuera del modal
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target === modal) {
                closeModal();
            }
        };

        // Si hubo error de login o se pasa open_modal por URL, abrir modal automáticamente
        <?php if (!empty($error) || isset($_GET['open_modal'])): ?>
            openModal();
        <?php endif; ?>
    </script>
</body>
</html>