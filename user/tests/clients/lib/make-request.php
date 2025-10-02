<?php

$base_url = 'http://localhost:8080/user/src';

function makeRequest(
    $path,
    $method = 'GET',
    $data = null,
    $contentType = 'application/json'
) {
    global $base_url;
    try {
        $ch = curl_init();

        // Set the URL and HTTP method
        curl_setopt($ch, CURLOPT_URL, $base_url . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Add data for POST/PUT requests
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        // Set headers
        $headers = [
            "Content-Type: $contentType",
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Return the response instead of outputting it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Include the HTTP status code in the response
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        // Execute the request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'body' => $response,
            'status_code' => $httpCode,
        ];
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        return null;
    }
}

?>