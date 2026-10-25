<?php
/**
 * Controles de paginación de los listados.
 * Espera $pag (devuelto por Paginador::cortar) y conserva los filtros actuales
 * de la URL (?estado=, ?medico=, ?fecha=...) en cada enlace.
 */
$pag = $pag ?? null;
if (!$pag) {
    return;
}

$total = (int)($pag['total'] ?? 0);
if ($total === 0) {
    return;   // el listado ya muestra su mensaje de "sin registros"
}

/** URL de una página con los filtros actuales (sin la página anterior). */
$qs = $_GET;
unset($qs['pagina']);
$enlace = function ($num) use ($qs) {
    $qs['pagina'] = (int)$num;
    return 'index.php?' . http_build_query($qs);
};

/** Ventana de números: máximo 9 botones con "…" a los costados. */
$nums = [];
$n = (int)$pag['totalPaginas'];
$p = (int)$pag['pagina'];
if ($n <= 9) {
    $nums = range(1, $n);
} else {
    $ini = max(1, min($p - 2, $n - 4));
    $fin = min($n, $ini + 4);
    $ini = max(1, $fin - 4);
    $nums = [];
    if ($ini > 1) {
        $nums[] = 1;
        if ($ini > 2) {
            $nums[] = '...';
        }
    }
    for ($i = $ini; $i <= $fin; $i++) {
        $nums[] = $i;
    }
    if ($fin < $n) {
        if ($fin < $n - 1) {
            $nums[] = '...';
        }
        $nums[] = $n;
    }
}
?>
<div class="paginacion">
    <span class="pag-info">
        <?php if ($total > 0): ?>
            Mostrando <strong><?= (int)$pag['desde'] ?>-<?= (int)$pag['hasta'] ?></strong>
            de <strong><?= $total ?></strong> registro<?= $total === 1 ? '' : 's' ?>
        <?php else: ?>
            Sin registros
        <?php endif; ?>
    </span>

    <?php if ($n > 1): ?>
        <div class="pag-botones">
            <?php if ($p > 1): ?>
                <a class="pag-btn" href="<?= htmlspecialchars($enlace($p - 1)) ?>" title="Página anterior">&laquo; Anterior</a>
            <?php endif; ?>

            <?php foreach ($nums as $num): ?>
                <?php if ($num === '...'): ?>
                    <span class="pag-puntos">…</span>
                <?php elseif ($num === $p): ?>
                    <span class="pag-btn pag-activo"><?= $num ?></span>
                <?php else: ?>
                    <a class="pag-btn" href="<?= htmlspecialchars($enlace($num)) ?>"><?= $num ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($p < $n): ?>
                <a class="pag-btn" href="<?= htmlspecialchars($enlace($p + 1)) ?>" title="Página siguiente">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
