<?php
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Load .env file from the project root (one level up from config/)
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                $_ENV[trim($name)] = trim($value);
            }
        }

        // Fetch credentials from environment variables with fallbacks
        $this->host = $_ENV['DB_HOST'] ?? 'aws-0-ap-southeast-1.pooler.supabase.com';
        $this->port = $_ENV['DB_PORT'] ?? '6543';
        $this->db_name = $_ENV['DB_NAME'] ?? 'postgres';
        $this->username = $_ENV['DB_USER'] ?? '';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name};sslmode=require";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Log the error securely and show a clean message without leaking credentials
            error_log("Database Connection Error: " . $exception->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
        return $this->conn;
    }
}
?>