<?php
namespace App\config;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    private PDO $pdo;
    private static ?Database $instance = null;
    private string $host = 'db-docker';
    private string $name = 'tank-game';
    private string $user = 'user';
    private string $password = 'password';
    private string $management_system = 'mysql';

    private function __construct(
        string $db_host,
        string $db_name,
        string $db_user,
        string $db_password,
        string $db_management_system,
    ) {
        $this->host = $db_host;
        $this->name = $db_name;
        $this->user = $db_user;
        $this->password = $db_password;
        $this->management_system = $db_management_system;

        $this->connect([]);
    }

    public static function getInstance(
        string $db_host,
        string $db_name,
        string $db_user,
        string $db_password,
        string $db_management_system
    ): Database {
        if (self::$instance === null) {
            self::$instance = new self($db_host, $db_name, $db_user, $db_password, $db_management_system);
        }
        
        return self::$instance;
    }

    public function connect(array $pdoOptions): void 
    {
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        $options = array_merge($defaultOptions, $pdoOptions);
        $dsn = "{$this->management_system}:host={$this->host};dbname={$this->name};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}