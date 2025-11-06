<?php
$password = 'emir123';
echo '<pre>';
echo "Password: $password\n";
echo "Hash:\n";
echo password_hash($password, PASSWORD_DEFAULT);
echo '</pre>';
?>
