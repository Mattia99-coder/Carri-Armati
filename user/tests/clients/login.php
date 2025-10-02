<?php

require_once "./clients/lib/make-request.php";


function login($username, $password)
{
    $response = makeRequest(
        '/login.php',
        'POST',
        json_encode([
            'username' => $username,
            'password' => $password,
        ])
    );
    
    return $response;
}

?>