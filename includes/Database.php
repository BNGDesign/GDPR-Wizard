<?php
/**
 * Database Connection Handler (Singleton Pattern)
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $connected = false;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->connected = true;
        } catch (PDOException $e) {
            $this->connected = false;
            error_log("[GDPR Wizard] Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }

    public function query($sql, $params = []) {
        if (!$this->isConnected()) {
            error_log('[GDPR Wizard] Database unavailable, skipping query.');
            return false;
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }
    
    public function insert($table, $data) {
        if (!$this->isConnected()) {
            return false;
        }
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = ':' . implode(', :', $keys);
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where) {
        if (!$this->isConnected()) {
            return false;
        }
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setString = implode(', ', $set);
        
        $whereString = [];
        foreach ($where as $key => $value) {
            $whereString[] = "{$key} = :where_{$key}";
            $data["where_{$key}"] = $value;
        }
        $whereClause = implode(' AND ', $whereString);
        
        $sql = "UPDATE {$table} SET {$setString} WHERE {$whereClause}";
        return $this->query($sql, $data);
    }
    
    public function delete($table, $where) {
        if (!$this->isConnected()) {
            return false;
        }
        $whereString = [];
        foreach ($where as $key => $value) {
            $whereString[] = "{$key} = :{$key}";
        }
        $whereClause = implode(' AND ', $whereString);
        
        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        return $this->query($sql, $where);
    }
    
    public function beginTransaction() {
        if (!$this->isConnected()) {
            return false;
        }
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        if (!$this->isConnected()) {
            return false;
        }
        return $this->pdo->commit();
    }

    public function rollback() {
        if (!$this->isConnected()) {
            return false;
        }
        return $this->pdo->rollBack();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}