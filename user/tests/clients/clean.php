<?php

require_once "./clients/lib/make-request.php";

function clean()
{
    $response=makeRequest(
        '/clean.php',
        'POST'
    );
}

?>