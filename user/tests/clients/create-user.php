<?php

require_once "./clients/lib/make-request.php";


function createUser($username, $password, $confirmPassword)
{
    $response = makeRequest(
        '/register.php',
        'POST',
        json_encode([
            'username' => $username,
            'password' => $password,
            'confirm-password' => $confirmPassword
        ])
    );
    
    return $response;
}

?>