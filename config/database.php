<?php
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $port;
    public $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: "localhost";
        $this->username = getenv('DB_USER') ?: "postgres";
        $this->password = getenv('DB_PASS') ?: "4436";
        $this->database = getenv('DB_NAME') ?: "student_management_system";
        $this->port = getenv('DB_PORT') ?: "5432";
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->database, 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
?>