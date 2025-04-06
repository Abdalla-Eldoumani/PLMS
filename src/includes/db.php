<?php
class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "parking_management";
    private $conn;
    
    public function __construct() {
        if (getenv('MYSQL_HOST')) $this->host = getenv('MYSQL_HOST');
        if (getenv('MYSQL_USER')) $this->user = getenv('MYSQL_USER');
        if (getenv('MYSQL_PASSWORD')) $this->pass = getenv('MYSQL_PASSWORD');
        if (getenv('MYSQL_DATABASE')) $this->dbname = getenv('MYSQL_DATABASE');
        
        try {
            $this->conn = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->user, $this->pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function getLastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    public function commit() {
        return $this->conn->commit();
    }
    
    public function rollback() {
        return $this->conn->rollBack();
    }
}