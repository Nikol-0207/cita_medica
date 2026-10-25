<?php
/**
 * Paginación de los listados (10 registros por página).
 *
 * Uso en el controlador:
 *     require_once ... '/helpers/Paginador.php';
 *     $pag = Paginador::cortar($filas);
 *     require ... vista;
 *
 * Uso en la vista:
 *     foreach ($pag['items'] as $i => $fila) ... número = $pag['desde'] + $i
 *     require __DIR__ . '/../layouts/paginacion.php';
 */
class Paginador {
    /** Registros que se muestran por página en todos los listados. */
    public const POR_PAGINA = 10;

    /** Página pedida en la URL (?pagina=N); 1 si no existe o no es válida. */
    public static function paginaActual() {
        $p = $_GET['pagina'] ?? '1';
        return (is_scalar($p) && ctype_digit((string)$p) && (int)$p > 0) ? (int)$p : 1;
    }

    /**
     * Corta la lista a la página actual y devuelve todo lo que la vista necesita.
     * Si la página pedida se pasa del final, se muestra la última.
     */
    public static function cortar(array $filas, $porPagina = self::POR_PAGINA) {
        $porPagina    = max(1, (int)$porPagina);
        $total        = count($filas);
        $totalPaginas = max(1, (int)ceil($total / $porPagina));
        $pagina       = min(self::paginaActual(), $totalPaginas);
        $desde        = ($pagina - 1) * $porPagina;

        return [
            'items'        => array_slice($filas, $desde, $porPagina),
            'total'        => $total,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'porPagina'    => $porPagina,
            'desde'        => $total > 0 ? $desde + 1 : 0,
            'hasta'        => min($desde + $porPagina, $total),
        ];
    }
}
