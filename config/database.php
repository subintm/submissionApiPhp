<?php

class Database {
    private $host = "localhost";
    private $database = "submission_api";
    private $username = "root";
    private $password = "";
    private $pdo;

    public function connect() {
        if ($this->pdo === null) {
            try {
                $this->pdo = new PDO(
                    "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Database connection failed"
                ]);
                exit;
            }
        }
        return $this->pdo;
    }
}