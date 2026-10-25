<?php
$reabrir = $reabrir ?? null;
$puedeGestionar = Permisos::tiene('especialidades.gestionar');
$attr = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Especialidades - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= $attr($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= $attr($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= $attr($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= $attr($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'especialidades'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Especialidades</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= $attr($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError) && empty($reabrir)): ?>
                <div class="alert-box alert-err"><?= $attr($mensajeError) ?></div>
            <?php endif; ?>

            <div class="panel-box panel-azul">
                <div class="panel-header">
                    <h4>Especialidades activas (<?= (int)$pag['total'] ?>)</h4>
                    <?php if ($puedeGestionar): ?>
                         <button type="button" id="btn-nueva" class="btn btn-main">+ Nueva especialidad</button>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <?php if (empty($especialidades)): ?>
                        <p class="placeholder-text">Aún no hay especialidades activas. Crea la primera con el botón de arriba.</p>
                    <?php else: ?>
                        <div class="tabla-wrap">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th style="width:50px">#</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <?php if ($puedeGestionar): ?>
                                    <th style="width:120px">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($especialidades as $i => $e): ?>
                                    <tr>
                                        <td><?= (int)$pag['desde'] + $i ?></td>
                                        <td><strong><?= $attr($e['nombre']) ?></strong></td>
                                        <td>
                                            <?php if (!empty($e['descripcion'])): ?>
                                                <?= $attr($e['descripcion']) ?>
                                            <?php else: ?>
                                                <span class="muted">Sin descripción</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($puedeGestionar): ?>
                                            <button type="button" class="btn-editar"
                                                    data-id="<?= (int)$e['id_especialidad'] ?>"
                                                    data-nombre="<?= $attr($e['nombre']) ?>"
                                                    data-descripcion="<?= $attr($e['descripcion'] ?? '') ?>">
                                                ✏️ Editar
                                            </button>
                                            <?php else: ?>
                                            <span class="muted">Solo lectura</span>
                                            <?php endif; ?>
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

<!-- MODAL: CREAR -->
<dialog id="modal-crear" class="modal-dlg">
    <form method="POST" action="index.php?route=especialidades_guardar">
        <div class="dlg-header">
            <h3>Nueva especialidad</h3>
            <button type="button" class="dlg-close" data-cerrar aria-label="Cerrar">&times;</button>
        </div>
        <div class="dlg-body">
            <div class="alert-box alert-err dlg-error" hidden></div>
            <div class="campo" style="margin-bottom:16px;">
                <label for="crear-nombre">Nombre *</label>
                <input type="text" id="crear-nombre" name="nombre" required maxlength="100" autocomplete="off">
            </div>
            <div class="campo">
                <label for="crear-descripcion">Descripción</label>
                <textarea id="crear-descripcion" name="descripcion" rows="3" maxlength="500"></textarea>
            </div>
        </div>
        <div class="dlg-footer">
            <button type="button" class="btn btn-sec" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-main">Guardar</button>
        </div>
    </form>
</dialog>

<!-- MODAL: EDITAR -->
<dialog id="modal-editar" class="modal-dlg">
    <form method="POST" action="index.php?route=especialidades_actualizar">
        <input type="hidden" name="id" id="editar-id">
        <div class="dlg-header">
            <h3>Editar especialidad</h3>
            <button type="button" class="dlg-close" data-cerrar aria-label="Cerrar">&times;</button>
        </div>
        <div class="dlg-body">
            <div class="alert-box alert-err dlg-error" hidden></div>
            <div class="campo" style="margin-bottom:16px;">
                <label for="editar-nombre">Nombre *</label>
                <input type="text" id="editar-nombre" name="nombre" required maxlength="100" autocomplete="off">
            </div>
            <div class="campo">
                <label for="editar-descripcion">Descripción</label>
                <textarea id="editar-descripcion" name="descripcion" rows="3" maxlength="500"></textarea>
            </div>
        </div>
        <div class="dlg-footer">
            <button type="button" class="btn btn-sec" data-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-main">Guardar cambios</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const modalCrear  = document.getElementById('modal-crear');
    const modalEditar = document.getElementById('modal-editar');

    function mostrarError(modal, mensaje) {
        const box = modal.querySelector('.dlg-error');
        if (mensaje) { box.textContent = mensaje; box.hidden = false; }
        else { box.hidden = true; }
    }

    function abrirCrear(datos, error) {
        document.getElementById('crear-nombre').value = datos ? datos.nombre : '';
        document.getElementById('crear-descripcion').value = datos ? datos.descripcion : '';
        mostrarError(modalCrear, error);
        modalCrear.showModal();
        document.getElementById('crear-nombre').focus();
    }

    function abrirEditar(datos, error) {
        document.getElementById('editar-id').value = datos.id;
        document.getElementById('editar-nombre').value = datos.nombre;
        document.getElementById('editar-descripcion').value = datos.descripcion;
        mostrarError(modalEditar, error);
        modalEditar.showModal();
        document.getElementById('editar-nombre').focus();
    }

    document.getElementById('btn-nueva').addEventListener('click', () => abrirCrear(null, null));

    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', () => abrirEditar({
            id: btn.dataset.id,
            nombre: btn.dataset.nombre,
            descripcion: btn.dataset.descripcion
        }, null));
    });

    // Cerrar con los botones X / Cancelar y al hacer clic fuera del cuadro
    document.querySelectorAll('[data-cerrar]').forEach(b =>
        b.addEventListener('click', () => b.closest('dialog').close()));
    document.querySelectorAll('dialog').forEach(d =>
        d.addEventListener('click', e => { if (e.target === d) d.close(); }));

    // Si hubo un error al guardar, se reabre el modal con lo que escribió
    const reabrir = <?= json_encode($reabrir ? [
        'modal'       => $reabrir['modal'] ?? '',
        'id'          => $reabrir['id'] ?? '',
        'nombre'      => $reabrir['nombre'] ?? '',
        'descripcion' => $reabrir['descripcion'] ?? '',
        'error'       => $mensajeError ?? '',
    ] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (reabrir) {
        if (reabrir.modal === 'crear')  abrirCrear(reabrir, reabrir.error);
        if (reabrir.modal === 'editar') abrirEditar(reabrir, reabrir.error);
    }
})();
</script>
</body>
</html>