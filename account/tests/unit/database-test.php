
<?php
// Import the Database class to be tested
require_once __DIR__ . '/../../src/config/Database.php';
// Import the minimal TestCase class for running the test
require_once __DIR__ . '/libs/test.php';

/**
 * Test: Database singleton returns the same instance.
 * This ensures that multiple calls to getInstance return the same object (singleton pattern).
 */
$test_db_instance = new TestCase('Database singleton returns same instance');
$test_db_instance->test_function = function() {
    $db1 = \App\config\Database::getInstance('db-docker', 'tank-game', 'user', 'password', 'mysql');
    $db2 = \App\config\Database::getInstance('db-docker', 'tank-game', 'user', 'password', 'mysql');
    if ($db1 !== $db2) throw new Exception('Database::getInstance did not return the same instance');
};
$test_db_instance->run();
