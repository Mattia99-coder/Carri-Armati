
/**
 * Minimal test runner function for Node.js.
 * Runs a test and logs the result to the console.
 * @param {string} name - The name of the test.
 * @param {Function} fn - The test function to execute.
 */
function test(name, fn) {
    try {
        fn();
        console.log(`✔️  ${name}`);
    } catch (e) {
        console.error(`❌  ${name}\n   ${e}`);
    }
}

// Export the test function for use in other test files
module.exports = { test };
