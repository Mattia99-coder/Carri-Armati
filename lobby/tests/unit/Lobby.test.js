
// Import JSDOM to simulate a DOM environment for testing
const { JSDOM } = require('jsdom');


/**
 * Shows the section with the given class name and hides all others.
 * @param {Element} elemento - The container element.
 * @param {string} nome - The class name of the section to show.
 */
function mostraSezione(elemento, nome) {
    elemento.querySelectorAll('.sezione').forEach(e => e.style.display = 'none');
    elemento.querySelector('.' + nome).style.display = 'block';
}

/**
 * Hides the section with the given class name.
 * @param {Element} elemento - The container element.
 * @param {string} nome - The class name of the section to hide.
 */
function nascondiSezione(elemento, nome) {
    elemento.querySelector('.' + nome).style.display = 'none';
}


// Test: mostraSezione should show the correct section and hide others
test('mostraSezione shows the correct section', () => {
    const dom = new JSDOM(`<div><div class='sezione a'></div><div class='sezione b'></div></div>`);
    const container = dom.window.document.querySelector('div');
    mostraSezione(container, 'b');
    expect(container.querySelector('.b').style.display).toBe('block');
    expect(container.querySelector('.a').style.display).toBe('none');
});

// Test: nascondiSezione should hide the correct section
test('nascondiSezione hides the correct section', () => {
    const dom = new JSDOM(`<div><div class='sezione a' style='display:block'></div></div>`);
    const container = dom.window.document.querySelector('div');
    nascondiSezione(container, 'a');
    expect(container.querySelector('.a').style.display).toBe('none');
});


/**
 * Minimal test runner function. Runs a test and logs the result.
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


/**
 * Minimal assertion function for test expectations.
 * @param {*} received - The actual value.
 * @returns {Object} - Assertion methods.
 */
function expect(received) {
    return {
        toBe(expected) {
            if (received !== expected) throw new Error(`Expected ${expected}, got ${received}`);
        }
    };
}
