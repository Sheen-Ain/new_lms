<?php

namespace App\Core;

/**
 * Database — single mysqli connection wrapped with prepared-statement
 * helpers. Every query in EduFlow V2 goes through here so that binding,
 * error handling and logging behave identically everywhere.
 */
class Database
{
    /** @var \mysqli|null */
    private static $connection = null;

    /** @var int number of statements executed this request */
    public static $queryCount = 0;

    /** @var array */
    public static $log = [];

    public static function connection()
    {
        if (self::$connection instanceof \mysqli) {
            return self::$connection;
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = @new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            Logger::critical('Database connection failed: ' . $conn->connect_error);
            http_response_code(503);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, 'Database connection failed: ' . $conn->connect_error . PHP_EOL);
                exit(1);
            }
            exit(APP_ENV === 'production'
                ? 'Service temporarily unavailable.'
                : 'Database connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset(DB_CHARSET);
        // Predictable NOW()/CURDATE() regardless of server timezone.
        @$conn->query("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
        @$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

        self::$connection = $conn;
        return $conn;
    }

    /**
     * Run a query with bound parameters.
     *
     * @param string $sql    SQL with ? placeholders
     * @param array  $params values in placeholder order
     * @return \mysqli_stmt
     */
    public static function run($sql, array $params = [])
    {
        $conn = self::connection();
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            Logger::error('SQL prepare failed: ' . $conn->error . ' | ' . $sql);
            throw new \RuntimeException('Database error while preparing statement.');
        }

        if ($params) {
            $types = '';
            $values = [];
            foreach ($params as $value) {
                if (is_int($value) || is_bool($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } elseif ($value === null) {
                    $types .= 's';
                } else {
                    $types .= 's';
                }
                $values[] = $value;
            }
            if (strlen($types) !== count($values)) {
                $types = str_repeat('s', count($values));
            }
            $bindValues = [$types];
            foreach ($values as $index => $value) {
                $bindValues[] = &$values[$index];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindValues);
        }

        if (!$stmt->execute()) {
            Logger::error('SQL execute failed: ' . $stmt->error . ' | ' . $sql);
            throw new \RuntimeException('Database error while executing statement.');
        }

        self::$queryCount++;
        return $stmt;
    }

    /** Fetch all rows as associative arrays. */
    public static function all($sql, array $params = [])
    {
        $stmt = self::run($sql, $params);
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /** Fetch a single row (or null). */
    public static function first($sql, array $params = [])
    {
        $stmt = self::run($sql, $params);
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    /** Fetch a single scalar value. */
    public static function value($sql, array $params = [], $column = null)
    {
        $row = self::first($sql, $params);
        if (!$row) {
            return null;
        }
        if ($column !== null) {
            return isset($row[$column]) ? $row[$column] : null;
        }
        return reset($row);
    }

    /** Fetch a list of scalar values from the first column. */
    public static function column($sql, array $params = [])
    {
        $rows = self::all($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = reset($row);
        }
        return $out;
    }

    /** Execute an INSERT/UPDATE/DELETE. Returns affected rows. */
    public static function write($sql, array $params = [])
    {
        $stmt = self::run($sql, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /** Insert a row and return its id. */
    public static function insert($sql, array $params = [])
    {
        $stmt = self::run($sql, $params);
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /** Build "?,?,?" placeholders. */
    public static function placeholders(array $items)
    {
        return implode(',', array_fill(0, count($items), '?'));
    }

    /** Escape a LIKE term safely. */
    public static function like($term)
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $term) . '%';
    }

    public static function begin()
    {
        self::connection()->begin_transaction();
    }

    public static function commit()
    {
        self::connection()->commit();
    }

    public static function rollback()
    {
        self::connection()->rollback();
    }
}
