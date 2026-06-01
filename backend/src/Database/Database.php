<?php

declare(strict_types=1);

namespace App\Database;

use mysqli;
use mysqli_sql_exception;

/**
 * Database connection wrapper using mysqli.
 * 
 * Provides a singleton pattern for database connections
 * with prepared statement support.
 */
class Database
{
    private static ?Database $instance = null;
    private mysqli $connection;

    /**
     * Private constructor for singleton pattern.
     *
     * @param array $config Database configuration
     * @throws mysqli_sql_exception If connection fails
     */
    private function __construct(array $config)
    {
        $this->connection = new mysqli(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['name'],
            $config['port']
        );

        if ($this->connection->connect_error) {
            throw new mysqli_sql_exception(
                "Database connection failed: {$this->connection->connect_error}"
            );
        }

        $this->connection->set_charset($config['charset'] ?? 'utf8mb4');
    }

    /**
     * Get singleton database instance.
     *
     * @param array|null $config Configuration array (required on first call)
     * @return Database
     */
    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('Database config required for first initialization');
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Get raw mysqli connection.
     *
     * @return mysqli
     */
    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    /**
     * Execute a prepared statement and return result.
     *
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @param string $types Parameter types (i, d, s, b)
     * @return \mysqli_result|bool
     */
    public function query(string $query, array $params = [], string $types = '')
    {
        $stmt = $this->connection->prepare($query);
        
        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: {$this->connection->error}");
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $result = $stmt->execute();
        
        if ($result === false) {
            throw new \RuntimeException("Execute failed: {$stmt->error}");
        }

        return $stmt->get_result() ?? $result;
    }

    /**
     * Fetch single row as associative array.
     *
     * @param string $query SQL query
     * @param array $params Parameters
     * @param string $types Parameter types
     * @return array|null
     */
    public function fetchOne(string $query, array $params = [], string $types = ''): ?array
    {
        $result = $this->query($query, $params, $types);
        
        if ($result instanceof \mysqli_result) {
            return $result->fetch_assoc() ?: null;
        }
        
        return null;
    }

    /**
     * Fetch all rows as associative arrays.
     *
     * @param string $query SQL query
     * @param array $params Parameters
     * @param string $types Parameter types
     * @return array
     */
    public function fetchAll(string $query, array $params = [], string $types = ''): array
    {
        $result = $this->query($query, $params, $types);
        
        if ($result instanceof \mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        
        return [];
    }

    /**
     * Get last inserted ID.
     *
     * @return int
     */
    public function getLastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    /**
     * Get affected rows count.
     *
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->connection->affected_rows;
    }

    /**
     * Begin transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->connection->begin_transaction();
    }

    /**
     * Commit transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction.
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    /**
     * Close connection (prevent cloning).
     */
    private function __clone() {}

    /**
     * Prevent unserialization (prevent singleton bypass).
     */
    public function __wakeup()
    {
        throw new \RuntimeException("Cannot unserialize singleton");
    }
}
