<?php

require_once "./clients/lib/check.php";
require_once "./clients/clean.php";
require_once "./clients/create-user.php";
require_once "./clients/login.php";

clean();

createUser(
    'testuser',
    'testpassword',
    'testpassword'
);

$response = login(
    'testuser',
    'testpassword'
);

check($response, 200, "1");

echo "Token created successfully\n";

?>