<?php

require_once "./clients/lib/check.php";
require_once "./clients/clean.php";
require_once "./clients/create-user.php";

clean();

$response = createUser(
    'testuser',
    'short',
    'short'
);

check($response, 422, "\"Password too short\"");

echo "Too short password identificated successfully\n";

?>