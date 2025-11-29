<?php
class Database
{
    private $host = "localhost";
    private $db_name = "login_system";
    private $username = "root";
    private $password = "";
    private $conn = null;

    public function getConnection()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database connection failed.",
            ]);
            exit;
        }

        return $this->conn;
    }
}
