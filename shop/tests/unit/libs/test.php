
<?php
/**
 * Minimal TestCase class for running unit tests.
 * Each instance represents a single test with a name, function, and optional tags.
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
     * @var array List of tags associated with this test (for filtering/grouping).
     */
    public $test_tags = [];

    /**
     * TestCase constructor.
     * @param string $name Name of the test case.
     * @param array $tags Optional tags for filtering/grouping tests.
     */
    public function __construct($name, $tags = []) {
        $this->test_name = $name;
        $this->test_tags = $tags;
    }

    /**
     * Runs the test by calling the assigned test function.
     */
    public function run() {
        call_user_func($this->test_function);
    }
}
