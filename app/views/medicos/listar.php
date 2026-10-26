<?php $filtro = $filtro ?? null; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de médicos - Clínica Vida Salud</title>
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

    <?php $activo = 'medicos'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Listado de médicos</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <div class="panel-box panel-azul">
                <div class="panel-header">
                    <h4>Médicos registrados (<?= (int)$pag['total'] ?>)</h4>
                    <div class="panel-filter">
                        <form method="GET" action="index.php" class="panel-filter">
                            <input type="hidden" name="route" value="medicos">
                            <label for="filtro-esp">Especialidad:</label>
                            <select id="filtro-esp" name="especialidad" class="form-select" onchange="this.form.submit()">
                                <option value="">Todas las especialidades</option>
                                <?php foreach ($especialidades as $e): ?>
                                    <option value="<?= (int)$e['id_especialidad'] ?>"
                                        <?= $filtro === (int)$e['id_especialidad'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($filtro !== null): ?>
                                <a href="index.php?route=medicos" class="link-volver">Quitar filtro</a>
                            <?php endif; ?>
                        </form>
                        <?php if (Permisos::tiene('medicos.crear')): ?>
                     <a href="index.php?route=medicos_crear" class="btn btn-main">+ Nuevo médico</a>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <?php $puedeGestionar = Permisos::tiene('medicos.gestionar'); ?>
                    <?php if (empty($medicos)): ?>
                        <p class="placeholder-text">
                            <?= $filtro !== null ? 'No hay médicos con esa especialidad.' : 'Aún no hay médicos registrados.' ?>
                        </p>
                    <?php else: ?>
                        <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Código</th>
                                    <th>Nombres y apellidos</th>
                                    <th>Reg. profesional</th>
                                    <th>Especialidades</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <?php if ($puedeGestionar): ?><th>Acciones</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicos as $i => $m): ?>
                                    <tr>
                                        <td><?= (int)$pag['desde'] + $i ?></td>
                                        <td><?= htmlspecialchars($m['codigo']) ?></td>
                                        <td>Dr(a). <?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos']) ?></td>
                                        <td><?= htmlspecialchars($m['registro_profesional']) ?></td>
                                        <td>
                                            <?php if (!empty($m['especialidades'])): ?>
                                                <?php foreach (explode('|', $m['especialidades']) as $esp): ?>
                                                    <span class="tag"><?= htmlspecialchars($esp) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($m['telefono']) ?></td>
                                        <td><?= htmlspecialchars($m['correo']) ?></td>
                                        <td>
                                            <span class="badge-estado <?= $m['estado'] === 'Activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                                <?= htmlspecialchars($m['estado']) ?>
                                            </span>
                                        </td>
                                        <?php if ($puedeGestionar): ?>
                                        <td>
                                            <a href="index.php?route=medico_ver&id_medico=<?= (int)$m['id_medico'] ?>"
                                               class="btn-editar" style="margin-right:6px">Ver información</a>
                                            <form action="index.php?route=medicos_estado" method="POST" style="display:inline"
                                                  onsubmit="return confirm('<?= $m['estado'] === 'Activo'
                                                      ? '¿Desactivar al médico? Sus horarios dejarán de ofrecerse al programar citas y desaparecerán del calendario; sus citas existentes se conservan.'
                                                      : '¿Activar al médico? Sus horarios volverán a estar disponibles para reservar.' ?>');">
                                                <input type="hidden" name="id_medico" value="<?= (int)$m['id_medico'] ?>">
                                                <?php if ($filtro !== null): ?>
                                                    <input type="hidden" name="especialidad" value="<?= (int)$filtro ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="<?= $m['estado'] === 'Activo' ? 'btn btn-danger' : 'btn btn-main' ?>"
                                                        style="padding:5px 10px;font-size:12px">
                                                    <?= $m['estado'] === 'Activo' ? 'Desactivar' : 'Activar' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <?php endif; ?>
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
</body>
</html>