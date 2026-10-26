<?php
class Database {
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            // Lee variables del servidor (Render/Railway/etc.) o usa valores locales por defecto
            $host     = getenv('DB_HOST') ?: '';
            $port     = getenv('DB_PORT') ?: '5432';
            $db_name  = getenv('DB_NAME') ?: '';
            $username = getenv('DB_USER') ?: '';
            $password = getenv('DB_PASSWORD') ?: '';
            
            // Neon requiere sslmode=require; en local puedes omitirlo o usar 'prefer'
            $sslmode  = getenv('DB_SSLMODE') ?: 'require';

            try {
                $dsn = "pgsql:host={$host};port={$port};dbname={$db_name};sslmode={$sslmode}";
                
                self::$conn = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Neon se conecta a través de PgBouncer (host terminado en "-pooler").
                    // Con prepares nativos, pdo_pgsql envía "DEALLOCATE pdo_stmt_N" al
                    // liberar cada sentencia; PgBouncer renombra las sentencias y ese
                    // DEALLOCATE falla, abortando la transacción completa (25P02).
                    // Con prepares emulados en el cliente no se envían PREPARE/DEALLOCATE.
                    PDO::ATTR_EMULATE_PREPARES   => true,
                ]);
            } catch (PDOException $e) {
                // Para depuración inicial puedes ver el error real con: die($e->getMessage());
                die("Error de conexión a la base de datos.");
            }
        }
        return self::$conn;
    }
}