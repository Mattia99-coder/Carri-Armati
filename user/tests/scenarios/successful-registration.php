<?php

require_once "./clients/lib/check.php";
require_once "./clients/clean.php";
require_once "./clients/create-user.php";

clean();

$response = createUser(
    'testuser',
    'testpassword',
    'testpassword'
);

check($response, 201, "1");

echo "User created successfully\n";

?>