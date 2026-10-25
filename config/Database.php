<?php
class Database {
    private static $host = "localhost";
    private static $port = "5432";
    private static $db_name = "BD_cita_medica";
    private static $username = "postgres";
    private static $password = "pgsWN222";
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            try {
                $dsn = "pgsql:host=" . self::$host . ";port=" . self::$port . ";dbname=" . self::$db_name;
                self::$conn = new PDO($dsn, self::$username, self::$password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            } catch (PDOException $e) {
                // No muestra credenciales en pantalla
                die("Error de conexión a la base de datos.");
            }
        }
        return self::$conn;
    }
}