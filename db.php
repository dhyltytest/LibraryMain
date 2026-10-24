
<?php

require_once 'config.php';

function connect_db(): mysqli
{
    global $conn;
    return $conn;
}
