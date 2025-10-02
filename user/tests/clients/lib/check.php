<?php

function check($response, $expectedCode, $expectedBody)
{
    if ($response['status_code'] !== $expectedCode) {
        echo "Unexpected status code: " . $response['status_code'] . "\n";
        echo "Expected status code: " . $expectedCode . "\n";
        exit;
    }
    if ($response['body'] !== $expectedBody) {
        echo "Unexpected body: " . $response['body'] . "\n";
        echo "Expected body: " . $expectedBody . "\n";
        exit;
    }
}

?>