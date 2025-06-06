<?php
declare(strict_types=1);

/**
 * Classe pour gérer les opérations de base de données MariaDB
 * Compatible PHP 8.2+
 */
class DatabaseManager {
    private PDO $connection;
    
    /**
     * Constructeur qui prend une connexion à la base de données
     */
    public function __construct(PDO $connection) {
        $this->connection = $connection;
        // Configuration spécifique pour MariaDB
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        // Définir le charset pour MariaDB
        $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    
    /**
     * Méthode pour exécuter une requête SELECT simple
     */
    public function select(
        string $table, 
        array $columns = ['*'], 
        array $where = [], 
        string $orderBy = '', 
        int $limit = 0
    ): array {
        // Validation du nom de table pour MariaDB
        $this->validateTableName($table);
        
        // Construction de la requête SELECT
        $escapedColumns = array_map(fn($col) => $col === '*' ? '*' : '`' . trim($col, '`') . '`', $columns);
        $sql = "SELECT " . implode(', ', $escapedColumns) . " FROM `$table`";
        
        // Ajout des conditions WHERE si présentes
        $params = [];
        if (!empty($where)) {
            $whereClause = [];
            foreach ($where as $column => $value) {
                $placeholder = 'where_' . $column;
                $whereClause[] = "`$column` = :$placeholder";
                $params[$placeholder] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        // Ajout de ORDER BY si spécifié
        if (!empty($orderBy)) {
            // Sécurisation de la clause ORDER BY
            $sql .= " ORDER BY " . $this->sanitizeOrderBy($orderBy);
        }
        
        // Ajout de LIMIT si spécifié
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de la sélection: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour exécuter une requête INSERT
     */
    public function insert(string $table, array $data): int {
        if (empty($data)) {
            throw new InvalidArgumentException("Les données à insérer ne peuvent pas être vides");
        }
        
        $this->validateTableName($table);
        
        // Construction de la requête INSERT avec backticks pour MariaDB
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value, $this->getPdoType($value));
            }
            
            if ($stmt->execute()) {
                return (int)$this->connection->lastInsertId();
            }
            
            return 0;
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de l'insertion: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour exécuter une requête UPDATE
     */
    public function update(string $table, array $data, array $where): int {
        if (empty($data)) {
            throw new InvalidArgumentException("Les données à mettre à jour ne peuvent pas être vides");
        }
        
        if (empty($where)) {
            throw new InvalidArgumentException("Les conditions WHERE sont obligatoires pour éviter une mise à jour globale");
        }
        
        $this->validateTableName($table);
        
        // Construction de la requête UPDATE
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $placeholder = 'set_' . $column;
            $setClause[] = "`$column` = :$placeholder";
            $params[$placeholder] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setClause);
        
        // Ajout des conditions WHERE
        $whereClause = [];
        foreach ($where as $column => $value) {
            $placeholder = 'where_' . $column;
            $whereClause[] = "`$column` = :$placeholder";
            $params[$placeholder] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $whereClause);
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de la mise à jour: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour exécuter une requête DELETE
     */
    public function delete(string $table, array $where): int {
        if (empty($where)) {
            throw new InvalidArgumentException("Les conditions WHERE sont obligatoires pour éviter une suppression globale");
        }
        
        $this->validateTableName($table);
        
        // Construction de la requête DELETE
        $sql = "DELETE FROM `$table`";
        
        // Ajout des conditions WHERE
        $params = [];
        $whereClause = [];
        foreach ($where as $column => $value) {
            $placeholder = 'where_' . $column;
            $whereClause[] = "`$column` = :$placeholder";
            $params[$placeholder] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $whereClause);
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de la suppression: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour exécuter une requête SQL personnalisée
     */
    public function query(string $sql, array $params = [], bool $fetchAll = true): mixed {
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($params as $key => $value) {
                $paramKey = str_starts_with($key, ':') ? $key : ":$key";
                $stmt->bindValue($paramKey, $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            
            // Déterminer si c'est une requête SELECT
            $isSelect = str_starts_with(strtoupper(trim($sql)), 'SELECT');
            
            if ($isSelect) {
                return $fetchAll ? $stmt->fetchAll() : $stmt->fetch();
            } else {
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de l'exécution de la requête: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour compter le nombre d'enregistrements dans une table
     */
    public function count(string $table, array $where = []): int {
        $this->validateTableName($table);
        
        // Construction de la requête COUNT
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        
        // Ajout des conditions WHERE si présentes
        $params = [];
        if (!empty($where)) {
            $whereClause = [];
            foreach ($where as $column => $value) {
                $placeholder = 'where_' . $column;
                $whereClause[] = "`$column` = :$placeholder";
                $params[$placeholder] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            $result = $stmt->fetch();
            return (int)$result['count'];
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors du comptage: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour effectuer une jointure entre tables
     */
    public function join(
        string $mainTable, 
        array $joins, 
        array $columns = ['*'], 
        array $where = [], 
        string $orderBy = '', 
        int $limit = 0
    ): array {
        $this->validateTableName($mainTable);
        
        // Construction de la requête SELECT avec JOIN
        $escapedColumns = array_map(fn($col) => $col === '*' ? '*' : '`' . trim($col, '`') . '`', $columns);
        $sql = "SELECT " . implode(', ', $escapedColumns) . " FROM `$mainTable`";
        
        // Ajout des jointures
        foreach ($joins as $table => $info) {
            $this->validateTableName($table);
            $condition = $info[0];
            $type = isset($info[1]) ? strtoupper($info[1]) : 'INNER';
            
            // Validation du type de jointure pour MariaDB
            if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'])) {
                throw new InvalidArgumentException("Type de jointure invalide: $type");
            }
            
            $sql .= " $type JOIN `$table` ON $condition";
        }
        
        // Ajout des conditions WHERE si présentes
        $params = [];
        if (!empty($where)) {
            $whereClause = [];
            foreach ($where as $column => $value) {
                $placeholder = 'where_' . $column;
                $whereClause[] = "`$column` = :$placeholder";
                $params[$placeholder] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        // Ajout de ORDER BY si spécifié
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . $this->sanitizeOrderBy($orderBy);
        }
        
        // Ajout de LIMIT si spécifié
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de la jointure: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour commencer une transaction
     */
    public function beginTransaction(): bool {
        try {
            return $this->connection->beginTransaction();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors du début de transaction: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour valider une transaction
     */
    public function commit(): bool {
        try {
            return $this->connection->commit();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors du commit: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour annuler une transaction
     */
    public function rollback(): bool {
        try {
            return $this->connection->rollBack();
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors du rollback: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode pour insérer ou mettre à jour (UPSERT) spécifique à MariaDB
     */
    public function upsert(string $table, array $data, array $updateData = []): int {
        if (empty($data)) {
            throw new InvalidArgumentException("Les données ne peuvent pas être vides");
        }
        
        $this->validateTableName($table);
        
        // Construction de la requête INSERT ... ON DUPLICATE KEY UPDATE
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        
        // Ajout de la clause ON DUPLICATE KEY UPDATE
        if (!empty($updateData)) {
            $updateClause = [];
            foreach ($updateData as $column => $value) {
                $updateClause[] = "`$column` = :update_$column";
            }
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateClause);
        } else {
            // Par défaut, met à jour avec les mêmes valeurs
            $updateClause = [];
            foreach ($data as $column => $value) {
                $updateClause[] = "`$column` = VALUES(`$column`)";
            }
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateClause);
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value, $this->getPdoType($value));
            }
            
            foreach ($updateData as $key => $value) {
                $stmt->bindValue(":update_$key", $value, $this->getPdoType($value));
            }
            
            if ($stmt->execute()) {
                return (int)$this->connection->lastInsertId();
            }
            
            return 0;
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de l'upsert: " . $e->getMessage());
        }
    }
    
    /**
     * Méthode privée pour valider le nom de table
     */
    private function validateTableName(string $table): void {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException("Nom de table invalide: $table");
        }
    }
    
    /**
     * Méthode privée pour sécuriser la clause ORDER BY
     */
    private function sanitizeOrderBy(string $orderBy): string {
        // Suppression des caractères dangereux et validation basique
        $orderBy = preg_replace('/[^a-zA-Z0-9_.,\s`]/', '', $orderBy);
        
        // Ajout de backticks aux noms de colonnes
        $parts = explode(',', $orderBy);
        $sanitized = [];
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*(ASC|DESC)?$/i', $part, $matches)) {
                $column = $matches[1];
                $direction = isset($matches[2]) ? ' ' . strtoupper($matches[2]) : '';
                $sanitized[] = "`$column`$direction";
            }
        }
        
        return implode(', ', $sanitized);
    }
    
    /**
     * Méthode privée pour déterminer le type PDO approprié
     */
    private function getPdoType(mixed $value): int {
        return match (gettype($value)) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'NULL' => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
?>
