<?php
// Espera: $horarios, $horario, $fecha, $fichas, $esPaciente, $pacientePropio,
//         $pacientes, $errorDisp, $colores, $mensajeExito, $mensajeError
$esPaciente    = $esPaciente ?? false;
$pacientePropio = $pacientePropio ?? null;
$pacientes     = $pacientes ?? [];
$fichas        = $fichas ?? [];
$horario       = $horario ?? null;
$fecha         = $fecha ?? '';
$errorDisp     = $errorDisp ?? null;

$v = fn($k, $d = '') => htmlspecialchars($d === '' ? ($_GET[$k] ?? '') : $d, ENT_QUOTES);

$libres    = count(array_filter($fichas, fn($f) => !$f['ocupada']));
$ocupadas  = count($fichas) - $libres;
$diaSemana = ['Domingo' => 0, 'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3,
              'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];

// Datos para el autocompletado de pacientes (se envían al navegador como JSON).
$idPacienteSel    = (int)($_GET['id_paciente'] ?? 0);
$pacienteSelLabel = '';
$pacientesData    = [];
foreach ($pacientes as $p) {
    $label = trim(($p['codigo'] ?? '') . ' - ' . ($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')
        . ' (CI ' . ($p['ci'] ?? '') . ')');
    $pacientesData[] = [
        'id'        => (int)($p['id_paciente'] ?? 0),
        'codigo'    => (string)($p['codigo'] ?? ''),
        'nombres'   => (string)($p['nombres'] ?? ''),
        'apellidos' => (string)($p['apellidos'] ?? ''),
        'ci'        => (string)($p['ci'] ?? ''),
        'label'     => $label,
    ];
    if ((int)($p['id_paciente'] ?? 0) === $idPacienteSel) {
        $pacienteSelLabel = $label;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar cita - Clínica Vida Salud</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($colores['primary'] ?? '#0D9488') ?>;
            --sidebar-bg: <?= htmlspecialchars($colores['sidebar_bg'] ?? '#0F172A') ?>;
            --neutral: <?= htmlspecialchars($colores['neutral'] ?? '#64748B') ?>;
            --bg-main: <?= htmlspecialchars($colores['bg_main'] ?? '#F8FAFC') ?>;
        }
        .sidebar { background-color: var(--sidebar-bg) !important; }
        .badge-programada   { background: #DBEAFE; color: #1E3A8A; }
        .badge-confirmada   { background: #CCFBF1; color: #115E59; }
        .badge-atendida     { background: #DCFCE7; color: #166534; }
        .badge-cancelada    { background: #FFE4E6; color: #9F1239; }
        .badge-inasistencia { background: #FEF3C7; color: #92400E; }

        .fichas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
        .ficha {
            border: 1px solid #E2E8F0; border-radius: 8px; background: #fff;
            padding: 12px 14px; display: flex; flex-direction: column; gap: 8px;
        }
        .ficha-hora { font-size: 17px; font-weight: 800; color: #0F172A; }
        .ficha-meta { font-size: 12px; color: #64748B; }
        .ficha-libre { border-color: #99F6E4; background: #F0FDFA; }
        .ficha-ocupada { background: #F8FAFC; border-color: #E2E8F0; opacity: .85; }
        .ficha .btn { width: 100%; padding: 8px; font-size: 13px; }
        .resumen-fichas { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; font-size: 14px; margin-bottom: 14px; }
        .dato { background: #fff; border: 1px solid #E2E8F0; border-radius: 6px; padding: 7px 12px; }
        .dato strong { color: var(--primary); }
        .paciente-fijo { padding: 10px 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; font-size: 14px; }

        /* Autocompletado de pacientes */
        .panel-buscador { overflow: visible; }
        .combobox { position: relative; }
        .combobox-input {
            width: 100%; padding: 9px 12px; border: 1px solid #D1D5DB; border-radius: 6px;
            font-size: 14px; font-family: inherit; outline: none; background: #fff;
        }
        .combobox-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
        }
        .combobox-opciones {
            position: absolute; left: 0; right: 0; top: calc(100% + 4px);
            background: #fff; border: 1px solid #CBD5E1; border-radius: 8px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .16);
            max-height: 240px; overflow-y: auto; z-index: 60; display: none;
        }
        .combobox-opciones.abierta { display: block; }
        .combobox-opcion {
            padding: 9px 12px; font-size: 14px; color: #0F172A; cursor: pointer;
            border-bottom: 1px solid #F1F5F9;
        }
        .combobox-opcion:last-child { border-bottom: none; }
        .combobox-opcion:hover, .combobox-opcion.activa { background: #F0FDFA; }
        .combobox-vacio { padding: 10px 12px; color: #64748B; font-size: 13px; }
    </style>
</head>
<body>
<div class="admin-wrapper">

    <?php $activo = 'citas_crear'; require __DIR__ . '/../layouts/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="top-nav">
            <div class="top-title">Sistema de Gestión de Citas Médicas</div>
            <?php require __DIR__ . '/../layouts/user_menu.php'; ?>
        </header>

        <div class="content-body">
            <h1 class="page-title">Programar una nueva cita</h1>

            <?php if (!empty($mensajeExito)): ?>
                <div class="alert-box alert-ok">✓ <?= htmlspecialchars($mensajeExito) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensajeError)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($mensajeError) ?></div>
            <?php endif; ?>
            <?php if (!empty($errorDisp)): ?>
                <div class="alert-box alert-err"><?= htmlspecialchars($errorDisp) ?></div>
            <?php endif; ?>

            <?php if (empty($horarios)): ?>
                <div class="panel-box panel-azul">
                    <div class="panel-body">
                        <p class="placeholder-text">No hay horarios de atención activos para programar citas.</p>
                        <div class="form-acciones">
                            <a href="index.php?route=citas" class="btn btn-sec">Volver</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <!-- ============ 1. Elegir horario, fecha y datos ============ -->
                <form action="index.php" method="GET" class="panel-box panel-azul panel-buscador">
                    <input type="hidden" name="route" value="citas_crear">
                    <div class="panel-header"><h4>1. Elija el horario disponible</h4></div>
                    <div class="panel-body">
                        <div class="form-grid">

                            <div class="campo col-6">
                                <label for="horario">Horario (doctor, especialidad y fichas) *</label>
                                <select id="horario" name="horario" required onchange="this.form.submit()">
                                    <option value="">Seleccionar horario</option>
                                    <?php foreach ($horarios as $h): ?>
                                        <option value="<?= (int)$h['id_horario'] ?>"
                                            <?= (int)($horario['id_horario'] ?? 0) === (int)$h['id_horario'] ? 'selected' : '' ?>>
                                            Dr(a). <?= htmlspecialchars($h['nombres'] . ' ' . $h['apellidos']) ?>
                                            - <?= htmlspecialchars(mb_strtoupper($h['especialidad'])) ?>
                                            · <?= htmlspecialchars(mb_strtoupper($h['dia_semana'])) ?>
                                            <?= substr($h['hora_inicio'], 0, 5) ?>-<?= substr($h['hora_fin'], 0, 5) ?>
                                            · <?= (int)$h['cantidad_fichas'] ?> fichas
                                            · hasta <?= date('d/m/Y', strtotime($h['fecha_fin'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo col-6">
                                <label for="fecha">Fecha de la cita<?= $horario ? ' *' : '' ?></label>
                                <input type="date" id="fecha" name="fecha"
                                       min="<?= date('Y-m-d') ?>"
                                       <?= !empty($horario['fecha_fin']) ? 'max="' . htmlspecialchars($horario['fecha_fin']) . '"' : '' ?>
                                       value="<?= htmlspecialchars($fecha) ?>"
                                       <?= $horario ? 'required onchange="this.form.submit()"' : '' ?>>
                                <?php if ($horario): ?>
                                    <p class="hint">
                                        Debe ser un/a <?= htmlspecialchars($horario['dia_semana']) ?> (día del horario)
                                        y hasta el <?= date('d/m/Y', strtotime($horario['fecha_fin'])) ?>.
                                        Cada fecha tiene sus propias fichas.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <?php if ($esPaciente): ?>
                                <div class="campo col-6">
                                    <label>Paciente</label>
                                    <div class="paciente-fijo">
                                        <?php if ($pacientePropio > 0): ?>
                                            Usted (paciente vinculado a su cuenta) — ID <?= (int)$pacientePropio ?>
                                        <?php else: ?>
                                            <span style="color:#B91C1C">Su cuenta no tiene un paciente vinculado.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="campo col-6">
                                    <label for="paciente_buscar">Paciente<?= $horario ? ' *' : '' ?></label>
                                    <div class="combobox" id="paciente-combobox">
                                        <input type="hidden" id="id_paciente" name="id_paciente"
                                               value="<?= $idPacienteSel > 0 ? $idPacienteSel : '' ?>">
                                        <input type="text" id="paciente_buscar" class="combobox-input"
                                               placeholder="Escriba el nombre, código o CI..."
                                               autocomplete="off" spellcheck="false"
                                               role="combobox" aria-autocomplete="list" aria-expanded="false"
                                               aria-controls="paciente_opciones"
                                               <?= $horario ? 'required' : '' ?>
                                               value="<?= htmlspecialchars($pacienteSelLabel, ENT_QUOTES) ?>">
                                        <div id="paciente_opciones" class="combobox-opciones" role="listbox"></div>
                                    </div>
                                    <p class="hint">Escriba las primeras letras y elija un paciente de la lista.</p>
                                </div>
                            <?php endif; ?>

                            <div class="campo col-12">
                                <label for="motivo">Motivo de la cita</label>
                                <textarea id="motivo" name="motivo" rows="2"
                                          placeholder="Ej.: Control general, dolor de cabeza..."><?= $v('motivo') ?></textarea>
                            </div>

                        </div>

                        <div class="form-acciones">
                            <a href="index.php?route=citas" class="btn btn-sec">Cancelar</a>
                            <button type="submit" class="btn btn-main">Ver fichas disponibles</button>
                        </div>
                    </div>
                </form>

                <!-- ============ 2. Elegir la ficha ============ -->
                <?php if ($horario && $fecha !== ''): ?>
                    <div class="panel-box panel-azul" style="margin-top:22px">
                        <div class="panel-header">
                            <h4>2. Elija la ficha (<?= count($fichas) ?> en total)</h4>
                        </div>
                        <div class="panel-body">

                            <div class="resumen-fichas">
                                <span class="dato"><strong><?= (int)$horario['duracion_cita'] ?></strong> min por cita</span>
                                <span class="dato"><strong><?= $libres ?></strong> libres</span>
                                <span class="dato"><strong><?= $ocupadas ?></strong> ocupadas</span>
                                <span class="dato">
                                    <?= htmlspecialchars($fecha) ?> (<?= htmlspecialchars($horario['dia_semana']) ?>)
                                </span>
                            </div>

                            <?php if (empty($fichas)): ?>
                                <p class="placeholder-text">
                                    <?= $fecha === date('Y-m-d')
                                        ? 'Ya no quedan fichas por empezar hoy. Elige otra fecha.'
                                        : 'Ese horario no tiene fichas calculadas (revisa la duración y el rango de horas).' ?>
                                </p>
                            <?php else: ?>
                                <div class="fichas-grid">
                                    <?php foreach ($fichas as $f): ?>
                                        <div class="ficha <?= $f['ocupada'] ? 'ficha-ocupada' : 'ficha-libre' ?>">
                                            <div class="ficha-hora"><?= $f['inicio'] ?> - <?= $f['fin'] ?></div>

                                            <?php if ($f['ocupada']): ?>
                                                <span class="badge-estado badge-<?= str_replace(' ', '-', mb_strtolower($f['estado'])) ?>">
                                                    <?= htmlspecialchars($f['estado']) ?>
                                                </span>
                                                <div class="ficha-meta">Ficha no disponible</div>
                                            <?php else: ?>
                                                <div class="ficha-meta">Disponible</div>
                                                <form action="index.php?route=citas_guardar" method="POST"
                                                      onsubmit="return confirm('¿Programar la cita de <?= $f['inicio'] ?> a <?= $f['fin'] ?>?');">
                                                    <input type="hidden" name="horario" value="<?= (int)$horario['id_horario'] ?>">
                                                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                                                    <input type="hidden" name="hora_inicio" value="<?= $f['inicio'] ?>">
                                                    <input type="hidden" name="hora_fin" value="<?= $f['fin'] ?>">
                                                    <input type="hidden" name="motivo" value="<?= $v('motivo') ?>">
                                                    <?php if (!$esPaciente): ?>
                                                        <input type="hidden" name="id_paciente"
                                                               value="<?= (int)($_GET['id_paciente'] ?? 0) ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" class="btn btn-main">Programar cita</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-acciones">
                                <a href="index.php?route=citas" class="btn btn-sec">Volver al listado</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>
</div>
<script>
(function () {
    const paciente = document.getElementById('id_paciente');
    const motivo   = document.getElementById('motivo');

    // Los botones "Programar cita" están en formularios POST independientes.
    // Si el usuario cambia el paciente o el motivo después de cargar las fichas,
    // hay que copiar esos valores a los hidden de cada formulario.
    function sincronizar() {
        document.querySelectorAll('form[action*="citas_guardar"]').forEach(function (form) {
            const pacienteForm = form.querySelector('input[name="id_paciente"]');
            if (pacienteForm && paciente) {
                pacienteForm.value = paciente.value;
            }
            const motivoForm = form.querySelector('input[name="motivo"]');
            if (motivoForm && motivo) {
                motivoForm.value = motivo.value;
            }
        });
    }

    if (paciente) {
        paciente.addEventListener('change', sincronizar);
    }
    if (motivo) {
        motivo.addEventListener('input', sincronizar);
    }
    sincronizar();
})();
</script>
<script type="application/json" id="pacientes-data"><?= json_encode(
    $pacientesData,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '[]' ?></script>
<script>
(function () {
    const dataEl = document.getElementById('pacientes-data');
    const combo  = document.getElementById('paciente-combobox');
    const input  = document.getElementById('paciente_buscar');
    const hidden = document.getElementById('id_paciente');
    const lista  = document.getElementById('paciente_opciones');
    if (!dataEl || !combo || !input || !hidden || !lista) {
        return;
    }

    const pacientes = JSON.parse(dataEl.textContent || '[]');

    // Búsqueda sin distinguir mayúsculas ni tildes.
    function normalizar(texto) {
        return (texto || '').toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    let seleccionado = input.value.trim();
    let indiceActivo = -1;
    let opcionesVisibles = [];

    function cerrar() {
        lista.classList.remove('abierta');
        input.setAttribute('aria-expanded', 'false');
        indiceActivo = -1;
    }

    function marcarActiva() {
        Array.prototype.forEach.call(lista.children, function (el, i) {
            el.classList.toggle('activa', i === indiceActivo);
        });
        if (lista.children[indiceActivo]) {
            lista.children[indiceActivo].scrollIntoView({block: 'nearest'});
        }
    }

    function elegir(p) {
        hidden.value = p.id;
        input.value = p.label;
        seleccionado = p.label;
        input.setCustomValidity('');
        cerrar();
        // Copia el id a los formularios POST de cada ficha.
        hidden.dispatchEvent(new Event('change', {bubbles: true}));
        input.focus();
    }

    function renderizar() {
        const q = normalizar(input.value);
        lista.innerHTML = '';
        opcionesVisibles = [];
        indiceActivo = -1;

        // No desplegar la lista completa: solo al escribir.
        if (q === '') {
            cerrar();
            return;
        }

        const encontrados = pacientes.filter(function (p) {
            const nombre    = normalizar(p.nombres + ' ' + p.apellidos);
            const invertido = normalizar(p.apellidos + ' ' + p.nombres);
            return nombre.startsWith(q)
                || invertido.startsWith(q)
                || normalizar(p.codigo).startsWith(q)
                || normalizar(p.ci).startsWith(q);
        }).slice(0, 8);

        if (encontrados.length === 0) {
            const vacio = document.createElement('div');
            vacio.className = 'combobox-vacio';
            vacio.textContent = 'Sin coincidencias.';
            lista.appendChild(vacio);
        } else {
            encontrados.forEach(function (p) {
                const op = document.createElement('div');
                op.className = 'combobox-opcion';
                op.setAttribute('role', 'option');
                op.textContent = p.label;
                // mousedown + preventDefault: el input no pierde el foco antes del clic.
                op.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    elegir(p);
                });
                lista.appendChild(op);
                opcionesVisibles.push(p);
            });
        }

        lista.classList.add('abierta');
        input.setAttribute('aria-expanded', 'true');
    }

    input.addEventListener('input', function () {
        input.setCustomValidity('');
        if (input.value !== seleccionado) {
            hidden.value = '';
            hidden.dispatchEvent(new Event('change', {bubbles: true}));
        }
        renderizar();
    });

    input.addEventListener('focus', function () {
        // Si ya hay un paciente elegido, se selecciona el texto para reemplazarlo al escribir.
        if (hidden.value !== '' && input.value === seleccionado) {
            input.select();
        }
        // Al enfocar sin texto no se muestra la lista completa.
        if (normalizar(input.value) === '' || hidden.value === '') {
            cerrar();
        }
    });

    input.addEventListener('blur', function () {
        // Si no se eligió una opción válida, se restaura (o limpia) el texto.
        window.setTimeout(function () {
            if (hidden.value === '') {
                input.value = '';
                seleccionado = '';
            } else {
                input.value = seleccionado;
            }
            // No dejar bloqueado el formulario con un mensaje de validación antiguo.
            input.setCustomValidity('');
            cerrar();
        }, 150);
    });

    input.addEventListener('keydown', function (e) {
        if (!lista.classList.contains('abierta')) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                renderizar();
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            indiceActivo = Math.min(indiceActivo + 1, opcionesVisibles.length - 1);
            marcarActiva();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            indiceActivo = Math.max(indiceActivo - 1, 0);
            marcarActiva();
        } else if (e.key === 'Enter') {
            if (opcionesVisibles.length > 0) {
                e.preventDefault();
                elegir(opcionesVisibles[indiceActivo >= 0 ? indiceActivo : 0]);
            }
        } else if (e.key === 'Escape') {
            cerrar();
        }
    });

    document.addEventListener('mousedown', function (e) {
        if (!combo.contains(e.target)) {
            cerrar();
        }
    });

    // Si se envía el formulario habiendo escrito un nombre sin elegirlo de la lista.
    function avisarFaltaPaciente() {
        input.setCustomValidity('Selecciona un paciente de la lista.');
        input.reportValidity();
        input.focus();
        if (normalizar(input.value) !== '') {
            renderizar();
        }
    }

    const formBusqueda = combo.closest('form');
    if (formBusqueda) {
        formBusqueda.addEventListener('submit', function (e) {
            if (hidden.value === '') {
                e.preventDefault();
                avisarFaltaPaciente();
            }
        });
    }

    // Cada botón "Programar cita" es un formulario aparte: si aún no se eligió
    // un paciente de la lista, se avisa antes de mostrar el confirm().
    document.querySelectorAll('form[action*="citas_guardar"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (hidden.value === '') {
                e.preventDefault();
                e.stopImmediatePropagation();
                avisarFaltaPaciente();
            }
        }, true);
    });

    // Mantiene coherente el hidden si el navegador restauró un valor previo.
    if (hidden.value === '') {
        input.value = '';
        seleccionado = '';
    }
})();
</script>
</body>
</html>
