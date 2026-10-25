<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración de Colores - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary']) ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg']) ?>;
            --tertiary: <?= htmlspecialchars($colores['tertiary']) ?>;
            --neutral: <?= htmlspecialchars($colores['neutral']) ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main']) ?>;
        }
        .color-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .color-item {
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .color-item label {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }
        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .color-input-wrapper input[type="color"] {
            -webkit-appearance: none;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 6px;
            cursor: pointer;
            background: none;
        }
        .color-input-wrapper input[type="color"]::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        .color-input-wrapper input[type="color"]::-webkit-color-swatch {
            border: 1px solid #CBD5E1;
            border-radius: 6px;
        }
        .hex-code {
            font-family: monospace;
            font-size: 13px;
            color: #64748B;
            width: 70px;
        }
        .preset-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .btn-preset {
            padding: 10px 18px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            background: #fff;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-preset:hover {
            border-color: var(--primary);
            background: #F1F5F9;
        }
        .circle-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
        }
        .btn-save-colors {
            margin-top: 25px;
            background-color: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-save-colors:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <aside class="sidebar" style="background-color: var(--sidebar-bg);">
        <div class="sidebar-header">
            <svg class="sidebar-logo-icon" viewBox="0 0 100 100" width="34" height="34">
                <path d="M 38 18 C 38 14, 42 12, 50 12 C 58 12, 62 14, 62 18 L 66 18 C 68 18, 70 20, 70 22 L 70 26 L 30 26 L 30 22 C 30 20, 32 18, 34 18 Z" fill="var(--primary)"/>
                <path d="M 30 22 L 18 22 C 15 22, 12 25, 12 28 L 12 88 C 12 91, 15 94, 18 94 L 72 94 C 75 94, 78 91, 78 88 L 78 72" fill="none" stroke="var(--primary)" stroke-width="4.5" stroke-linecap="round"/>
                <path d="M 70 22 L 82 22 C 85 22, 88 25, 88 28 L 88 42" fill="none" stroke="var(--primary)" stroke-width="4.5" stroke-linecap="round"/>
                <path d="M 68 44 C 58 35, 46 44, 46 54 C 46 68, 64 80, 68 84 C 72 80, 90 68, 90 54 C 90 44, 78 35, 68 44 Z" fill="#ffffff" stroke="#e11d48" stroke-width="4.5" stroke-linejoin="round"/>
                <path d="M 52 64 L 62 64 L 66 57 L 72 73 L 77 64 L 95 64" fill="none" stroke="var(--neutral)" stroke-width="3.5" stroke-linecap="round"/>
            </svg>
            <span class="brand-text">Clínica Vida Salud</span>
        </div>
        <nav class="sidebar-menu">
            <a href="index.php?route=admin" class="menu-item"><span class="icon">📊</span><span>Dashboard</span></a>
            <a href="index.php?route=configuraciones" class="menu-item active"><span class="icon">⚙️</span><span>Configuración</span></a>
            <a href="index.php?route=logout" class="menu-item btn-logout"><span class="icon">🚪</span><span>Cerrar sesión</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-nav">
           <div class="top-title">Sistema de Gestión de Citas Médicas</div>
          <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <?php if (!empty($mensaje)): ?>
                <div style="background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
                    ✓ <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <!-- BOTONES DE TEMAS RÁPIDOS -->
            <div class="panel-box">
                <h4 style="margin-bottom: 15px; color: var(--primary);">Plantillas Rápidas</h4>
                <div class="preset-buttons">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="preset" value="saludcore">
                        <button type="submit" class="btn-preset">
                            <span class="circle-dot" style="background: #0D9488;"></span> SaludCore (Oficial)
                        </button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="preset" value="azul">
                        <button type="submit" class="btn-preset">
                            <span class="circle-dot" style="background: #1565C0;"></span> Azul Médico
                        </button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="preset" value="dark">
                        <button type="submit" class="btn-preset">
                            <span class="circle-dot" style="background: #020617;"></span> Dark Clinic
                        </button>
                    </form>
                </div>
            </div>

            <!-- FORMULARIO LIBRE DE SELECCIÓN DE COLOR -->
            <div class="panel-box">
                <h4 style="margin-bottom: 8px; color: var(--primary);">Selector Libre de Colores</h4>
                <p style="color: #64748B; font-size: 14px; margin-bottom: 15px;">
                    Haz clic en cada recuadro para escoger el color exacto que desees para la plataforma:
                </p>

                <form action="index.php?route=configuraciones" method="POST">
                    <div class="color-picker-grid">
                        
                        <div class="color-item">
                            <div>
                                <label for="primary">Color Principal (Primary)</label>
                                <div style="font-size: 12px; color: #888;">Botones, bordes activos y logo</div>
                            </div>
                            <div class="color-input-wrapper">
                                <span class="hex-code" id="hex-primary"><?= htmlspecialchars($colores['primary']) ?></span>
                                <input type="color" id="primary" name="primary" value="<?= htmlspecialchars($colores['primary']) ?>" onchange="updateHex(this, 'hex-primary')">
                            </div>
                        </div>

                        <div class="color-item">
                            <div>
                                <label for="sidebar_bg">Fondo Sidebar</label>
                                <div style="font-size: 12px; color: #888;">Barra lateral de navegación</div>
                            </div>
                            <div class="color-input-wrapper">
                                <span class="hex-code" id="hex-sidebar"><?= htmlspecialchars($colores['sidebar_bg']) ?></span>
                                <input type="color" id="sidebar_bg" name="sidebar_bg" value="<?= htmlspecialchars($colores['sidebar_bg']) ?>" onchange="updateHex(this, 'hex-sidebar')">
                            </div>
                        </div>

                        <div class="color-item">
                            <div>
                                <label for="tertiary">Color Terciario</label>
                                <div style="font-size: 12px; color: #888;">Tarjetas de médicos y acentos</div>
                            </div>
                            <div class="color-input-wrapper">
                                <span class="hex-code" id="hex-tertiary"><?= htmlspecialchars($colores['tertiary']) ?></span>
                                <input type="color" id="tertiary" name="tertiary" value="<?= htmlspecialchars($colores['tertiary']) ?>" onchange="updateHex(this, 'hex-tertiary')">
                            </div>
                        </div>

                        <div class="color-item">
                            <div>
                                <label for="neutral">Texto Neutro / Iconos</label>
                                <div style="font-size: 12px; color: #888;">Subtítulos y etiquetas secundarias</div>
                            </div>
                            <div class="color-input-wrapper">
                                <span class="hex-code" id="hex-neutral"><?= htmlspecialchars($colores['neutral']) ?></span>
                                <input type="color" id="neutral" name="neutral" value="<?= htmlspecialchars($colores['neutral']) ?>" onchange="updateHex(this, 'hex-neutral')">
                            </div>
                        </div>

                        <div class="color-item">
                            <div>
                                <label for="bg_main">Fondo General</label>
                                <div style="font-size: 12px; color: #888;">Fondo del contenido principal</div>
                            </div>
                            <div class="color-input-wrapper">
                                <span class="hex-code" id="hex-bg"><?= htmlspecialchars($colores['bg_main']) ?></span>
                                <input type="color" id="bg_main" name="bg_main" value="<?= htmlspecialchars($colores['bg_main']) ?>" onchange="updateHex(this, 'hex-bg')">
                            </div>
                        </div>

                        <div class="color-item">
                            <div>
                                <label for="card_stat_2">Tarjeta Pacientes</label>
                                <div style="font-size: 12px; color: #888;">Fondo de la tarjeta 2</div>
                            </div>
                            <div class="color-input-wrapper">
                                <span class="hex-code" id="hex-card2"><?= htmlspecialchars($colores['card_stat_2']) ?></span>
                                <input type="color" id="card_stat_2" name="card_stat_2" value="<?= htmlspecialchars($colores['card_stat_2']) ?>" onchange="updateHex(this, 'hex-card2')">
                            </div>
                        </div>

                    </div>

                    <button type="submit" class="btn-save-colors">
                        💾 Guardar y Aplicar Colores
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    function updateHex(input, targetId) {
        document.getElementById(targetId).textContent = input.value.toUpperCase();
    }
</script>

</body>
</html>