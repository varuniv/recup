<?php
use PDO;
use PDOException;

class dbconnect {
    private static $servername = "localhost";
    private static $username = "overlord";
    private static $password = "2580";
    private static $dbName = "test";
    private static $conn = null;

    public static function connect() {
        if (self::$conn === null) {
            try {
                // Ajout de charset=utf8 ici
                $dsn = "mysql:host=" . self::$servername . ";dbname=" . self::$dbName . ";charset=utf8";
                self::$conn = new PDO($dsn, self::$username, self::$password);
                self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "" . PHP_EOL;
            } catch (PDOException $e) {
                echo "Connection failed: " . $e->getMessage() . PHP_EOL;
                exit;
            }
        }

        return self::$conn;
    }
}

return dbconnect::connect();

