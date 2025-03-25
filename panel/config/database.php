<?php
/**
 * Database Configuration
 * WordPress Hosting Panel with LiteSpeed
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'panel_db');
define('DB_USER', 'panel_user');
define('DB_PASSWORD', 'change_this_password'); // This will be updated during installation

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    // Singleton pattern to ensure only one database connection
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get the PDO connection
    public function getConnection() {
        return $this->connection;
    }
    
    // Execute a query and return the statement
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database query error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    // Execute a query and return a single row
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Execute a query and return all rows
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Execute a query and return the last inserted ID
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
    
    // Execute a query and return the number of affected rows
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Begin a transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Commit a transaction
    public function commit() {
        return $this->connection->commit();
    }
    
    // Rollback a transaction
    public function rollback() {
        return $this->connection->rollBack();
    }
}