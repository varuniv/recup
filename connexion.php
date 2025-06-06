<?php
class DBManager {
    private string $host = 'localhost';        // Hôte MariaDB
    private string $user = 'root';             // Utilisateur MariaDB
    private string $password = '';             // Mot de passe
    private string $database = 'nom_de_la_base'; // Nom de la base (à adapter)

    private ?mysqli $conn = null;

    public function __construct() {
        $this->connect();
    }

    private function connect(): void {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn = new mysqli(
                $this->host,
                $this->user,
                $this->password,
                $this->database
            );

            $this->conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    public function getConnection(): mysqli {
        if (!$this->conn) {
            $this->connect();
        }
        return $this->conn;
    }

    public function query(string $sql): mysqli_result|bool {
        try {
            return $this->conn->query($sql);
        } catch (mysqli_sql_exception $e) {
            die("Erreur lors de l'exécution de la requête : " . $e->getMessage());
        }
    }

    public function prepare(string $sql): mysqli_stmt {
        try {
            return $this->conn->prepare($sql);
        } catch (mysqli_sql_exception $e) {
            die("Erreur lors de la préparation de la requête : " . $e->getMessage());
        }
    }

    public function escapeString(string $string): string {
        return $this->conn->real_escape_string($string);
    }

    public function lastInsertId(): int {
        return $this->conn->insert_id;
    }

    public function close(): void {
        if ($this->conn !== null) {
            $this->conn->close();
        }
    }
}
?>

