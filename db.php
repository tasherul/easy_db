<?php
/**
 * Class DB made by tasherul islam
 * Email: tasherulislam@gmail.com
 * tasherulislam.com
 * Easy DB
 * Secure, fluent MySQL query builder and executor.
 * Loads DB credentials from .env, supports parameterized queries,
 * joins, aggregation, pagination, create/drop table, and more.
 */
final class DB {
    private SecurityLayer $security;
    private QueryBuilder $queryBuilder;
    private Executor $executor;
    private bool $debug;

    public function __construct() {
        $env = $this->loadEnv();
        $this->debug = isset($env['DB_DEBUG']) && strtolower($env['DB_DEBUG']) === 'true';
        $this->security = new SecurityLayer();
        $this->queryBuilder = new QueryBuilder();
        $this->executor = new Executor($env, $this->debug);
    }

    /**
     * Load .env file and return as associative array.
     * @return array<string,string>
     */
    private function loadEnv(): array {
        $path = __DIR__ . '/.env';
        $env = [];
        if (file_exists($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim($value);
            }
        }
        return $env;
    }

    /**
     * Reset internal state to prepare for next query.
     * @return void
     */
    private function resetAll(): void {
        $this->queryBuilder->reset();
        $this->security->reset();
    }

    /**
     * Set table for query.
     * @param string $name Table name (simple string)
     * @return self Fluent interface
     */
    public function table(string $name): static {
        $this->queryBuilder->setTable($this->security->sanitizeIdentifier($name));
        return $this;
    }

    /**
     * Set SELECT columns.
     * Accepts '*' or complex expressions like 'COUNT(*) as total'.
     * @param string $columns Columns to select
     * @return self
     */
    public function select(string $columns = '*'): static {
        $columnsTrim = trim($columns);
        if ($columnsTrim === '*') {
            $this->queryBuilder->setSelect('*');
            return $this;
        }
        // If complex SQL detected, set raw
        if (preg_match('/[\s(),]| AS /i', $columnsTrim)) {
            $this->queryBuilder->setSelectRaw($columnsTrim);
        } else {
            $sanitized = $this->security->sanitizeColumns($columnsTrim);
            $this->queryBuilder->setSelect($sanitized);
        }
        return $this;
    }

    /**
     * Add WHERE condition(s).
     * @param string|array $key Column name or associative array of column => value
     * @param mixed|null $value Value to compare (ignored if $key is array)
     * @return self
     */
    public function where(string|array $key, $value = null): static {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->queryBuilder->addWhere($this->security->sanitizeIdentifier($k), $v);
            }
        } else {
            $this->queryBuilder->addWhere($this->security->sanitizeIdentifier($key), $value);
        }
        return $this;
    }

    /**
     * Add OR WHERE condition.
     * @param string $key Column name
     * @param mixed $value Value
     * @return self
     */
    public function orWhere(string $key, $value): static {
        $this->queryBuilder->addOrWhere($this->security->sanitizeIdentifier($key), $value);
        return $this;
    }

    /**
     * Add WHERE IN condition.
     * @param string $column Column name
     * @param array<mixed> $values List of values
     * @return self
     */
    public function whereIn(string $column, array $values): static {
        $this->queryBuilder->addWhereIn($this->security->sanitizeIdentifier($column), $values);
        return $this;
    }

    /**
     * Add LIKE condition.
     * @param string $column Column name
     * @param string $value Search pattern
     * @return self
     */
    public function like(string $column, string $value): static {
        $this->queryBuilder->addLike($this->security->sanitizeIdentifier($column), $value);
        return $this;
    }

    /**
     * Add BETWEEN condition.
     * @param string $column Column name
     * @param mixed $start Start value
     * @param mixed $end End value
     * @return self
     */
    public function between(string $column, $start, $end): static {
        $this->queryBuilder->addBetween($this->security->sanitizeIdentifier($column), $start, $end);
        return $this;
    }

    /**
     * Set GROUP BY clause.
     * @param string $column Column name
     * @return self
     */
    public function groupBy(string $column): static {
        $this->queryBuilder->setGroupBy($this->security->sanitizeIdentifier($column));
        return $this;
    }

    /**
     * Set ORDER BY clause.
     * @param string $column Column name and order (e.g., 'id DESC')
     * @return self
     */
    public function orderBy(string $column): static {
        $this->queryBuilder->setOrderBy($this->security->sanitizeOrderBy($column));
        return $this;
    }

    /**
     * Set LIMIT.
     * @param int $limit Max number of records
     * @return self
     */
    public function limit(int $limit): static {
        $this->queryBuilder->setLimit($limit);
        return $this;
    }

    /**
     * Set pagination.
     * @param int $perPage Number of records per page
     * @param int $page Page number (starting at 1)
     * @return self
     */
    public function paginate(int $perPage, int $page): static {
        $this->queryBuilder->setPagination($perPage, $page);
        return $this;
    }

    /**
     * Add JOIN clause.
     * @param string $table Table name to join
     * @param string $mainKey Main table join key
     * @param string $foreignKey Foreign table join key
     * @param string $type JOIN type (INNER, LEFT, RIGHT)
     * @return self
     */
    public function join(string $table, string $mainKey, string $foreignKey, string $type = 'INNER'): static {
        $this->queryBuilder->addJoin(
            $this->security->sanitizeIdentifier($table),
            $this->security->sanitizeIdentifier($mainKey),
            $this->security->sanitizeIdentifier($foreignKey),
            $type
        );
        return $this;
    }

    /**
     * Add LEFT JOIN clause.
     * @param string $table Table to join
     * @param string $mainKey Main table join key
     * @param string $foreignKey Foreign table join key
     * @return self
     */
    public function leftJoin(string $table, string $mainKey, string $foreignKey): static {
        return $this->join($table, $mainKey, $foreignKey, 'LEFT');
    }

    /**
     * Add RIGHT JOIN clause.
     * @param string $table Table to join
     * @param string $mainKey Main table join key
     * @param string $foreignKey Foreign table join key
     * @return self
     */
    public function rightJoin(string $table, string $mainKey, string $foreignKey): static {
        return $this->join($table, $mainKey, $foreignKey, 'RIGHT');
    }

    /**
     * Insert data into table.
     * @param array<string,mixed> $data Column-value pairs
     * @return bool True on success
     */
    public function insert(array $data): bool {
        $sanitized = $this->security->sanitizeData($data);
        $sql = $this->queryBuilder->buildInsert($sanitized);
        $result = $this->executor->execute($sql['query'], $sql['params']);
        $this->resetAll();
        return $result;
    }

    /**
     * Insert data and return last insert ID or false on failure.
     * @param array<string,mixed> $data Column-value pairs
     * @return int|false Last inserted ID or false
     */
    public function insertGetId(array $data): int|false {
        $sanitized = $this->security->sanitizeData($data);
        $sql = $this->queryBuilder->buildInsert($sanitized);
        $success = $this->executor->execute($sql['query'], $sql['params']);
        $this->resetAll();
        return $success ? $this->executor->lastInsertId() : false;
    }

    /**
     * Update records matching WHERE clause.
     * @param array<string,mixed> $data Column-value pairs to update
     * @return bool True on success
     */
    public function update(array $data): bool {
        $sanitized = $this->security->sanitizeData($data);
        $sql = $this->queryBuilder->buildUpdate($sanitized);
        $result = $this->executor->execute($sql['query'], $sql['params']);
        $this->resetAll();
        return $result;
    }

    /**
     * Delete records matching WHERE clause.
     * @return bool True on success
     */
    public function delete(): bool {
        $sql = $this->queryBuilder->buildDelete();
        $result = $this->executor->execute($sql['query'], $sql['params']);
        $this->resetAll();
        return $result;
    }

    /**
     * Count records matching conditions.
     * @param string $column Column to count, default '*'
     * @return int Count value
     */
    public function count(string $column = '*'): int {
        $sql = $this->queryBuilder->buildAggregate('COUNT', $column);
        $result = $this->executor->execute($sql['query'], $sql['params'], true);
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Sum column values.
     * @param string $column Column to sum
     * @return float Sum value
     */
    public function sum(string $column): float {
        $sql = $this->queryBuilder->buildAggregate('SUM', $column);
        $result = $this->executor->execute($sql['query'], $sql['params'], true);
        return (float)($result[0]['total'] ?? 0);
    }

    /**
     * Average column values.
     * @param string $column Column to average
     * @return float Average value
     */
    public function avg(string $column): float {
        $sql = $this->queryBuilder->buildAggregate('AVG', $column);
        $result = $this->executor->execute($sql['query'], $sql['params'], true);
        return (float)($result[0]['average'] ?? 0);
    }

    /**
     * Minimum column value.
     * @param string $column Column to find min
     * @return float Min value
     */
    public function min(string $column): float {
        $sql = $this->queryBuilder->buildAggregate('MIN', $column);
        $result = $this->executor->execute($sql['query'], $sql['params'], true);
        return (float)($result[0]['minimum'] ?? 0);
    }

    /**
     * Maximum column value.
     * @param string $column Column to find max
     * @return float Max value
     */
    public function max(string $column): float {
        $sql = $this->queryBuilder->buildAggregate('MAX', $column);
        $result = $this->executor->execute($sql['query'], $sql['params'], true);
        return (float)($result[0]['maximum'] ?? 0);
    }

    /**
     * Create table.
     * @param string $tableName Name of table
     * @param array<string,string> $columnsDefinition Columns and definitions
     * @return bool True on success
     */
    public function createTable(string $tableName, array $columnsDefinition): bool {
        $columnsSql = [];
        foreach ($columnsDefinition as $column => $definition) {
            $columnsSql[] = "`$column` $definition";
        }
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (" . implode(", ", $columnsSql) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        return $this->executor->execute($sql);
    }

    /**
     * Drop table.
     * @param string $tableName Name of table
     * @return bool True on success
     */
    public function dropTable(string $tableName): bool {
        $sql = "DROP TABLE IF EXISTS `$tableName`";
        return $this->executor->execute($sql);
    }

    /**
     * Fetch the first matching record.
     * @return array<string,mixed>|null First record or null if none
     */
    public function first(): ?array {
        $this->limit(1);
        $result = $this->run();
        return $result[0] ?? null;
    }

    /**
     * Run the built SELECT query and return results.
     * @return array<int,array<string,mixed>>|false Query result set or false on failure
     */
    public function run(): array|false {
        $sql = $this->queryBuilder->buildSelect();
        return $this->executor->execute($sql['query'], $sql['params'], true);
    }

    /**
     * Return paginated results as JSON string.
     * @return string JSON encoded data with page info and results
     */
    public function paginateJson(): string {
        $data = $this->run();
        return json_encode([
            'page' => $this->queryBuilder->page,
            'per_page' => $this->queryBuilder->perPage,
            'results' => $data
        ]);
    }
}

final class SecurityLayer {
    /**
     * Sanitize an identifier for SQL queries.
     * Ensures the identifier contains only valid characters (alphanumeric, underscores, and dots).
     * Escapes backticks within the identifier to prevent SQL injection.
     * 
     * @param string $input The identifier to sanitize.
     * @return string The sanitized identifier enclosed in backticks.
     * @throws InvalidArgumentException If the identifier contains invalid characters.
     */

    public function sanitizeIdentifier(string $input): string {
        if (!preg_match('/^[a-zA-Z0-9_\\.]+$/', $input)) {
            throw new InvalidArgumentException("Invalid identifier: $input");
        }
        return "`" . str_replace("`", "``", $input) . "`";
    }

    /**
     * Sanitize a list of columns for SQL queries.
     * Splits the input string by comma, trims each column, and sanitizes each column
     * using `sanitizeIdentifier`. The sanitized columns are then joined back into a
     * single string.
     * @param string $columns Comma-separated list of columns to sanitize
     * @return string Sanitized column list
     */
    public function sanitizeColumns(string $columns): string {
        $cols = explode(',', $columns);
        $sanitized = array_map(fn($c) => $this->sanitizeIdentifier(trim($c)), $cols);
        return implode(', ', $sanitized);
    }

    /**
     * Sanitize an ORDER BY clause for SQL queries.
     * Ensures the input string contains only valid characters (alphanumeric, underscores, dots, spaces, commas, and ASC/DESC keywords).
     * Throws an InvalidArgumentException if the input contains invalid characters.
     * @param string $orderBy The ORDER BY clause to sanitize
     * @return string The sanitized ORDER BY clause
     */
    public function sanitizeOrderBy(string $orderBy): string {
        if (!preg_match('/^[a-zA-Z0-9_\\.\\s,]+(ASC|DESC)?$/i', $orderBy)) {
            throw new InvalidArgumentException("Invalid ORDER BY clause: $orderBy");
        }
        return $orderBy;
    }

    /**
     * Sanitize an array of data for HTML output.
     * Uses `htmlspecialchars` with double encoding and substitution to
     * prevent XSS attacks.
     * @param array<string,mixed> $data The data to sanitize
     * @return array<string,mixed> The sanitized data
     */
    public function sanitizeData(array $data): array {
        return array_map(fn($v) => is_string($v) ? htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE) : $v, $data);
    }

    /**
     * Resets the query builder state to its initial state.
     * This is useful for creating multiple queries with the same
     * query builder instance.
     */
    public function reset(): void {
    }
}

final class QueryBuilder {
    public string $table = '';
    private string $columns = '*';
    private array $where = [];
    private array $orWhere = [];
    private string $whereIn = '';
    private string $like = '';
    private string $between = '';
    private string $orderBy = '';
    private string $groupBy = '';
    private string $join = '';
    private string $limit = '';
    public int $page = 0;
    public int $perPage = 0;

    private array $bindings = [];

    /**
     * Resets the query builder to its initial state.
     * Clears all clauses, bindings, and pagination settings.
     * This is useful for starting a new query without lingering state.
     */

    public function reset(): void {
        $this->table = '';
        $this->columns = '*';
        $this->where = [];
        $this->orWhere = [];
        $this->whereIn = '';
        $this->like = '';
        $this->between = '';
        $this->orderBy = '';
        $this->groupBy = '';
        $this->join = '';
        $this->limit = '';
        $this->bindings = [];
        $this->page = 0;
        $this->perPage = 0;
    }

    /**
     * Sets the table to select from.
     * @param string $table The table to select from
     */
    public function setTable(string $table): void {
        $this->table = $table;
    }

    /**
     * Sets the columns to select from the table.
     * @param string $columns The columns to select, separated by commas
     */
    public function setSelect(string $columns): void {
        $this->columns = $columns;
    }

    /**
     * Sets the SELECT columns using a raw SQL string.
     * This method allows using raw SQL for the SELECT clause, bypassing any
     * automatic sanitization or processing.
     * @param string $raw The raw SQL string for the SELECT columns
     */
    public function setSelectRaw(string $raw): void {
        $this->columns = $raw;
    }

    /**
     * Adds a WHERE condition to the query.
     * @param string $column The column to condition on
     * @param mixed $value The value to compare against
     */
    public function addWhere(string $column, $value): void {
        $this->where[] = "$column = ?";
        $this->bindings[] = $value;
    }

    /**
     * Adds an OR condition to the query.
     * @param string $column The column to condition on
     * @param mixed $value The value to compare against
     */
    public function addOrWhere(string $column, $value): void {
        $this->orWhere[] = "$column = ?";
        $this->bindings[] = $value;
    }

    /**
     * Adds a WHERE IN condition to the query.
     * @param string $column The column to condition on
     * @param array $values The values to compare against
     */
    public function addWhereIn(string $column, array $values): void {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->whereIn = "$column IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, $values);
    }

    /**
     * Adds a LIKE condition to the query.
     * @param string $column The column to condition on
     * @param string $value The value to compare against
     */
    public function addLike(string $column, string $value): void {
        $this->like = "$column LIKE ?";
        $this->bindings[] = "%$value%";
    }

    /**
     * Adds a BETWEEN condition to the query.
     * @param string $column The column to apply the BETWEEN condition on
     * @param mixed $start The start value of the range
     * @param mixed $end The end value of the range
     */
    public function addBetween(string $column, $start, $end): void {
        $this->between = "$column BETWEEN ? AND ?";
        $this->bindings[] = $start;
        $this->bindings[] = $end;
    }

    /**
     * Sets the GROUP BY clause for the query.
     * @param string $groupBy The columns to group by
     */
    public function setGroupBy(string $groupBy): void {
        $this->groupBy = " GROUP BY $groupBy";
    }



    /**
     * Sets the ORDER BY clause for the query.
     * @param string $orderBy The columns to order by, and the order (e.g. 'id DESC')
     */
    public function setOrderBy(string $orderBy): void {
        $this->orderBy = " ORDER BY $orderBy";
    }

    /**
     * Sets the LIMIT clause for the query.
     * @param int $limit The max number of rows to return
     */
    public function setLimit(int $limit): void {
        $this->limit = " LIMIT $limit";
    }

    /**
     * Sets the pagination for the query.
     * @param int $perPage The maximum number of records to return per page
     * @param int $page The page number to return (1-indexed)
     */
    public function setPagination(int $perPage, int $page): void {
        $this->perPage = $perPage;
        $this->page = $page;
        $offset = ($page - 1) * $perPage;
        $this->limit = " LIMIT $perPage OFFSET $offset";
    }

    /**
     * Adds a JOIN clause to the query.
     * @param string $table The table to join
     * @param string $mainKey The column on the main table to join on
     * @param string $foreignKey The column on the foreign table to join on
     * @param string $type The type of join to perform (INNER, LEFT, RIGHT, FULL)
     */
    public function addJoin(string $table, string $mainKey, string $foreignKey, string $type): void {
        $this->join .= " $type JOIN $table ON $mainKey = $foreignKey";
    }

    /**
     * Builds a SELECT query using the current query state.
     * @return array ['query' => string, 'params' => array]
     */
    public function buildSelect(): array {
        $sql = "SELECT {$this->columns} FROM {$this->table} {$this->join}" . $this->buildWhere() . $this->groupBy . $this->orderBy . $this->limit;
        return ['query' => $sql, 'params' => $this->bindings];
    }

/**
 * Builds an INSERT SQL query.
 * @param array<string,mixed> $data Column-value pairs for the insert
 * @return array<string,mixed> Associative array containing 'query' as the SQL
 *                             insert query string and 'params' as the values 
 *                             to be inserted, in the correct order
 */
    public function buildInsert(array $data): array {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        return ['query' => $sql, 'params' => array_values($data)];
    }

    /**
     * Builds an UPDATE SQL query.
     * @param array<string,mixed> $data Column-value pairs for the update
     * @return array<string,mixed> Associative array containing 'query' as the SQL
     *                             update query string and 'params' as the values 
     *                             to be updated, in the correct order
     */
    public function buildUpdate(array $data): array {
        $updates = implode(',', array_map(fn($k) => "$k = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET $updates" . $this->buildWhere();
        $params = array_merge(array_values($data), $this->bindings);
        return ['query' => $sql, 'params' => $params];
    }

    /**
     * Builds a DELETE SQL query.
     * @return array<string,mixed> Associative array containing 'query' as the SQL
     *                             delete query string and 'params' as the values 
     *                             to be used in the WHERE clause, in the correct order
     */
    public function buildDelete(): array {
        $sql = "DELETE FROM {$this->table}" . $this->buildWhere();
        return ['query' => $sql, 'params' => $this->bindings];
    }

    /**
     * Builds an aggregate SQL query.
     * @param string $func Aggregate function name (SUM, COUNT, AVG, MAX, MIN)
     * @param string $column Column to apply the aggregate function to
     * @return array<string,mixed> Associative array containing 'query' as the SQL
     *                             aggregate query string and 'params' as the values 
     *                             to be used in the WHERE clause, in the correct order
     */
    public function buildAggregate(string $func, string $column): array {
        $alias = strtolower($func) === 'count' ? 'total' : strtolower($func);
        $sql = "SELECT $func($column) as $alias FROM {$this->table} {$this->join}" . $this->buildWhere();
        return ['query' => $sql, 'params' => $this->bindings];
    }

    /**
     * Builds the WHERE clause for the SQL query.
     * Combines the various conditions (AND, OR, IN, LIKE, BETWEEN) into
     * a single WHERE clause string. If no conditions are set, returns an
     * empty string. The conditions are combined using 'AND' by default.
     * 
     * @return string The complete WHERE clause of the SQL query, or an
     *                empty string if no conditions exist.
     */

    private function buildWhere(): string {
        $clauses = [];
        if (!empty($this->where)) $clauses[] = implode(' AND ', $this->where);
        if (!empty($this->orWhere)) $clauses[] = implode(' OR ', $this->orWhere);
        if ($this->whereIn) $clauses[] = $this->whereIn;
        if ($this->like) $clauses[] = $this->like;
        if ($this->between) $clauses[] = $this->between;
        return $clauses ? " WHERE " . implode(' AND ', $clauses) : '';
    }
}

final class Executor {
    private PDO $pdo;
    private bool $debug;

    /**
     * Constructs a new Executor object.
     * @param array $env Associative array with DB connection settings:
     *                   - DB_HOST: hostname of the DB server
     *                   - DB_NAME: name of the DB to use
     *                   - DB_USER: username to use for the DB connection
     *                   - DB_PASS: password to use for the DB connection
     * @param bool $debug Whether to enable debug mode (default: false)
     */
    public function __construct(array $env, bool $debug) {
        $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
        try {
            $this->pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("DB Connection failed: " . $e->getMessage());
        }
        $this->debug = $debug;
    }

    /**
     * Executes a SQL query and returns the result.
     * @param string $sql SQL query to execute
     * @param array $bindings Values to use in the query (default: [])
     * @param bool $fetch Whether to fetch the result (default: false)
     * @return array|bool Result of the query, or false on failure.
     *                    If $fetch is true, returns an associative array of results.
     *                    If $fetch is false, returns true on success or false on failure.
     */
    public function execute(string $sql, array $bindings = [], bool $fetch = false): array|bool {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            if ($this->debug) {
                echo "<pre><strong>SQL:</strong> $sql\n<strong>Bindings:</strong>";
                print_r($bindings);
            }
            return $fetch ? $stmt->fetchAll(PDO::FETCH_ASSOC) : true;
        } catch (PDOException $e) {
            echo "<pre><strong>Error:</strong> " . $e->getMessage();
            if ($this->debug) {
                echo "\n<strong>SQL:</strong> $sql\n<strong>Bindings:</strong> ";
                print_r($bindings);
            }
            return false;
        }
    }

    /**
     * Retrieve the ID of the last inserted row.
     * @return int The last inserted ID as an integer.
     */
    public function lastInsertId(): int {
        return (int)$this->pdo->lastInsertId();
    }
}
