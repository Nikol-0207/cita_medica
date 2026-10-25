<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de recepcionistas - Clínica Vida Salud</title>
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

    <?php $activo = 'recepcionistas'; require __DIR__ . '/../../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Listado de recepcionistas</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <div class="panel-box panel-azul">
                <div class="panel-header">
                    <h4>Recepcionistas registrados (<?= (int)$pag['total'] ?>)</h4>
                    <a href="index.php?route=recepcionistas_crear" class="btn btn-main">+ Nuevo recepcionista</a>
                </div>
                <div class="panel-body">
                    <?php if (empty($recepcionistas)): ?>
                        <p class="placeholder-text">Aún no hay recepcionistas registrados.</p>
                    <?php else: ?>
                        <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombres y apellidos</th>
                                    <th>CI</th>
                                    <th>Celular</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Registrado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recepcionistas as $i => $r): ?>
                                    <tr>
                                        <td><?= (int)$pag['desde'] + $i ?></td>
                                        <td><?= htmlspecialchars($r['nombres'] . ' ' . $r['apellidos']) ?></td>
                                        <td><?= htmlspecialchars($r['ci']) ?></td>
                                        <td><?= htmlspecialchars($r['celular']) ?></td>
                                        <td><?= htmlspecialchars($r['correo']) ?></td>
                                        <td>
                                            <span class="badge-estado <?= $r['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                                <?= htmlspecialchars($r['estado']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha_registro']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <?php require __DIR__ . '/../../layouts/paginacion.php'; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>