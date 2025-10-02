<?php

require_once "./clients/lib/check.php";
require_once "./clients/clean.php";
require_once "./clients/create-user.php";

clean();

createUser(
    'testuser',
    'testpassword',
    'testpassword'
);

$response = createUser(
    'testuser',
    'testpassword',
    'testpassword'
);

check($response, 409, "\"Username already exists\"");

echo "Duplicate username identificated successfully\n";

?>