<?php
class Database {
    private $host = "localhost";
    private $username = "postgres"; // Standard PostgreSQL user
    private $password = "1234"; // Placeholder
    private $database = "student_management_system";
    private $port = "5432"; // Standard PostgreSQL port
    public $conn;

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