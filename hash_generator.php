<?php
require_once 'hash_setup.php'; 

$plain_password = '12345678';
$hashed_password = hashPassword($plain_password);

echo "Password Plain Text: " . $plain_password . "<br>";
echo "HASH yang Aman untuk Database: <strong>" . $hashed_password . "</strong>";
?>