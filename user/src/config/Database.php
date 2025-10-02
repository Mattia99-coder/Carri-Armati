<?php

/**
 * Singleton class for managing a PDO database connection.
 */
class Database {
    /**
     * @var \PDO|null The PDO instance for database connection.
     */
    private $pdo;

    /**
     * @var Database|null The singleton instance of the Database class.
     */
    private static $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @param string $user Database username.
     * @param string $password Database password.
     */
    private function __construct($user, $password) 
    {
        $this->connect($user, $password);
    }

    /**
     * Initializes the singleton instance of the Database class.
     *
     * @param string $user Database username.
     * @param string $password Database password.
     * @return Database The singleton instance.
     * @throws \RuntimeException If the instance is already initialized.
     */
    public static function newInstance($user, $password): Database 
    {
        if (self::$instance !== null) {
            throw new RuntimeException("Database already initialized. Please use Database.getInstance().");
        }

        return self::$instance = new self($user, $password);
    }

    /**
     * Returns the singleton instance of the Database class.
     *
     * @return Database The singleton instance.
     * @throws \RuntimeException If the instance is not yet initialized.
     */
    public static function getInstance(): Database 
    {
        if (self::$instance === null) {
            throw new RuntimeException("Database never initialized. Please use new Database(\$user, \$password) to initialize.");
        }
        return self::$instance;
    }

    /**
     * Establishes a PDO connection to the database.
     *
     * @param string $user Database username.
     * @param string $password Database password.
     * @throws \RuntimeException If the connection fails.
     */
    private function connect($user, $password): void 
    {
        try {
            $host = getenv('DATABASE_HOST') ?: '127.0.0.1';
            $dbname = 'tank-game';
            $port = getenv('DATABASE_PORT') ?: '13306';

            $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];

            $this->pdo = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection error");
        }
    }

    /**
     * Returns the PDO connection instance.
     *
     * @return \PDO The PDO connection.
     */
    public function getConnection(): \PDO 
    {
        return $this->pdo;
    }

    /**
     * Prevents cloning of the singleton instance.
     *
     * @throws \Exception Always, as cloning is not allowed.
     */
    private function __clone() 
    {
        throw new Exception("Cannot clone singleton");
    }

    /**
     * Prevents unserialization of the singleton instance.
     *
     * @throws \Exception Always, as unserialization is not allowed.
     */
    public function __wakeup() 
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Destroys the singleton instance and closes the PDO connection.
     * Only used for testing purposes.
     *
     * @throws \RuntimeException If the instance is not yet initialized.
     */
    public function destroy() 
    {
        if (self::$instance === null) {
            throw new RuntimeException("Database instance not yet initialized.");
        }

        self::$instance = null;
        $this->pdo = null;
    }
}
?>