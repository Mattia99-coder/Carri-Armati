<?php
/**
 * Unit tests for the Database singleton implementation.
 *
 * Defines test cases to verify correct behavior of Database::newInstance and Database::getInstance.
 */

require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/libs/database.php';
require_once __DIR__ . '/libs/test.php';

/**
 * Test that newInstance returns a Database instance.
 *
 * @var TestCase $test_newInstance
 */
$test_newInstance = new TestCase("newInstance returns Database instance", ["database"]);
$test_newInstance->test_function = function() {
    withTestDatabase(function($db) {
        assertBool($db instanceof Database, "newInstance did not return a Database instance");
    });
};

/**
 * Test that getInstance returns the singleton instance.
 *
 * @var TestCase $test_getInstance
 */
$test_getInstance = new TestCase("getInstance returns the Singleton instance", ["database"]);
$test_getInstance->test_function = function() {
    withTestDatabase(function($db) {
        $instance = Database::getInstance();
        assertBool($instance === $db, "getInstance returns a different object than newInstance");
    });
};