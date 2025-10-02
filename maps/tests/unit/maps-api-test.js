
// Import the minimal test runner from the local test utility
const { test } = require('./test');

// Import Node's HTTP module to make requests to the local server
const http = require('http');


/**
 * Test that the /maps/slots endpoint returns HTTP 200.
 * This test assumes the maps microservice is running on localhost:3000.
 */
test('GET /maps/slots returns 200', async () => {
    await new Promise((resolve, reject) => {
        http.get('http://localhost:3000/maps/slots', res => {
            if (res.statusCode !== 200) reject(new Error('Status not 200'));
            else resolve();
        }).on('error', reject);
    });
});
