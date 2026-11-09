<?php

/**
 * Traduce excepciones de PostgreSQL a mensajes aptos para mostrar al usuario.
 */
class ErroresBD {
    /**
     * De un mensaje tipo:
     *   "SQLSTATE[P0001]: Raise exception: 7 ERROR:  No quedan fichas disponibles...
     *    CONTEXT: PL/pgSQL function ..."
     * deja solo la primera línea posterior a "ERROR:" ("No quedan fichas
     * disponibles..."). Si el mensaje no viene de PostgreSQL (excepción de
     * negocio), se devuelve tal cual.
     */
    public static function mensaje(Throwable $e) {
        $m = $e->getMessage();
        $pos = strrpos($m, 'ERROR:');
        if ($pos !== false) {
            $m = substr($m, $pos + strlen('ERROR:'));
        }
        $linea = explode("\n", $m, 2);
        return trim($linea[0]);
    }
}
