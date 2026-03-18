<?php
// Database Configuration for SmartHRM System
// Compatible with Laragon MySQL setup

class Database {
    private $host = 'localhost';
    private $port = '3306';
    private $database = 'smarthrm_db';
    private $username = 'root';
    private $password = '';
    private $pdo = null;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            // Try multiple connection methods for Laragon compatibility
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Try alternative connection without database name first
            try {
                $dsn_alt = "mysql:host={$this->host};port={$this->port};charset=utf8mb4";
                $this->pdo = new PDO($dsn_alt, $this->username, $this->password, $options);

                // Create database if it doesn't exist
                $this->pdo->exec("CREATE DATABASE IF NOT EXISTS {$this->database}");
                $this->pdo->exec("USE {$this->database}");

            } catch (PDOException $e2) {
                die('Database Connection Failed: ' . $e2->getMessage() . '<br>Original error: ' . $e->getMessage());
            }
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die('Query Error: ' . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die('Execute Error: ' . $e->getMessage());
        }
    }

    public function insert($table, $data) {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));

            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);

            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            $stmt->execute();
            return $this->lastInsertId();
        } catch (PDOException $e) {
            die('Insert Error: ' . $e->getMessage());
        }
    }

    public function update($table, $data, $where, $whereParams = []) {
        try {
            $setClause = '';
            foreach (array_keys($data) as $column) {
                $setClause .= "{$column} = :set_{$column}, ";
            }
            $setClause = rtrim($setClause, ', ');

            // Convert ? placeholders to named parameters to avoid mixing
            $whereNamed = $where;
            $namedWhereParams = [];

            if (is_array($whereParams) && !empty($whereParams)) {
                // If using positional parameters (like 'id = ?'), convert to named
                if (strpos($where, '?') !== false) {
                    $paramCount = 0;
                    $whereNamed = preg_replace_callback('/\?/', function($matches) use (&$paramCount) {
                        $paramCount++;
                        return ":where_param_{$paramCount}";
                    }, $where);

                    // Create named parameters for where clause
                    for ($i = 0; $i < count($whereParams); $i++) {
                        $namedWhereParams["where_param_" . ($i + 1)] = $whereParams[$i];
                    }
                } else {
                    // Already using named parameters
                    $namedWhereParams = $whereParams;
                }
            }

            $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereNamed}";

            // Debug: Log the SQL being generated for updates
            error_log("UPDATE SQL: " . $sql);
            error_log("UPDATE Data: " . print_r($data, true));

            $stmt = $this->pdo->prepare($sql);

            // Bind data parameters with 'set_' prefix
            foreach ($data as $key => $value) {
                $stmt->bindValue(":set_$key", $value);
            }

            // Bind where parameters with converted names
            foreach ($namedWhereParams as $key => $value) {
                $paramName = strpos($key, ':') === 0 ? $key : ":$key";
                $stmt->bindValue($paramName, $value);
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("UPDATE Error: " . $e->getMessage());
            die('Update Error: ' . $e->getMessage());
        }
    }

    public function delete($table, $where, $whereParams = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);

            if (is_array($whereParams)) {
                for ($i = 0; $i < count($whereParams); $i++) {
                    $stmt->bindValue($i + 1, $whereParams[$i]);
                }
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            die('Delete Error: ' . $e->getMessage());
        }
    }
}
?>