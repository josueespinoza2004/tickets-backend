<?php
// api/debug_create_ticket.php
file_put_contents('debug_log.txt', print_r($_POST, true), FILE_APPEND);
file_put_contents('debug_log.txt', print_r($_FILES, true), FILE_APPEND);
echo "Logged";
