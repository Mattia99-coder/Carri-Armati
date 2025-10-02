<?php

require_once "./clients/lib/check.php";
require_once "./clients/clean.php";
require_once "./clients/create-user.php";

clean();

$response = createUser(
    'testuser',
    'testpassword',
    'wrongpassword'
);

check($response, 422, "\"Password mismatch\"");

echo "Password mismatch identificated successfully\n";

?>