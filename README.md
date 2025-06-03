# Easy DB
# Fluent PHP MySQL DB Class

A secure, fluent, and lightweight PHP MySQL database query builder and executor class with support for prepared statements, joins, aggregation, pagination, and schema creation.

## Features

- Fluent interface for building queries: `select()`, `where()`, `join()`, `groupBy()`, `orderBy()`, etc.
- Safe parameter binding using PDO prepared statements to prevent SQL injection.
- Support for single and multiple `where` clauses, `whereIn`, `like`, `between`.
- Aggregate functions: `count()`, `sum()`, `avg()`, `min()`, `max()`.
- Insert with retrieval of last inserted ID (`insertGetId()`).
- Table creation and dropping with `createTable()` and `dropTable()`.
- Pagination support returning JSON output.
- Configurable via `.env` file.
- Debug mode enabled via environment variable.
- Written as a single PHP file with no dependencies.

## Installation

1. Clone or download this repository.

2. Create a `.env` file in the same directory with your database credentials:

    ```
    DB_HOST=localhost
    DB_NAME=your_database
    DB_USER=your_user
    DB_PASS=your_password
    DB_DEBUG=true
    ```

3. Include or require the PHP file in your project:

    ```php
    require 'DB.php';
    ```

4. Instantiate and use the `DB` class:

    ```php
    $db = new DB();
    $users = $db->table('users')->where('status', 'active')->select('*')->run();
    print_r($users);
    ```

## Usage Examples

See the bottom of the PHP file for extensive usage examples including table creation, inserting, selecting, updating, deleting, joins, aggregates, and pagination.

## Environment Variables

- `DB_HOST` - Database host
- `DB_NAME` - Database name
- `DB_USER` - Database user
- `DB_PASS` - Database password
- `DB_DEBUG` - Enable debug output (`true` or `false`)


## Full Examples and Usage

Below are detailed examples demonstrating every main function of the class:

### First Add this class
```php
$db = new DB();
```
### Create a new table
```php
$db->createTable('test_users', [
    'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
    'name' => 'VARCHAR(100) NOT NULL',
    'email' => 'VARCHAR(100) UNIQUE',
    'status' => 'VARCHAR(50)',
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
]);
```
### Insert single records
```php
$db->table('test_users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active'
]);
```
### Insert single records with return insert ID
```php
$newUserId = $db->table('test_users')->insertGetId([
    'name' => 'Charlie',
    'email' => 'charlie@example.com',
    'status' => 'pending'
]);
echo "New user inserted with ID: $newUserId\n";
```
### Select all rows
```php
$allUsers = $db->table('test_users')->select('*')->run();
print_r($allUsers);
```

### Select with WHERE clause
```php
$activeUsers = $db->table('test_users')->where('status', 'active')->run();
print_r($activeUsers);
```
### Select with multiple WHERE conditions
```php
$bobActive = $db->table('test_users')->where(['status' => 'active', 'name' => 'Bob Johnson'])->run();
print_r($bobActive);
```
### Select WHERE IN multiple values
```php
$usersByIds = $db->table('test_users')->whereIn('id', [1, 2])->run();
print_r($usersByIds);
```
### Select with LIKE
```php
$aliceLike = $db->table('test_users')->like('name', 'Alice')->run();
print_r($aliceLike);
```
### Select with BETWEEN dates (assuming date column 'created_at')
```php
$dateRangeUsers = $db->table('test_users')->between('created_at', '2000-01-01', '2100-01-01')->run();
print_r($dateRangeUsers);
```
### Group by with COUNT aggregate
```php
$userStatusCounts = $db->table('test_users')->select('status, COUNT(*) AS total')->groupBy('status')->run();
print_r($userStatusCounts);
```
### Order by descending
```php
$orderedUsers = $db->table('test_users')->select('*')->orderBy('name DESC')->run();
print_r($orderedUsers);
```
### Update records with WHERE
```php
$db->table('test_users')->where('id', $newUserId)->update(['status' => 'active']);
```
### Count rows with a condition
```php
$activeCount = $db->table('test_users')->where('status', 'active')->count();
echo "Number of active users: $activeCount\n";
```
### Delete record by condition
```php
$db->table('test_users')->where('id', $newUserId)->delete();
```
### Fetch single row with `first()`
```php
$user = $db->table('test_users')->where('id', 1)->first();
print_r($user);
```
### Pagination with JSON output `(page 1, 2 per page)`
```php
$paginatedJson = $db->table('test_users')->paginate(2, 1)->paginateJson();
echo $paginatedJson;
```
### Drop the table when finished
```php
$db->dropTable('test_users');
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Feel free to open issues or pull requests.

---

Made with ❤️ by [Tasherul Islam] @tasherul
