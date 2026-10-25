<?php
require_once __DIR__ . '/../../config/Database.php';

class Configuracion {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getValor($clave, $valorPorDefecto = null) {
        $sql = "SELECT valor FROM configuracion_sistema WHERE clave = :clave LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':clave', $clave);
        $stmt->execute();
        $res = $stmt->fetchColumn();
        return $res !== false ? $res : $valorPorDefecto;
    }

    public function setValor($clave, $valor, $descripcion = '') {
        $sql = "INSERT INTO configuracion_sistema (clave, valor, descripcion)
                VALUES (:clave, :valor, :descripcion)
                ON CONFLICT (clave) 
                DO UPDATE SET valor = EXCLUDED.valor";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':clave'       => $clave,
            ':valor'       => $valor,
            ':descripcion' => $descripcion
        ]);
    }

    /**
     * Devuelve el conjunto de colores activos (predeterminado SaludCore si no existe)
     */
    public function getColoresPersonalizados() {
        $json = $this->getValor('colores_personalizados', null);
        if ($json) {
            $colores = json_decode($json, true);
            if (is_array($colores)) {
                return $colores;
            }
        }
        // Valores por defecto de SaludCore
        return [
            'primary'      => '#0D9488',
            'sidebar_bg'   => '#0F172A',
            'tertiary'     => '#6366F1',
            'neutral'      => '#64748B',
            'bg_main'      => '#F8FAFC',
            'card_stat_1'  => '#0D9488',
            'card_stat_2'  => '#10B981',
            'card_stat_3'  => '#6366F1',
            'card_stat_4'  => '#F59E0B',
            'card_stat_5'  => '#64748B',
            'card_stat_6'  => '#0284C7'
        ];
    }
}