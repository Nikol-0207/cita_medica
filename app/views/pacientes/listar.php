<?php
$esMedico   = $esMedico ?? false;
$busqueda   = $busqueda ?? '';
$hayFiltros = $hayFiltros ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esMedico ? 'Mis pacientes' : 'Listado de pacientes' ?> - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
        .filtro-busqueda { display: flex; gap: 6px; align-items: center; }
        .filtro-busqueda input[type="text"] {
            padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; min-width: 220px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'pacientes'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title"><?= $esMedico ? 'Mis pacientes' : 'Listado de pacientes' ?></h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <div class="panel-box panel-azul">
                <div class="panel-header">
                    <h4><?= $esMedico ? 'Pacientes con citas conmigo' : 'Pacientes registrados' ?> (<?= (int)$pag['total'] ?>)</h4>

                    <div class="panel-filter">
                        <form method="GET" action="index.php" class="panel-filter" id="form-busqueda">
                            <input type="hidden" name="route" value="pacientes">
                            <span class="filtro-busqueda">
                                <input type="text" name="busqueda" id="busqueda" value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>"
                                       placeholder="Buscar por nombre, apellido, CI o código" autocomplete="off">
                                <button type="submit" class="btn btn-main">Buscar</button>
                            </span>
                            <?php if ($hayFiltros): ?>
                                <a href="index.php?route=pacientes" class="link-volver">Quitar filtros</a>
                            <?php endif; ?>
                        </form>

                        <?php if (Permisos::tiene('pacientes.crear')): ?>
                        <a href="index.php?route=pacientes_crear" class="btn btn-main">+ Nuevo paciente</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <?php if (empty($pacientes)): ?>
                        <p class="placeholder-text">
                            <?php if ($hayFiltros): ?>
                                No hay pacientes que coincidan con "<?= htmlspecialchars($busqueda) ?>".
                            <?php else: ?>
                                <?= $esMedico ? 'Todavía no tiene pacientes con citas registradas.' : 'Aún no hay pacientes registrados.' ?>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Código</th>
                                    <th>Nombres y apellidos</th>
                                    <th>CI</th>
                                    <th>Celular</th>
                                    <th>Correo</th>
                                    <th>Grupo sanguíneo</th>
                                    <?php if ($esMedico): ?>
                                    <th>Mis citas</th>
                                    <th>Próxima cita</th>
                                    <?php endif; ?>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pacientes as $i => $p): ?>
                                    <tr>
                                        <td><?= (int)$pag['desde'] + $i ?></td>
                                        <td><?= htmlspecialchars($p['codigo']) ?></td>
                                        <td><?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?></td>
                                        <td><?= htmlspecialchars($p['ci']) ?></td>
                                        <td><?= htmlspecialchars($p['telefono']) ?></td>
                                        <td><?= htmlspecialchars($p['correo'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($p['tipo_sangre'] ?? '—') ?></td>
                                        <?php if ($esMedico): ?>
                                        <td><?= (int)($p['total_citas'] ?? 0) ?></td>
                                        <td><?= !empty($p['proxima_fecha']) ? date('d/m/Y', strtotime($p['proxima_fecha'])) : '—' ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge-estado <?= $p['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                                <?= htmlspecialchars($p['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="index.php?route=paciente_ver&id_paciente=<?= (int)$p['id_paciente'] ?>"
                                               class="btn-editar">Ver información</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <?php require __DIR__ . '/../layouts/paginacion.php'; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
    // Filtra mientras escribe (con una pausa para no disparar en cada tecla).
    (function () {
        var input = document.getElementById('busqueda');
        var form  = document.getElementById('form-busqueda');
        var timer = null;
        if (!input || !form) { return; }
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { form.submit(); }, 450);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(timer); form.submit(); }
        });
    })();
</script>
</body>
</html>