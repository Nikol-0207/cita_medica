<?php
// Uso: $activo = 'dashboard'; require .../admin_sidebar.php;
$act     = fn($r) => (($activo ?? '') === $r) ? ' active' : '';
$grupo   = fn($p) => str_starts_with($activo ?? '', $p);
$inicio  = Permisos::inicio();
$esMedico = Permisos::esMedico();
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <svg class="sidebar-logo-icon" viewBox="0 0 100 100" width="34" height="34" xmlns="http://www.w3.org/2000/svg">
            <path d="M 38 18 C 38 14, 42 12, 50 12 C 58 12, 62 14, 62 18 L 66 18 C 68 18, 70 20, 70 22 L 70 26 L 30 26 L 30 22 C 30 20, 32 18, 34 18 Z" fill="var(--primary)"/>
            <path d="M 30 22 L 18 22 C 15 22, 12 25, 12 28 L 12 88 C 12 91, 15 94, 18 94 L 72 94 C 75 94, 78 91, 78 88 L 78 72" fill="none" stroke="var(--primary)" stroke-width="4.5" stroke-linecap="round"/>
            <path d="M 70 22 L 82 22 C 85 22, 88 25, 88 28 L 88 42" fill="none" stroke="var(--primary)" stroke-width="4.5" stroke-linecap="round"/>
            <path d="M 68 44 C 58 35, 46 44, 46 54 C 46 68, 64 80, 68 84 C 72 80, 90 68, 90 54 C 90 44, 78 35, 68 44 Z" fill="#ffffff" stroke="#e11d48" stroke-width="4.5" stroke-linejoin="round"/>
            <path d="M 52 64 L 62 64 L 66 57 L 72 73 L 77 64 L 95 64" fill="none" stroke="var(--neutral)" stroke-width="3.5" stroke-linecap="round"/>
        </svg>
        <span class="brand-text">Clínica Vida Salud</span>
    </div>

    <div class="user-panel"><span class="user-role-label"><?= htmlspecialchars($_SESSION['user_role'] ?? '') ?></span></div>

    <nav class="sidebar-menu">
        <?php if (Permisos::tiene('dashboard')): ?>
        <a href="index.php?route=<?= $inicio ?>" class="menu-item<?= $act('dashboard') ?>"><span class="icon">📊</span><span>Dashboard</span></a>
        <?php endif; ?>

        <?php if (Permisos::tiene('recepcionistas.gestionar')): ?>
            <a href="index.php?route=recepcionistas" class="menu-item<?= $grupo('recepcionistas') ? ' active' : '' ?>"><span class="icon">👤</span><span>Recepcionistas</span></a>
            <?php if ($grupo('recepcionistas')): ?>
            <div class="submenu">
                <a href="index.php?route=recepcionistas_crear" class="menu-item<?= $act('recepcionistas_crear') ?>"><span>○ Registrar recepcionista</span></a>
                <a href="index.php?route=recepcionistas" class="menu-item<?= $act('recepcionistas') ?>"><span>○ Listado de recepcionistas</span></a>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (Permisos::tiene('pacientes.ver')): ?>
            <a href="index.php?route=pacientes" class="menu-item<?= $grupo('pacientes') ? ' active' : '' ?>"><span class="icon">👥</span><span><?= $esMedico ? 'Mis pacientes' : 'Pacientes' ?></span></a>
            <?php if ($grupo('pacientes') && Permisos::tiene('pacientes.crear')): ?>
            <div class="submenu">
                <a href="index.php?route=pacientes_crear" class="menu-item<?= $act('pacientes_crear') ?>"><span>○ Registrar paciente</span></a>
                <a href="index.php?route=pacientes" class="menu-item<?= $act('pacientes') ?>"><span>○ Listado de pacientes</span></a>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (Permisos::tiene('medicos.ver')): ?>
            <a href="index.php?route=medicos" class="menu-item<?= $grupo('medicos') ? ' active' : '' ?>"><span class="icon">👨‍⚕️</span><span>Médicos</span></a>
            <?php if ($grupo('medicos') && Permisos::tiene('medicos.crear')): ?>
            <div class="submenu">
                <a href="index.php?route=medicos_crear" class="menu-item<?= $act('medicos_crear') ?>"><span>○ Registrar médico</span></a>
                <a href="index.php?route=medicos" class="menu-item<?= $act('medicos') ?>"><span>○ Listado de médicos</span></a>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (Permisos::tiene('especialidades.ver')): ?>
            <a href="index.php?route=especialidades" class="menu-item<?= $act('especialidades') ?>"><span class="icon">🩺</span><span>Especialidades</span></a>
        <?php endif; ?>

        <?php if (Permisos::tiene('horarios.ver')): ?>
            <a href="index.php?route=horarios" class="menu-item<?= $grupo('horarios') ? ' active' : '' ?>"><span class="icon">📅</span><span><?= $esMedico ? 'Mi horario' : 'Horarios de Atención' ?></span></a>
        <?php endif; ?>

        <?php if (Permisos::tiene('citas.ver')): ?>
            <a href="index.php?route=citas" class="menu-item<?= $grupo('citas') ? ' active' : '' ?>"><span class="icon">📋</span><span><?= $esMedico ? 'Mi agenda' : (Permisos::esPaciente() ? 'Mis citas' : 'Citas Médicas') ?></span></a>
            <?php if ($grupo('citas') && Permisos::tiene('citas.crear')): ?>
            <div class="submenu">
                <a href="index.php?route=citas_crear" class="menu-item<?= $act('citas_crear') ?>"><span>○ Programar cita</span></a>
                <a href="index.php?route=citas" class="menu-item<?= $act('citas') ?>"><span>○ Listado de citas</span></a>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (Permisos::tiene('historial.ver')): ?>
            <a href="index.php?route=historial" class="menu-item<?= $act('historial') ?>"><span class="icon">📁</span><span>Historial de Citas</span></a>
        <?php endif; ?>

        <?php if (Permisos::tiene('configuraciones')): ?>
            <a href="index.php?route=configuraciones" class="menu-item<?= $act('configuraciones') ?>"><span class="icon">⚙️</span><span>Configuración</span></a>
        <?php endif; ?>

        <a href="index.php?route=logout" class="menu-item btn-logout"><span class="icon">🚪</span><span>Cerrar sesión</span></a>
    </nav>
</aside>