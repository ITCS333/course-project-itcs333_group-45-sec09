<?php


class AssignmentDB {
    private $host = 'localhost';
    private $dbname = 'course_assignments';
    private $username = 'root';
    private $password = '';
    
    // Database connection
    private $conn;
    
    /**
     * Create database connection
     * @return PDO 
     */
    public function connect() {
        $this->conn = null;
        
        try {
            // Create PDO connection
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbname,
                $this->username,
                $this->password
            );
            
            // Set PDO to throw exceptions on errors
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
        
        return $this->conn;
    }
}
?>