<?php
// Espera: $paciente, $contactos, $puedeEditar, $old, $mensajeExito, $mensajeError, $colores
$editable = !empty($puedeEditar);
$esMedico = Permisos::esMedico();

if (is_array($old) && array_key_exists('contactos', $old)) {
    $contactos = $old['contactos'];
}
$contactos = is_array($contactos) ? $contactos : [];

$v = function ($k, $default = '') use ($old) {
    $valor = is_array($old) ? ($old[$k] ?? $default) : $default;
    return htmlspecialchars((string)$valor, ENT_QUOTES);
};
$fechaLegible = function ($fecha) {
    $ts = !empty($fecha) ? strtotime($fecha) : false;
    return $ts ? date('d/m/Y', $ts) : '—';
};
$fechaNac = !empty($paciente['fecha_nacimiento']) ? substr((string)$paciente['fecha_nacimiento'], 0, 10) : '';
$sangre   = is_array($old) ? ($old['tipo_sangre'] ?? '') : ($paciente['tipo_sangre'] ?? '');
$ro       = $editable ? '' : 'readonly';
$dis      = $editable ? '' : 'disabled';

if ($editable && !$contactos) {
    $contactos = [['nombre' => '', 'parentesco' => '', 'telefono' => '']];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del paciente - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
        .campo input[readonly], .campo textarea[readonly] { background: #F8FAFC; color: #475569; }
        .contacto-fila {
            display: grid; grid-template-columns: 2fr 1fr 1.3fr auto; gap: 10px;
            align-items: end; margin-bottom: 10px;
        }
        .contacto-fila .campo { margin: 0; }
        .contacto-fila .btn { padding: 9px 12px; white-space: nowrap; }
        @media (max-width: 760px) {
            .contacto-fila { grid-template-columns: 1fr; }
        }
        .aviso-lectura {
            background: #F1F5F9; border-left: 4px solid var(--primary);
            padding: 12px 14px; border-radius: 4px; color: #475569;
            font-size: 13px; margin-bottom: 18px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Información del paciente</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>

            <?php if (!$editable): ?>
                <div class="aviso-lectura">
                    <?= $esMedico
                        ? 'Vista de solo lectura: puede consultar los datos personales, el contacto de emergencia y la información médica del paciente.'
                        : 'Vista de solo lectura.' ?>
                </div>
            <?php endif; ?>

            <?php if ($editable): ?>
            <form action="index.php?route=paciente_actualizar" method="POST">
                <input type="hidden" name="id_paciente" value="<?= (int)$paciente['id_paciente'] ?>">
            <?php endif; ?>

                <div class="panel-box panel-azul">
                    <div class="panel-header">
                        <h4>Datos personales</h4>
                        <a href="index.php?route=pacientes" class="link-volver">← Volver al listado</a>
                    </div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="campo col-3">
                                <label>Código</label>
                                <input type="text" readonly value="<?= htmlspecialchars($paciente['codigo']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label>Estado</label>
                                <input type="text" readonly value="<?= htmlspecialchars($paciente['estado']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label>Fecha de registro</label>
                                <input type="text" readonly value="<?= $fechaLegible($paciente['fecha_registro']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label>Cuenta de usuario</label>
                                <input type="text" readonly
                                       value="<?= !empty($paciente['usuario']) ? htmlspecialchars($paciente['usuario']) : 'Sin cuenta vinculada' ?>">
                            </div>

                            <div class="campo col-6">
                                <label for="nombres">Nombres *</label>
                                <input type="text" id="nombres" name="nombres" required <?= $ro ?>
                                       value="<?= $v('nombres', $paciente['nombres']) ?>">
                            </div>
                            <div class="campo col-6">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required <?= $ro ?>
                                       value="<?= $v('apellidos', $paciente['apellidos']) ?>">
                            </div>

                            <div class="campo col-3">
                                <label for="ci">CI *</label>
                                <input type="text" id="ci" name="ci" required <?= $ro ?>
                                       value="<?= $v('ci', $paciente['ci']) ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="fecha_nacimiento">Fecha de nacimiento *</label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                                       max="<?= date('Y-m-d') ?>" <?= $ro ?>
                                       value="<?= $v('fecha_nacimiento', $fechaNac) ?>">
                            </div>
                            <div class="campo col-3">
                                <label for="sexo">Género</label>
                                <select id="sexo" name="sexo" <?= $dis ?>>
                                    <option value="M" <?= ($v('sexo', $paciente['sexo']) === 'M') ? 'selected' : '' ?>>MASCULINO</option>
                                    <option value="F" <?= ($v('sexo', $paciente['sexo']) === 'F') ? 'selected' : '' ?>>FEMENINO</option>
                                </select>
                            </div>
                            <div class="campo col-3">
                                <label for="nro_seguro">Nro de seguro</label>
                                <input type="text" id="nro_seguro" name="nro_seguro" <?= $ro ?>
                                       value="<?= $v('nro_seguro', $paciente['nro_seguro'] ?? '') ?>">
                            </div>

                            <div class="campo col-4">
                                <label for="telefono">Celular *</label>
                                <input type="text" id="telefono" name="telefono" required <?= $ro ?>
                                       value="<?= $v('telefono', $paciente['telefono']) ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="correo">Correo</label>
                                <input type="email" id="correo" name="correo" <?= $ro ?>
                                       value="<?= $v('correo', $paciente['correo'] ?? '') ?>">
                            </div>
                            <div class="campo col-4">
                                <label for="direccion">Dirección *</label>
                                <input type="text" id="direccion" name="direccion" required <?= $ro ?>
                                       value="<?= $v('direccion', $paciente['direccion']) ?>">
                            </div>

                            <div class="campo col-12">
                                <label for="observaciones">Observaciones</label>
                                <textarea id="observaciones" name="observaciones" rows="3" <?= $ro ?>><?= $v('observaciones', $paciente['observaciones'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel-box panel-azul">
                    <div class="panel-header"><h4>Información médica</h4></div>
                    <div class="panel-body">
                        <div class="form-grid">
                            <div class="campo col-4">
                                <label for="tipo_sangre">Grupo sanguíneo *</label>
                                <select id="tipo_sangre" name="tipo_sangre" required <?= $dis ?>>
                                    <option value="" <?= $sangre === '' ? 'selected' : '' ?> disabled>—</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $g): ?>
                                        <option value="<?= $g ?>" <?= $sangre === $g ? 'selected' : '' ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo col-8">
                                <label for="alergias">Alergias</label>
                                <input type="text" id="alergias" name="alergias" placeholder="Ninguna" <?= $ro ?>
                                       value="<?= $v('alergias', $paciente['alergias'] ?? '') ?>">
                            </div>
                            <div class="campo col-12">
                                <label>Última actualización de la información médica</label>
                                <input type="text" readonly
                                       value="<?= $fechaLegible($paciente['fecha_actualizacion_medica'] ?? null) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel-box panel-azul">
                    <div class="panel-header">
                        <h4>Contactos de emergencia</h4>
                        <?php if ($editable): ?>
                            <button type="button" class="btn btn-sec" id="agregar-contacto">+ Agregar contacto</button>
                        <?php endif; ?>
                    </div>
                    <div class="panel-body">
                        <?php if (!$contactos): ?>
                            <p class="placeholder-text">No hay contactos de emergencia registrados.</p>
                        <?php endif; ?>

                        <div id="lista-contactos">
                            <?php foreach ($contactos as $c): ?>
                                <div class="contacto-fila">
                                    <div class="campo">
                                        <label>Nombre *</label>
                                        <input type="text" name="contacto_nombre[]" <?= $ro ?>
                                               value="<?= htmlspecialchars($c['nombre'] ?? '') ?>">
                                    </div>
                                    <div class="campo">
                                        <label>Parentesco</label>
                                        <input type="text" name="contacto_parentesco[]" <?= $ro ?>
                                               value="<?= htmlspecialchars($c['parentesco'] ?? '') ?>">
                                    </div>
                                    <div class="campo">
                                        <label>Teléfono *</label>
                                        <input type="text" name="contacto_telefono[]" <?= $ro ?>
                                               value="<?= htmlspecialchars($c['telefono'] ?? '') ?>">
                                    </div>
                                    <?php if ($editable): ?>
                                        <button type="button" class="btn btn-danger quitar-contacto">Quitar</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($editable): ?>
                    <div class="form-acciones">
                        <a href="index.php?route=pacientes" class="btn btn-sec">Cancelar</a>
                        <button type="submit" class="btn btn-main">Guardar cambios</button>
                    </div>
                </form>
                <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($editable): ?>
<script>
(function () {
    var lista = document.getElementById('lista-contactos');
    var agregar = document.getElementById('agregar-contacto');
    if (!lista || !agregar) { return; }

    function crearFila() {
        var fila = document.createElement('div');
        fila.className = 'contacto-fila';
        fila.innerHTML =
            '<div class="campo"><label>Nombre *</label>' +
            '<input type="text" name="contacto_nombre[]"></div>' +
            '<div class="campo"><label>Parentesco</label>' +
            '<input type="text" name="contacto_parentesco[]"></div>' +
            '<div class="campo"><label>Teléfono *</label>' +
            '<input type="text" name="contacto_telefono[]"></div>' +
            '<button type="button" class="btn btn-danger quitar-contacto">Quitar</button>';
        return fila;
    }

    agregar.addEventListener('click', function () {
        lista.appendChild(crearFila());
    });

    lista.addEventListener('click', function (e) {
        var boton = e.target.closest('.quitar-contacto');
        if (!boton) { return; }
        var fila = boton.closest('.contacto-fila');
        if (fila) { fila.remove(); }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
