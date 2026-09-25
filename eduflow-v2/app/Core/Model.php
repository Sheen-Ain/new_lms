<?php

namespace App\Core;

/**
 * Model — thin active-record base class.
 *
 * Child models declare $table and $fillable. All queries are built with
 * bound parameters; nothing is interpolated from user input.
 */
abstract class Model
{
    /** @var string database table */
    protected $table = '';

    /** @var array columns that may be mass assigned */
    protected $fillable = [];

    /** @var string primary key column */
    protected $primaryKey = 'id';

    /** @var array instance cache */
    private static $instances = [];

    /** @return static */
    public static function instance()
    {
        $class = get_called_class();
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    public function table()
    {
        return $this->table;
    }

    /* ── Reads ─────────────────────────────────────────────────── */

    public function find($id, $columns = '*')
    {
        return Database::first(
            'SELECT ' . $columns . ' FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = ? LIMIT 1',
            [$id]
        );
    }

    public function findOrFail($id)
    {
        $row = $this->find($id);
        if (!$row) {
            throw new \RuntimeException('Record not found in ' . $this->table . ' (id=' . (int) $id . ')');
        }
        return $row;
    }

    /**
     * Flexible lookup: where(['status' => 'active'], 'created_at DESC', 10).
     */
    public function where(array $conditions = [], $order = null, $limit = null, $offset = 0, $columns = '*')
    {
        list($whereSql, $params) = $this->buildWhere($conditions);
        $sql = 'SELECT ' . $columns . ' FROM `' . $this->table . '`' . $whereSql;
        if ($order) {
            $sql .= ' ORDER BY ' . $this->safeOrder($order);
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        return Database::all($sql, $params);
    }

    public function firstWhere(array $conditions = [], $order = null, $columns = '*')
    {
        $rows = $this->where($conditions, $order, 1, 0, $columns);
        return $rows ? $rows[0] : null;
    }

    public function count(array $conditions = [])
    {
        list($whereSql, $params) = $this->buildWhere($conditions);
        return (int) Database::value('SELECT COUNT(*) FROM `' . $this->table . '`' . $whereSql, $params);
    }

    public function exists(array $conditions = [])
    {
        return $this->count($conditions) > 0;
    }

    public function all($order = null, $limit = null, $offset = 0)
    {
        return $this->where([], $order, $limit, $offset);
    }

    /** Paginated list returning rows + meta. */
    public function paginate(array $conditions = [], $page = 1, $perPage = PER_PAGE, $order = null, $columns = '*')
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(PER_PAGE_MAX, (int) $perPage));
        $total = $this->count($conditions);
        $rows = $this->where($conditions, $order, $perPage, ($page - 1) * $perPage, $columns);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    /* ── Writes ────────────────────────────────────────────────── */

    /** Insert a row and return its id. */
    public function create(array $data)
    {
        $data = $this->filterFillable($data);
        if (!$data) {
            throw new \InvalidArgumentException('Nothing to insert into ' . $this->table . '.');
        }

        $columns = array_keys($data);
        $sql = 'INSERT INTO `' . $this->table . '` (`' . implode('`, `', $columns) . '`)'
            . ' VALUES (' . Database::placeholders($columns) . ')';

        return Database::insert($sql, array_values($data));
    }

    public function update($id, array $data)
    {
        $data = $this->filterFillable($data);
        if (!$data) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = '`' . $column . '` = ?';
        }

        $params = array_values($data);
        $params[] = $id;

        return Database::write(
            'UPDATE `' . $this->table . '` SET ' . implode(', ', $assignments)
            . ' WHERE `' . $this->primaryKey . '` = ?',
            $params
        );
    }

    public function delete($id)
    {
        return Database::write(
            'DELETE FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = ?',
            [$id]
        );
    }

    public function deleteMany(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        return Database::write(
            'DELETE FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` IN (' . Database::placeholders($ids) . ')',
            $ids
        );
    }

    public function updateMany(array $ids, array $data)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $data = $this->filterFillable($data);
        if (!$ids || !$data) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = '`' . $column . '` = ?';
        }

        $params = array_values($data);
        foreach ($ids as $id) {
            $params[] = $id;
        }

        return Database::write(
            'UPDATE `' . $this->table . '` SET ' . implode(', ', $assignments)
            . ' WHERE `' . $this->primaryKey . '` IN (' . Database::placeholders($ids) . ')',
            $params
        );
    }

    /* ── Internals ─────────────────────────────────────────────── */

    protected function filterFillable(array $data)
    {
        if (!$this->fillable) {
            return $data;
        }
        $out = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** @return array{0:string,1:array} */
    protected function buildWhere(array $conditions)
    {
        if (!$conditions) {
            return ['', []];
        }

        $clauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
            if ($value === null) {
                $clauses[] = '`' . $safeColumn . '` IS NULL';
            } elseif (is_array($value)) {
                $values = array_values($value);
                $clauses[] = '`' . $safeColumn . '` IN (' . Database::placeholders($values) . ')';
                $params = array_merge($params, $values);
            } else {
                $clauses[] = '`' . $safeColumn . '` = ?';
                $params[] = $value;
            }
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /** Whitelist ORDER BY fragments ("created_at DESC, name ASC"). */
    protected function safeOrder($order)
    {
        $parts = explode(',', (string) $order);
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z0-9_]+)(\s+(ASC|DESC))?$/i', $part, $match)) {
                $out[] = '`' . $match[1] . '`' . (isset($match[3]) ? ' ' . strtoupper($match[3]) : '');
            }
        }
        return $out ? implode(', ', $out) : '`' . $this->primaryKey . '` DESC';
    }
}

