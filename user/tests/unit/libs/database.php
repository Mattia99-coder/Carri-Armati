<?php
require_once __DIR__ . '/../../../src/config/Database.php';

/**
 * Creates a new instance of the test database.
 *
 * @return Database The test database instance.
 */
function newTestDatabase() {
    $db = Database::newInstance('test-user', 'test-password');
    return $db;
}

/**
 * Executes a callback with a test database instance, ensuring cleanup.
 *
 * @param callable $callback The function to execute with the database instance.
 * @return void
 */
function withTestDatabase($callback) {
    $db = newTestDatabase();
    try {
        $callback($db);
    } finally {
        $db->destroy();
    }
}