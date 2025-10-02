<?php
/**
 * Main entry point for running unit tests for the user microservice.
 *
 * Usage:
 *   php main.php <mode> [tag1 tag2 ...]
 *   <mode>: 'hard' or 'soft' - determines the test runner behavior.
 *   [tag1 tag2 ...]: Optional tags to filter which tests to run.
 *
 * Loads test definitions and executes them using the specified runner.
 */

require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/database-test.php';
require_once __DIR__ . '/libs/database.php';
require_once __DIR__ . '/libs/test.php';

// Array of test functions to run.
$tests = [
    $test_newInstance,
    $test_getInstance,
];

// Parse command line arguments for test mode and tags.
$test_mode = $argv[1];
$test_tags = [];

for ($i = 2; $i < $argc; $i++) {
    $test_tags[] = $argv[$i];
}

// Run tests using the selected runner.
if ($test_mode === 'hard') {
    hardFailRunner($tests, $test_tags);
} elseif ($test_mode === 'soft') {
    softFailRunner($tests, $test_tags);
} else {
    echo "Invalid test mode. Use 'hard' or 'soft'.\n";
    exit(1);
}