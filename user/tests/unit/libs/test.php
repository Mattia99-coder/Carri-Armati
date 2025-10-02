<?php

/**
 * Class TestCase
 * Represents a single test case with a name, function, and tags.
 */
class TestCase {
    /**
     * @var string Name of the test case.
     */
    public $test_name;

    /**
     * @var callable The function to execute for this test.
     */
    public $test_function;

    /**
     * @var array List of tags associated with this test.
     */
    public $test_tags = [];

    /**
     * TestCase constructor.
     * @param string $name Name of the test case.
     * @param array $tags Tags for filtering/grouping tests.
     */
    public function __construct($name, $tags = []) {
        $this->test_name = $name;
        $this->test_tags = $tags;
    }
}

/**
 * Asserts that a condition is true, throws Exception if not.
 *
 * @param bool $condition The condition to assert.
 * @param string $fail_message Message to display if assertion fails.
 * @throws Exception
 * @return void
 */
function assertBool($condition, $fail_message) {
    if (!$condition) {
        throw new Exception("$fail_message\n");
    }
}

/**
 * Filters tests by tags. If no tests match, returns all tests.
 *
 * @param TestCase[] $tests Array of TestCase objects.
 * @param array $tags Tags to filter by.
 * @return TestCase[] Filtered array of TestCase objects.
 */
function findTaggedTests(array $tests, array $tags = []) {
    $tagged_tests = [];
    foreach ($tests as $test) {
        if (!empty(array_intersect($test->test_tags, $tags))) {
            $tagged_tests[] = $test;
        }
    }
    if (empty($tagged_tests)) {
        return $tests;
    }
    return $tagged_tests;
}

/**
 * Runs tests and stops at the first failure.
 *
 * @param TestCase[] $tests Array of TestCase objects.
 * @param array $tags Tags to filter by.
 * @return void
 */
function hardFailRunner(array $tests, array $tags = []) {
    foreach (findTaggedTests($tests, $tags) as $test) {
        try {
            ($test->test_function)();
            echo "[PASS] " . $test->test_name . "\n";
        } catch (Exception $e) {
            echo "[FAIL] " . $test->test_name . "\n";
            echo "Exception: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

/**
 * Runs all tests, reporting all failures at the end.
 *
 * @param TestCase[] $tests Array of TestCase objects.
 * @param array $tags Tags to filter by.
 * @return void
 */
function softFailRunner(array $tests, array $tags = []) {
    $failures = [];
    foreach (findTaggedTests($tests, $tags) as $test) {
        try {
            ($test->test_function)();
            echo "[PASS] " . $test->test_name . "\n";
        } catch (Exception $e) {
            echo "[FAIL] " . $test->test_name . "\n";
            echo "Exception: " . $e->getMessage() . "\n";
            $failures[] = $test->test_name;
        }
    }
    if (!empty($failures)) {
        echo "\nSoft Failures!\n\n- ";
        echo implode("\n- ", $failures) . "\n";
        exit(1);
    }
    echo "All tests passed!\n";
}